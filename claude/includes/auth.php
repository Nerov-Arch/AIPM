<?php
/**
 * Session, sign-in and the current user.
 *
 * DEV-ONLY: accounts come from the session-backed mock store (includes/store.php).
 * Passwords are hashed with password_hash(), but because the store is not a
 * database this is still a development login, not production authentication.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/mock-data.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/ppa.php';
require_once __DIR__ . '/funds.php';
require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/aip.php';
require_once __DIR__ . '/links.php';
require_once __DIR__ . '/dashboard.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 300;

/** The signed-in user's record, or null. Inactive accounts are signed out. */
function currentUser(): ?array
{
    static $cache = null;
    $id = $_SESSION['user_id'] ?? null;
    if ($id === null) {
        return null;
    }
    if ($cache !== null && $cache['id'] === $id) {
        return $cache;
    }
    $user = storeFindUser((int) $id);
    if (!$user || $user['status'] !== 'Active') {
        unset($_SESSION['user_id']);
        $_SESSION['signed_out_reason'] = 'Your account is no longer active. Contact the system administrator.';
        return null;
    }
    return $cache = $user;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

/**
 * Redirect to the login page when no one is signed in, and to My Account
 * when the user must change a temporary password first.
 */
function requireLogin(string $rootPrefix = ''): void
{
    $user = currentUser();
    if (!$user) {
        header('Location: ' . $rootPrefix . 'login.php');
        exit;
    }
    $onAccountPage = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'account.php';
    if (!empty($user['must_change']) && !$onAccountPage) {
        header('Location: ' . $rootPrefix . 'pages/account.php');
        exit;
    }
}

/**
 * Try to sign in. Returns null on success, or an error message.
 * Repeated failures lock sign-in for this browser session.
 */
function attemptLogin(string $identity, string $password): ?string
{
    $lockedUntil = $_SESSION['login_locked_until'] ?? 0;
    if ($lockedUntil > time()) {
        $minutes = (int) ceil(($lockedUntil - time()) / 60);
        return 'Too many failed attempts. Try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
    }

    $user = storeFindUserByIdentity($identity);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
        auditLog('auth.login_failed', $identity, 'Attempt ' . $_SESSION['login_failures'] . ' of ' . LOGIN_MAX_ATTEMPTS,
            ['name' => $user['name'] ?? 'Unknown user', 'username' => $identity, 'role' => $user['role'] ?? '—']);
        if ($_SESSION['login_failures'] >= LOGIN_MAX_ATTEMPTS) {
            $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_SECONDS;
            $_SESSION['login_failures'] = 0;
            auditLog('auth.login_locked', $identity, 'Locked for ' . (LOGIN_LOCK_SECONDS / 60) . ' minutes',
                ['name' => $user['name'] ?? 'Unknown user', 'username' => $identity, 'role' => $user['role'] ?? '—']);
            return 'Too many failed attempts. Sign-in is locked for ' . (LOGIN_LOCK_SECONDS / 60) . ' minutes.';
        }
        return 'The username or password is incorrect.';
    }
    if ($user['status'] !== 'Active') {
        auditLog('auth.login_failed', $user['username'], 'Account is inactive', $user);
        return 'This account is inactive. Contact the system administrator.';
    }

    session_regenerate_id(true);
    unset($_SESSION['login_failures'], $_SESSION['login_locked_until'], $_SESSION['signed_out_reason']);
    $_SESSION['user_id'] = $user['id'];
    storeTouchUser($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
    auditLog('auth.login', $user['username'], 'Signed in as ' . $user['role'], $user);
    return null;
}

/** Sign out but keep the mock store, so test data survives switching accounts. */
function logoutUser(): void
{
    $user = currentUser();
    if ($user) {
        auditLog('auth.logout', $user['username']);
    }
    unset($_SESSION['user_id'], $_SESSION['csrf_token']);
    session_regenerate_id(true);
}
