<?php
/**
 * Sidebar navigation. Each page sets $activePage to one of the keys below.
 */
$navGroups = [
    '' => [
        'dashboard' => ['href' => 'dashboard.php', 'icon' => '▦', 'label' => 'Dashboard', 'perm' => 'dashboard.view'],
    ],
    'Planning' => [
        'projects' => ['href' => 'pages/projects.php',       'icon' => '▤', 'label' => 'Project Encoding', 'perm' => 'ppa.encode'],
        'linking'  => ['href' => 'pages/project-linking.php', 'icon' => '⛓', 'label' => 'Project Linking', 'perm' => 'linking.view'],
    ],
    'Workflow' => [
        'review'         => ['href' => 'pages/review-workflow.php',         'icon' => '✓', 'label' => 'Review', 'perm' => 'review.view'],
        'authorization'  => ['href' => 'pages/authorization-workflow.php',  'icon' => '✎', 'label' => 'Authorization', 'perm' => 'authorization.view'],
        'implementation' => ['href' => 'pages/implementation-workflow.php', 'icon' => '▶', 'label' => 'Implementation', 'perm' => 'implementation.view'],
    ],
    'Reports & Funds' => [
        'consolidation' => ['href' => 'pages/aip-consolidation.php', 'icon' => '▣', 'label' => 'AIP Consolidation', 'perm' => 'consolidation.view'],
        'funds'         => ['href' => 'pages/fund-monitoring.php',   'icon' => '▥', 'label' => 'Fund Monitoring', 'perm' => 'funds.view'],
    ],
    'Administration' => [
        'users' => ['href' => 'pages/users.php', 'icon' => '♙', 'label' => 'User Management', 'perm' => 'users.manage'],
    ],
];
// Show only the pages the signed-in user's role may open.
foreach ($navGroups as $groupLabel => $items) {
    $navGroups[$groupLabel] = array_filter($items, fn ($item) => can($item['perm']));
    if (!$navGroups[$groupLabel]) {
        unset($navGroups[$groupLabel]);
    }
}
?>
<aside class="sidebar" data-sidebar>
    <div class="brand-block">
        <div class="brand-mark">AIP</div>
        <div><strong>AIP Manager</strong><small>MPDO Indang, Cavite<br>Annual Investment Program</small></div>
        <button class="sidebar-close" type="button" aria-label="Close menu" data-sidebar-close>×</button>
    </div>
    <?php if (can('ppa.encode')): ?><a class="new-proposal" href="<?= $assetPrefix ?>pages/projects.php"><span>＋</span> New Proposal</a><?php endif; ?>
    <nav class="primary-nav" aria-label="Main navigation">
        <?php foreach ($navGroups as $groupLabel => $items): ?>
            <?php if ($groupLabel !== ''): ?><p class="nav-group-label"><?= e($groupLabel) ?></p><?php endif; ?>
            <?php foreach ($items as $key => $item): ?>
                <a class="nav-item <?= $activePage === $key ? 'active' : '' ?>" href="<?= $assetPrefix . $item['href'] ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>><span aria-hidden="true"><?= $item['icon'] ?></span><?= e($item['label']) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <a class="nav-item <?= $activePage === 'account' ? 'active' : '' ?>" href="<?= $assetPrefix ?>pages/account.php"><span aria-hidden="true">⚙</span>My Account</a>
        <span class="nav-item disabled" aria-disabled="true" title="Available in a later stage"><span aria-hidden="true">?</span>Support<em>Soon</em></span>
        <a class="logout-link" href="<?= $assetPrefix ?>logout.php"><span aria-hidden="true">↪</span>Sign out</a>
    </div>
</aside>
<div class="sidebar-overlay" data-sidebar-close></div>
