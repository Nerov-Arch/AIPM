<?php
$assetPrefix = $assetPrefix ?? '';
require_once __DIR__ . '/auth.php';
requireLogin($assetPrefix);
require_once __DIR__ . '/mock-data.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$pageSection = $pageSection ?? '';
$activePage = $activePage ?? 'dashboard';
$userName = $_SESSION['user']['name'] ?? 'AIP Administrator';
$userRole = $_SESSION['user']['role'] ?? 'Administrator';
$userInitials = initials($userName);
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
            <span>Sample data only. Changes are not saved until the database is connected.</span>
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
                <button class="icon-button" type="button" aria-label="Notifications" data-toast="No new notifications.">♧</button>
                <button class="icon-button" type="button" aria-label="Help" data-toast="Need help? Contact the MPDO system administrator.">?</button>
                <div class="user-menu" title="Signed in as <?= e($userRole) ?>"><span class="avatar"><?= e($userInitials) ?></span><span class="user-name"><?= e($userName) ?></span><span class="chevron">⌄</span></div>
            </div>
        </header>
        <main class="content">
