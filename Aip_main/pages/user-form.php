<?php
$pageSection = 'Administration';
$activePage = 'users';
$requiredPermission = 'users.manage';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$me = currentUser();
$roles = mockRoles();
$offices = mockOffices();

$editing = null;
if (isset($_GET['id'])) {
    $editing = storeFindUser((int) $_GET['id']);
    if (!$editing) {
        flash('error', 'That user account was not found.');
        header('Location: users.php');
        exit;
    }
}
$isSelf = $editing && $editing['id'] === $me['id'];
$pageTitle = $editing ? 'Edit User' : 'Create New User';

$fields = ['name', 'username', 'email', 'position', 'role', 'office', 'status'];
$old = $editing ? array_intersect_key($editing, array_flip($fields)) : ['name' => '', 'username' => '', 'email' => '', 'position' => '', 'role' => '', 'office' => '', 'status' => 'Active'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    foreach ($fields as $field) {
        $old[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    $old['username'] = strtolower($old['username']);
    if ($isSelf) {
        // Administrators cannot change their own role or status.
        $old['role'] = $editing['role'];
        $old['status'] = $editing['status'];
    }

    if ($old['name'] === '') {
        $errors['name'] = 'Full name is required.';
    } elseif (mb_strlen($old['name']) > 100) {
        $errors['name'] = 'Use 100 characters or fewer.';
    }

    if ($old['username'] === '') {
        $errors['username'] = 'Username is required.';
    } elseif (!preg_match('/^[a-z0-9._-]{3,30}$/', $old['username'])) {
        $errors['username'] = 'Use 3–30 lowercase letters, numbers, dots, dashes or underscores.';
    }

    if ($old['email'] === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150) {
        $errors['email'] = 'Enter a valid email address.';
    }

    foreach (storeUsers() as $other) {
        if ($editing && $other['id'] === $editing['id']) {
            continue;
        }
        if (!isset($errors['username']) && strtolower($other['username']) === $old['username']) {
            $errors['username'] = 'This username is already taken.';
        }
        if (!isset($errors['email']) && strtolower($other['email']) === strtolower($old['email'])) {
            $errors['email'] = 'This email is already used by another account.';
        }
    }

    if (mb_strlen($old['position']) > 100) {
        $errors['position'] = 'Use 100 characters or fewer.';
    }
    if (!in_array($old['role'], $roles, true)) {
        $errors['role'] = 'Choose a role from the list.';
    }
    if (!isset($offices[$old['office']])) {
        $errors['office'] = 'Choose an office from the list.';
    }
    if (!in_array($old['status'], ['Active', 'Inactive'], true)) {
        $errors['status'] = 'Choose a status.';
    }

    // Keep at least one active administrator.
    if ($editing && !$errors && $editing['role'] === 'Administrator' && $editing['status'] === 'Active'
        && ($old['role'] !== 'Administrator' || $old['status'] !== 'Active')
        && storeActiveAdminCount($editing['id']) === 0) {
        $errors['role'] = 'This is the last active administrator. Assign another administrator first.';
    }

    if (!$errors) {
        if ($editing) {
            $changes = [];
            foreach ($fields as $field) {
                if ((string) $editing[$field] !== $old[$field]) {
                    $changes[] = ucfirst($field) . ': ' . ($editing[$field] === '' ? '—' : $editing[$field]) . ' → ' . ($old[$field] === '' ? '—' : $old[$field]);
                }
            }
            if (!$changes) {
                flash('info', 'No changes were made to ' . e($editing['name']) . '.');
            } else {
                storeSaveUser(['id' => $editing['id']] + $old);
                auditLog('user.update', $old['username'], implode('; ', $changes));
                if ($editing['role'] !== $old['role']) {
                    auditLog('user.role_change', $old['username'], $editing['role'] . ' → ' . $old['role']);
                }
                if ($editing['status'] !== $old['status']) {
                    auditLog($old['status'] === 'Active' ? 'user.activate' : 'user.deactivate', $old['username'], 'Changed from the edit form');
                }
                flash('success', 'Saved changes to ' . e($old['name']) . '.');
            }
        } else {
            $temporary = generateTemporaryPassword();
            $saved = storeSaveUser($old + ['password_hash' => password_hash($temporary, PASSWORD_DEFAULT), 'must_change' => true]);
            auditLog('user.create', $saved['username'], $saved['role'] . ' · ' . $saved['office'] . ' · ' . $saved['status']);
            flash('success', 'Created the account for ' . e($saved['name']) . ' (' . e($saved['username']) . '). Temporary password: <code class="temp-password">' . e($temporary) . '</code> Give it to the user privately; they must change it when they first sign in. It will not be shown again.');
        }
        header('Location: users.php');
        exit;
    }
}

include '../includes/header.php';
?>
<div class="page-heading">
    <div>
        <p class="eyebrow"><a href="users.php">User Management</a> / <?= $editing ? 'Edit' : 'New account' ?></p>
        <h1><?= $editing ? 'Edit ' . e($editing['name']) : 'Create New User' ?></h1>
        <p class="muted"><?= $editing ? 'Update the account details, role, office or status.' : 'A temporary password is generated when the account is created. The user must change it at first sign-in.' ?></p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert error" role="alert"><strong>Please correct <?= count($errors) === 1 ? 'the field' : 'the ' . count($errors) . ' fields' ?> below.</strong><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form method="post" class="user-form-layout" data-validate novalidate>
    <?= csrfField() ?>
    <section class="panel form-panel">
        <div class="form-section-heading"><span class="section-icon blue-bg">♙</span><div><h2>Account details</h2><p>Who the account belongs to and how they sign in.</p></div></div>
        <div class="form-divider"></div>
        <label>Full Name <em>*</em><input name="name" type="text" maxlength="100" value="<?= e($old['name']) ?>" autocomplete="off" required><small class="field-error" data-error-for="name"><?= e($errors['name'] ?? '') ?></small></label>
        <div class="form-columns">
            <label>Username <em>*</em><input name="username" type="text" maxlength="30" pattern="[a-z0-9._\-]{3,30}" data-rule="username" value="<?= e($old['username']) ?>" autocomplete="off" required><small>Lowercase letters, numbers, dots, dashes or underscores.</small><small class="field-error" data-error-for="username"><?= e($errors['username'] ?? '') ?></small></label>
            <label>Email Address <em>*</em><input name="email" type="email" maxlength="150" data-rule="email" value="<?= e($old['email']) ?>" autocomplete="off" required><small class="field-error" data-error-for="email"><?= e($errors['email'] ?? '') ?></small></label>
        </div>
        <label>Position / Designation<input name="position" type="text" maxlength="100" placeholder="e.g. Planning Officer II" value="<?= e($old['position']) ?>"><small class="field-error" data-error-for="position"><?= e($errors['position'] ?? '') ?></small></label>
    </section>

    <section class="panel form-panel">
        <div class="form-section-heading"><span class="section-icon amber-bg">✓</span><div><h2>Role and access</h2><p>The role decides what this user can do in the AIP process.</p></div></div>
        <div class="form-divider"></div>
        <fieldset class="role-options" <?= $isSelf ? 'disabled' : '' ?>>
            <legend>Role <em>*</em></legend>
            <?php foreach (ROLES as $role => $description): ?>
                <label class="role-option">
                    <input type="radio" name="role" value="<?= e($role) ?>" <?= $old['role'] === $role ? 'checked' : '' ?> required>
                    <span><strong><?= e($role) ?></strong><small><?= e($description) ?></small></span>
                </label>
            <?php endforeach; ?>
            <small class="field-error" data-error-for="role"><?= e($errors['role'] ?? '') ?></small>
        </fieldset>
        <?php if ($isSelf): ?><p class="form-note">You cannot change your own role or status. Another administrator must do this.</p><?php endif; ?>
        <div class="form-columns">
            <label>Assigned Office <em>*</em><select name="office" required><option value="">Select office</option><?php foreach ($offices as $code => $name): ?><option value="<?= e($code) ?>" <?= $old['office'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select><small>Encoders can only see and encode PPAs of this office.</small><small class="field-error" data-error-for="office"><?= e($errors['office'] ?? '') ?></small></label>
            <label>Status <em>*</em><select name="status" required <?= $isSelf ? 'disabled' : '' ?>><option value="Active" <?= $old['status'] === 'Active' ? 'selected' : '' ?>>Active (can sign in)</option><option value="Inactive" <?= $old['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive (cannot sign in)</option></select><small class="field-error" data-error-for="status"><?= e($errors['status'] ?? '') ?></small></label>
        </div>
    </section>

    <?php if ($editing): ?>
        <section class="panel form-panel record-meta">
            <div class="form-section-heading"><span class="section-icon blue-bg">ⓘ</span><div><h2>Record history</h2><p>Who created and last changed this account.</p></div></div>
            <div class="form-divider"></div>
            <dl class="detail-list">
                <dt>Created</dt><dd><?= e(date('M j, Y g:i A', strtotime($editing['created_at']))) ?> by <?= e($editing['created_by']) ?></dd>
                <dt>Last updated</dt><dd><?= e(date('M j, Y g:i A', strtotime($editing['updated_at']))) ?> by <?= e($editing['updated_by']) ?></dd>
                <dt>Last sign-in</dt><dd><?= $editing['last_login'] ? e(date('M j, Y g:i A', strtotime($editing['last_login']))) : 'Never' ?></dd>
            </dl>
        </section>
    <?php endif; ?>

    <div class="form-actions">
        <a class="secondary-button" href="users.php">Cancel</a>
        <button class="primary-button" type="submit"><?= $editing ? 'Save changes' : 'Create user' ?> <span>→</span></button>
    </div>
</form>
<?php include '../includes/footer.php'; ?>
