<?php
/**
 * Shared queue layout for the Review, Authorization and Implementation pages.
 *
 * The including page sets $queue:
 *   eyebrow, title, description, step (1-4),
 *   pending (status codes), processed (status codes),
 *   pendingHeading, processedHeading, actionLabel, actPermission,
 *   decisionPage (bool), showProgress (bool)
 *
 * Rows come from the PPA store (includes/ppa.php). Pending rows open the decision
 * page (pages/ppa-review.php) when the page sets decisionPage; decisions are
 * enforced in includes/workflow.php.
 */
$queueId = 'queue-table';
$steps = ['Encoding', 'Review', 'Authorization', 'Implementation'];
$rows = [];
foreach (visiblePpas() as $ppa) {   // Encoders see only their own office
    if (in_array($ppa['status'], $queue['pending'], true)) {
        $rows[] = $ppa + ['state' => 'pending'];
    } elseif (in_array($ppa['status'], $queue['processed'], true)) {
        $rows[] = $ppa + ['state' => 'processed'];
    }
}
// Oldest waiting first, so delayed submissions are seen first.
usort($rows, fn ($a, $b) => [$a['state'], $a['state'] === 'pending' ? statusSince($a) : ''] <=> [$b['state'], $b['state'] === 'pending' ? statusSince($b) : '']);
$overdueCount = count(array_filter($rows, fn ($r) => $r['state'] === 'pending' && daysInStatus($r) > WORKFLOW_OVERDUE_DAYS));
$officesInQueue = array_unique(array_column($rows, 'office'));
sort($officesInQueue);
$pendingCount = count(array_filter($rows, fn ($r) => $r['state'] === 'pending'));
$processedCount = count($rows) - $pendingCount;
$showProgress = !empty($queue['showProgress']);
$canAct = !empty($queue['actPermission']) && can($queue['actPermission']);
$scopeNote = can('scope.all_offices') ? '' : 'Showing PPAs of your office (' . officeName(currentUser()['office']) . ') only.';
?>
<div class="page-heading">
    <div><p class="eyebrow"><?= e($queue['eyebrow']) ?></p><h1><?= e($queue['title']) ?></h1><p class="muted"><?= e($queue['description']) ?></p></div>
    <div class="segmented-control" role="group" aria-label="Show records">
        <button class="active" type="button" data-tab="pending" data-for="<?= $queueId ?>" aria-pressed="true">Pending (<?= $pendingCount ?>)</button>
        <button type="button" data-tab="processed" data-for="<?= $queueId ?>" aria-pressed="false">Processed (<?= $processedCount ?>)</button>
    </div>
</div>

<?php if ($scopeNote): ?><div class="alert info" role="status"><?= e($scopeNote) ?></div><?php endif; ?>
<?php if ($overdueCount): ?><div class="alert error" role="status"><strong><?= $overdueCount ?> PPA<?= $overdueCount === 1 ? ' has' : 's have' ?> been waiting more than <?= WORKFLOW_OVERDUE_DAYS ?> days.</strong> They are listed first.</div><?php endif; ?>

<section class="panel cycle-panel">
    <div class="panel-heading"><h2>Current Cycle: FY <?= AIP_FISCAL_YEAR ?> AIP Preparation</h2></div>
    <div class="cycle-stepper">
        <?php foreach ($steps as $i => $label): $n = $i + 1; ?>
            <?php if ($i > 0): ?><div class="cycle-line <?= $n <= $queue['step'] ? 'complete' : '' ?>"></div><?php endif; ?>
            <div class="cycle-step <?= $n < $queue['step'] ? 'complete' : ($n === $queue['step'] ? 'current' : '') ?>"><span><?= $n < $queue['step'] ? '✓' : $n ?></span><b><?= e($label) ?></b></div>
        <?php endforeach; ?>
    </div>
</section>

<section class="panel review-table-panel flush-panel" id="<?= $queueId ?>" data-table-panel data-page-size="5">
    <div class="panel-heading">
        <h2><span data-tab-heading data-for="<?= $queueId ?>" data-label-pending="<?= e($queue['pendingHeading']) ?>" data-label-processed="<?= e($queue['processedHeading']) ?>"><?= e($queue['pendingHeading']) ?></span> (<span data-count data-for="<?= $queueId ?>"><?= $pendingCount ?></span>)</h2>
        <div class="heading-actions">
            <button class="filter-button" type="button" data-toggle-target="#queue-filters">☷ Filter Options</button>
            <button class="filter-button" type="button" data-export-csv="<?= e(strtolower(str_replace(' ', '-', $queue['title']))) ?>.csv" data-for="<?= $queueId ?>">⇩ Export</button>
        </div>
    </div>
    <div class="filter-bar" id="queue-filters" hidden>
        <label>Implementing office<select data-filter="office" data-for="<?= $queueId ?>"><option value="">All offices</option><?php foreach ($officesInQueue as $code): ?><option value="<?= e($code) ?>"><?= e(officeName($code)) ?></option><?php endforeach; ?></select></label>
        <label>Fund source<select data-filter="fund" data-for="<?= $queueId ?>"><option value="">All sources</option><?php foreach (fundSources() as $code => $fund): ?><option value="<?= e($code) ?>"><?= e($fund['name']) ?></option><?php endforeach; ?></select></label>
        <label>Search<input type="search" placeholder="Title or ref no." data-search data-for="<?= $queueId ?>"></label>
        <button class="text-button" type="button" data-filter-reset data-for="<?= $queueId ?>">Clear filters</button>
    </div>
    <div class="table-wrap">
        <table class="activity-table">
            <thead><tr><th>Ref #</th><th>Project Title</th><th>Implementing Office</th><th>Estimated Cost</th><?php if ($showProgress): ?><th>Progress</th><?php endif; ?><th>Status</th><th>In status</th><th data-no-export>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $ppa):
                $days = daysInStatus($ppa);
                $overdue = $ppa['state'] === 'pending' && $days > WORKFLOW_OVERDUE_DAYS;
                $mine = $canAct && $ppa['state'] === 'pending' && array_filter(workflowActionsFor($ppa), fn ($a) => $a['allowed']);
                $target = !empty($queue['decisionPage']) && $ppa['state'] === 'pending' ? 'ppa-review.php' : 'ppa-view.php';
            ?>
                <tr data-row data-state="<?= $ppa['state'] ?>" data-office="<?= e($ppa['office']) ?>" data-fund="<?= e($ppa['fund']) ?>" data-cost="<?= $ppa['cost'] ?>">
                    <td><a class="ref-link" href="<?= $target ?>?ref=<?= urlencode($ppa['ref']) ?>"><?= e($ppa['ref']) ?></a></td>
                    <td class="title-cell" data-export="<?= e($ppa['title']) ?>"><a class="title-link" href="<?= $target ?>?ref=<?= urlencode($ppa['ref']) ?>"><strong><?= e($ppa['title']) ?></strong></a><small class="table-subtext"><?= e($ppa['type']) ?> · <?= e(ppaFundLabel($ppa)) ?></small></td>
                    <td data-export="<?= e(officeName($ppa['office'])) ?>"><?= e($ppa['office']) ?></td>
                    <td class="amount" data-export="<?= number_format($ppa['cost'], 2, '.', '') ?>"><?= peso($ppa['cost']) ?></td>
                    <?php if ($showProgress): ?><td data-export="<?= $ppa['progress'] ?>%"><div class="table-progress"><div class="progress-track"><span style="width:<?= (int) $ppa['progress'] ?>%"></span></div><b><?= (int) $ppa['progress'] ?>%</b></div></td><?php endif; ?>
                    <td><?= statusPill($ppa['status']) ?></td>
                    <td data-export="<?= $days ?> days"><span class="<?= $overdue ? 'overdue-text' : 'table-subtext' ?>"><?= e(waitingLabel($days)) ?><?= $overdue ? ' · overdue' : '' ?></span></td>
                    <td><a class="review-action <?= $mine ? '' : 'quiet' ?>" href="<?= $target ?>?ref=<?= urlencode($ppa['ref']) ?>"><?= e($mine ? $queue['actionLabel'] : 'View') ?></a></td>
                </tr>
            <?php endforeach; ?>
                <tr data-empty hidden><td class="empty-cell" colspan="<?= $showProgress ? 8 : 7 ?>">No records match the current view or filters.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar" data-pager data-noun="entries"></div>
</section>
