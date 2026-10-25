<?php
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$requiredPermission = 'dashboard.view';
include 'includes/header.php';

// MOCK: all figures below are calculated from the sample PPAs.
$ppas = visiblePpas();   // Encoders see only their own office
$approvedStatuses = ['approved', 'ongoing', 'completed'];
$pendingStatuses = ['for_review', 'recommended', 'returned'];

$totalCount = count($ppas);
$totalCost = array_sum(array_column($ppas, 'cost'));
$approved = array_filter($ppas, fn ($p) => in_array($p['status'], $approvedStatuses, true));
$approvedCount = count($approved);
$pendingCount = count(array_filter($ppas, fn ($p) => in_array($p['status'], $pendingStatuses, true)));
$disapprovedCount = $totalCount - $approvedCount - $pendingCount;
$approvedCost = array_sum(array_column($approved, 'cost'));
$inProgress = array_filter($ppas, fn ($p) => in_array($p['status'], ['ongoing', 'completed'], true));
$avgProgress = $inProgress ? round(array_sum(array_column($inProgress, 'progress')) / count($inProgress)) : 0;

$pct = fn ($n) => $totalCount ? round($n / $totalCount * 100) : 0;
$approvedPct = $pct($approvedCount);
$pendingPct = $pct($pendingCount);
$disapprovedPct = max(0, 100 - $approvedPct - $pendingPct);

$byOffice = [];
foreach ($ppas as $ppa) {
    $byOffice[$ppa['office']] ??= ['count' => 0, 'cost' => 0];
    $byOffice[$ppa['office']]['count']++;
    $byOffice[$ppa['office']]['cost'] += $ppa['cost'];
}
uasort($byOffice, fn ($a, $b) => $b['cost'] <=> $a['cost']);
$byOffice = array_slice($byOffice, 0, 5, true);

$recent = $ppas;
usort($recent, fn ($a, $b) => strcmp($b['updated'], $a['updated']));
$recent = array_slice($recent, 0, 5);
?>
<div class="page-heading">
    <div><p class="eyebrow">Overview</p><h1>FY <?= AIP_FISCAL_YEAR ?> Dashboard</h1><p class="muted"><?= can('scope.all_offices') ? 'Summary of the Annual Investment Program being prepared by the MPDO.' : 'Summary of PPAs from ' . e(officeName(currentUser()['office'])) . '.' ?></p></div>
    <div class="heading-actions">
        <button class="secondary-button" type="button" data-export-csv="recent-ppa-activity.csv" data-for="recent-table">⇩ Export</button>
        <?php if (can('ppa.encode')): ?><a class="primary-button" href="pages/projects.php">+ New project</a><?php endif; ?>
    </div>
</div>

<section class="stat-grid">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total PPAs</span><span class="stat-icon blue">▣</span></div><strong><?= $totalCount ?></strong><p><?= $pendingCount ?> still in review or authorization</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total proposed cost</span><span class="stat-icon green">▤</span></div><strong><?= pesoShort($totalCost) ?></strong><p><?= pesoShort($approvedCost) ?> approved for the AIP</p></article>
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Implementation progress</span><span class="stat-icon amber">◔</span></div><strong><?= $avgProgress ?>%</strong><p>Average of <?= count($inProgress) ?> ongoing and completed PPAs</p><div class="progress-track"><span style="width:<?= $avgProgress ?>%"></span></div></article>
</section>

<section class="dashboard-grid">
    <article class="panel approval-panel">
        <div class="panel-heading"><div><h2>Approval status</h2><p class="muted">PPA distribution by current stage</p></div><a href="pages/review-workflow.php">View workflow <span>→</span></a></div>
        <div class="approval-visual">
            <div class="donut" style="background:conic-gradient(var(--green) 0 <?= $approvedPct ?>%, var(--amber) <?= $approvedPct ?>% <?= $approvedPct + $pendingPct ?>%, var(--red) <?= $approvedPct + $pendingPct ?>% 100%)" role="img" aria-label="<?= $approvedCount ?> approved, <?= $pendingCount ?> pending, <?= $disapprovedCount ?> disapproved"><div><strong><?= $totalCount ?></strong><small>Total PPAs</small></div></div>
            <div class="approval-legend">
                <div><i class="legend-dot approved"></i><span>Approved</span><strong><?= $approvedCount ?> <small><?= $approvedPct ?>%</small></strong></div>
                <div><i class="legend-dot pending"></i><span>Pending</span><strong><?= $pendingCount ?> <small><?= $pendingPct ?>%</small></strong></div>
                <div><i class="legend-dot rejected"></i><span>Disapproved</span><strong><?= $disapprovedCount ?> <small><?= $disapprovedPct ?>%</small></strong></div>
            </div>
        </div>
    </article>
    <article class="panel">
        <div class="panel-heading"><div><h2>Top allocations by office</h2><p class="muted">Proposed cost per implementing office</p></div><?php if (can('funds.view')): ?><a href="pages/fund-monitoring.php">Fund monitoring <span>→</span></a><?php endif; ?></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Department / office</th><th>PPAs</th><th>Allocation</th></tr></thead>
                <tbody>
                <?php foreach ($byOffice as $code => $office): ?>
                    <tr><td><span class="table-name"><?= e(officeName($code)) ?></span><div class="mini-bar"><span style="width:<?= $totalCost ? round($office['cost'] / $totalCost * 100) : 0 ?>%"></span></div></td><td><?= $office['count'] ?></td><td class="amount"><?= pesoShort($office['cost']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
</section>

<section class="panel recent-panel" id="recent-table" data-table-panel>
    <div class="panel-heading"><div><h2>Recent PPA activity</h2><p class="muted">Latest updates across the program</p></div><a href="pages/project-linking.php">See all PPAs <span>→</span></a></div>
    <div class="table-wrap">
        <table class="activity-table">
            <thead><tr><th>Project</th><th>Office</th><th>Status</th><th>Updated</th><th>Amount</th><th data-no-export></th></tr></thead>
            <tbody>
            <?php foreach ($recent as $ppa):
                $detail = ['eyebrow' => $ppa['ref'], 'title' => $ppa['title'], 'fields' => [
                    ['Type', $ppa['type']], ['Program', $ppa['program']], ['Implementing office', officeName($ppa['office'])],
                    ['Fund source', fundName($ppa['fund'])], ['Estimated cost', peso($ppa['cost'])], ['Status', statusLabel($ppa['status'])],
                    ['Schedule', displayDate($ppa['start']) . ' – ' . displayDate($ppa['end'])], ['Last updated', displayDate($ppa['updated'])],
                ]];
            ?>
                <tr data-row>
                    <td class="title-cell"><strong><?= e($ppa['title']) ?></strong></td>
                    <td data-export="<?= e(officeName($ppa['office'])) ?>"><?= e($ppa['office']) ?></td>
                    <td><?= statusPill($ppa['status']) ?></td>
                    <td><?= e(displayDate($ppa['updated'])) ?></td>
                    <td class="amount" data-export="<?= number_format($ppa['cost'], 2, '.', '') ?>"><?= peso($ppa['cost']) ?></td>
                    <td><button class="row-action" type="button" aria-label="Open <?= e($ppa['ref']) ?>" data-detail="<?= e(json_encode($detail)) ?>">→</button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include 'includes/footer.php'; ?>
