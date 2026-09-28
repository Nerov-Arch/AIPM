<?php
$pageTitle = 'Dashboard';
$activePage = 'dashboard';
$stats = [
    ['label' => 'Total projects', 'value' => '1,248', 'change' => '+12.5%', 'note' => 'from last cycle', 'class' => 'blue', 'icon' => '▣'],
    ['label' => 'Total allocation', 'value' => '₱4.2B', 'change' => '+8.3%', 'note' => 'vs. previous year', 'class' => 'green', 'icon' => '▤'],
    ['label' => 'Overall progress', 'value' => '68%', 'change' => '+5.7%', 'note' => 'on track', 'class' => 'amber', 'icon' => '◔'],
];
$funds = [
    ['name' => 'Department of Public Works', 'projects' => '142', 'allocation' => '₱1.2B', 'share' => 29],
    ['name' => 'Health Services Office', 'projects' => '85', 'allocation' => '₱850M', 'share' => 20],
    ['name' => 'Education Department', 'projects' => '210', 'allocation' => '₱620M', 'share' => 15],
    ['name' => 'Social Welfare', 'projects' => '54', 'allocation' => '₱410M', 'share' => 10],
    ['name' => 'Agriculture Office', 'projects' => '67', 'allocation' => '₱380M', 'share' => 9],
];
$recent = [
    ['title' => 'Community Health Center Expansion', 'department' => 'Health Services Office', 'status' => 'Approved', 'date' => 'Jun 18, 2025', 'amount' => '₱24.5M'],
    ['title' => 'Rural Access Road Improvement', 'department' => 'Department of Public Works', 'status' => 'For review', 'date' => 'Jun 17, 2025', 'amount' => '₱18.2M'],
    ['title' => 'School Building Rehabilitation', 'department' => 'Education Department', 'status' => 'Pending', 'date' => 'Jun 16, 2025', 'amount' => '₱12.8M'],
];
include 'includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">Overview</p><h1>FY 2025 Dashboard</h1><p class="muted">A real-time view of your Annual Investment Program.</p></div><div class="heading-actions"><button class="secondary-button" type="button" data-toast="Your report is being prepared.">⇩ Export</button><a class="primary-button" href="pages/projects.php">+ New project</a></div></div>
<section class="stat-grid">
<?php foreach ($stats as $stat): ?><article class="stat-card"><div class="stat-top"><span class="stat-label"><?= htmlspecialchars($stat['label']) ?></span><span class="stat-icon <?= $stat['class'] ?>"><?= $stat['icon'] ?></span></div><strong><?= htmlspecialchars($stat['value']) ?></strong><p><span class="trend">↗ <?= htmlspecialchars($stat['change']) ?></span> <?= htmlspecialchars($stat['note']) ?></p><?php if ($stat['label'] === 'Overall progress'): ?><div class="progress-track"><span style="width:68%"></span></div><?php endif; ?></article><?php endforeach; ?>
</section>
<section class="dashboard-grid">
<article class="panel approval-panel"><div class="panel-heading"><div><h2>Approval status</h2><p class="muted">Project distribution by current stage</p></div><a href="pages/review-workflow.php">View workflow <span>→</span></a></div><div class="approval-visual"><div class="donut"><div><strong>1,248</strong><small>Total projects</small></div></div><div class="approval-legend"><div><i class="legend-dot approved"></i><span>Approved</span><strong>850 <small>68%</small></strong></div><div><i class="legend-dot pending"></i><span>Pending</span><strong>320 <small>25%</small></strong></div><div><i class="legend-dot rejected"></i><span>Rejected</span><strong>78 <small>7%</small></strong></div></div></div></article>
<article class="panel"><div class="panel-heading"><div><h2>Top fund allocations</h2><p class="muted">Allocation by department</p></div><a href="pages/fund-monitoring.php">View all <span>→</span></a></div><div class="table-wrap"><table><thead><tr><th>Department / office</th><th>Projects</th><th>Allocation</th></tr></thead><tbody><?php foreach ($funds as $fund): ?><tr><td><span class="table-name"><?= htmlspecialchars($fund['name']) ?></span><div class="mini-bar"><span style="width:<?= $fund['share'] ?>%"></span></div></td><td><?= htmlspecialchars($fund['projects']) ?></td><td class="amount"><?= htmlspecialchars($fund['allocation']) ?></td></tr><?php endforeach; ?></tbody></table></div></article>
</section>
<section class="panel recent-panel"><div class="panel-heading"><div><h2>Recent project activity</h2><p class="muted">Latest updates across your program</p></div><a href="pages/projects.php">See all projects <span>→</span></a></div><div class="table-wrap"><table class="activity-table"><thead><tr><th>Project</th><th>Department</th><th>Status</th><th>Updated</th><th>Amount</th><th></th></tr></thead><tbody><?php foreach ($recent as $item): ?><tr><td><strong><?= htmlspecialchars($item['title']) ?></strong></td><td><?= htmlspecialchars($item['department']) ?></td><td><span class="status-pill <?= strtolower(str_replace(' ', '-', $item['status'])) ?>"><i></i><?= htmlspecialchars($item['status']) ?></span></td><td><?= htmlspecialchars($item['date']) ?></td><td class="amount"><?= htmlspecialchars($item['amount']) ?></td><td><button class="row-action" type="button" aria-label="Open project">→</button></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php include 'includes/footer.php'; ?>
