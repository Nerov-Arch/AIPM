<?php
/**
 * Fund Source Monitoring: fund ceilings and their use by PPAs,
 * the funding of each PPA, and funding inconsistencies.
 */
$pageTitle = 'Fund Monitoring';
$pageSection = 'Reports & Funds';
$activePage = 'funds';
$requiredPermission = 'funds.view';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

/* ---------- Fund source actions (POST → redirect) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    requirePermission('funds.manage', $assetPrefix);
    $fund = storeFindFund((string) ($_POST['key'] ?? ''));
    $action = $_POST['action'] ?? '';
    $usedBy = $fund ? array_filter(storePpas(), fn ($p) => isset(ppaAllocations($p)[$fund['key']]) || ($p['fund'] ?? '') === $fund['key']) : [];

    if (!$fund) {
        flash('error', 'That fund source was not found.');
    } elseif ($action === 'toggle') {
        $activate = empty($fund['active']);
        storeSaveFund(['key' => $fund['key'], 'active' => $activate]);
        auditLog($activate ? 'fund.activate' : 'fund.deactivate', $fund['key'], $fund['name']);
        flash('success', e($fund['name']) . ($activate
            ? ' was activated and can be chosen for PPAs.'
            : ' was deactivated. It can no longer be chosen for PPAs' . ($usedBy ? '; ' . count($usedBy) . ' existing PPA(s) charged to it are flagged under Funding issues.' : '.')));
    } elseif ($action === 'delete') {
        if ($usedBy) {
            flash('error', e($fund['name']) . ' cannot be deleted because ' . count($usedBy) . ' PPA(s) are charged to it. Deactivate it instead.');
        } else {
            storeDeleteFund($fund['key']);
            auditLog('fund.delete', $fund['key'], $fund['name']);
            flash('success', e($fund['name']) . ' was deleted.');
        }
    }
    header('Location: fund-monitoring.php');
    exit;
}

$canManage = can('funds.manage');
$totals = fundTotals();
$issues = fundingIssues();
$errorCount = count(array_filter($issues, fn ($i) => $i['level'] === 'error'));
$ppas = array_values(array_filter(visiblePpas(), fn ($p) => $p['status'] !== 'disapproved'));
$issueRefs = array_count_values(array_filter(array_column($issues, 'ref')));

$active = array_filter($totals, fn ($t) => !empty($t['active']));
$sum = fn (string $field) => array_sum(array_column($active, $field));
$totalCeiling = $sum('ceiling');
$totalProgrammed = $sum('programmed');
$totalPending = $sum('pending');
$totalUtilized = $sum('utilized');
$programmedPct = $totalCeiling ? round($totalProgrammed / $totalCeiling * 100, 1) : 0;
$utilizedPct = $totalProgrammed ? round($totalUtilized / $totalProgrammed * 100, 1) : 0;
$tab = in_array($_GET['tab'] ?? '', ['sources', 'ppas', 'issues'], true) ? $_GET['tab'] : 'sources';

include '../includes/header.php';
?>
<div class="page-heading">
    <div><p class="eyebrow">Financial oversight</p><h1>Fund Source Monitoring</h1><p class="muted">Fund ceilings for FY <?= AIP_FISCAL_YEAR ?>, the PPAs charged to each source, and funding inconsistencies to resolve before consolidation.</p></div>
    <div class="heading-actions">
        <?php if ($canManage): ?><a class="primary-button" href="fund-form.php">＋ Add Fund Source</a><?php endif; ?>
    </div>
</div>

<section class="stat-grid four">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total fund ceiling</span><span class="stat-icon blue">▥</span></div><strong><?= pesoShort($totalCeiling) ?></strong><p><?= count($active) ?> active fund sources</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Programmed (approved)</span><span class="stat-icon green">▣</span></div><strong><?= pesoShort($totalProgrammed) ?></strong><div class="progress-track"><span style="width:<?= min(100, $programmedPct) ?>%"></span></div><p><?= $programmedPct ?>% of ceiling · <?= pesoShort($totalCeiling - $totalProgrammed) ?> available</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Pending decision</span><span class="stat-icon amber">◔</span></div><strong><?= pesoShort($totalPending) ?></strong><p>Under review, authorization or revision</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Funding issues</span><span class="stat-icon <?= $errorCount ? 'amber' : 'green' ?>">!</span></div><strong class="<?= $errorCount ? 'orange-number' : '' ?>"><?= count($issues) ?></strong><p><?= $errorCount ?> error<?= $errorCount === 1 ? '' : 's' ?> · <?= count($issues) - $errorCount ?> warning<?= count($issues) - $errorCount === 1 ? '' : 's' ?> · <?= $utilizedPct ?>% of programmed utilized</p></article>
</section>

<div class="tab-bar" role="tablist" aria-label="Fund monitoring sections">
    <button type="button" role="tab" class="<?= $tab === 'sources' ? 'active' : '' ?>" aria-pressed="<?= $tab === 'sources' ? 'true' : 'false' ?>" data-view-group="fund-tabs" data-view-show="#tab-sources">Fund sources (<?= count($totals) ?>)</button>
    <button type="button" role="tab" class="<?= $tab === 'ppas' ? 'active' : '' ?>" aria-pressed="<?= $tab === 'ppas' ? 'true' : 'false' ?>" data-view-group="fund-tabs" data-view-show="#tab-ppas">PPA funding (<?= count($ppas) ?>)</button>
    <button type="button" role="tab" class="<?= $tab === 'issues' ? 'active' : '' ?>" aria-pressed="<?= $tab === 'issues' ? 'true' : 'false' ?>" data-view-group="fund-tabs" data-view-show="#tab-issues">Funding issues (<?= count($issues) ?>)</button>
</div>

<!-- ======================= Fund sources ======================= -->
<div id="tab-sources" <?= $tab !== 'sources' ? 'hidden' : '' ?>>
<section class="panel fund-table-panel flush-panel" id="funds-table" data-table-panel>
    <div class="panel-heading">
        <div><h2>Fund Sources</h2><p class="muted">Approved PPAs count as programmed; PPAs still under review count as pending.</p></div>
        <div class="heading-actions">
            <div class="view-toggle" role="group" aria-label="View">
                <button class="active" type="button" data-view-group="funds" data-view-show="#fund-table-view" aria-pressed="true" aria-label="Table view" title="Table view">▤</button>
                <button type="button" data-view-group="funds" data-view-show="#fund-card-view" aria-pressed="false" aria-label="Card view" title="Card view">☷</button>
            </div>
            <button class="filter-button" type="button" data-export-csv="fund-sources-fy<?= AIP_FISCAL_YEAR ?>.csv" data-for="funds-table">⇩ Export</button>
        </div>
    </div>
    <div class="table-wrap" id="fund-table-view">
        <table class="activity-table fund-table">
            <thead><tr><th>Fund Source</th><th>Ceiling</th><th>Programmed</th><th>Pending</th><th>Available</th><th>Utilized</th><th>Status</th><th data-no-export>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($totals as $key => $fund): $used = $fund['count'] + $fund['pendingCount']; ?>
                <tr data-row>
                    <td data-export="<?= e($fund['name'] . ' (' . $fund['code'] . ')') ?>"><a class="title-link" href="fund-source.php?key=<?= urlencode($key) ?>"><strong><?= e($fund['name']) ?></strong></a><small class="table-subtext"><?= e($fund['code']) ?> · <?= e($fund['category']) ?></small></td>
                    <td class="amount" data-export="<?= number_format($fund['ceiling'], 2, '.', '') ?>"><?= peso($fund['ceiling']) ?></td>
                    <td class="amount" data-export="<?= number_format($fund['programmed'], 2, '.', '') ?>"><?= peso($fund['programmed']) ?><small class="table-subtext"><?= $fund['count'] ?> PPA<?= $fund['count'] === 1 ? '' : 's' ?> · <?= number_format($fund['share'], 1) ?>%</small></td>
                    <td class="amount" data-export="<?= number_format($fund['pending'], 2, '.', '') ?>"><?= peso($fund['pending']) ?><small class="table-subtext"><?= $fund['pendingCount'] ?> PPA<?= $fund['pendingCount'] === 1 ? '' : 's' ?></small></td>
                    <td class="amount <?= $fund['available'] < 0 ? 'danger-text' : '' ?>" data-export="<?= number_format($fund['available'], 2, '.', '') ?>"><?= peso($fund['available']) ?></td>
                    <td class="amount" data-export="<?= number_format($fund['utilized'], 2, '.', '') ?>"><?= peso($fund['utilized']) ?></td>
                    <td data-export="<?= e($fund['state'][0]) ?>"><div class="ceiling-meter" title="Programmed <?= number_format($fund['share'], 1) ?>%, with pending <?= number_format($fund['projectedShare'], 1) ?>%"><span class="programmed" style="width:<?= min(100, $fund['share']) ?>%"></span><span class="projected" style="width:<?= min(100, $fund['projectedShare']) ?>%"></span></div><?= statusPill($fund['state'][1] === 'approved' ? 'approved' : ($fund['state'][1] === 'pending' ? 'recommended' : ($fund['state'][1] === 'inactive' ? 'draft' : 'disapproved')), $fund['state'][0]) ?></td>
                    <td>
                        <div class="row-actions">
                            <a class="text-button" href="fund-source.php?key=<?= urlencode($key) ?>">View</a>
                            <?php if ($canManage): ?>
                                <a class="text-button" href="fund-form.php?key=<?= urlencode($key) ?>">Edit</a>
                                <form method="post" data-confirm="<?= !empty($fund['active']) ? 'Deactivate ' . e($fund['name']) . '? It will no longer be available for PPAs.' : 'Activate ' . e($fund['name']) . '?' ?>">
                                    <?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="key" value="<?= e($key) ?>">
                                    <button class="text-button <?= !empty($fund['active']) ? 'danger-text' : '' ?>" type="submit"><?= !empty($fund['active']) ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <?php if ($used === 0): ?>
                                    <form method="post" data-confirm="Delete <?= e($fund['name']) ?>? This cannot be undone.">
                                        <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="key" value="<?= e($key) ?>">
                                        <button class="text-button danger-text" type="submit">Delete</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="fund-cards" id="fund-card-view" hidden>
        <?php foreach ($totals as $key => $fund): ?>
            <article class="fund-card">
                <h3><a class="title-link" href="fund-source.php?key=<?= urlencode($key) ?>"><?= e($fund['name']) ?></a></h3>
                <small class="table-subtext"><?= e($fund['category']) ?></small>
                <div class="ceiling-meter large"><span class="programmed" style="width:<?= min(100, $fund['share']) ?>%"></span><span class="projected" style="width:<?= min(100, $fund['projectedShare']) ?>%"></span></div>
                <dl><dt>Ceiling</dt><dd><?= peso($fund['ceiling']) ?></dd><dt>Programmed</dt><dd><?= peso($fund['programmed']) ?></dd><dt>Pending</dt><dd><?= peso($fund['pending']) ?></dd><dt>Available</dt><dd class="<?= $fund['available'] < 0 ? 'danger-text' : '' ?>"><?= peso($fund['available']) ?></dd><dt>Status</dt><dd><?= e($fund['state'][0]) ?></dd></dl>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="pagination-bar"><span><span class="legend-swatch programmed"></span> Programmed (approved) &nbsp; <span class="legend-swatch projected"></span> Including pending PPAs · inactive sources are excluded from the totals above</span></div>
</section>
</div>

<!-- ======================= PPA funding ======================= -->
<div id="tab-ppas" <?= $tab !== 'ppas' ? 'hidden' : '' ?>>
<section class="panel flush-panel" id="ppa-funding-table" data-table-panel data-page-size="10">
    <div class="panel-heading">
        <div><h2>PPA Funding</h2><p class="muted">The fund sources and amounts each PPA is charged to.</p></div>
        <button class="filter-button" type="button" data-export-csv="ppa-funding-fy<?= AIP_FISCAL_YEAR ?>.csv" data-for="ppa-funding-table">⇩ Export</button>
    </div>
    <div class="filter-bar">
        <label>Fund source<select data-filter="primary" data-for="ppa-funding-table"><option value="">All sources</option><?php foreach (fundSources() as $key => $fund): ?><option value="<?= e($key) ?>"><?= e($fund['name']) ?></option><?php endforeach; ?></select></label>
        <label>Stage<select data-filter="bucket" data-for="ppa-funding-table"><option value="">All</option><option value="programmed">Approved (programmed)</option><option value="pending">Pending decision</option></select></label>
        <label>Funding check<select data-filter="check" data-for="ppa-funding-table"><option value="">All</option><option value="issue">Has an issue</option><option value="ok">No issues</option></select></label>
        <label>Search<input type="search" placeholder="Title, ref no. or office" data-search data-for="ppa-funding-table"></label>
        <button class="text-button" type="button" data-filter-reset data-for="ppa-funding-table">Clear filters</button>
    </div>
    <div class="table-wrap">
        <table class="activity-table ppa-table">
            <thead><tr><th>Ref No.</th><th>PPA</th><th>Office</th><th>Status</th><th>PPA Cost</th><th>Allocation</th><th>Check</th><?php if ($canManage): ?><th data-no-export>Actions</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($ppas as $ppa):
                $alloc = ppaAllocations($ppa);
                $hasIssue = isset($issueRefs[$ppa['ref']]);
            ?>
                <tr data-row data-primary="<?= e($ppa['fund']) ?>" data-bucket="<?= fundBucket($ppa['status']) ?>" data-check="<?= $hasIssue ? 'issue' : 'ok' ?>">
                    <td><a class="ref-link" href="ppa-view.php?ref=<?= urlencode($ppa['ref']) ?>"><?= e($ppa['ref']) ?></a></td>
                    <td class="title-cell" data-export="<?= e($ppa['title']) ?>"><strong><?= e($ppa['title']) ?></strong><small class="table-subtext"><?= e($ppa['type']) ?></small></td>
                    <td data-export="<?= e(officeName($ppa['office'])) ?>"><?= e($ppa['office']) ?></td>
                    <td><?= statusPill($ppa['status']) ?></td>
                    <td class="amount" data-export="<?= number_format($ppa['cost'], 2, '.', '') ?>"><?= peso($ppa['cost']) ?></td>
                    <td data-export="<?= e(implode('; ', array_map(fn ($k, $v) => fundName($k) . ' ' . number_format($v, 2, '.', ''), array_keys($alloc), $alloc))) ?>">
                        <?php foreach ($alloc as $fundKey => $amount): ?><div class="alloc-line"><span><?= e(fundName($fundKey)) ?></span><b><?= peso($amount) ?></b></div><?php endforeach; ?>
                        <?php if (!$alloc): ?><span class="table-subtext">None</span><?php endif; ?>
                    </td>
                    <td data-export="<?= $hasIssue ? 'Issue' : 'OK' ?>"><?= $hasIssue ? '<span class="status-pill rejected"><i></i>' . $issueRefs[$ppa['ref']] . ' issue' . ($issueRefs[$ppa['ref']] > 1 ? 's' : '') . '</span>' : '<span class="status-pill approved"><i></i>OK</span>' ?></td>
                    <?php if ($canManage): ?><td><a class="text-button" href="ppa-funding.php?ref=<?= urlencode($ppa['ref']) ?>">Edit allocation</a></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
                <tr data-empty hidden><td class="empty-cell" colspan="8">No PPAs match the filters.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar" data-pager data-noun="PPAs"></div>
</section>
</div>

<!-- ======================= Issues ======================= -->
<div id="tab-issues" <?= $tab !== 'issues' ? 'hidden' : '' ?>>
<section class="panel flush-panel">
    <div class="panel-heading"><div><h2>Funding Issues</h2><p class="muted">Checked automatically every time this page loads. Resolve errors before consolidating the AIP.</p></div></div>
    <?php if (!$issues): ?>
        <p class="empty-state">No funding inconsistencies found. Every PPA is fully allocated and every fund source is within its ceiling.</p>
    <?php else: ?>
        <ul class="issue-list">
            <?php foreach ($issues as $issue): ?>
                <li class="<?= $issue['level'] ?>">
                    <span class="issue-level"><?= $issue['level'] === 'error' ? 'Error' : 'Warning' ?></span>
                    <div>
                        <strong><?= e($issue['type']) ?></strong>
                        <?php if ($issue['ref'] !== ''): $p = storeFindPpa($issue['ref']); ?> · <a href="ppa-view.php?ref=<?= urlencode($issue['ref']) ?>"><?= e($issue['ref']) ?></a> <?= e($p['title'] ?? '') ?><?php endif; ?>
                        <?php if ($issue['ref'] === '' && $issue['fund'] !== ''): ?> · <a href="fund-source.php?key=<?= urlencode($issue['fund']) ?>"><?= e(fundName($issue['fund'])) ?></a><?php endif; ?>
                        <p><?= e($issue['message']) ?></p>
                    </div>
                    <?php if ($canManage): ?>
                        <?php if ($issue['ref'] !== ''): ?><a class="text-button" href="ppa-funding.php?ref=<?= urlencode($issue['ref']) ?>">Fix allocation</a>
                        <?php else: ?><a class="text-button" href="fund-form.php?key=<?= urlencode($issue['fund']) ?>">Review ceiling</a><?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
</div>
<?php include '../includes/footer.php'; ?>
