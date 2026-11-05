<?php
/**
 * 403 page shown by requirePermission(). Expects $assetPrefix and $deniedPermission.
 */
$pageSection = '';
$activePage = '';
$skipPermissionCheck = true;
include __DIR__ . '/header.php';
$user = currentUser();
?>
<section class="panel forbidden-panel">
    <p class="eyebrow">Access denied</p>
    <h1>You don’t have permission to open this page</h1>
    <p class="muted">Your role, <strong><?= e($user['role'] ?? '') ?></strong>, does not include: <?= e(PERMISSION_LABELS[$deniedPermission] ?? $deniedPermission) ?>.</p>
    <p class="muted">This attempt was recorded in the activity log. If you need access, ask the system administrator to review your role.</p>
    <a class="primary-button" href="<?= $assetPrefix ?>dashboard.php">Back to dashboard</a>
</section>
<?php include __DIR__ . '/footer.php'; ?>
