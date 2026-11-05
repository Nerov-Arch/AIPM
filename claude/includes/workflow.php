<?php
/**
 * Review and approval workflow.
 *
 * Sequence:  Encoder submits
 *            → Reviewer (MPDO) returns it to the office, or forwards it to Finance
 *            → Finance approves the budget (the requested amount or lower), or returns it to the MPDO
 *            → Reviewer (MPDO) gives the final decision: approve (entering the climate change
 *              expenditure) or disapprove, or sends it back to Finance.
 *
 * Every action is checked here on the server: the user's role and the PPA's
 * current status. No one may act on a PPA they encoded themselves.
 */

/** Items the reviewer confirms before forwarding a PPA to Finance. */
const REVIEW_CHECKLIST = [
    'complete'   => 'All required AIP fields are complete and consistent',
    'alignment'  => 'Aligned with the Comprehensive Development Plan / LDIP priorities',
    'cost'       => 'Cost estimate and expense class breakdown are reasonable',
    'funding'    => 'Fund source is appropriate and fully allocated',
    'schedule'   => 'Schedule and expected outputs are realistic for the fiscal year',
];

/** Days after which a pending item is flagged as overdue in the queues. */
const WORKFLOW_OVERDUE_DAYS = 7;

/** Statuses that are still waiting on someone in the review sequence. */
const WORKFLOW_PENDING_STATUSES = ['for_review', 'for_budget', 'recommended', 'returned'];

/** Workflow actions: label, from status, to status, permission. */
const WORKFLOW_ACTIONS = [
    'return'         => ['label' => 'Return for revision',   'from' => 'for_review',  'to' => 'returned',    'perm' => 'review.act', 'audit' => 'ppa.return'],
    'forward'        => ['label' => 'Forward to Finance',    'from' => 'for_review',  'to' => 'for_budget',  'perm' => 'review.act', 'audit' => 'ppa.forward'],
    'budget_approve' => ['label' => 'Approve budget',        'from' => 'for_budget',  'to' => 'recommended', 'perm' => 'budget.act', 'audit' => 'ppa.budget_approve'],
    'budget_return'  => ['label' => 'Return to MPDO',        'from' => 'for_budget',  'to' => 'for_review',  'perm' => 'budget.act', 'audit' => 'ppa.budget_return'],
    'approve'        => ['label' => 'Approve',               'from' => 'recommended', 'to' => 'approved',    'perm' => 'review.act', 'audit' => 'ppa.approve'],
    'disapprove'     => ['label' => 'Disapprove',            'from' => 'recommended', 'to' => 'disapproved', 'perm' => 'review.act', 'audit' => 'ppa.disapprove'],
    'send_back'      => ['label' => 'Send back to Finance',  'from' => 'recommended', 'to' => 'for_budget',  'perm' => 'review.act', 'audit' => 'ppa.send_back'],
];

/** The page where the signed-in user acts on a PPA at its current stage. */
function decisionPageFor(array $ppa): string
{
    return $ppa['status'] === 'for_budget' ? 'budget-review.php' : 'ppa-review.php';
}

/** Where a PPA is in the sequence, and who it is waiting on. */
function ppaStage(array $ppa): array
{
    return match ($ppa['status']) {
        'draft'       => ['step' => 1, 'holder' => officeName($ppa['office']) . ' (encoder)', 'waiting' => 'Not yet submitted'],
        'returned'    => ['step' => 1, 'holder' => officeName($ppa['office']) . ' (encoder)', 'waiting' => 'Waiting for the office to revise and resubmit'],
        'for_review'  => ['step' => 2, 'holder' => 'MPDO Reviewer', 'waiting' => 'Waiting for MPDO review'],
        'for_budget'  => ['step' => 3, 'holder' => 'Finance (Budget Office)', 'waiting' => 'Waiting for Finance to review the requested budget'],
        'recommended' => ['step' => 4, 'holder' => 'MPDO Reviewer', 'waiting' => 'Budget approved; waiting for the MPDO final approval'],
        'approved'    => ['step' => 5, 'holder' => officeName($ppa['office']), 'waiting' => 'Approved; ready for implementation'],
        'disapproved' => ['step' => 4, 'holder' => '—', 'waiting' => 'Disapproved; processing ended'],
        'ongoing'     => ['step' => 5, 'holder' => officeName($ppa['office']), 'waiting' => 'Being implemented'],
        'completed'   => ['step' => 5, 'holder' => '—', 'waiting' => 'Completed'],
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
            $reason = 'You encoded this PPA, so someone else must act on it.';
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

/** Validate a peso amount field; returns [value|null, error]. */
function workflowMoney(array $input, string $field, string $label): array
{
    $value = parseMoney((string) ($input[$field] ?? ''));
    if ($value === null) {
        return [null, 'Enter a valid amount for ' . $label . ', e.g. 1,250,000.00'];
    }
    if ($value > 999999999999.99) {
        return [null, $label . ' is too large.'];
    }
    return [$value, ''];
}

/**
 * Scale a split allocation to a new total, keeping each fund's share.
 * Rounding differences go to the largest allocation.
 */
function scaleAllocations(array $allocations, float $newTotal): array
{
    $old = array_sum($allocations);
    if (count($allocations) <= 1 || $old <= 0) {
        return [];
    }
    $scaled = [];
    foreach ($allocations as $key => $amount) {
        $scaled[$key] = round($amount / $old * $newTotal, 2);
    }
    $largest = array_keys($scaled, max($scaled))[0];
    $scaled[$largest] = round($scaled[$largest] + $newTotal - array_sum($scaled), 2);
    return $scaled;
}

/** Put the requested budget back (used whenever a PPA goes back before Finance's decision). */
function restoreRequestedBudget(array $ppa): array
{
    $requested = ppaRequested($ppa);
    $update = ['ps' => $requested['ps'], 'mooe' => $requested['mooe'], 'co' => $requested['co'], 'budget' => null];
    if (!empty($ppa['allocations'])) {
        $update['allocations'] = scaleAllocations($ppa['allocations'], $requested['total']);
    }
    return $update;
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
    $user = currentUser();
    $actor = $user['name'] . ' (' . $user['username'] . ')';
    $now = date('Y-m-d H:i:s');

    $remarks = trim((string) ($input['remarks'] ?? ''));
    $needsRemarks = in_array($action, ['return', 'disapprove', 'send_back', 'budget_return'], true);
    if ($needsRemarks && mb_strlen($remarks) < 10) {
        $who = ['send_back' => 'Finance', 'budget_return' => 'MPDO reviewer'][$action] ?? 'implementing office';
        $errors['remarks'] = $remarks === ''
            ? 'Explain the reason so the ' . $who . ' knows what to do.'
            : 'Please give a more specific reason (at least 10 characters).';
    } elseif (mb_strlen($remarks) > 1000) {
        $errors['remarks'] = 'Use 1000 characters or fewer.';
    }

    $checked = array_values(array_intersect(array_keys(REVIEW_CHECKLIST), (array) ($input['checklist'] ?? [])));
    $issues = ppaFundingIssues($ppa['ref']);
    $fundErrors = array_filter($issues, fn ($i) => $i['level'] === 'error');

    $update = ['ref' => $ppa['ref'], 'status' => $def['to']];
    $historyLabel = [
        'return'         => 'Returned for revision',
        'forward'        => 'Evaluated and forwarded to Finance for budget review',
        'budget_approve' => 'Budget approved by Finance',
        'budget_return'  => 'Returned to the MPDO by Finance',
        'approve'        => 'Approved (final approval)',
        'disapprove'     => 'Disapproved (final decision)',
        'send_back'      => 'Sent back to Finance for budget re-evaluation',
    ][$action];

    if ($action === 'forward') {
        $missing = array_diff(array_keys(REVIEW_CHECKLIST), $checked);
        if ($missing) {
            $errors['checklist'] = 'Confirm every checklist item before forwarding (' . count($missing) . ' not confirmed).';
        }
        if ($fundErrors) {
            $errors['funding'] = 'Resolve the funding errors first: ' . implode(' ', array_column($fundErrors, 'message'));
        }
        $update += ['checklist' => $checked, 'recommended_by_id' => $user['id'], 'recommended_by' => $actor, 'recommended_at' => $now,
                    'budget' => ['requested' => ['ps' => (float) $ppa['ps'], 'mooe' => (float) $ppa['mooe'], 'co' => (float) $ppa['co']]]];
    }
    if ($action === 'return') {
        $update += ['checklist' => $checked, 'remarks' => $remarks] + restoreRequestedBudget($ppa);
    }
    if ($action === 'budget_return') {
        $update += ['remarks' => $remarks] + restoreRequestedBudget($ppa);
    }
    if ($action === 'budget_approve') {
        $requested = ppaRequested($ppa);
        $approved = [];
        foreach (['ps' => 'Personal Services', 'mooe' => 'MOOE', 'co' => 'Capital Outlay'] as $field => $label) {
            [$value, $error] = workflowMoney($input, 'approved_' . $field, $label);
            if ($error !== '') {
                $errors['approved_' . $field] = $error;
            }
            $approved[$field] = $value ?? 0.0;
        }
        $total = round(array_sum($approved), 2);
        if (!array_filter($errors, fn ($k) => str_starts_with($k, 'approved_'), ARRAY_FILTER_USE_KEY)) {
            if ($total <= 0) {
                $errors['approved_co'] = 'Approve an amount under at least one expense class, or return the PPA to the MPDO.';
            } elseif ($total > $requested['total'] + 0.001) {
                $errors['approved_co'] = 'The approved budget (' . peso($total) . ') cannot be more than the requested ' . peso($requested['total']) . '.';
            } elseif ($total < $requested['total'] - 0.001 && mb_strlen($remarks) < 10) {
                $errors['remarks'] = 'Explain why the budget is lower than requested (at least 10 characters).';
            }
        }
        $update += $approved + ['budget' => [
            'requested' => ['ps' => $requested['ps'], 'mooe' => $requested['mooe'], 'co' => $requested['co']],
            'approved'  => $approved,
            'by'        => $actor,
            'by_id'     => $user['id'],
            'at'        => $now,
            'remarks'   => $remarks,
        ]];
        if (!empty($ppa['allocations'])) {
            $update['allocations'] = scaleAllocations($ppa['allocations'], $total);
        }
        $historyLabel = abs($total - $requested['total']) < 0.01
            ? 'Budget approved by Finance (' . peso($total) . ', as requested)'
            : 'Budget approved by Finance at ' . peso($total) . ' (requested ' . peso($requested['total']) . ')';
    }
    if ($action === 'send_back') {
        $requested = ppaRequested($ppa);
        $update += ['remarks' => $remarks, 'ps' => $requested['ps'], 'mooe' => $requested['mooe'], 'co' => $requested['co'],
                    'budget' => ['requested' => ['ps' => $requested['ps'], 'mooe' => $requested['mooe'], 'co' => $requested['co']]]];
        if (!empty($ppa['allocations'])) {
            $update['allocations'] = scaleAllocations($ppa['allocations'], $requested['total']);
        }
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
        // Climate change expenditure is entered manually by the MPDO at final approval.
        $cc = [];
        foreach (['cc_adaptation' => 'climate change adaptation', 'cc_mitigation' => 'climate change mitigation'] as $field => $label) {
            [$value, $error] = workflowMoney($input, $field, $label);
            if ($error !== '') {
                $errors[$field] = $error;
            }
            $cc[$field] = $value ?? 0.0;
        }
        $cc['cc_typology'] = strtoupper(trim((string) ($input['cc_typology'] ?? '')));
        $ccTotal = $cc['cc_adaptation'] + $cc['cc_mitigation'];
        if (!isset($errors['cc_adaptation'], $errors['cc_mitigation']) && $ccTotal > (float) $ppa['cost'] + 0.001) {
            $errors['cc_adaptation'] = 'Climate change expenditure (' . peso($ccTotal) . ') cannot exceed the approved cost of ' . peso((float) $ppa['cost']) . '.';
        }
        if ($cc['cc_typology'] !== '' && !preg_match('/^[A-Z0-9][A-Z0-9.\-]{1,19}$/', $cc['cc_typology'])) {
            $errors['cc_typology'] = 'Use letters, numbers, dots or dashes for the typology code (e.g. A612-01).';
        } elseif ($ccTotal > 0 && $cc['cc_typology'] === '') {
            $errors['cc_typology'] = 'A typology code is required when climate change expenditure is entered.';
        } elseif ($ccTotal <= 0 && $cc['cc_typology'] !== '') {
            $errors['cc_adaptation'] = 'Enter the adaptation or mitigation amount for this typology code, or clear the code.';
        }
        $update += $cc;
    }
    if (in_array($action, ['approve', 'disapprove'], true)) {
        $update += ['decided_by' => $actor, 'decided_at' => $now, 'decision_remarks' => $remarks];
    }

    if ($errors) {
        return [$errors, null];
    }
    $saved = storeSavePpa($update, $historyLabel, $remarks);
    auditLog($def['audit'], $ppa['ref'], $ppa['title'] . ($action === 'budget_approve' ? ' · ' . peso($saved['cost']) : '') . ($remarks !== '' ? ' · ' . $remarks : ''));
    return [[], $saved];
}
