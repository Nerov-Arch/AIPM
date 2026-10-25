<?php
/**
 * Sidebar navigation. Each page sets $activePage to one of the keys below.
 */
$navGroups = [
    '' => [
        'dashboard' => ['href' => 'dashboard.php', 'icon' => '▦', 'label' => 'Dashboard'],
    ],
    'Planning' => [
        'projects' => ['href' => 'pages/projects.php',       'icon' => '▤', 'label' => 'Project Encoding'],
        'linking'  => ['href' => 'pages/project-linking.php', 'icon' => '⛓', 'label' => 'Project Linking'],
    ],
    'Workflow' => [
        'review'         => ['href' => 'pages/review-workflow.php',         'icon' => '✓', 'label' => 'Review'],
        'authorization'  => ['href' => 'pages/authorization-workflow.php',  'icon' => '✎', 'label' => 'Authorization'],
        'implementation' => ['href' => 'pages/implementation-workflow.php', 'icon' => '▶', 'label' => 'Implementation'],
    ],
    'Reports & Funds' => [
        'consolidation' => ['href' => 'pages/aip-consolidation.php', 'icon' => '▣', 'label' => 'AIP Consolidation'],
        'funds'         => ['href' => 'pages/fund-monitoring.php',   'icon' => '▥', 'label' => 'Fund Monitoring'],
    ],
    'Administration' => [
        'users' => ['href' => 'pages/users.php', 'icon' => '♙', 'label' => 'User Management'],
    ],
];
?>
<aside class="sidebar" data-sidebar>
    <div class="brand-block">
        <div class="brand-mark">AIP</div>
        <div><strong>AIP Manager</strong><small>MPDO Indang, Cavite<br>Annual Investment Program</small></div>
        <button class="sidebar-close" type="button" aria-label="Close menu" data-sidebar-close>×</button>
    </div>
    <a class="new-proposal" href="<?= $assetPrefix ?>pages/projects.php"><span>＋</span> New Proposal</a>
    <nav class="primary-nav" aria-label="Main navigation">
        <?php foreach ($navGroups as $groupLabel => $items): ?>
            <?php if ($groupLabel !== ''): ?><p class="nav-group-label"><?= e($groupLabel) ?></p><?php endif; ?>
            <?php foreach ($items as $key => $item): ?>
                <a class="nav-item <?= $activePage === $key ? 'active' : '' ?>" href="<?= $assetPrefix . $item['href'] ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>><span aria-hidden="true"><?= $item['icon'] ?></span><?= e($item['label']) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <span class="nav-item disabled" aria-disabled="true" title="Available in a later stage"><span aria-hidden="true">⚙</span>Settings<em>Soon</em></span>
        <span class="nav-item disabled" aria-disabled="true" title="Available in a later stage"><span aria-hidden="true">?</span>Support<em>Soon</em></span>
        <a class="logout-link" href="<?= $assetPrefix ?>logout.php"><span aria-hidden="true">↪</span>Sign out</a>
    </div>
</aside>
<div class="sidebar-overlay" data-sidebar-close></div>
