<?php
/**
 * Evaluate a submitted PPA and record a decision.
 *   Reviewer (status Pending Review):       return for revision, or recommend
 *   Approver (status Pending Authorization): approve, disapprove, or send back
 * All rules are enforced in includes/workflow.php.
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
$activePage = $ppa['status'] === 'recommended' ? 'authorization' : 'review';
$queuePage = $activePage === 'authorization' ? 'authorization-workflow.php' : 'review-workflow.php';
$pageTitle = 'Evaluate ' . $ppa['ref'];

$errors = [];
$input = ['remarks' => '', 'checklist' => $ppa['checklist'] ?? [], 'resolution' => '', 'resolution_date' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = (string) ($_POST['action'] ?? '');
    $input = [
        'remarks'         => (string) ($_POST['remarks'] ?? ''),
        'checklist'       => (array) ($_POST['checklist'] ?? []),
        'resolution'      => (string) ($_POST['resolution'] ?? ''),
        'resolution_date' => (string) ($_POST['resolution_date'] ?? ''),
    ];
    [$errors, $saved] = applyWorkflowAction($ppa, $action, $input);
    if (!$errors) {
        $messages = [
            'return'     => ' was returned to ' . officeName($ppa['office']) . ' for revision.',
            'recommend'  => ' was recommended and forwarded to the approver.',
            'approve'    => ' was approved and is included in the FY ' . AIP_FISCAL_YEAR . ' AIP.',
            'disapprove' => ' was disapproved.',
            'send_back'  => ' was sent back to the MPDO for re-evaluation.',
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
$lastRemarks = '';
foreach (array_reverse($ppa['history']) as $entry) {
    if ($entry['remarks'] !== '') { $lastRemarks = $entry; break; }
}

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="<?= $queuePage ?>"><?= $activePage === 'authorization' ? 'Authorization' : 'Review' ?></a> / <?= e($ppa['ref']) ?></p>
        <h1><?= e($ppa['title']) ?></h1>
        <p class="muted"><?= e($ppa['type']) ?> · <?= e(officeName($ppa['office'])) ?> · encoded by <?= e(explode(' (', $ppa['created_by'])[0]) ?></p>
    </div>
    <div class="heading-actions"><a class="secondary-button" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>">Open full record</a></div>
</div>

<?php include '../includes/stage-tracker.php'; ?>

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
                <dt>Cost</dt><dd>PS <?= peso((float) $ppa['ps']) ?> · MOOE <?= peso((float) $ppa['mooe']) ?> · CO <?= peso((float) $ppa['co']) ?><br><strong>Total <?= peso($ppa['cost']) ?></strong></dd>
                <dt>Funding</dt><dd><?php foreach (ppaAllocations($ppa) as $fundKey => $amount): ?><?= e(fundName($fundKey)) ?>: <?= peso($amount) ?><br><?php endforeach; ?></dd>
                <?php if ((float) $ppa['cc_adaptation'] + (float) $ppa['cc_mitigation'] > 0): ?><dt>Climate change</dt><dd>Adaptation <?= peso((float) $ppa['cc_adaptation']) ?> · Mitigation <?= peso((float) $ppa['cc_mitigation']) ?> · <?= e($ppa['cc_typology']) ?></dd><?php endif; ?>
                <?php if (!empty($ppa['recommended_by'])): ?><dt>Recommended by</dt><dd><?= e($ppa['recommended_by']) ?> on <?= e(date('M j, Y', strtotime($ppa['recommended_at']))) ?></dd><?php endif; ?>
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
            <div class="form-section-heading"><span class="section-icon green-bg">✓</span><div><h2><?= $isApprovalStage ? 'Final decision' : ($isReviewStage ? 'MPDO evaluation' : 'Decision') ?></h2><p><?= $canDecide ? 'Your decision is recorded under your name.' : 'No action is needed from you.' ?></p></div></div>
            <div class="form-divider"></div>

            <?php foreach ($blocked as $key => $block): ?><p class="funding-issue warning"><?= e($block['reason']) ?></p><?php break; endforeach; ?>

            <?php if (!$canDecide): ?>
                <p class="muted"><?= e(ppaStage($ppa)['waiting']) ?>.</p>
                <?php if ($ppa['status'] === 'for_review' && !can('review.act')): ?><p class="form-note">Only MPDO reviewers can evaluate this PPA.</p><?php endif; ?>
                <?php if ($ppa['status'] === 'recommended' && !can('authorization.act')): ?><p class="form-note">Only approvers can decide on this PPA.</p><?php endif; ?>
                <a class="secondary-button" href="<?= $queuePage ?>">Back to queue</a>
            <?php else: ?>
                <form method="post" class="decision-form" novalidate>
                    <?= csrfField() ?>
                    <?php if ($isReviewStage): ?>
                        <fieldset class="checklist">
                            <legend>Review checklist <small>(all required to recommend)</small></legend>
                            <?php foreach (REVIEW_CHECKLIST as $key => $label): ?>
                                <label class="check-option"><input type="checkbox" name="checklist[]" value="<?= $key ?>" <?= in_array($key, $input['checklist'], true) ? 'checked' : '' ?>> <span><?= e($label) ?></span></label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endif; ?>
                    <?php if ($isApprovalStage): ?>
                        <div class="approval-record">
                            <p class="legend-like">Approval record <small>(optional, links the PPA to its resolution)</small></p>
                            <label>Resolution No.<input name="resolution" type="text" maxlength="40" placeholder="e.g. SB Res. No. 2026-120" value="<?= e($input['resolution']) ?>"></label>
                            <label>Date<input name="resolution_date" type="date" max="<?= date('Y-m-d') ?>" value="<?= e($input['resolution_date']) ?>"></label>
                        </div>
                    <?php endif; ?>
                    <label class="remarks-label">Remarks <small><?= $isReviewStage ? '(required when returning)' : '(required when disapproving or sending back)' ?></small>
                        <textarea name="remarks" rows="4" maxlength="1000" placeholder="<?= $isReviewStage ? 'What should the office correct or add?' : 'Reason for your decision' ?>"><?= e($input['remarks']) ?></textarea>
                    </label>
                    <div class="decision-buttons">
                        <?php if (!empty($actions['recommend']['allowed'])): ?>
                            <button class="primary-button" type="submit" name="action" value="recommend" data-confirm-action="Recommend <?= e($ppa['ref']) ?> and forward it to the approver?">✓ Recommend for approval</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['return']['allowed'])): ?>
                            <button class="secondary-button" type="submit" name="action" value="return" data-confirm-action="Return <?= e($ppa['ref']) ?> to <?= e(officeName($ppa['office'])) ?> for revision?">↺ Return for revision</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['approve']['allowed'])): ?>
                            <button class="primary-button" type="submit" name="action" value="approve" data-confirm-action="Approve <?= e($ppa['ref']) ?> for inclusion in the FY <?= AIP_FISCAL_YEAR ?> AIP?">✓ Approve</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['send_back']['allowed'])): ?>
                            <button class="secondary-button" type="submit" name="action" value="send_back" data-confirm-action="Send <?= e($ppa['ref']) ?> back to the MPDO for re-evaluation?">↺ Send back to MPDO</button>
                        <?php endif; ?>
                        <?php if (!empty($actions['disapprove']['allowed'])): ?>
                            <button class="secondary-button danger-button" type="submit" name="action" value="disapprove" data-confirm-action="Disapprove <?= e($ppa['ref']) ?>? This ends its processing for this AIP.">✕ Disapprove</button>
                        <?php endif; ?>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
