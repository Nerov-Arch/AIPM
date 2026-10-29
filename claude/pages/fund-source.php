<?php
/**
 * One fund source: its ceiling, how much is programmed and pending,
 * and every PPA charged to it.
 */
$pageSection = 'Reports & Funds';
$activePage = 'funds';
$requiredPermission = 'funds.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$key = (string) ($_GET['key'] ?? '');
$totals = fundTotals();
if (!isset($totals[$key])) {
    flash('error', 'That fund source was not found.');
    header('Location: fund-monitoring.php');
    exit;
}
$fund = $totals[$key];
$pageTitle = $fund['name'];
$rows = [];
foreach (visiblePpas() as $ppa) {
    $alloc = ppaAllocations($ppa);
    if (isset($alloc[$key]) && $ppa['status'] !== 'disapproved') {
        $rows[] = $ppa + ['charged' => $alloc[$key], 'bucket' => fundBucket($ppa['status'])];
    }
}
$fundIssues = array_values(array_filter(fundingIssues(), fn ($i) => $i['fund'] === $key || in_array($i['ref'], array_column($rows, 'ref'), true)));
$tableId = 'fund-ppas';

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="fund-monitoring.php">Fund Monitoring</a> / <?= e($fund['code']) ?></p>
        <h1><?= e($fund['name']) ?></h1>
        <p class="muted"><?= e($fund['category']) ?><?= $fund['description'] !== '' ? ' · ' . e($fund['description']) : '' ?></p>
    </div>
    <div class="heading-actions">
        <?= statusPill(!empty($fund['active']) ? 'approved' : 'draft', !empty($fund['active']) ? 'Active' : 'Inactive') ?>
        <button class="secondary-button" type="button" data-export-csv="<?= e(strtolower($fund['code'])) ?>-ppas.csv" data-for="<?= $tableId ?>">⇩ Export</button>
        <?php if (can('funds.manage')): ?><a class="primary-button" href="fund-form.php?key=<?= urlencode($key) ?>">Edit fund source</a><?php endif; ?>
    </div>
</div>

<section class="stat-grid four">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">FY <?= AIP_FISCAL_YEAR ?> ceiling</span><span class="stat-icon blue">▥</span></div><strong><?= peso($fund['ceiling']) ?></strong><p><?= e($fund['code']) ?></p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Programmed</span><span class="stat-icon green">▣</span></div><strong><?= peso($fund['programmed']) ?></strong><p><?= $fund['count'] ?> approved PPA<?= $fund['count'] === 1 ? '' : 's' ?> · <?= number_format($fund['share'], 1) ?>% of ceiling</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Pending decision</span><span class="stat-icon amber">◔</span></div><strong><?= peso($fund['pending']) ?></strong><p><?= $fund['pendingCount'] ?> PPA<?= $fund['pendingCount'] === 1 ? '' : 's' ?> under review or authorization</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Available</span><span class="stat-icon <?= $fund['available'] < 0 ? 'amber' : 'green' ?>">₱</span></div><strong class="<?= $fund['available'] < 0 ? 'orange-number' : '' ?>"><?= peso($fund['available']) ?></strong><p><?= peso($fund['ceiling'] - $fund['programmed'] - $fund['pending']) ?> if all pending PPAs are approved</p></article>
</section>

<section class="panel ceiling-panel">
    <div class="panel-heading"><div><h2>Ceiling use</h2><p class="muted"><?= e($fund['state'][0]) ?> · <?= peso($fund['utilized']) ?> utilized so far</p></div></div>
    <div class="ceiling-meter large"><span class="programmed" style="width:<?= min(100, $fund['share']) ?>%"></span><span class="projected" style="width:<?= min(100, $fund['projectedShare']) ?>%"></span></div>
    <div class="meter-labels"><span><span class="legend-swatch programmed"></span> Programmed <?= number_format($fund['share'], 1) ?>%</span><span><span class="legend-swatch projected"></span> With pending <?= number_format($fund['projectedShare'], 1) ?>%</span><span>Ceiling <?= peso($fund['ceiling']) ?></span></div>
    <?php foreach ($fundIssues as $issue): ?>
        <p class="funding-issue <?= $issue['level'] ?>"><strong><?= e($issue['type']) ?><?= $issue['ref'] !== '' ? ' (' . e($issue['ref']) . ')' : '' ?>:</strong> <?= e($issue['message']) ?></p>
    <?php endforeach; ?>
</section>

<section class="panel flush-panel" id="<?= $tableId ?>" data-table-panel data-page-size="10">
    <div class="panel-heading">
        <div><h2>PPAs charged to this fund</h2><p class="muted">Total charged: <strong data-sum="charged" data-for="<?= $tableId ?>">₱0.00</strong></p></div>
        <div class="segmented-control" role="group" aria-label="Show">
            <button class="active" type="button" data-tab="" data-for="<?= $tableId ?>" aria-pressed="true">All (<?= count($rows) ?>)</button>
            <button type="button" data-tab="programmed" data-for="<?= $tableId ?>" aria-pressed="false">Programmed (<?= $fund['count'] ?>)</button>
            <button type="button" data-tab="pending" data-for="<?= $tableId ?>" aria-pressed="false">Pending (<?= $fund['pendingCount'] ?>)</button>
        </div>
    </div>
    <div class="table-wrap">
        <table class="activity-table ppa-table">
            <thead><tr><th>Ref No.</th><th>PPA</th><th>Office</th><th>Status</th><th>PPA Cost</th><th>Charged to this fund</th><th>Utilized</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $ppa): $share = $ppa['cost'] > 0 ? $ppa['charged'] / $ppa['cost'] : 0; ?>
                <tr data-row data-state="<?= $ppa['bucket'] ?>" data-charged="<?= $ppa['charged'] ?>">
                    <td><a class="ref-link" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>"><?= e($ppa['ref']) ?></a></td>
                    <td class="title-cell" data-export="<?= e($ppa['title']) ?>"><strong><?= e($ppa['title']) ?></strong><?php if (ppaIsSplit($ppa)): ?><small class="table-subtext">Split: <?= e(ppaFundLabel($ppa)) ?></small><?php endif; ?></td>
                    <td data-export="<?= e(officeName($ppa['office'])) ?>"><?= e($ppa['office']) ?></td>
                    <td><?= statusPill($ppa['status']) ?></td>
                    <td class="amount" data-export="<?= number_format($ppa['cost'], 2, '.', '') ?>"><?= peso($ppa['cost']) ?></td>
                    <td class="amount" data-export="<?= number_format($ppa['charged'], 2, '.', '') ?>"><?= peso($ppa['charged']) ?><small class="table-subtext"><?= number_format($share * 100, 1) ?>% of PPA</small></td>
                    <td class="amount" data-export="<?= number_format((float) $ppa['utilized'] * $share, 2, '.', '') ?>"><?= peso((float) $ppa['utilized'] * $share) ?></td>
                </tr>
            <?php endforeach; ?>
                <tr data-empty hidden><td class="empty-cell" colspan="7">No PPAs are charged to this fund source yet.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar" data-pager data-noun="PPAs"></div>
</section>
<p class="form-note">Last updated <?= e(date('M j, Y g:i A', strtotime($fund['updated_at']))) ?> by <?= e($fund['updated_by']) ?>.</p>
<?php include '../includes/footer.php'; ?>
