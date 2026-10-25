<?php
/**
 * Dashboard: a live overview of the AIP process, computed from the stored
 * records each time it loads (see includes/dashboard.php).
 */
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$requiredPermission = 'dashboard.view';
include 'includes/header.php';

$filters = [
    'office' => can('scope.all_offices') ? (string) ($_GET['office'] ?? '') : '',
    'sector' => (string) ($_GET['sector'] ?? ''),
];
$filters = array_filter($filters, fn ($v) => $v !== '');
if (isset($filters['office']) && !isset(mockOffices()[$filters['office']])) { unset($filters['office']); }
if (isset($filters['sector']) && !isset(ppaSectors()[$filters['sector']])) { unset($filters['sector']); }
$d = dashboardData($filters);
$me = currentUser();
$scopeLabel = can('scope.all_offices') ? (isset($filters['office']) ? officeName($filters['office']) : 'All offices') : officeName($me['office']);
$submittedCount = count($d['submitted']);
$pendingCount = count($d['pending']);

// Approval status chart
$segments = [
    ['Approved',       count($d['approved']),                                     'var(--green)'],
    ['Pending',        $d['pipeline']['for_review']['count'] + $d['pipeline']['recommended']['count'], 'var(--amber)'],
    ['Returned',       $d['pipeline']['returned']['count'],                      '#e58a47'],
    ['Disapproved',    $d['pipeline']['disapproved']['count'],                   'var(--red)'],
];
$segTotal = array_sum(array_column($segments, 1));
$gradient = [];
$at = 0;
foreach ($segments as [$label, $n, $color]) {
    $pct = $segTotal ? $n / $segTotal * 100 : 0;
    $gradient[] = $color . ' ' . round($at, 2) . '% ' . round($at + $pct, 2) . '%';
    $at += $pct;
}
$maxStage = max(1, ...array_values(array_column($d['pipeline'], 'count')));
$maxSector = max(1, ...array_values(array_map(fn ($r) => $r['amount'], $d['bySector'] ?: [['amount' => 1]])));
$maxOffice = max(1, ...array_values(array_map(fn ($r) => $r['amount'], $d['byOffice'] ?: [['amount' => 1]])));
$awaitingHint = can('review.act') ? 'Submitted PPAs waiting for your MPDO evaluation.'
    : (can('authorization.act') ? 'Recommended PPAs waiting for your final decision.'
    : (can('ppa.encode') ? 'PPAs returned to your office for revision.' : ''));
?>
<div class="page-heading">
    <div><p class="eyebrow">Overview · <?= e($scopeLabel) ?><?= isset($filters['sector']) ? ' · ' . e(ppaSectors()[$filters['sector']]) : '' ?></p><h1>FY <?= AIP_FISCAL_YEAR ?> AIP Dashboard</h1><p class="muted">Live summary of the Annual Investment Program, computed from the records in the system as of <?= e(date('M j, Y g:i A')) ?>.</p></div>
    <div class="heading-actions no-print">
        <button class="secondary-button" type="button" data-print>▣ Print</button>
        <?php if (can('ppa.encode')): ?><a class="primary-button" href="pages/projects.php">+ New PPA</a><?php endif; ?>
    </div>
</div>

<form class="filter-bar dashboard-filters no-print" method="get">
    <?php if (can('scope.all_offices')): ?>
        <label>Office<select name="office"><option value="">All offices</option><?php foreach (mockOffices() as $code => $name): ?><option value="<?= e($code) ?>" <?= ($filters['office'] ?? '') === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></label>
    <?php endif; ?>
    <label>Sector<select name="sector"><option value="">All sectors</option><?php foreach (ppaSectors() as $code => $name): ?><option value="<?= e($code) ?>" <?= ($filters['sector'] ?? '') === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></label>
    <button class="secondary-button" type="submit">Apply</button>
    <?php if ($filters): ?><a class="text-button" href="dashboard.php">Clear filters</a><?php endif; ?>
</form>

<section class="stat-grid five">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Submitted PPAs</span><span class="stat-icon blue">▣</span></div><strong data-kpi="submitted"><?= $submittedCount ?></strong><p><?= can('ppa.encode') ? $d['drafts'] . ' draft' . ($d['drafts'] === 1 ? '' : 's') . ' not yet submitted' : pesoShort($d['totalCost']) . ' proposed' ?></p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Pending decisions</span><span class="stat-icon amber">◔</span></div><strong data-kpi="pending" class="<?= $d['overdue'] ? 'orange-number' : '' ?>"><?= $pendingCount ?></strong><p><?= count($d['overdue']) ?> overdue (over <?= WORKFLOW_OVERDUE_DAYS ?> days)</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Approved for the AIP</span><span class="stat-icon green">✓</span></div><strong data-kpi="approved"><?= count($d['approved']) ?></strong><p><?= pesoShort($d['approvedCost']) ?> · approval rate <?= $d['approvalRate'] === null ? '—' : number_format($d['approvalRate'], 0) . '%' ?></p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total proposed cost</span><span class="stat-icon blue">₱</span></div><strong data-kpi="cost"><?= pesoShort($d['totalCost']) ?></strong><div class="progress-track"><span style="width:<?= $d['totalCost'] > 0 ? round($d['approvedCost'] / $d['totalCost'] * 100) : 0 ?>%"></span></div><p><?= $d['totalCost'] > 0 ? number_format($d['approvedCost'] / $d['totalCost'] * 100, 0) : 0 ?>% already approved</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Implementation</span><span class="stat-icon green">▶</span></div><strong data-kpi="progress"><?= $d['avgProgress'] === null ? '—' : round($d['avgProgress']) . '%' ?></strong><div class="progress-track"><span style="width:<?= round($d['avgProgress'] ?? 0) ?>%"></span></div><p>Average progress of <?= count($d['implementing']) ?> ongoing/completed · <?= pesoShort($d['utilized']) ?> utilized</p></article>
</section>

<?php if ($awaitingHint !== ''): ?>
<section class="panel awaiting-panel" id="awaiting">
    <div class="panel-heading"><div><h2>Awaiting your action <span class="count-badge"><?= count($awaiting) ?></span></h2><p class="muted"><?= e($awaitingHint) ?> Oldest first.</p></div></div>
    <?php if (!$awaiting): ?>
        <p class="empty-state">Nothing is waiting for you right now.</p>
    <?php else: ?>
        <div class="table-wrap"><table class="activity-table">
            <thead><tr><th>Ref No.</th><th>PPA</th><th>Office</th><th>Status</th><th>Waiting</th><th></th></tr></thead>
            <tbody>
            <?php foreach (array_slice($awaiting, 0, 6) as $item): $wait = daysInStatus($item); $link = $item['status'] === 'returned' ? 'pages/projects.php?ref=' : 'pages/ppa-review.php?ref='; ?>
                <tr>
                    <td><a class="ref-link" href="<?= $link . urlencode($item['ref']) ?>"><?= e($item['ref']) ?></a></td>
                    <td class="title-cell"><strong><?= e($item['title']) ?></strong></td>
                    <td><?= e($item['office']) ?></td>
                    <td><?= statusPill($item['status']) ?></td>
                    <td><span class="<?= $wait > WORKFLOW_OVERDUE_DAYS ? 'overdue-text' : 'table-subtext' ?>"><?= e(waitingLabel($wait)) ?><?= $wait > WORKFLOW_OVERDUE_DAYS ? ' · overdue' : '' ?></span></td>
                    <td><a class="review-action" href="<?= $link . urlencode($item['ref']) ?>"><?= $item['status'] === 'returned' ? 'Revise' : ($item['status'] === 'recommended' ? 'Decide' : 'Evaluate') ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php if (count($awaiting) > 6): ?><p class="form-note">And <?= count($awaiting) - 6 ?> more in the <?= can('authorization.act') ? 'Authorization' : (can('review.act') ? 'Review' : 'Project Encoding') ?> queue.</p><?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="dash-grid">
    <article class="panel">
        <div class="panel-heading"><div><h2>Process pipeline</h2><p class="muted">Where every PPA is right now</p></div></div>
        <ul class="pipeline">
            <?php foreach ($d['pipeline'] as $status => $stage): ?>
                <li><a href="<?= e($stage['link']) ?>">
                    <span class="pipeline-label"><?= statusPill($status, $stage['label']) ?></span>
                    <span class="pipeline-bar"><span class="<?= e(statusClass($status)) ?>" style="width:<?= round($stage['count'] / $maxStage * 100) ?>%"></span></span>
                    <span class="pipeline-count" data-stage="<?= $status ?>"><?= $stage['count'] ?></span>
                    <span class="pipeline-amount"><?= pesoShort($stage['amount']) ?></span>
                </a></li>
            <?php endforeach; ?>
        </ul>
    </article>
    <article class="panel approval-panel">
        <div class="panel-heading"><div><h2>Approval status</h2><p class="muted">Submitted PPAs by decision</p></div><a href="pages/review-workflow.php">Review queue <span>→</span></a></div>
        <div class="approval-visual">
            <div class="donut" style="background:<?= $segTotal ? 'conic-gradient(' . implode(', ', $gradient) . ')' : '#edf1f5' ?>" role="img" aria-label="<?= e(implode(', ', array_map(fn ($s) => $s[1] . ' ' . strtolower($s[0]), $segments))) ?>"><div><strong><?= $segTotal ?></strong><small>Submitted</small></div></div>
            <div class="approval-legend">
                <?php foreach ($segments as [$label, $n, $color]): ?>
                    <div><i class="legend-dot" style="background:<?= $color ?>"></i><span><?= e($label) ?></span><strong><?= $n ?> <small><?= $segTotal ? round($n / $segTotal * 100) : 0 ?>%</small></strong></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="turnaround">
            <div><span class="stat-label">Avg. time in MPDO review</span><strong><?= e(dashboardDays($d['reviewTime']['avg'])) ?></strong><small><?= $d['reviewTime']['count'] ?> evaluation<?= $d['reviewTime']['count'] === 1 ? '' : 's' ?></small></div>
            <div><span class="stat-label">Avg. time to final decision</span><strong><?= e(dashboardDays($d['authTime']['avg'])) ?></strong><small><?= $d['authTime']['count'] ?> decision<?= $d['authTime']['count'] === 1 ? '' : 's' ?></small></div>
        </div>
    </article>
</section>

<section class="dash-grid">
    <article class="panel">
        <div class="panel-heading"><div><h2>Fund distribution</h2><p class="muted"><?= $d['showCeilings'] ? 'Programmed and pending against each fund ceiling' : 'Amounts charged to each fund source' ?></p></div><?php if (can('funds.view')): ?><a href="pages/fund-monitoring.php">Fund monitoring <span>→</span></a><?php endif; ?></div>
        <ul class="fund-bars">
            <?php $maxFund = max(1, ...array_values(array_map(fn ($f) => $d['showCeilings'] ? max((float) $f['ceiling'], $f['programmed'] + $f['pending']) : $f['programmed'] + $f['pending'], $d['funds'] ?: [['ceiling' => 1, 'programmed' => 0, 'pending' => 0]]))); ?>
            <?php foreach ($d['funds'] as $key => $f): $scale = $d['showCeilings'] ? max(1, (float) $f['ceiling'], $f['programmed'] + $f['pending']) : $maxFund; ?>
                <?php if (!$d['showCeilings'] && $f['programmed'] + $f['pending'] <= 0) { continue; } ?>
                <li>
                    <div class="fund-bar-head"><strong><?= e($f['name']) ?></strong><span><?= pesoShort($f['programmed']) ?> programmed · <?= pesoShort($f['pending']) ?> pending<?= $d['showCeilings'] ? ' · ceiling ' . pesoShort((float) $f['ceiling']) : '' ?></span></div>
                    <div class="ceiling-meter large"><span class="programmed" style="width:<?= min(100, $f['programmed'] / $scale * 100) ?>%"></span><span class="projected" style="width:<?= min(100, ($f['programmed'] + $f['pending']) / $scale * 100) ?>%"></span><?php if ($d['showCeilings'] && $f['ceiling'] > 0): ?><i class="ceiling-mark" style="left:<?= min(100, $f['ceiling'] / $scale * 100) ?>%"></i><?php endif; ?></div>
                    <?php if ($d['showCeilings'] && $f['state'][1] !== 'approved'): ?><small class="fund-state <?= e($f['state'][1]) ?>"><?= e($f['state'][0]) ?></small><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="legend-row"><span class="legend-swatch programmed"></span> Programmed (approved) <span class="legend-swatch projected"></span> Including pending<?= $d['showCeilings'] ? ' <span class="legend-ceiling"></span> Ceiling' : '' ?></p>
    </article>
    <article class="panel">
        <div class="panel-heading"><div><h2>Distribution</h2><p class="muted">Proposed cost (excluding disapproved)</p></div>
            <div class="segmented-control" role="group" aria-label="Distribution by">
                <button class="active" type="button" data-view-group="dist" data-view-show="#dist-sector" aria-pressed="true">Sector</button>
                <button type="button" data-view-group="dist" data-view-show="#dist-office" aria-pressed="false">Office</button>
            </div>
        </div>
        <?php foreach (['sector' => [$d['bySector'], $maxSector, fn ($k) => ppaSectors()[$k] ?? $k], 'office' => [$d['byOffice'], $maxOffice, 'officeName']] as $id => [$rows, $max, $namer]): ?>
            <table class="summary-table" id="dist-<?= $id ?>" <?= $id !== 'sector' ? 'hidden' : '' ?>>
                <?php foreach ($rows as $key => $row): ?>
                    <tr><th><?php if ($id === 'office' && canSeeOffice($key)): ?><a href="pages/office.php?code=<?= urlencode($key) ?>"><?= e($namer($key)) ?></a><?php else: ?><?= e($namer($key)) ?><?php endif; ?><small><?= $row['count'] ?> PPA<?= $row['count'] === 1 ? '' : 's' ?> · <?= pesoShort($row['approved']) ?> approved</small></th>
                        <td><div class="dist-bar"><span class="all" style="width:<?= round($row['amount'] / $max * 100) ?>%"></span><span class="ok" style="width:<?= round($row['approved'] / $max * 100) ?>%"></span></div></td>
                        <td class="amount"><?= pesoShort($row['amount']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?><tr><td class="empty-cell">No PPAs yet.</td></tr><?php endif; ?>
            </table>
        <?php endforeach; ?>
    </article>
</section>

<section class="dash-grid">
    <article class="panel">
        <div class="panel-heading"><div><h2>Monitoring and compliance</h2><p class="muted">Items that need attention before the AIP is final</p></div></div>
        <div class="compliance-grid">
            <a class="compliance-tile <?= $d['overdue'] ? 'bad' : 'good' ?>" href="pages/review-workflow.php"><span>Overdue submissions</span><strong><?= count($d['overdue']) ?></strong><small>Waiting more than <?= WORKFLOW_OVERDUE_DAYS ?> days</small></a>
            <?php foreach ($d['compliance'] as $key => $c): $bad = is_int($c['value']) ? $c['value'] > 0 : $c['errors'] > 0; ?>
                <a class="compliance-tile <?= $bad ? ($c['errors'] ? 'bad' : 'warn') : 'good' ?>" href="<?= e($c['link']) ?>"><span><?= e($c['label']) ?></span><strong><?= e((string) $c['value']) ?></strong><small><?= e($c['detail'] ?? ($c['errors'] ? $c['errors'] . ' error' . ($c['errors'] === 1 ? '' : 's') : ($bad ? 'Warnings to review' : 'All clear'))) ?></small></a>
            <?php endforeach; ?>
        </div>
        <?php if ($d['pending']): ?>
            <h3 class="sub-heading">Oldest pending</h3>
            <ul class="linked-list">
                <?php foreach (array_slice($d['pending'], 0, 4) as $p): $w = daysInStatus($p); ?>
                    <li><a class="ref-link" href="pages/ppa-view.php?ref=<?= urlencode($p['ref']) ?>"><?= e($p['ref']) ?></a> <?= e($p['title']) ?><small><?= e(statusLabel($p['status'])) ?> · with <?= e(ppaStage($p)['holder']) ?> · <span class="<?= $w > WORKFLOW_OVERDUE_DAYS ? 'overdue-text' : '' ?>"><?= e(waitingLabel($w)) ?></span></small></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </article>
    <article class="panel">
        <div class="panel-heading"><div><h2>Recent activity</h2><p class="muted">Latest actions on PPAs, by whom</p></div></div>
        <?php if (!$d['activity']): ?><p class="empty-state">No activity yet.</p><?php endif; ?>
        <ol class="history-list activity-feed">
            <?php foreach ($d['activity'] as $a): ?>
                <li><div class="history-head"><strong><?= e($a['action']) ?></strong><?= statusPill($a['status']) ?></div>
                    <small><a href="pages/ppa-view.php?ref=<?= urlencode($a['ref']) ?>"><?= e($a['ref']) ?></a> <?= e(mb_strimwidth($a['title'], 0, 48, '…')) ?> · <?= e(explode(' (', $a['by'])[0]) ?> · <?= e(date('M j, g:i A', strtotime($a['time']))) ?></small></li>
            <?php endforeach; ?>
        </ol>
    </article>
</section>

<?php if ($d['accounts']): ?>
<section class="panel accounts-panel">
    <div class="panel-heading"><div><h2>Accounts and access today</h2><p class="muted">From the activity log</p></div><a href="pages/users.php?tab=activity">Activity log <span>→</span></a></div>
    <div class="compliance-grid">
        <a class="compliance-tile good" href="pages/users.php"><span>Active accounts</span><strong><?= $d['accounts']['active'] ?></strong><small>of <?= $d['accounts']['total'] ?> total</small></a>
        <a class="compliance-tile good" href="pages/users.php?tab=activity"><span>Sign-ins today</span><strong><?= $d['accounts']['signins'] ?></strong><small><?= $d['accounts']['actions'] ?> logged actions</small></a>
        <a class="compliance-tile <?= $d['accounts']['failed'] ? 'warn' : 'good' ?>" href="pages/users.php?tab=activity"><span>Failed sign-ins today</span><strong><?= $d['accounts']['failed'] ?></strong><small>Wrong password or inactive account</small></a>
        <a class="compliance-tile <?= $d['accounts']['denied'] ? 'warn' : 'good' ?>" href="pages/users.php?tab=activity"><span>Access denied today</span><strong><?= $d['accounts']['denied'] ?></strong><small>Pages opened without permission</small></a>
    </div>
</section>
<?php endif; ?>
<?php include 'includes/footer.php'; ?>
