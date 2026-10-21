<?php
$pageTitle = 'Review Workflow';
$activePage = 'review';
$assetPrefix = '../';
$reviews = [
 ['ref'=>'PRJ-1042','title'=>'City-wide Smart Street Lighting Upgrade','office'=>'Engineering Dept.','cost'=>'₱12,500,000.00'],
 ['ref'=>'PRJ-1045','title'=>'Public Market Renovation Phase II','office'=>'Economic Enterprise','cost'=>'₱8,200,000.00'],
 ['ref'=>'PRJ-1048','title'=>'Procurement of Rescue Vehicles','office'=>'Disaster Risk Mgmt.','cost'=>'₱15,000,000.00'],
];
include '../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">Governance</p><h1>Review Workflow</h1><p class="muted">Evaluate and process submitted project proposals for the upcoming fiscal year.</p></div><div class="segmented-control"><button class="active" type="button">Pending</button><button type="button">Processed</button></div></div>
<section class="panel cycle-panel"><div class="panel-heading"><h2>Current Cycle: FY 2025 Preparation</h2></div><div class="cycle-stepper"><div class="cycle-step complete"><span>✓</span><b>Encoding</b></div><div class="cycle-line complete"></div><div class="cycle-step current"><span>2</span><b>Review</b></div><div class="cycle-line"></div><div class="cycle-step"><span>3</span><b>Authorization</b></div><div class="cycle-line"></div><div class="cycle-step"><span>4</span><b>Implementation</b></div></div></section>
<section class="panel review-table-panel"><div class="panel-heading"><h2>Pending Evaluations (12)</h2><button class="filter-button" type="button" data-toast="Review filters are ready.">☷ Filter Options</button></div><div class="table-wrap"><table class="activity-table"><thead><tr><th>Ref #</th><th>Project Title</th><th>Implementing Office</th><th>Estimated Cost</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($reviews as $review): ?><tr><td><?= htmlspecialchars($review['ref']) ?></td><td><strong><?= htmlspecialchars($review['title']) ?></strong></td><td><?= htmlspecialchars($review['office']) ?></td><td class="amount"><?= htmlspecialchars($review['cost']) ?></td><td><span class="status-pill pending"><i></i>Pending Review</span></td><td><button class="review-action" type="button" data-toast="<?= htmlspecialchars($review['ref']) ?> opened for evaluation.">Review</button></td></tr><?php endforeach; ?></tbody></table></div><div class="pagination-bar"><span>Showing 1 to 3 of 12 entries</span><div><button type="button">‹</button><button class="current-page" type="button">1</button><button type="button">2</button><button type="button">3</button><button type="button">›</button></div></div></section>
<?php include '../includes/footer.php'; ?>
