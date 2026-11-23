<?php
/**
 * User accounts and the activity log, stored in MySQL (tables `users`, `audit_log`).
 * Records are returned as arrays in the same shape the pages have always used:
 *   id, username, name, email, role, office, position, status, password_hash,
 *   must_change, last_login, created_at, created_by, updated_at, updated_by
 */

const AUDIT_LOG_LIMIT = 500;

/** Kept for older callers; the database needs no initialisation per request. */
function storeInit(): void
{
}

function userFromRow(array $r): array
{
    return [
        'id'            => (int) $r['id'],
        'username'      => $r['username'],
        'name'          => $r['full_name'],
        'email'         => $r['email'],
        'role'          => $r['role'],
        'office'        => $r['office_code'],
        'position'      => $r['position'],
        'status'        => $r['status'],
        'password_hash' => $r['password_hash'],
        'must_change'   => (bool) $r['must_change_password'],
        'last_login'    => $r['last_login_at'],
        'created_at'    => $r['created_at'],
        'created_by'    => dbActorLabel($r['created_by_name'] ?? null, $r['created_by_username'] ?? null) ?: 'System',
        'updated_at'    => $r['updated_at'],
        'updated_by'    => dbActorLabel($r['updated_by_name'] ?? null, $r['updated_by_username'] ?? null) ?: 'System',
    ];
}

/** All users keyed by id. */
function storeUsers(): array
{
    return dbCached('users', function () {
        $rows = dbAll('SELECT u.*, c.full_name AS created_by_name, c.username AS created_by_username,
                              m.full_name AS updated_by_name, m.username AS updated_by_username
                       FROM users u
                       LEFT JOIN users c ON c.id = u.created_by
                       LEFT JOIN users m ON m.id = u.updated_by
                       ORDER BY u.id');
        $users = [];
        foreach ($rows as $row) {
            $users[(int) $row['id']] = userFromRow($row);
        }
        return $users;
    });
}

function storeFindUser(int $id): ?array
{
    return storeUsers()[$id] ?? null;
}

/** Find by username or email (case-insensitive). */
function storeFindUserByIdentity(string $identity): ?array
{
    $identity = strtolower(trim($identity));
    if ($identity === '') {
        return null;
    }
    foreach (storeUsers() as $user) {
        if (strtolower($user['username']) === $identity || strtolower($user['email']) === $identity) {
            return $user;
        }
    }
    return null;
}

/** Array keys used by the pages => columns of the users table. */
const USER_COLUMNS = [
    'username' => 'username', 'name' => 'full_name', 'email' => 'email', 'role' => 'role', 'office' => 'office_code',
    'position' => 'position', 'status' => 'status', 'password_hash' => 'password_hash',
    'must_change' => 'must_change_password', 'last_login' => 'last_login_at',
];

/**
 * Insert (no id) or update (with id) a user. Returns the saved record.
 * Stamps who made the change, for accountability.
 */
function storeSaveUser(array $user): array
{
    [$actorId] = dbActor();
    $values = [];
    foreach (USER_COLUMNS as $key => $column) {
        if (array_key_exists($key, $user)) {
            $values[$column] = $key === 'must_change' ? (int) (bool) $user[$key] : $user[$key];
        }
    }
    if (empty($user['id'])) {
        $values += ['must_change_password' => 1, 'position' => ''];
        $values['created_by'] = $actorId;
        $values['updated_by'] = $actorId;
        $columns = array_keys($values);
        dbExec('INSERT INTO users (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
            array_values($values));
        $id = (int) db()->lastInsertId();
    } else {
        $id = (int) $user['id'];
        $values['updated_by'] = $actorId;
        $sets = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($values)));
        dbExec("UPDATE users SET $sets WHERE id = ?", [...array_values($values), $id]);
    }
    return storeFindUser($id);
}

/** Update fields of a user without changing the "updated by" stamp (e.g. last login). */
function storeTouchUser(int $id, array $fields): void
{
    $values = [];
    foreach (USER_COLUMNS as $key => $column) {
        if (array_key_exists($key, $fields)) {
            $values[$column] = $fields[$key];
        }
    }
    if ($values) {
        $sets = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($values)));
        // keep updated_at unchanged: this is not an edit of the account
        dbExec("UPDATE users SET $sets, updated_at = updated_at WHERE id = ?", [...array_values($values), $id]);
    }
}

/** Count active administrators, optionally ignoring one user. */
function storeActiveAdminCount(?int $exceptId = null): int
{
    return (int) dbValue("SELECT COUNT(*) FROM users WHERE role = 'Administrator' AND status = 'Active' AND id <> ?", [$exceptId ?? 0]);
}

/** Generate a readable temporary password, e.g. "Kp7-mQ4x-Tz9". */
function generateTemporaryPassword(): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $parts = [];
    for ($p = 0; $p < 3; $p++) {
        $part = '';
        for ($i = 0; $i < 3; $i++) {
            $part .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $parts[] = $part;
    }
    return implode('-', $parts);
}

/**
 * Record an action in the activity log.
 *
 * @param string $action  e.g. user.create, user.update, auth.login
 * @param string $target  what the action was about, e.g. "jbautista"
 * @param string $details human-readable details
 * @param array|null $actorOverride actor when no one is signed in (failed logins)
 */
function auditLog(string $action, string $target = '', string $details = '', ?array $actorOverride = null): void
{
    $actor = $actorOverride ?? currentUser();
    dbExec('INSERT INTO audit_log (action, target, details, user_id, username, actor_name, role, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
        $action, mb_substr($target, 0, 100), $details,
        isset($actor['id']) && storeFindUser((int) $actor['id']) ? (int) $actor['id'] : null,
        mb_substr($actor['username'] ?? '—', 0, 150), mb_substr($actor['name'] ?? 'Unknown', 0, 120), mb_substr($actor['role'] ?? '—', 0, 20),
        $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
}

/** The most recent activity log entries, newest first. */
function auditEntries(): array
{
    return dbCached('audit', fn () => array_map(fn ($r) => [
        'time'     => $r['created_at'],
        'actor'    => $r['actor_name'],
        'username' => $r['username'],
        'role'     => $r['role'],
        'action'   => $r['action'],
        'target'   => $r['target'],
        'details'  => (string) $r['details'],
        'ip'       => $r['ip_address'],
    ], dbAll('SELECT * FROM audit_log ORDER BY id DESC LIMIT ' . AUDIT_LOG_LIMIT)));
}

/** Labels for activity log actions. */
function auditActionLabel(string $action): string
{
    return [
        'auth.login'          => 'Signed in',
        'auth.login_failed'   => 'Failed sign-in',
        'auth.login_locked'   => 'Sign-in locked',
        'auth.logout'         => 'Signed out',
        'auth.otp_sent'       => 'Sign-in code sent',
        'auth.otp_failed'     => 'Wrong sign-in code',
        'auth.password_change'=> 'Changed own password',
        'access.denied'       => 'Access denied',
        'user.create'         => 'Created user',
        'user.update'         => 'Updated user',
        'user.role_change'    => 'Changed role',
        'user.activate'       => 'Activated user',
        'user.deactivate'     => 'Deactivated user',
        'user.password_reset' => 'Reset password',
        'ppa.create'          => 'Created PPA draft',
        'ppa.update'          => 'Edited PPA',
        'ppa.submit'          => 'Submitted PPA',
        'ppa.resubmit'        => 'Resubmitted PPA',
        'ppa.delete'          => 'Deleted PPA draft',
        'ppa.funding'         => 'Changed PPA fund allocation',
        'ppa.return'          => 'Returned PPA for revision',
        'ppa.recommend'       => 'Recommended PPA',
        'ppa.forward'         => 'Forwarded PPA to Finance',
        'ppa.edit_in_review'  => 'Office edited PPA under review',
        'ppa.budget_approve'  => 'Approved PPA budget',
        'ppa.budget_return'   => 'Returned PPA to MPDO (budget)',
        'expense.add'         => 'Recorded expense',
        'expense.delete'      => 'Removed expense',
        'expense.verify'      => 'Verified expense',
        'expense.flag'        => 'Flagged expense',
        'ppa.approve'         => 'Approved PPA',
        'ppa.disapprove'      => 'Disapproved PPA',
        'ppa.send_back'       => 'Sent PPA back to Finance',
        'link.record_create'  => 'Registered approval record',
        'link.record_update'  => 'Edited approval record',
        'link.record_delete'  => 'Deleted approval record',
        'link.attach'         => 'Linked approval record',
        'link.detach'         => 'Unlinked approval record',
        'aip.version'         => 'Generated AIP version',
        'aip.finalize'        => 'Finalized consolidated AIP',
        'fund.create'         => 'Added fund source',
        'fund.update'         => 'Edited fund source',
        'fund.activate'       => 'Activated fund source',
        'fund.deactivate'     => 'Deactivated fund source',
        'fund.delete'         => 'Deleted fund source',
    ][$action] ?? $action;
}

/* ---------------- Flash messages (shown once after a redirect) ---------------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function takeFlashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ---------------- CSRF protection ---------------- */

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/** Reject a POST whose token is missing or wrong. */
function verifyCsrf(): void
{
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals(csrfToken(), $sent)) {
        http_response_code(400);
        echo '<!DOCTYPE html><meta charset="utf-8"><title>Form expired</title><body style="font-family:sans-serif;padding:40px">'
            . '<h1>This form has expired</h1><p>Please go back, refresh the page and try again.</p></body>';
        exit;
    }
}
