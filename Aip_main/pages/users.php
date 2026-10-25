<?php
$pageTitle = 'User Management';
$pageSection = 'Administration';
$activePage = 'users';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
require_once '../includes/mock-data.php';

$users = mockUsers();
$roles = mockRoles();
$offices = mockOffices();
$tableId = 'users-table';

// Create-user form: validated on the server, but not stored (no database yet).
$formErrors = [];
$formOld = ['name' => '', 'email' => '', 'role' => '', 'office' => ''];
$created = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    foreach ($formOld as $field => $_) {
        $formOld[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    if ($formOld['name'] === '') {
        $formErrors['name'] = 'Full name is required.';
    } elseif (mb_strlen($formOld['name']) > 100) {
        $formErrors['name'] = 'Use 100 characters or fewer.';
    }
    if ($formOld['email'] === '') {
        $formErrors['email'] = 'Email address is required.';
    } elseif (!filter_var($formOld['email'], FILTER_VALIDATE_EMAIL)) {
        $formErrors['email'] = 'Enter a valid email address.';
    } elseif (in_array(strtolower($formOld['email']), array_map('strtolower', array_column($users, 'email')), true)) {
        $formErrors['email'] = 'This email is already used by another account.';
    }
    if (!in_array($formOld['role'], $roles, true)) {
        $formErrors['role'] = 'Choose a role from the list.';
    }
    if (!isset($offices[$formOld['office']])) {
        $formErrors['office'] = 'Choose an office from the list.';
    }
    if (!$formErrors) {
        $created = $formOld;
        $formOld = ['name' => '', 'email' => '', 'role' => '', 'office' => ''];
    }
}

include '../includes/header.php';
?>
<div class="page-heading">
    <div><p class="eyebrow">Access control</p><h1>User Management</h1><p class="muted">Manage system access, roles, and assigned offices.</p></div>
    <div class="heading-actions">
        <button class="secondary-button" type="button" data-toggle-target="#user-filters">☷ Filter</button>
        <button class="secondary-button" type="button" data-export-csv="users.csv" data-for="<?= $tableId ?>">⇩ Export</button>
        <button class="primary-button" type="button" data-dialog-open="#create-user-dialog">♙ Create New User</button>
    </div>
</div>

<?php if ($created): ?>
    <div class="alert success" role="status"><strong>User details are valid.</strong> Development version: the account for <?= e($created['name']) ?> (<?= e($created['role']) ?>, <?= e($created['office']) ?>) was <strong>not created</strong> because the database is not connected yet.</div>
<?php endif; ?>

<section class="panel users-panel" id="<?= $tableId ?>" data-table-panel data-page-size="5">
    <div class="user-table-toolbar">
        <label class="search-box wide"><span aria-hidden="true">⌕</span><span class="visually-hidden">Search users</span><input type="search" placeholder="Search users by name or email..." data-search data-for="<?= $tableId ?>"></label>
        <div class="density-toggle"><span>Density:</span><button class="active" type="button" data-density="standard" data-for="<?= $tableId ?>">Standard</button><button type="button" data-density="dense" data-for="<?= $tableId ?>">Dense</button></div>
    </div>
    <div class="filter-bar" id="user-filters" hidden>
        <label>Role<select data-filter="role" data-for="<?= $tableId ?>"><option value="">All roles</option><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>"><?= e($role) ?></option><?php endforeach; ?></select></label>
        <label>Office<select data-filter="office" data-for="<?= $tableId ?>"><option value="">All offices</option><?php foreach ($offices as $code => $name): ?><option value="<?= e($code) ?>"><?= e($name) ?></option><?php endforeach; ?></select></label>
        <label>Status<select data-filter="status" data-for="<?= $tableId ?>"><option value="">All</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></label>
        <button class="text-button" type="button" data-filter-reset data-for="<?= $tableId ?>">Clear filters</button>
    </div>
    <div class="table-wrap">
        <table class="activity-table">
            <thead><tr><th data-no-export><input type="checkbox" aria-label="Select all users on this page" data-select-all data-for="<?= $tableId ?>"></th><th>User details</th><th>Role &amp; permissions</th><th>Office</th><th>Status</th><th data-no-export>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user):
                $detail = ['eyebrow' => 'User account', 'title' => $user['name'], 'fields' => [
                    ['Email', $user['email']], ['Role', $user['role']], ['Office', officeName($user['office'])], ['Status', $user['status']],
                ], 'note' => 'Development version: editing, deactivating and resetting passwords will be added in the user management stage.'];
            ?>
                <tr data-row data-role="<?= e($user['role']) ?>" data-office="<?= e($user['office']) ?>" data-status="<?= e($user['status']) ?>">
                    <td><input type="checkbox" aria-label="Select <?= e($user['name']) ?>"></td>
                    <td data-export="<?= e($user['name'] . ' <' . $user['email'] . '>') ?>"><div class="person"><span class="avatar small-avatar"><?= e(initials($user['name'])) ?></span><span><strong><?= e($user['name']) ?></strong><small class="table-subtext"><?= e($user['email']) ?></small></span></div></td>
                    <td><strong><?= e($user['role']) ?></strong></td>
                    <td data-export="<?= e(officeName($user['office'])) ?>"><?= e($user['office']) ?></td>
                    <td><span class="status-pill <?= strtolower($user['status']) ?>"><i></i><?= e($user['status']) ?></span></td>
                    <td><button class="row-action" type="button" aria-label="View <?= e($user['name']) ?>" data-detail="<?= e(json_encode($detail)) ?>">···</button></td>
                </tr>
            <?php endforeach; ?>
                <tr data-empty hidden><td class="empty-cell" colspan="6">No users match your search or filters.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar" data-pager data-noun="users"></div>
</section>

<dialog class="form-dialog" id="create-user-dialog" aria-labelledby="create-user-title" <?= $formErrors ? 'data-open-on-load' : '' ?>>
    <form method="post" data-validate novalidate>
        <div class="dialog-head">
            <div><p class="eyebrow">New account</p><h2 id="create-user-title">Create New User</h2></div>
            <button class="dialog-close" type="button" aria-label="Close" data-dialog-close>×</button>
        </div>
        <div class="form-grid">
            <label class="full">Full Name <em>*</em><input name="name" type="text" maxlength="100" value="<?= e($formOld['name']) ?>" required><small class="field-error" data-error-for="name"><?= e($formErrors['name'] ?? '') ?></small></label>
            <label class="full">Email Address <em>*</em><input name="email" type="email" maxlength="150" data-rule="email" value="<?= e($formOld['email']) ?>" required><small class="field-error" data-error-for="email"><?= e($formErrors['email'] ?? '') ?></small></label>
            <label>Role <em>*</em><select name="role" required><option value="">Select role</option><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>" <?= $formOld['role'] === $role ? 'selected' : '' ?>><?= e($role) ?></option><?php endforeach; ?></select><small class="field-error" data-error-for="role"><?= e($formErrors['role'] ?? '') ?></small></label>
            <label>Office <em>*</em><select name="office" required><option value="">Select office</option><?php foreach ($offices as $code => $name): ?><option value="<?= e($code) ?>" <?= $formOld['office'] === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select><small class="field-error" data-error-for="office"><?= e($formErrors['office'] ?? '') ?></small></label>
        </div>
        <p class="dialog-note">Development version: the details are checked, but no account is created until the database is connected. Passwords will be set by the user on first sign-in.</p>
        <div class="dialog-actions">
            <button class="secondary-button" type="button" data-dialog-close>Cancel</button>
            <button class="primary-button" type="submit" name="create_user" value="1">Create User</button>
        </div>
    </form>
</dialog>
<?php include '../includes/footer.php'; ?>
