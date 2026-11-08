<?php
/**
 * Page header. Before including it, a page sets:
 *   $pageTitle, $pageSection, $activePage, $assetPrefix
 *   $requiredPermission  (permission needed to open the page)
 */
$assetPrefix = $assetPrefix ?? '';
require_once __DIR__ . '/auth.php';
requireLogin($assetPrefix);
if (!empty($requiredPermission) && empty($skipPermissionCheck)) {
    requirePermission($requiredPermission, $assetPrefix);
}

$pageTitle = $pageTitle ?? 'Dashboard';
$pageSection = $pageSection ?? '';
$activePage = $activePage ?? 'dashboard';
$currentUser = currentUser();
$flashes = takeFlashes();
$awaiting = awaitingMyAction();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> | AIP Management System</title>
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/overrides.css">
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/components.css">
</head>
<body class="app-body" data-root-prefix="<?= e($assetPrefix) ?>">
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-area">
        <div class="dev-banner" role="note">
            <strong>Development version</strong>
            <span>Sample data kept in this browser session only. Nothing is saved to a database yet.</span>
        </div>
        <header class="topbar">
            <button class="mobile-menu" type="button" aria-label="Open menu" data-sidebar-toggle>☰</button>
            <nav class="breadcrumbs" aria-label="Breadcrumb">
                <span>AIP Management System</span>
                <?php if ($pageSection !== ''): ?><strong>/</strong><span><?= e($pageSection) ?></span><?php endif; ?>
                <strong>/</strong><b><?= e($pageTitle) ?></b>
            </nav>
            <div class="topbar-actions">
                <form class="search-box" role="search" action="<?= $assetPrefix ?>pages/project-linking.php" method="get">
                    <span aria-hidden="true">⌕</span>
                    <label class="visually-hidden" for="global-search">Search PPAs</label>
                    <input id="global-search" type="search" name="q" placeholder="Search PPAs, ref no., office..." value="<?= e($_GET['q'] ?? '') ?>">
                </form>
                <a class="icon-button bell" href="<?= $assetPrefix ?>dashboard.php#awaiting" aria-label="<?= count($awaiting) ?> item<?= count($awaiting) === 1 ? '' : 's' ?> awaiting your action" title="<?= count($awaiting) ?> awaiting your action">♧<?php if ($awaiting): ?><b class="bell-count"><?= count($awaiting) > 99 ? '99+' : count($awaiting) ?></b><?php endif; ?></a>
                <button class="icon-button" type="button" aria-label="Help" data-toast="Need help? Contact the MPDO system administrator.">?</button>
                <a class="user-menu" href="<?= $assetPrefix ?>pages/account.php" title="My account">
                    <span class="avatar"><?= e(initials($currentUser['name'])) ?></span>
                    <span class="user-name"><?= e($currentUser['name']) ?><small><?= e($currentUser['role']) ?> · <?= e($currentUser['office']) ?></small></span>
                    <span class="chevron">⌄</span>
                </a>
            </div>
        </header>
        <main class="content">
        <?php foreach ($flashes as $flash): ?>
            <div class="alert <?= e($flash['type']) ?>" role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"><?= $flash['message'] ?></div>
        <?php endforeach; ?>
