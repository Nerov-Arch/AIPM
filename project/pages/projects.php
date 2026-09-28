<?php
$pageTitle = 'Project Encoding';
$activePage = 'projects';
$projects = [
 ['title'=>'Community Health Center Expansion','department'=>'Health Services Office','status'=>'Approved','amount'=>'₱24.5M','updated'=>'Jun 18, 2025'],
 ['title'=>'Rural Access Road Improvement','department'=>'Department of Public Works','status'=>'For review','amount'=>'₱18.2M','updated'=>'Jun 17, 2025'],
 ['title'=>'School Building Rehabilitation','department'=>'Education Department','status'=>'Pending','amount'=>'₱12.8M','updated'=>'Jun 16, 2025'],
 ['title'=>'Farm-to-Market Road Program','department'=>'Agriculture Office','status'=>'Approved','amount'=>'₱31.4M','updated'=>'Jun 14, 2025'],
];
include '../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">Program workspace</p><h1>Project encoding</h1><p class="muted">Create, review, and manage proposed investments.</p></div><button class="primary-button" type="button" data-toast="The new project form will open here.">+ New project</button></div>
<div class="filter-bar"><label class="search-box wide"><span>⌕</span><input type="search" placeholder="Search projects..." data-table-search></label><select><option>All departments</option><option>Health Services Office</option><option>Education Department</option></select><select><option>All statuses</option><option>Approved</option><option>Pending</option></select></div>
<section class="panel"><div class="panel-heading"><div><h2>All projects <span class="count-badge">1,248</span></h2><p class="muted">Showing the latest encoded project proposals</p></div></div><div class="table-wrap"><table class="activity-table searchable-table"><thead><tr><th>Project</th><th>Department</th><th>Status</th><th>Amount</th><th>Last updated</th><th></th></tr></thead><tbody><?php foreach ($projects as $project): ?><tr><td><strong><?= htmlspecialchars($project['title']) ?></strong></td><td><?= htmlspecialchars($project['department']) ?></td><td><span class="status-pill <?= strtolower(str_replace(' ', '-', $project['status'])) ?>"><i></i><?= htmlspecialchars($project['status']) ?></span></td><td class="amount"><?= htmlspecialchars($project['amount']) ?></td><td><?= htmlspecialchars($project['updated']) ?></td><td><button class="row-action" type="button">→</button></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php include '../includes/footer.php'; ?>
