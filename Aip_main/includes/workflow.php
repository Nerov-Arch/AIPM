<?php
/**
 * Review and approval workflow.
 *
 * Sequence:  Encoder submits → Reviewer (MPDO) returns or recommends
 *            → Approver approves, disapproves or sends back to the MPDO.
 *
 * Every action is checked here on the server: the user's role, the PPA's
 * current status, and separation of duties (no one acts on a PPA they
 * encoded, and the person who recommended a PPA cannot also approve it).
 */

/** Items the reviewer confirms before recommending a PPA. */
const REVIEW_CHECKLIST = [
    'complete'   => 'All required AIP fields are complete and consistent',
    'alignment'  => 'Aligned with the Comprehensive Development Plan / LDIP priorities',
    'cost'       => 'Cost estimate and expense class breakdown are reasonable',
    'funding'    => 'Fund source is appropriate and fully allocated',
    'schedule'   => 'Schedule and expected outputs are realistic for the fiscal year',
];

/** Days after which a pending item is flagged as overdue in the queues. */
const WORKFLOW_OVERDUE_DAYS = 7;

/** Workflow actions: label, from status, to status, permission. */
const WORKFLOW_ACTIONS = [
    'return'     => ['label' => 'Return for revision',    'from' => 'for_review',  'to' => 'returned',    'perm' => 'review.act',        'audit' => 'ppa.return'],
    'recommend'  => ['label' => 'Recommend for approval', 'from' => 'for_review',  'to' => 'recommended', 'perm' => 'review.act',        'audit' => 'ppa.recommend'],
    'approve'    => ['label' => 'Approve',                'from' => 'recommended', 'to' => 'approved',    'perm' => 'authorization.act', 'audit' => 'ppa.approve'],
    'disapprove' => ['label' => 'Disapprove',             'from' => 'recommended', 'to' => 'disapproved', 'perm' => 'authorization.act', 'audit' => 'ppa.disapprove'],
    'send_back'  => ['label' => 'Send back to MPDO',      'from' => 'recommended', 'to' => 'for_review',  'perm' => 'authorization.act', 'audit' => 'ppa.send_back'],
];

/** Where a PPA is in the sequence, and who it is waiting on. */
function ppaStage(array $ppa): array
{
    return match ($ppa['status']) {
        'draft'       => ['step' => 1, 'holder' => officeName($ppa['office']) . ' (encoder)', 'waiting' => 'Not yet submitted'],
        'returned'    => ['step' => 1, 'holder' => officeName($ppa['office']) . ' (encoder)', 'waiting' => 'Waiting for the office to revise and resubmit'],
        'for_review'  => ['step' => 2, 'holder' => 'MPDO Reviewer', 'waiting' => 'Waiting for MPDO review'],
        'recommended' => ['step' => 3, 'holder' => 'Approver', 'waiting' => 'Waiting for final approval'],
        'approved'    => ['step' => 4, 'holder' => officeName($ppa['office']), 'waiting' => 'Approved; ready for implementation'],
        'disapproved' => ['step' => 3, 'holder' => '—', 'waiting' => 'Disapproved; processing ended'],
        'ongoing'     => ['step' => 4, 'holder' => officeName($ppa['office']), 'waiting' => 'Being implemented'],
        'completed'   => ['step' => 4, 'holder' => '—', 'waiting' => 'Completed'],
        default       => ['step' => 1, 'holder' => '—', 'waiting' => ''],
    };
}

/** When the PPA entered its current status (from its history). */
function statusSince(array $ppa): string
{
    $since = $ppa['updated_at'] ?? date('Y-m-d H:i:s');
    $previous = null;
    foreach ($ppa['history'] ?? [] as $entry) {
        if ($entry['status'] !== $previous) {
            $since = $entry['time'];
            $previous = $entry['status'];
        }
    }
    return $since;
}

/** Whole days the PPA has been in its current status. */
function daysInStatus(array $ppa): int
{
    return max(0, (int) floor((time() - strtotime(statusSince($ppa))) / 86400));
}

function waitingLabel(int $days): string
{
    return $days === 0 ? 'Today' : ($days === 1 ? '1 day' : $days . ' days');
}

/**
 * Actions the signed-in user may take on this PPA now, with the reason
 * when an action is blocked: [action => ['allowed' => bool, 'reason' => string]].
 */
function workflowActionsFor(array $ppa): array
{
    $user = currentUser();
    $actions = [];
    foreach (WORKFLOW_ACTIONS as $key => $def) {
        if ($ppa['status'] !== $def['from'] || !can($def['perm'])) {
            continue;
        }
        $reason = '';
        if (($ppa['created_by_id'] ?? null) === $user['id']) {
            $reason = 'You encoded this PPA, so someone else must ' . ($def['perm'] === 'review.act' ? 'review' : 'approve') . ' it.';
        } elseif ($def['perm'] === 'authorization.act' && in_array($key, ['approve', 'disapprove'], true) && ($ppa['recommended_by_id'] ?? null) === $user['id']) {
            $reason = 'You recommended this PPA, so another approver must decide on it.';
        }
        $actions[$key] = ['allowed' => $reason === '', 'reason' => $reason];
    }
    return $actions;
}

/** PPAs waiting on the signed-in user (for the dashboard and the bell). */
function awaitingMyAction(): array
{
    $mine = [];
    foreach (visiblePpas() as $ppa) {
        $actions = workflowActionsFor($ppa);
        if (array_filter($actions, fn ($a) => $a['allowed'])) {
            $mine[] = $ppa;
        } elseif ($ppa['status'] === 'returned' && can('ppa.encode') && canSeeOffice($ppa['office'])) {
            $mine[] = $ppa;
        }
    }
    usort($mine, fn ($a, $b) => strcmp(statusSince($a), statusSince($b)));   // oldest first
    return $mine;
}

/**
 * Validate and apply a workflow action. Returns [errors, saved PPA or null].
 */
function applyWorkflowAction(array $ppa, string $action, array $input): array
{
    $errors = [];
    $def = WORKFLOW_ACTIONS[$action] ?? null;
    $allowed = workflowActionsFor($ppa)[$action] ?? null;
    if (!$def || !$allowed) {
        return [['action' => 'This action is not available for ' . $ppa['ref'] . ' at its current stage.'], null];
    }
    if (!$allowed['allowed']) {
        return [['action' => $allowed['reason']], null];
    }

    $remarks = trim((string) ($input['remarks'] ?? ''));
    $needsRemarks = in_array($action, ['return', 'disapprove', 'send_back'], true);
    if ($needsRemarks && mb_strlen($remarks) < 10) {
        $errors['remarks'] = $remarks === ''
            ? 'Explain the reason so the ' . ($action === 'send_back' ? 'MPDO reviewer' : 'implementing office') . ' knows what to do.'
            : 'Please give a more specific reason (at least 10 characters).';
    } elseif (mb_strlen($remarks) > 1000) {
        $errors['remarks'] = 'Use 1000 characters or fewer.';
    }

    $checked = array_values(array_intersect(array_keys(REVIEW_CHECKLIST), (array) ($input['checklist'] ?? [])));
    $issues = ppaFundingIssues($ppa['ref']);
    $fundErrors = array_filter($issues, fn ($i) => $i['level'] === 'error');

    $update = ['ref' => $ppa['ref'], 'status' => $def['to']];
    if ($action === 'recommend') {
        $missing = array_diff(array_keys(REVIEW_CHECKLIST), $checked);
        if ($missing) {
            $errors['checklist'] = 'Confirm every checklist item before recommending (' . count($missing) . ' not confirmed).';
        }
        if ($fundErrors) {
            $errors['funding'] = 'Resolve the funding errors first: ' . implode(' ', array_column($fundErrors, 'message'));
        }
        $user = currentUser();
        $update += ['checklist' => $checked, 'recommended_by_id' => $user['id'], 'recommended_by' => $user['name'] . ' (' . $user['username'] . ')', 'recommended_at' => date('Y-m-d H:i:s')];
    }
    if ($action === 'return') {
        $update += ['checklist' => $checked, 'remarks' => $remarks];
    }
    if ($action === 'send_back') {
        $update += ['remarks' => $remarks, 'recommended_by_id' => null];
    }
    if ($action === 'approve') {
        if ($issues) {
            $errors['funding'] = 'This PPA has funding issues to resolve before approval: ' . implode(' ', array_column($issues, 'message'));
        }
        // Approving must not push any of its fund sources over the ceiling.
        $totals = fundTotals();
        foreach (ppaAllocations($ppa) as $fundKey => $amount) {
            $t = $totals[$fundKey] ?? null;
            if ($t && $t['programmed'] + $amount > (float) $t['ceiling'] + 0.001) {
                $errors['ceiling'] = 'Approving would put ' . $t['name'] . ' over its ceiling: ' . peso($t['programmed'] + $amount) . ' programmed against a ' . peso((float) $t['ceiling']) . ' ceiling.';
            }
        }
        $resolution = trim((string) ($input['resolution'] ?? ''));
        $resolutionDate = trim((string) ($input['resolution_date'] ?? ''));
        if ($resolution !== '' && !preg_match('/^[A-Za-z0-9 .,\/#\-]{3,40}$/', $resolution)) {
            $errors['resolution'] = 'Use letters, numbers and . , / # - (e.g. SB Res. No. 2026-120).';
        }
        if ($resolution !== '' && $resolutionDate === '') {
            $errors['resolution_date'] = 'Enter the date of the resolution.';
        } elseif ($resolutionDate !== '') {
            $date = DateTime::createFromFormat('Y-m-d', $resolutionDate);
            if (!$date || $date->format('Y-m-d') !== $resolutionDate) {
                $errors['resolution_date'] = 'Enter a valid date.';
            } elseif ($resolutionDate > date('Y-m-d')) {
                $errors['resolution_date'] = 'The resolution date cannot be in the future.';
            } elseif ($resolution === '') {
                $errors['resolution'] = 'Enter the resolution number for this date.';
            }
        }
        $update += ['record' => $resolution, 'recordDate' => $resolution !== '' ? $resolutionDate : ''];
    }
    if (in_array($action, ['approve', 'disapprove'], true)) {
        $user = currentUser();
        $update += ['decided_by' => $user['name'] . ' (' . $user['username'] . ')', 'decided_at' => date('Y-m-d H:i:s'), 'decision_remarks' => $remarks];
    }

    if ($errors) {
        return [$errors, null];
    }
    $historyLabel = [
        'return'     => 'Returned for revision',
        'recommend'  => 'Recommended for approval',
        'approve'    => 'Approved' . ($update['record'] ?? '' ? ' (' . $update['record'] . ')' : ''),
        'disapprove' => 'Disapproved',
        'send_back'  => 'Sent back to the MPDO for re-evaluation',
    ][$action];
    $saved = storeSavePpa($update, $historyLabel, $remarks);
    auditLog($def['audit'], $ppa['ref'], $ppa['title'] . ($remarks !== '' ? ' · ' . $remarks : ''));
    if ($action === 'approve' && $update['record'] !== '') {
        // Register the resolution as an approval record and link it to this PPA.
        linkPpaByRecordNumber($ppa['ref'], $update['record'], $update['recordDate']);
        $saved = storeFindPpa($ppa['ref']);
    }
    return [[], $saved];
}
