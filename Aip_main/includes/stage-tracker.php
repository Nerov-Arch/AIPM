<?php
/**
 * Where a PPA is in the review sequence and who it is waiting on.
 * Expects $ppa.
 */
$stage = ppaStage($ppa);
$trackerSteps = ['Encoding', 'MPDO Review', 'Approval', 'Implementation'];
$ended = $ppa['status'] === 'disapproved';
$days = daysInStatus($ppa);
$isWaiting = in_array($ppa['status'], ['returned', 'for_review', 'recommended'], true);
?>
<section class="panel stage-tracker <?= $ended ? 'ended' : '' ?>">
    <div class="stage-steps">
        <?php foreach ($trackerSteps as $i => $label): $n = $i + 1;
            if ($n < $stage['step']) {
                $state = 'complete';
            } elseif ($n === $stage['step']) {
                $state = $ended ? 'stopped' : ($ppa['status'] === 'completed' ? 'complete' : 'current');
            } else {
                $state = '';
            }
        ?>
            <?php if ($i > 0): ?><div class="cycle-line <?= $n <= $stage['step'] && !($ended && $n === $stage['step']) ? 'complete' : '' ?>"></div><?php endif; ?>
            <div class="cycle-step <?= $state ?>"><span><?= $state === 'complete' ? '✓' : ($state === 'stopped' ? '×' : $n) ?></span><b><?= e($label) ?></b></div>
        <?php endforeach; ?>
    </div>
    <div class="stage-summary">
        <div><span class="stat-label">Status</span><?= statusPill($ppa['status']) ?></div>
        <div><span class="stat-label">Currently with</span><strong><?= e($stage['holder']) ?></strong></div>
        <div><span class="stat-label"><?= $isWaiting ? 'Waiting' : 'In this status' ?></span><strong class="<?= $isWaiting && $days > WORKFLOW_OVERDUE_DAYS ? 'overdue-text' : '' ?>"><?= e(waitingLabel($days)) ?><?= $isWaiting && $days > WORKFLOW_OVERDUE_DAYS ? ' · overdue' : '' ?></strong></div>
        <div class="stage-note"><?= e($stage['waiting']) ?></div>
    </div>
</section>
