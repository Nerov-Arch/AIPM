<?php
/**
 * ============================================================
 * MOCK STORE: DEVELOPMENT VERSION ONLY
 * ============================================================
 * Keeps user accounts and the activity log in the PHP session so the
 * User Management module can be fully exercised without a database.
 *
 * - Data survives sign-out and switching between test accounts in the
 *   same browser, but is lost when the browser session ends or expires.
 * - It is NOT shared between browsers or computers.
 * - In the database phase each function below is reimplemented with
 *   prepared statements (tables: users, audit_logs). Callers stay the same.
 * ============================================================
 */

const AUDIT_LOG_LIMIT = 500;

/** Create the store from the mock data on first use. */
function storeInit(): void
{
    if (isset($_SESSION['mock_store'])) {
        return;
    }
    $now = date('Y-m-d H:i:s');
    $hash = password_hash(MOCK_DEV_PASSWORD, PASSWORD_DEFAULT);
    $users = [];
    foreach (mockUsers() as $user) {
        $users[$user['id']] = $user + [
            'password_hash'   => $hash,
            'must_change'     => false,
            'created_at'      => $now,
            'created_by'      => 'System (sample data)',
            'updated_at'      => $now,
            'updated_by'      => 'System (sample data)',
            'last_login'      => null,
        ];
    }
    $_SESSION['mock_store'] = [
        'users'   => $users,
        'next_id' => max(array_keys($users)) + 1,
        'audit'   => [],
    ];
}

/** All users keyed by id. */
function storeUsers(): array
{
    storeInit();
    return $_SESSION['mock_store']['users'];
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

/**
 * Insert (no id) or update (with id) a user. Returns the saved record.
 * Stamps who made the change and when, for accountability.
 */
function storeSaveUser(array $user): array
{
    storeInit();
    $actor = currentUser();
    $actorName = $actor ? $actor['name'] . ' (' . $actor['username'] . ')' : 'System';
    $now = date('Y-m-d H:i:s');

    if (empty($user['id'])) {
        $user['id'] = $_SESSION['mock_store']['next_id']++;
        $user += ['created_at' => $now, 'created_by' => $actorName, 'last_login' => null, 'must_change' => true];
    }
    $user['updated_at'] = $now;
    $user['updated_by'] = $actorName;
    $existing = $_SESSION['mock_store']['users'][$user['id']] ?? [];
    $_SESSION['mock_store']['users'][$user['id']] = array_merge($existing, $user);
    return $_SESSION['mock_store']['users'][$user['id']];
}

/** Update fields of a user without changing the "updated by" stamp (e.g. last login). */
function storeTouchUser(int $id, array $fields): void
{
    storeInit();
    if (isset($_SESSION['mock_store']['users'][$id])) {
        $_SESSION['mock_store']['users'][$id] = array_merge($_SESSION['mock_store']['users'][$id], $fields);
    }
}

/** Count active administrators, optionally ignoring one user. */
function storeActiveAdminCount(?int $exceptId = null): int
{
    $count = 0;
    foreach (storeUsers() as $user) {
        if ($user['role'] === 'Administrator' && $user['status'] === 'Active' && $user['id'] !== $exceptId) {
            $count++;
        }
    }
    return $count;
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
    storeInit();
    $actor = $actorOverride ?? currentUser();
    array_unshift($_SESSION['mock_store']['audit'], [
        'time'     => date('Y-m-d H:i:s'),
        'actor'    => $actor['name'] ?? 'Unknown',
        'username' => $actor['username'] ?? '—',
        'role'     => $actor['role'] ?? '—',
        'action'   => $action,
        'target'   => $target,
        'details'  => $details,
        'ip'       => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    $_SESSION['mock_store']['audit'] = array_slice($_SESSION['mock_store']['audit'], 0, AUDIT_LOG_LIMIT);
}

function auditEntries(): array
{
    storeInit();
    return $_SESSION['mock_store']['audit'];
}

/** Labels for activity log actions. */
function auditActionLabel(string $action): string
{
    return [
        'auth.login'          => 'Signed in',
        'auth.login_failed'   => 'Failed sign-in',
        'auth.login_locked'   => 'Sign-in locked',
        'auth.logout'         => 'Signed out',
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
