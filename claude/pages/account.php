<?php
$pageTitle = 'My Account';
$pageSection = '';
$activePage = 'account';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);

$me = currentUser();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($current === '') {
        $errors['current_password'] = 'Enter your current password.';
    } elseif (!password_verify($current, $me['password_hash'])) {
        $errors['current_password'] = 'Your current password is incorrect.';
    }
    if ($new === '') {
        $errors['new_password'] = 'Enter a new password.';
    } elseif (strlen($new) < 8 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $errors['new_password'] = 'Use at least 8 characters with at least one letter and one number.';
    } elseif ($current !== '' && hash_equals($current, $new)) {
        $errors['new_password'] = 'The new password must be different from the current one.';
    }
    if (!isset($errors['new_password']) && $confirm !== $new) {
        $errors['confirm_password'] = 'The passwords do not match.';
    }

    if (!$errors) {
        storeSaveUser(['id' => $me['id'], 'password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change' => false]);
        auditLog('auth.password_change', $me['username']);
        flash('success', 'Your password was changed.');
        header('Location: ' . (!empty($me['must_change']) ? '../dashboard.php' : 'account.php'));
        exit;
    }
}

include '../includes/header.php';
?>
<div class="page-heading"><div><p class="eyebrow">Your profile</p><h1>My Account</h1><p class="muted">Your role, assigned office and sign-in details.</p></div></div>

<?php if (!empty($me['must_change'])): ?>
    <div class="alert info" role="status"><strong>You are using a temporary password.</strong> Set a new password to continue using the system.</div>
<?php endif; ?>

<div class="account-layout">
    <section class="panel form-panel">
        <div class="form-section-heading"><span class="section-icon blue-bg">♙</span><div><h2><?= e($me['name']) ?></h2><p><?= e($me['position'] ?: $me['role']) ?></p></div></div>
        <div class="form-divider"></div>
        <dl class="detail-list">
            <dt>Username</dt><dd><?= e($me['username']) ?></dd>
            <dt>Email</dt><dd><?= e($me['email']) ?></dd>
            <dt>Role</dt><dd><?= e($me['role']) ?></dd>
            <dt>Responsibilities</dt><dd><?= e(ROLES[$me['role']] ?? '') ?></dd>
            <dt>Assigned office</dt><dd><?= e(officeName($me['office'])) ?></dd>
            <dt>Last sign-in</dt><dd><?= $me['last_login'] ? e(date('M j, Y g:i A', strtotime($me['last_login']))) : '—' ?></dd>
        </dl>
        <p class="form-note">Only an administrator can change your name, role or office.</p>
    </section>

    <section class="panel form-panel">
        <div class="form-section-heading"><span class="section-icon amber-bg">▣</span><div><h2>Change password</h2><p>At least 8 characters with a letter and a number.</p></div></div>
        <div class="form-divider"></div>
        <form method="post" data-validate novalidate>
            <?= csrfField() ?>
            <label>Current Password <em>*</em><input name="current_password" type="password" autocomplete="current-password" required><small class="field-error" data-error-for="current_password"><?= e($errors['current_password'] ?? '') ?></small></label>
            <label>New Password <em>*</em><input name="new_password" type="password" autocomplete="new-password" data-rule="password" required><small class="field-error" data-error-for="new_password"><?= e($errors['new_password'] ?? '') ?></small></label>
            <label>Confirm New Password <em>*</em><input name="confirm_password" type="password" autocomplete="new-password" data-same-as="new_password" required><small class="field-error" data-error-for="confirm_password"><?= e($errors['confirm_password'] ?? '') ?></small></label>
            <div class="form-actions"><button class="primary-button" type="submit">Change password</button></div>
        </form>
    </section>
</div>
<?php include '../includes/footer.php'; ?>
