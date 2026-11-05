<?php
/**
 * MPDO Review and Approval of a PPA (one Reviewer role does both steps).
 *   Pending Review:      return to the office, or forward to Finance for budget review
 *   For Final Approval:  set the approval status (approved / disapproved), entering the
 *                        climate change expenditure, or send it back to Finance
 * The Finance step is on pages/budget-review.php. All rules are enforced in
 * includes/workflow.php.
 */
$pageSection = 'Workflow';
$requiredPermission = 'review.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$ppa = storeFindPpa((string) ($_GET['ref'] ?? ''));
if (!$ppa || $ppa['status'] === 'draft' || !canSeeOffice($ppa['office'])) {
    flash('error', 'That PPA was not found or you do not have access to it.');
    header('Location: review-workflow.php');
    exit;
}
if ($ppa['status'] === 'for_budget' && can('budget.view')) {
    header('Location: budget-review.php?ref=' . urlencode($ppa['ref']));
    exit;
}
$activePage = 'review';
$queuePage = 'review-workflow.php';
$pageTitle = ($ppa['status'] === 'recommended' ? 'Final approval: ' : 'Evaluate ') . $ppa['ref'];
$money = fn ($v) => (float) $v > 0 ? number_format((float) $v, 2) : '';

$errors = [];
$input = ['remarks' => '', 'checklist' => $ppa['checklist'] ?? [], 'approval_status' => '',
          'cc_adaptation' => $money($ppa['cc_adaptation'] ?? 0), 'cc_mitigation' => $money($ppa['cc_mitigation'] ?? 0), 'cc_typology' => (string) ($ppa['cc_typology'] ?? '')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');
    $input = [
        'remarks'         => (string) ($_POST['remarks'] ?? ''),
        'checklist'       => (array) ($_POST['checklist'] ?? []),
        'approval_status' => (string) ($_POST['approval_status'] ?? ''),
        'cc_adaptation'   => trim((string) ($_POST['cc_adaptation'] ?? '')),
        'cc_mitigation'   => trim((string) ($_POST['cc_mitigation'] ?? '')),
        'cc_typology'     => trim((string) ($_POST['cc_typology'] ?? '')),
    ];
    if ($action === 'decide') {
        // The final decision is recorded as an approval status.
        $action = ['approved' => 'approve', 'disapproved' => 'disapprove'][$input['approval_status']] ?? '';
        [$errors, $saved] = $action === ''
            ? [['approval_status' => 'Choose the approval status: Approved or Disapproved.'], null]
            : applyWorkflowAction($ppa, $action, $input);
    } else {
        [$errors, $saved] = applyWorkflowAction($ppa, $action, $input);
    }
    if (!$errors) {
        $messages = [
            'return'     => ' was returned to ' . officeName($ppa['office']) . ' for revision.',
            'forward'    => ' was evaluated and forwarded to Finance for budget review.',
            'approve'    => ' is now Approved and included in the FY ' . AIP_FISCAL_YEAR . ' AIP.',
            'disapprove' => ' is now Disapproved.',
            'send_back'  => ' was sent back to Finance for budget re-evaluation.',
        ];
        flash('success', e($ppa['ref']) . e($messages[$action]));
        header('Location: ' . $queuePage);
        exit;
    }
}

$actions = workflowActionsFor($ppa);
$canDecide = (bool) array_filter($actions, fn ($a) => $a['allowed']);
$blocked = array_filter($actions, fn ($a) => !$a['allowed']);
$issues = ppaFundingIssues($ppa['ref']);
$sectors = ppaSectors();
$isReviewStage = $ppa['status'] === 'for_review';
$isApprovalStage = $ppa['status'] === 'recommended';
$requested = ppaRequested($ppa);
$approvedBudget = ppaApprovedBudget($ppa);
$editedInReview = !empty($ppa['edited_in_review_at']) && $ppa['edited_in_review_at'] >= ($ppa['submitted_at'] ?? '');
$lastRemarks = '';
foreach (array_reverse($ppa['history']) as $entry) {
    if ($entry['remarks'] !== '') { $lastRemarks = $entry; break; }
}

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="<?= $queuePage ?>">Review &amp; Approval</a> / <?= e($ppa['ref']) ?></p>
        <h1><?= e($ppa['title']) ?></h1>
        <p class="muted"><?= e($ppa['type']) ?> · <?= e(officeName($ppa['office'])) ?> · encoded by <?= e(explode(' (', $ppa['created_by'])[0]) ?></p>
    </div>
    <div class="heading-actions"><a class="secondary-button" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>">Open full record</a></div>
</div>

<?php include '../includes/stage-tracker.php'; ?>
<?php if ($editedInReview && $isReviewStage): ?>
    <div class="alert info" role="status" data-edited-in-review><strong><?= e(officeName($ppa['office'])) ?> edited this PPA while it was under review</strong> (<?= e(date('M j, Y g:i A', strtotime($ppa['edited_in_review_at']))) ?>). The changes are listed in the processing history.</div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert error" role="alert"><strong>The decision was not saved.</strong><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="review-layout">
    <div class="review-main">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">ⓘ</span><div><h2>Submission summary</h2><p>Submitted <?= $ppa['submitted_at'] ? e(date('M j, Y g:i A', strtotime($ppa['submitted_at']))) : '—' ?></p></div></div>
            <div class="form-divider"></div>
            <dl class="detail-list wide-terms">
                <dt>Program</dt><dd><?= e($ppa['program'] ?: '—') ?></dd>
                <dt>Sector</dt><dd><?= e($sectors[$ppa['sector']] ?? '—') ?></dd>
                <dt>Schedule</dt><dd><?= e(displayDate($ppa['start'])) ?> – <?= e(displayDate($ppa['end'])) ?></dd>
                <dt>Rationale</dt><dd class="prewrap"><?= e($ppa['description'] ?: '—') ?></dd>
                <dt>Expected outputs</dt><dd class="prewrap"><?= e($ppa['expected_output'] ?: '—') ?></dd>
                <dt>Requested budget</dt><dd>PS <?= peso($requested['ps']) ?> · MOOE <?= peso($requested['mooe']) ?> · CO <?= peso($requested['co']) ?><br><strong>Total <?= peso($requested['total']) ?></strong></dd>
                <?php if ($approvedBudget): ?><dt>Approved budget</dt><dd>PS <?= peso($approvedBudget['ps']) ?> · MOOE <?= peso($approvedBudget['mooe']) ?> · CO <?= peso($approvedBudget['co']) ?><br><strong data-approved-budget>Total <?= peso($approvedBudget['total']) ?></strong>
                    <?php $diff = $approvedBudget['total'] - $requested['total']; ?><?php if (abs($diff) >= 0.01): ?><span class="budget-diff down"><?= peso($diff) ?> (<?= number_format($diff / max(1, $requested['total']) * 100, 1) ?>%) vs requested</span><?php else: ?><span class="budget-diff">As requested</span><?php endif; ?>
                    <br><small class="table-subtext">by <?= e($ppa['budget']['by']) ?> on <?= e(date('M j, Y', strtotime($ppa['budget']['at']))) ?><?= $ppa['budget']['remarks'] !== '' ? ' · ' . e($ppa['budget']['remarks']) : '' ?></small></dd><?php endif; ?>
                <dt>Funding</dt><dd><?php foreach (ppaAllocations($ppa) as $fundKey => $amount): ?><?= e(fundName($fundKey)) ?>: <?= peso($amount) ?><br><?php endforeach; ?></dd>
                <?php if ((float) $ppa['cc_adaptation'] + (float) $ppa['cc_mitigation'] > 0): ?><dt>Climate change</dt><dd>Adaptation <?= peso((float) $ppa['cc_adaptation']) ?> · Mitigation <?= peso((float) $ppa['cc_mitigation']) ?> · <?= e($ppa['cc_typology']) ?></dd><?php endif; ?>
                <?php if (!empty($ppa['recommended_by']) && !$isReviewStage): ?><dt>Evaluated by</dt><dd><?= e($ppa['recommended_by']) ?> on <?= e(date('M j, Y', strtotime($ppa['recommended_at']))) ?></dd><?php endif; ?>
            </dl>
            <?php foreach ($issues as $issue): ?>
                <p class="funding-issue <?= $issue['level'] ?>"><strong><?= e($issue['type']) ?>:</strong> <?= e($issue['message']) ?><?php if (can('funds.manage')): ?> <a href="ppa-funding.php?ref=<?= urlencode($ppa['ref']) ?>">Fix allocation</a><?php endif; ?></p>
            <?php endforeach; ?>
        </section>

        <?php if ($lastRemarks): ?>
            <section class="panel form-panel">
                <div class="form-section-heading"><span class="section-icon amber-bg">✎</span><div><h2>Latest remarks</h2><p><?= e($lastRemarks['action']) ?> · <?= e($lastRemarks['by']) ?> · <?= e(date('M j, Y', strtotime($lastRemarks['time']))) ?></p></div></div>
                <p class="remark-quote"><?= e($lastRemarks['remarks']) ?></p>
            </section>
        <?php endif; ?>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">↺</span><div><h2>Processing history</h2><p>Every step, by whom and when</p></div></div>
            <div class="form-divider"></div>
            <ol class="history-list">
                <?php foreach (array_reverse($ppa['history']) as $entry): ?>
                    <li><div class="history-head"><strong><?= e($entry['action']) ?></strong><?= statusPill($entry['status']) ?></div><small><?= e(date('M j, Y g:i A', strtotime($entry['time']))) ?> · <?= e($entry['by']) ?></small><?php if ($entry['remarks'] !== ''): ?><p><?= e($entry['remarks']) ?></p><?php endif; ?></li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>

    <div class="review-side">
        <section class="panel form-panel decision-panel">
            <div class="form-section-heading"><span class="section-icon green-bg">✓</span><div><h2><?= $isApprovalStage ? 'Final approval' : ($isReviewStage ? 'MPDO evaluation' : 'Decision') ?></h2><p><?= $canDecide ? 'Your decision is recorded under your name.' : 'No action is needed from you.' ?></p></div></div>
            <div class="form-divider"></div>

            <?php foreach ($blocked as $key => $block): ?><p class="funding-issue warning"><?= e($block['reason']) ?></p><?php break; endforeach; ?>

            <?php if (!$canDecide): ?>
                <p class="muted"><?= e(ppaStage($ppa)['waiting']) ?>.</p>
                <?php if (in_array($ppa['status'], ['for_review', 'recommended'], true) && !can('review.act')): ?><p class="form-note">Only MPDO reviewers can evaluate and approve this PPA.</p><?php endif; ?>
                <?php if ($ppa['status'] === 'for_budget'): ?><p class="form-note">Finance is reviewing the requested budget.</p><?php endif; ?>
                <a class="secondary-button" href="<?= $queuePage ?>">Back to queue</a>
            <?php else: ?>
                <form method="post" class="decision-form" novalidate>
                    <?= csrfField() ?>
                    <?php if ($isReviewStage): ?>
                        <fieldset class="checklist">
                            <legend>Review checklist <small>(all required to forward to Finance)</small></legend>
                            <?php foreach (REVIEW_CHECKLIST as $key => $label): ?>
                                <label class="check-option"><input type="checkbox" name="checklist[]" value="<?= $key ?>" <?= in_array($key, $input['checklist'], true) ? 'checked' : '' ?>> <span><?= e($label) ?></span></label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endif; ?>
                    <?php if ($isApprovalStage): ?>
                        <fieldset class="cc-fields">
                            <legend>Climate change expenditure <small>(entered by the MPDO; leave blank if not applicable)</small></legend>
                            <label>Adaptation<div class="currency-input"><span>₱</span><input name="cc_adaptation" type="text" inputmode="decimal" placeholder="0.00" value="<?= e($input['cc_adaptation']) ?>"></div><?php if (isset($errors['cc_adaptation'])): ?><small class="field-error"><?= e($errors['cc_adaptation']) ?></small><?php endif; ?></label>
                            <label>Mitigation<div class="currency-input"><span>₱</span><input name="cc_mitigation" type="text" inputmode="decimal" placeholder="0.00" value="<?= e($input['cc_mitigation']) ?>"></div><?php if (isset($errors['cc_mitigation'])): ?><small class="field-error"><?= e($errors['cc_mitigation']) ?></small><?php endif; ?></label>
                            <label>CC typology code<input name="cc_typology" type="text" maxlength="20" placeholder="e.g. A612-01" value="<?= e($input['cc_typology']) ?>"><?php if (isset($errors['cc_typology'])): ?><small class="field-error"><?= e($errors['cc_typology']) ?></small><?php endif; ?></label>
                            <p class="form-note">Recorded only when the PPA is approved. Cannot exceed the approved cost of <?= peso((float) $ppa['cost']) ?>.</p>
                        </fieldset>
                        <fieldset class="approval-status-field">
                            <legend>Approval status <em>*</em></legend>
                            <label class="check-option"><input type="radio" name="approval_status" value="approved" <?= $input['approval_status'] === 'approved' ? 'checked' : '' ?>> <span><?= statusPill('approved') ?> include in the FY <?= AIP_FISCAL_YEAR ?> AIP</span></label>
                            <label class="check-option"><input type="radio" name="approval_status" value="disapproved" <?= $input['approval_status'] === 'disapproved' ? 'checked' : '' ?>> <span><?= statusPill('disapproved') ?> end its processing (remarks required)</span></label>
                            <?php if (isset($errors['approval_status'])): ?><small class="field-error"><?= e($errors['approval_status']) ?></small><?php endif; ?>
                        </fieldset>
                    <?php endif; ?>
                    <label class="remarks-label">Remarks <small><?= $isReviewStage ? '(required when returning)' : '(required when disapproved or sending back)' ?></small>
                        <textarea name="remarks" rows="4" maxlength="1000" placeholder="<?= $isReviewStage ? 'What should the office correct or add?' : 'Reason for your decision' ?>"><?= e($input['remarks']) ?></textarea>
                    </label>
                    <div class="decision-buttons">
                        <?php if (!empty($actions['forward']['allowed'])): ?>
                            <button class="primary-button" type="submit" name="action" value="forward" data-confirm-action="Forward <?= e($ppa['ref']) ?> to Finance for budget review?">✓ Forward to Finance</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['return']['allowed'])): ?>
                            <button class="secondary-button" type="submit" name="action" value="return" data-confirm-action="Return <?= e($ppa['ref']) ?> to <?= e(officeName($ppa['office'])) ?> for revision?">↺ Return for revision</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['approve']['allowed'])): ?>
                            <button class="primary-button" type="submit" name="action" value="decide" data-confirm-action="Save the approval status of <?= e($ppa['ref']) ?>? This is the final decision.">✓ Save approval status</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['send_back']['allowed'])): ?>
                            <button class="secondary-button" type="submit" name="action" value="send_back" data-confirm-action="Send <?= e($ppa['ref']) ?> back to Finance to re-evaluate the budget?">↺ Send back to Finance</button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
