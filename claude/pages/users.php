<?php
$pageTitle = 'User Management';
$pageSection = 'Administration';
$activePage = 'users';
$requiredPermission = 'users.manage';
$assetPrefix = '../';

require_once '../includes/auth.php';
requireLogin($assetPrefix);
requirePermission($requiredPermission, $assetPrefix);

$me = currentUser();

/* ---------- Account actions (POST → redirect) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $target = storeFindUser((int) ($_POST['user_id'] ?? 0));

    if (!$target) {
        flash('error', 'That user account was not found.');
    } elseif ($action === 'toggle_status') {
        $activate = $target['status'] !== 'Active';
        if (!$activate && $target['id'] === $me['id']) {
            flash('error', 'You cannot deactivate your own account.');
        } elseif (!$activate && $target['role'] === 'Administrator' && storeActiveAdminCount($target['id']) === 0) {
            flash('error', 'You cannot deactivate the last active administrator.');
        } else {
            storeSaveUser(['id' => $target['id'], 'status' => $activate ? 'Active' : 'Inactive']);
            auditLog($activate ? 'user.activate' : 'user.deactivate', $target['username'], $target['name'] . ' (' . $target['role'] . ')');
            flash('success', e($target['name']) . ' was ' . ($activate ? 'activated and can now sign in.' : 'deactivated and can no longer sign in.'));
        }
    } elseif ($action === 'reset_password') {
        if ($target['id'] === $me['id']) {
            flash('error', 'Use My Account to change your own password.');
        } else {
            $temporary = generateTemporaryPassword();
            storeSaveUser(['id' => $target['id'], 'password_hash' => password_hash($temporary, PASSWORD_DEFAULT), 'must_change' => true]);
            auditLog('user.password_reset', $target['username'], 'Temporary password issued; change required at next sign-in');
            flash('success', 'Password reset for ' . e($target['name']) . '. Temporary password: <code class="temp-password">' . e($temporary) . '</code> Give it to the user privately; they must change it when they sign in. It will not be shown again.');
        }
    } else {
        flash('error', 'Unknown action.');
    }
    header('Location: users.php');
    exit;
}

$users = storeUsers();
$roles = mockRoles();
$offices = mockOffices();
$auditEntries = auditEntries();
$roleCounts = array_count_values(array_column(array_filter($users, fn ($u) => $u['status'] === 'Active'), 'role'));
$activeCount = count(array_filter($users, fn ($u) => $u['status'] === 'Active'));
$tab = in_array($_GET['tab'] ?? '', ['users', 'roles', 'activity'], true) ? $_GET['tab'] : 'users';
$tableId = 'users-table';
$auditId = 'audit-table';

include '../includes/header.php';
?>
<div class="page-heading">
    <div><p class="eyebrow">Access control</p><h1>User Management</h1><p class="muted">Create and manage user accounts, assign roles and offices, and review account activity.</p></div>
    <div class="heading-actions">
        <a class="primary-button" href="user-form.php">♙ Create New User</a>
    </div>
</div>

<section class="stat-grid four user-stats">
    <article class="stat-card"><div class="stat-top"><span class="stat-label">Total accounts</span><span class="stat-icon blue">♙</span></div><strong><?= count($users) ?></strong><p><?= $activeCount ?> active · <?= count($users) - $activeCount ?> inactive</p></article>
    <?php foreach (['Encoder' => 'green', 'Reviewer' => 'blue', 'Approver' => 'amber'] as $role => $color): ?>
        <article class="stat-card"><div class="stat-top"><span class="stat-label">Active <?= e(strtolower($role)) ?>s</span><span class="stat-icon <?= $color ?>">◉</span></div><strong><?= $roleCounts[$role] ?? 0 ?></strong><p><?= e(PERMISSION_LABELS[[ 'Encoder' => 'ppa.encode', 'Reviewer' => 'review.act', 'Approver' => 'authorization.act'][$role]]) ?></p></article>
    <?php endforeach; ?>
</section>

<div class="tab-bar" role="tablist" aria-label="User management sections">
    <button type="button" role="tab" class="<?= $tab === 'users' ? 'active' : '' ?>" aria-pressed="<?= $tab === 'users' ? 'true' : 'false' ?>" data-view-group="user-tabs" data-view-show="#tab-users">User accounts</button>
    <button type="button" role="tab" class="<?= $tab === 'roles' ? 'active' : '' ?>" aria-pressed="<?= $tab === 'roles' ? 'true' : 'false' ?>" data-view-group="user-tabs" data-view-show="#tab-roles">Roles &amp; permissions</button>
    <button type="button" role="tab" class="<?= $tab === 'activity' ? 'active' : '' ?>" aria-pressed="<?= $tab === 'activity' ? 'true' : 'false' ?>" data-view-group="user-tabs" data-view-show="#tab-activity">Activity log (<?= count($auditEntries) ?>)</button>
</div>

<!-- ======================= Users ======================= -->
<div id="tab-users" <?= $tab !== 'users' ? 'hidden' : '' ?>>
<section class="panel users-panel" id="<?= $tableId ?>" data-table-panel data-page-size="8">
    <div class="user-table-toolbar">
        <label class="search-box wide"><span aria-hidden="true">⌕</span><span class="visually-hidden">Search users</span><input type="search" placeholder="Search by name, username or email..." data-search data-for="<?= $tableId ?>"></label>
        <div class="heading-actions">
            <button class="filter-button" type="button" data-toggle-target="#user-filters">☷ Filter</button>
            <button class="filter-button" type="button" data-export-csv="users.csv" data-for="<?= $tableId ?>">⇩ Export</button>
        </div>
    </div>
    <div class="filter-bar" id="user-filters" hidden>
        <label>Role<select data-filter="role" data-for="<?= $tableId ?>"><option value="">All roles</option><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>"><?= e($role) ?></option><?php endforeach; ?></select></label>
        <label>Office<select data-filter="office" data-for="<?= $tableId ?>"><option value="">All offices</option><?php foreach ($offices as $code => $name): ?><option value="<?= e($code) ?>"><?= e($name) ?></option><?php endforeach; ?></select></label>
        <label>Status<select data-filter="status" data-for="<?= $tableId ?>"><option value="">All</option><option value="Active">Active</option><option value="Inactive">Inactive</option></select></label>
        <button class="text-button" type="button" data-filter-reset data-for="<?= $tableId ?>">Clear filters</button>
    </div>
    <div class="table-wrap">
        <table class="activity-table user-table">
            <thead><tr><th>User</th><th>Username</th><th>Role</th><th>Office</th><th>Status</th><th>Last sign-in</th><th data-no-export>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user):
                $isMe = $user['id'] === $me['id'];
                $isActive = $user['status'] === 'Active';
                $detail = ['eyebrow' => 'User account', 'title' => $user['name'], 'fields' => [
                    ['Username', $user['username']],
                    ['Email', $user['email']],
                    ['Position', $user['position'] !== '' ? $user['position'] : '—'],
                    ['Role', $user['role']],
                    ['Role responsibilities', ROLES[$user['role']] ?? ''],
                    ['Office', officeName($user['office'])],
                    ['Status', $user['status'] . (!empty($user['must_change']) ? ' (must change password)' : '')],
                    ['Last sign-in', $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never'],
                    ['Created', date('M j, Y g:i A', strtotime($user['created_at'])) . ' by ' . $user['created_by']],
                    ['Last updated', date('M j, Y g:i A', strtotime($user['updated_at'])) . ' by ' . $user['updated_by']],
                ]];
            ?>
                <tr data-row data-role="<?= e($user['role']) ?>" data-office="<?= e($user['office']) ?>" data-status="<?= e($user['status']) ?>">
                    <td data-export="<?= e($user['name'] . ' <' . $user['email'] . '>') ?>"><div class="person"><span class="avatar small-avatar <?= $isActive ? '' : 'muted-avatar' ?>"><?= e(initials($user['name'])) ?></span><span><strong><?= e($user['name']) ?><?= $isMe ? ' <em class="you-badge">You</em>' : '' ?></strong><small class="table-subtext"><?= e($user['email']) ?></small></span></div></td>
                    <td><?= e($user['username']) ?></td>
                    <td><strong><?= e($user['role']) ?></strong><?php if ($user['position'] !== ''): ?><small class="table-subtext"><?= e($user['position']) ?></small><?php endif; ?></td>
                    <td data-export="<?= e(officeName($user['office'])) ?>" title="<?= e(officeName($user['office'])) ?>"><?= e($user['office']) ?></td>
                    <td><span class="status-pill <?= $isActive ? 'active' : 'inactive' ?>"><i></i><?= e($user['status']) ?></span><?php if (!empty($user['must_change'])): ?><small class="table-subtext">Temporary password</small><?php endif; ?></td>
                    <td><?= $user['last_login'] ? e(date('M j, g:i A', strtotime($user['last_login']))) : '<span class="table-subtext">Never</span>' ?></td>
                    <td>
                        <div class="row-actions">
                            <button class="text-button" type="button" data-detail="<?= e(json_encode($detail)) ?>">View</button>
                            <a class="text-button" href="user-form.php?id=<?= $user['id'] ?>">Edit</a>
                            <?php if (!$isMe): ?>
                                <form method="post" data-confirm="Reset the password for <?= e($user['name']) ?>? A temporary password will be issued and they must change it at next sign-in.">
                                    <?= csrfField() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button class="text-button" type="submit">Reset password</button>
                                </form>
                                <form method="post" data-confirm="<?= $isActive ? 'Deactivate ' . e($user['name']) . '? They will no longer be able to sign in.' : 'Activate ' . e($user['name']) . '? They will be able to sign in again.' ?>">
                                    <?= csrfField() ?><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button class="text-button <?= $isActive ? 'danger-text' : '' ?>" type="submit"><?= $isActive ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
                <tr data-empty hidden><td class="empty-cell" colspan="7">No users match your search or filters.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar" data-pager data-noun="users"></div>
</section>
</div>

<!-- ======================= Roles ======================= -->
<div id="tab-roles" <?= $tab !== 'roles' ? 'hidden' : '' ?>>
    <section class="role-grid">
        <?php foreach (ROLES as $role => $description): ?>
            <article class="panel role-card">
                <div class="role-card-head"><h2><?= e($role) ?></h2><span class="status-pill active"><i></i><?= $roleCounts[$role] ?? 0 ?> active</span></div>
                <p class="muted"><?= e($description) ?></p>
                <ul>
                    <?php foreach (PERMISSIONS as $perm => $allowed): if (in_array($role, $allowed, true) && $perm !== 'scope.all_offices'): ?>
                        <li><?= e(PERMISSION_LABELS[$perm]) ?></li>
                    <?php endif; endforeach; ?>
                    <?php if (!in_array($role, PERMISSIONS['scope.all_offices'], true)): ?><li class="scope-note">Sees records of own office only</li><?php endif; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </section>
    <section class="panel flush-panel">
        <div class="panel-heading"><div><h2>Permission matrix</h2><p class="muted">Enforced on the server for every page and action, not only by hiding buttons.</p></div></div>
        <div class="table-wrap">
            <table class="activity-table matrix-table">
                <thead><tr><th>Permission</th><?php foreach (ROLES as $role => $_): ?><th><?= e($role) ?></th><?php endforeach; ?></tr></thead>
                <tbody>
                <?php foreach (PERMISSIONS as $perm => $allowed): ?>
                    <tr><td><?= e(PERMISSION_LABELS[$perm]) ?></td><?php foreach (ROLES as $role => $_): ?><td><?= in_array($role, $allowed, true) ? '<span class="matrix-yes" aria-label="Allowed">✓</span>' : '<span class="matrix-no" aria-label="Not allowed">—</span>' ?></td><?php endforeach; ?></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<!-- ======================= Activity log ======================= -->
<div id="tab-activity" <?= $tab !== 'activity' ? 'hidden' : '' ?>>
<section class="panel flush-panel" id="<?= $auditId ?>" data-table-panel data-page-size="12">
    <div class="panel-heading">
        <div><h2>Activity log</h2><p class="muted">Sign-ins, account changes and denied access, each tied to the user who performed it. Newest first.</p></div>
        <button class="filter-button" type="button" data-export-csv="activity-log.csv" data-for="<?= $auditId ?>">⇩ Export</button>
    </div>
    <div class="filter-bar">
        <label>Action<select data-filter="action" data-for="<?= $auditId ?>"><option value="">All actions</option><?php foreach (array_unique(array_column($auditEntries, 'action')) as $action): ?><option value="<?= e($action) ?>"><?= e(auditActionLabel($action)) ?></option><?php endforeach; ?></select></label>
        <label>Performed by<select data-filter="username" data-for="<?= $auditId ?>"><option value="">Everyone</option><?php foreach (array_unique(array_column($auditEntries, 'username')) as $username): ?><option value="<?= e($username) ?>"><?= e($username) ?></option><?php endforeach; ?></select></label>
        <label>Search<input type="search" placeholder="Target or details" data-search data-for="<?= $auditId ?>"></label>
        <button class="text-button" type="button" data-filter-reset data-for="<?= $auditId ?>">Clear filters</button>
    </div>
    <div class="table-wrap">
        <table class="activity-table audit-table">
            <thead><tr><th>Date &amp; time</th><th>Performed by</th><th>Action</th><th>Target</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($auditEntries as $entry):
                $tone = str_starts_with($entry['action'], 'access.') || $entry['action'] === 'auth.login_failed' || $entry['action'] === 'auth.login_locked' || $entry['action'] === 'user.deactivate' ? 'rejected' : (str_starts_with($entry['action'], 'user.') ? 'for-review' : 'inactive');
            ?>
                <tr data-row data-action="<?= e($entry['action']) ?>" data-username="<?= e($entry['username']) ?>">
                    <td data-export="<?= e($entry['time']) ?>"><?= e(date('M j, Y g:i:s A', strtotime($entry['time']))) ?></td>
                    <td data-export="<?= e($entry['actor'] . ' (' . $entry['username'] . ', ' . $entry['role'] . ')') ?>"><strong><?= e($entry['actor']) ?></strong><small class="table-subtext"><?= e($entry['username']) ?> · <?= e($entry['role']) ?></small></td>
                    <td data-export="<?= e(auditActionLabel($entry['action'])) ?>"><span class="status-pill <?= $tone ?>"><i></i><?= e(auditActionLabel($entry['action'])) ?></span></td>
                    <td><?= e($entry['target']) ?></td>
                    <td class="title-cell"><?= e($entry['details']) ?></td>
                </tr>
            <?php endforeach; ?>
                <tr data-empty hidden><td class="empty-cell" colspan="5">No activity recorded yet.</td></tr>
            </tbody>
        </table>
    </div>
    <div class="pagination-bar" data-pager data-noun="entries"></div>
</section>
</div>
<?php include '../includes/footer.php'; ?>
