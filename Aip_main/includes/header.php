<?php
require_once __DIR__ . '/auth.php';
requireLogin();
$pageTitle = $pageTitle ?? 'Dashboard';
$activePage = $activePage ?? 'dashboard';
$assetPrefix = $assetPrefix ?? '';
$userName = $_SESSION['user']['name'] ?? 'AIP Administrator';
$userInitials = 'AA';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | AIP Management System</title>
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $assetPrefix ?>assets/css/overrides.css">
</head>
<body class="app-body" data-root-prefix="<?= htmlspecialchars($assetPrefix) ?>">
<div class="app-shell">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <div class="main-area">
        <header class="topbar">
            <button class="mobile-menu" type="button" aria-label="Open menu" data-sidebar-toggle>☰</button>
            <div class="breadcrumbs"><span>AIP Management System</span><strong>/</strong><b><?= htmlspecialchars($pageTitle) ?></b></div>
            <div class="topbar-actions">
                <label class="search-box" aria-label="Search projects">
                    <span>⌕</span>
                    <input type="search" placeholder="Search projects..." data-global-search>
                </label>
                <button class="icon-button" type="button" aria-label="Notifications">♧<i></i></button>
                <button class="icon-button" type="button" aria-label="Help">?</button>
                <div class="user-menu"><span class="avatar"><?= $userInitials ?></span><span class="user-name"><?= htmlspecialchars($userName) ?></span><span class="chevron">⌄</span></div>
            </div>
        </header>
        <main class="content">
