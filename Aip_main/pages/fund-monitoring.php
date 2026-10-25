<?php
$pageTitle = 'Fund Monitoring';
$pageSection = 'Reports & Funds';
$activePage = 'funds';
$requiredPermission = 'funds.view';
$assetPrefix = '../';
include '../includes/header.php';

// MOCK: figures are calculated from the sample PPAs and fund ceilings.
$programmedStatuses = ['approved', 'ongoing', 'completed'];
$pendingStatuses = ['for_review', 'recommended', 'returned'];
$funds = [];
foreach (mockFundSources() as $code => $fund) {
    $funds[$code] = $fund + ['programmed' => 0, 'pending' => 0, 'utilized' => 0, 'count' => 0];
}
foreach (visiblePpas() as $ppa) {
    if (!isset($funds[$ppa['fund']])) {
        continue;
    }
    if (in_array($ppa['status'], $programmedStatuses, true)) {
        $funds[$ppa['fund']]['programmed'] += $ppa['cost'];
        $funds[$ppa['fund']]['utilized'] += $ppa['utilized'];
        $funds[$ppa['fund']]['count']++;
    } elseif (in_array($ppa['status'], $pendingStatuses, true)) {
        $funds[$ppa['fund']]['pending'] += $ppa['cost'];
    }
}
$totalCeiling = $totalProgrammed = $totalUtilized = $totalPending = 0;
foreach ($funds as $code => $fund) {
    $remaining = $fund['ceiling'] - $fund['programmed'];
    $share = $fund['ceiling'] > 0 ? $fund['programmed'] / $fund['ceiling'] * 100 : 0;
    if ($fund['programmed'] + $fund['pending'] > $fund['ceiling']) {
        $status = ['Over Ceiling if Approved', 'rejected'];
    } elseif ($share < 20) {
        $status = ['Low Programming', 'pending'];
    } else {
        $status = ['On Track', 'approved'];
    }
    $funds[$code] += ['remaining' => $remaining, 'share' => $share, 'statusLabel' => $status[0], 'statusClass' => $status[1]];
    $totalCeiling += $fund['ceiling'];
    $totalProgrammed += $fund['programmed'];
    $totalUtilized += $fund['utilized'];
    $totalPending += $fund['pending'];
}
$programmedPct = $totalCeiling ? round($totalProgrammed / $totalCeiling * 100, 1) : 0;
$tableId = 'funds-table';
?>
<div class="page-heading">
    <div><p class="eyebrow">Financial oversight</p><h1>Fund Source Monitoring</h1><p class="muted">Track ceilings, programmed amounts and utilization for FY <?= AIP_FISCAL_YEAR ?> across all funding sources.</p></div>
    <button class="secondary-button" type="button" data-export-csv="fund-sources-fy<?= AIP_FISCAL_YEAR ?>.csv" data-for="<?= $tableId ?>">⇩ Export Report</button>
</div>

<section class="stat-grid fund-summary">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total fund ceiling</span><span class="stat-icon blue">▥</span></div><strong><?= peso($totalCeiling) ?></strong><p><?= count($funds) ?> fund sources</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Programmed (approved PPAs)</span><span class="stat-icon green">▣</span></div><strong><?= peso($totalProgrammed) ?></strong><div class="progress-track"><span style="width:<?= min(100, $programmedPct) ?>%"></span></div><p class="align-right"><?= $programmedPct ?>% of ceiling · <?= peso($totalUtilized) ?> utilized</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Remaining balance</span><span class="stat-icon amber">▱</span></div><strong class="orange-number"><?= peso($totalCeiling - $totalProgrammed) ?></strong><p><?= peso($totalPending) ?> still under review or authorization</p></article>
</section>

<section class="panel fund-table-panel" id="<?= $tableId ?>" data-table-panel>
    <div class="panel-heading">
        <div><h2>Fund Sources Breakdown</h2></div>
        <div class="view-toggle" role="group" aria-label="View">
            <button class="active" type="button" data-view-group="funds" data-view-show="#fund-table-view" aria-pressed="true" aria-label="Table view" title="Table view">▤</button>
            <button type="button" data-view-group="funds" data-view-show="#fund-card-view" aria-pressed="false" aria-label="Card view" title="Card view">☷</button>
        </div>
    </div>
    <div class="table-wrap" id="fund-table-view">
        <table class="activity-table">
            <thead><tr><th>Fund Source</th><th>Ceiling</th><th>Programmed</th><th>Utilized</th><th>Remaining</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($funds as $code => $fund): ?>
                <tr data-row data-fund="<?= e($code) ?>">
                    <td data-export="<?= e($fund['name'] . ' (' . $fund['code'] . ')') ?>"><strong><?= e($fund['name']) ?></strong><small class="table-subtext">Code: <?= e($fund['code']) ?> · <?= $fund['count'] ?> PPA<?= $fund['count'] === 1 ? '' : 's' ?></small></td>
                    <td class="amount" data-export="<?= number_format($fund['ceiling'], 2, '.', '') ?>"><?= peso($fund['ceiling']) ?></td>
                    <td class="amount" data-export="<?= number_format($fund['programmed'], 2, '.', '') ?>"><?= peso($fund['programmed']) ?><small class="table-subtext <?= $fund['statusClass'] === 'pending' ? 'orange-text' : '' ?>"><?= number_format($fund['share'], 1) ?>% of ceiling</small></td>
                    <td class="amount" data-export="<?= number_format($fund['utilized'], 2, '.', '') ?>"><?= peso($fund['utilized']) ?></td>
                    <td class="amount" data-export="<?= number_format($fund['remaining'], 2, '.', '') ?>"><?= peso($fund['remaining']) ?></td>
                    <td><span class="status-pill <?= $fund['statusClass'] ?>"><i></i><?= e($fund['statusLabel']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="fund-cards" id="fund-card-view" hidden>
        <?php foreach ($funds as $fund): ?>
            <article class="fund-card">
                <h3><?= e($fund['name']) ?></h3>
                <span class="status-pill <?= $fund['statusClass'] ?>"><i></i><?= e($fund['statusLabel']) ?></span>
                <div class="progress-track"><span style="width:<?= min(100, round($fund['share'])) ?>%"></span></div>
                <dl><dt>Ceiling</dt><dd><?= peso($fund['ceiling']) ?></dd><dt>Programmed</dt><dd><?= peso($fund['programmed']) ?></dd><dt>Pending</dt><dd><?= peso($fund['pending']) ?></dd><dt>Remaining</dt><dd><?= peso($fund['remaining']) ?></dd></dl>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="pagination-bar"><span><?= count($funds) ?> fund sources · Programmed = approved, ongoing and completed PPAs</span></div>
</section>
<?php include '../includes/footer.php'; ?>
