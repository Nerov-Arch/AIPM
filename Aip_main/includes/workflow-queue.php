<?php
/**
 * Shared queue layout for the Review, Authorization and Implementation pages.
 *
 * The including page sets $queue:
 *   eyebrow, title, description, step (1-4),
 *   pending (status codes), processed (status codes),
 *   pendingHeading, processedHeading, actionLabel, note,
 *   showProgress (bool)
 *
 * Rows come from the PPA store (includes/ppa.php). Actions only open record details;
 * changing a PPA's status needs the workflow stage and the database.
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
        <label>Fund source<select data-filter="fund" data-for="<?= $queueId ?>"><option value="">All sources</option><?php foreach (mockFundSources() as $code => $fund): ?><option value="<?= e($code) ?>"><?= e($fund['name']) ?></option><?php endforeach; ?></select></label>
        <label>Search<input type="search" placeholder="Title or ref no." data-search data-for="<?= $queueId ?>"></label>
        <button class="text-button" type="button" data-filter-reset data-for="<?= $queueId ?>">Clear filters</button>
    </div>
    <div class="table-wrap">
        <table class="activity-table">
            <thead><tr><th>Ref #</th><th>Project Title</th><th>Implementing Office</th><th>Estimated Cost</th><?php if ($showProgress): ?><th>Progress</th><?php endif; ?><th>Status</th><th data-no-export>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $ppa):
                $fields = [
                    ['Reference no.', $ppa['ref']],
                    ['Type', $ppa['type']],
                    ['Program', $ppa['program']],
                    ['Implementing office', officeName($ppa['office'])],
                    ['Responsible officer', $ppa['officer']],
                    ['Fund source', fundName($ppa['fund'])],
                    ['Estimated cost', peso($ppa['cost'])],
                    ['Schedule', displayDate($ppa['start']) . ' – ' . displayDate($ppa['end'])],
                    ['Status', statusLabel($ppa['status'])],
                    ['Approval record', $ppa['record'] !== '' ? $ppa['record'] . ' (' . displayDate($ppa['recordDate']) . ')' : 'None attached'],
                    ['Last updated', displayDate($ppa['updated'])],
                ];
                if ($showProgress) {
                    $fields[] = ['Physical progress', $ppa['progress'] . '%'];
                    $fields[] = ['Amount utilized', peso($ppa['utilized'])];
                }
                $detail = ['eyebrow' => $queue['title'], 'title' => $ppa['title'], 'fields' => $fields, 'note' => ($canAct && $ppa['state'] === 'pending') ? $queue['note'] : ''];
            ?>
                <tr data-row data-state="<?= $ppa['state'] ?>" data-office="<?= e($ppa['office']) ?>" data-fund="<?= e($ppa['fund']) ?>" data-cost="<?= $ppa['cost'] ?>">
                    <td><?= e($ppa['ref']) ?></td>
                    <td class="title-cell" data-export="<?= e($ppa['title']) ?>"><a class="title-link" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>"><strong><?= e($ppa['title']) ?></strong></a><small class="table-subtext"><?= e($ppa['type']) ?> · <?= e(fundName($ppa['fund'])) ?></small></td>
                    <td data-export="<?= e(officeName($ppa['office'])) ?>"><?= e($ppa['office']) ?></td>
                    <td class="amount" data-export="<?= number_format($ppa['cost'], 2, '.', '') ?>"><?= peso($ppa['cost']) ?></td>
                    <?php if ($showProgress): ?><td data-export="<?= $ppa['progress'] ?>%"><div class="table-progress"><div class="progress-track"><span style="width:<?= (int) $ppa['progress'] ?>%"></span></div><b><?= (int) $ppa['progress'] ?>%</b></div></td><?php endif; ?>
                    <td><?= statusPill($ppa['status']) ?></td>
                    <td><button class="review-action" type="button" data-detail="<?= e(json_encode($detail)) ?>"><?= e($canAct && $ppa['state'] === 'pending' ? $queue['actionLabel'] : 'View') ?></button></td>
                </tr>
            <?php endforeach; ?>
                <tr data-empty hidden><td class="empty-cell" colspan="<?= $showProgress ? 7 : 6 ?>">No records match the current view or filters.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar" data-pager data-noun="entries"></div>
</section>
