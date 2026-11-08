<?php
/**
 * Finance budget review of one PPA (between MPDO evaluation and final approval).
 * Finance compares the requested budget with fund availability and approves the
 * requested amount or a lower one, or returns the PPA to the MPDO.
 * All rules are enforced in includes/workflow.php.
 */
$pageSection = 'Workflow';
$activePage = 'budget';
$requiredPermission = 'budget.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$ppa = storeFindPpa((string) ($_GET['ref'] ?? ''));
if (!$ppa || in_array($ppa['status'], ['draft', 'for_review', 'returned'], true) && empty($ppa['budget']['approved']) || !canSeeOffice($ppa['office'])) {
    flash('error', 'That PPA has not reached budget review, or you do not have access to it.');
    header('Location: budget-workflow.php');
    exit;
}
$pageTitle = 'Budget review: ' . $ppa['ref'];
$requested = ppaRequested($ppa);
$money = fn (float $v) => $v > 0 ? number_format($v, 2) : '';

$errors = [];
$input = ['approved_ps' => $money($requested['ps']), 'approved_mooe' => $money($requested['mooe']), 'approved_co' => $money($requested['co']), 'remarks' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');
    foreach (array_keys($input) as $field) {
        $input[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    [$errors, $saved] = applyWorkflowAction($ppa, $action, $input);
    if (!$errors) {
        flash('success', $action === 'budget_approve'
            ? e($ppa['ref']) . ' budget approved at ' . e(peso($saved['cost'])) . ' and sent to the MPDO for final approval.'
            : e($ppa['ref']) . ' was returned to the MPDO.');
        header('Location: budget-workflow.php');
        exit;
    }
}

$actions = workflowActionsFor($ppa);
$canDecide = (bool) array_filter($actions, fn ($a) => $a['allowed']);
$approvedBudget = ppaApprovedBudget($ppa);
$totals = fundTotals();
$issues = ppaFundingIssues($ppa['ref']);
$classes = ['ps' => 'Personal Services (PS)', 'mooe' => 'MOOE', 'co' => 'Capital Outlay (CO)'];

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="budget-workflow.php">Budget Review</a> / <?= e($ppa['ref']) ?></p>
        <h1><?= e($ppa['title']) ?></h1>
        <p class="muted"><?= e($ppa['type']) ?> · <?= e(officeName($ppa['office'])) ?> · evaluated by <?= e(explode(' (', $ppa['recommended_by'] ?? '—')[0]) ?></p>
    </div>
    <div class="heading-actions"><a class="secondary-button" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>">Open full record</a></div>
</div>

<?php include '../includes/stage-tracker.php'; ?>

<?php if ($errors): ?>
    <div class="alert error" role="alert"><strong>The budget decision was not saved.</strong><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="review-layout">
    <div class="review-main">
        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon amber-bg">₱</span><div><h2>Requested vs approved budget</h2><p>By expense class</p></div></div>
            <div class="form-divider"></div>
            <table class="cost-table budget-compare" data-budget-compare>
                <thead><tr><th>Expense class</th><th>Requested</th><th>Approved</th><th>Difference</th></tr></thead>
                <tbody>
                <?php foreach ($classes as $field => $label): $a = $approvedBudget[$field] ?? null; ?>
                    <tr><th><?= e($label) ?></th><td><?= peso($requested[$field]) ?></td><td><?= $a === null ? '—' : peso($a) ?></td><td class="<?= $a !== null && $a < $requested[$field] ? 'budget-diff down' : '' ?>"><?= $a === null ? '—' : peso($a - $requested[$field]) ?></td></tr>
                <?php endforeach; ?>
                <tr class="cost-total"><th>Total</th><td><?= peso($requested['total']) ?></td><td><?= $approvedBudget ? peso($approvedBudget['total']) : 'Not yet decided' ?></td><td><?= $approvedBudget ? peso($approvedBudget['total'] - $requested['total']) : '—' ?></td></tr>
                </tbody>
            </table>
            <?php if ($approvedBudget): ?><p class="form-note">Decided by <?= e($ppa['budget']['by']) ?> on <?= e(date('M j, Y g:i A', strtotime($ppa['budget']['at']))) ?><?= $ppa['budget']['remarks'] !== '' ? ': ' . e($ppa['budget']['remarks']) : '' ?></p><?php endif; ?>
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">▥</span><div><h2>Fund availability</h2><p>Programmed amounts are approved PPAs; available is what is left under the ceiling</p></div></div>
            <div class="form-divider"></div>
            <table class="cost-table">
                <thead><tr><th>Fund source</th><th>Charged to this PPA</th><th>Ceiling</th><th>Programmed</th><th>Available</th></tr></thead>
                <tbody>
                <?php foreach (ppaAllocations($ppa) as $fundKey => $amount): $t = $totals[$fundKey] ?? null; ?>
                    <tr><th><?= e(fundName($fundKey)) ?></th><td><?= peso($amount) ?></td><td><?= $t ? peso((float) $t['ceiling']) : '—' ?></td><td><?= $t ? peso($t['programmed']) : '—' ?></td><td class="<?= $t && $t['available'] < $amount ? 'budget-diff down' : '' ?>"><?= $t ? peso($t['available']) : '—' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php foreach ($issues as $issue): ?><p class="funding-issue <?= $issue['level'] ?>"><strong><?= e($issue['type']) ?>:</strong> <?= e($issue['message']) ?></p><?php endforeach; ?>
        </section>

        <section class="panel form-panel">
            <div class="form-section-heading"><span class="section-icon blue-bg">ⓘ</span><div><h2>PPA summary</h2><p><?= e($ppa['program'] ?: 'No program') ?></p></div></div>
            <div class="form-divider"></div>
            <dl class="detail-list wide-terms">
                <dt>Schedule</dt><dd><?= e(displayDate($ppa['start'])) ?> – <?= e(displayDate($ppa['end'])) ?></dd>
                <dt>Rationale</dt><dd class="prewrap"><?= e($ppa['description'] ?: '—') ?></dd>
                <dt>Expected outputs</dt><dd class="prewrap"><?= e($ppa['expected_output'] ?: '—') ?></dd>
            </dl>
            <div class="form-divider"></div>
            <ol class="history-list">
                <?php foreach (array_slice(array_reverse($ppa['history']), 0, 6) as $entry): ?>
                    <li><div class="history-head"><strong><?= e($entry['action']) ?></strong><?= statusPill($entry['status']) ?></div><small><?= e(date('M j, Y g:i A', strtotime($entry['time']))) ?> · <?= e($entry['by']) ?></small><?php if ($entry['remarks'] !== ''): ?><p><?= e($entry['remarks']) ?></p><?php endif; ?></li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>

    <div class="review-side">
        <section class="panel form-panel decision-panel">
            <div class="form-section-heading"><span class="section-icon green-bg">✓</span><div><h2>Budget decision</h2><p><?= $canDecide ? 'Your decision is recorded under your name.' : 'No action is needed from you.' ?></p></div></div>
            <div class="form-divider"></div>
            <?php foreach (array_filter($actions, fn ($a) => !$a['allowed']) as $block): ?><p class="funding-issue warning"><?= e($block['reason']) ?></p><?php break; endforeach; ?>
            <?php if (!$canDecide): ?>
                <p class="muted"><?= e(ppaStage($ppa)['waiting']) ?>.</p>
                <?php if ($ppa['status'] === 'for_budget' && !can('budget.act')): ?><p class="form-note">Only Finance staff can approve the budget.</p><?php endif; ?>
                <a class="secondary-button" href="budget-workflow.php">Back to queue</a>
            <?php else: ?>
                <form method="post" class="decision-form" novalidate>
                    <?= csrfField() ?>
                    <p class="legend-like">Approved budget <small>(the requested amount or lower)</small></p>
                    <?php foreach ($classes as $field => $label): ?>
                        <label><?= e($label) ?> <small class="table-subtext">requested <?= peso($requested[$field]) ?></small><div class="currency-input"><span>₱</span><input name="approved_<?= $field ?>" type="text" inputmode="decimal" placeholder="0.00" data-total-part value="<?= e($input['approved_' . $field]) ?>"></div><?php if (isset($errors['approved_' . $field])): ?><small class="field-error"><?= e($errors['approved_' . $field]) ?></small><?php endif; ?></label>
                    <?php endforeach; ?>
                    <div class="total-cost"><span>Approved total</span><strong data-total-display>₱0.00</strong></div>
                    <label class="remarks-label">Remarks <small>(required when lowering the budget or returning)</small>
                        <textarea name="remarks" rows="4" maxlength="1000" placeholder="Basis for the approved amount, or why it is returned"><?= e($input['remarks']) ?></textarea>
                        <?php if (isset($errors['remarks'])): ?><small class="field-error"><?= e($errors['remarks']) ?></small><?php endif; ?>
                    </label>
                    <div class="decision-buttons">
                        <button class="primary-button" type="submit" name="action" value="budget_approve" data-confirm-action="Approve this budget for <?= e($ppa['ref']) ?> and send it to the MPDO for final approval?">✓ Approve budget</button>
                        <button class="secondary-button" type="submit" name="action" value="budget_return" data-confirm-action="Return <?= e($ppa['ref']) ?> to the MPDO?">↺ Return to MPDO</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
