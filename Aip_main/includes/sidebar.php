<aside class="sidebar" data-sidebar>
    <div class="brand-block">
        <div class="brand-mark">AIP</div>
        <div><strong>AIP Manager</strong><small>Annual Investment<br>Program</small></div>
        <button class="sidebar-close" type="button" aria-label="Close menu" data-sidebar-close>×</button>
    </div>
    <a class="new-proposal" href="<?= $assetPrefix ?>pages/projects.php"><span>＋</span> New Proposal</a>
    <nav class="primary-nav" aria-label="Main navigation">
        <a class="nav-item <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= $assetPrefix ?>dashboard.php"><span>▦</span>Dashboard</a>
        <a class="nav-item <?= $activePage === 'projects' ? 'active' : '' ?>" href="<?= $assetPrefix ?>pages/projects.php"><span>▤</span>Project Encoding</a>
        <a class="nav-item <?= $activePage === 'funds' ? 'active' : '' ?>" href="<?= $assetPrefix ?>pages/fund-monitoring.php"><span>▥</span>Fund Monitoring</a>
        <a class="nav-item <?= $activePage === 'review' ? 'active' : '' ?>" href="<?= $assetPrefix ?>pages/review-workflow.php"><span>✓</span>Review Workflow</a>
        <a class="nav-item <?= $activePage === 'users' ? 'active' : '' ?>" href="<?= $assetPrefix ?>pages/users.php"><span>♙</span>User Management</a>
    </nav>
    <div class="sidebar-bottom">
        <a class="nav-item" href="#"><span>⚙</span>Settings</a>
        <a class="nav-item" href="#"><span>?</span>Support</a>
        <a class="logout-link" href="<?= $assetPrefix ?>logout.php"><span>↪</span>Sign out</a>
    </div>
</aside>
<div class="sidebar-overlay" data-sidebar-close></div>
