<?php
/**
 * Session, sign-in and the current user.
 *
 * Accounts are in the `users` table. Sign-in has two steps: the password,
 * then a one-time code sent to the account's @indang.gov.ph email
 * (`login_otps`). Failed attempts are recorded in `login_attempts`; five
 * failures for the same account within five minutes lock it for five minutes.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once __DIR__ . '/reference.php';
require_once __DIR__ . '/store.php';
require_once __DIR__ . '/ppa.php';
require_once __DIR__ . '/funds.php';
require_once __DIR__ . '/workflow.php';
require_once __DIR__ . '/aip.php';
require_once __DIR__ . '/links.php';
require_once __DIR__ . '/expenses.php';
require_once __DIR__ . '/dashboard.php';
require_once __DIR__ . '/mailer.php';

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_SECONDS = 300;
const OTP_MINUTES = 5;          // a sign-in code is valid this long
const OTP_MAX_ATTEMPTS = 5;     // wrong codes allowed before starting over
const OTP_RESEND_SECONDS = 60;  // wait before another code can be sent

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

/** The key failed attempts are counted under: the account's username, or what was typed. */
function loginIdentityKey(string $identity, ?array $user): string
{
    return strtolower($user['username'] ?? trim($identity));
}

/** Seconds until the account may try again, or 0 when it is not locked. */
function loginLockRemaining(string $key): int
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_LOCK_SECONDS);
    $lastSuccess = dbValue('SELECT MAX(attempted_at) FROM login_attempts WHERE identity = ? AND success = 1', [$key]);
    $from = $lastSuccess !== null && $lastSuccess > $since ? $lastSuccess : $since;
    $failures = dbAll('SELECT attempted_at FROM login_attempts WHERE identity = ? AND success = 0 AND attempted_at > ? ORDER BY attempted_at DESC LIMIT ' . LOGIN_MAX_ATTEMPTS,
        [$key, $from]);
    if (count($failures) < LOGIN_MAX_ATTEMPTS) {
        return 0;
    }
    return max(0, strtotime($failures[0]['attempted_at']) + LOGIN_LOCK_SECONDS - time());
}

function recordLoginAttempt(string $key, bool $success): void
{
    dbExec('INSERT INTO login_attempts (identity, ip_address, success) VALUES (?, ?, ?)', [mb_substr($key, 0, 150), $_SERVER['REMOTE_ADDR'] ?? '', (int) $success]);
}

/**
 * Step 1 of sign-in: check the password. On success a one-time code is sent
 * to the account's @indang.gov.ph email and step 2 (verifyOtp) must follow.
 * Returns null when a code was sent, or an error message.
 */
function attemptLogin(string $identity, string $password): ?string
{
    $user = storeFindUserByIdentity($identity);
    $key = loginIdentityKey($identity, $user);
    $wait = loginLockRemaining($key);
    if ($wait > 0) {
        $minutes = (int) ceil($wait / 60);
        return 'Too many failed attempts. Try again in ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
    }
    if (!$user || !password_verify($password, $user['password_hash'])) {
        return loginFailure($identity, $user);
    }
    if ($user['status'] !== 'Active') {
        auditLog('auth.login_failed', $user['username'], 'Account is inactive', $user);
        return 'This account is inactive. Contact the system administrator.';
    }
    if (!isGovEmail($user['email'])) {
        auditLog('auth.login_failed', $user['username'], 'No government email linked', $user);
        return 'This account is not linked to an @' . GOV_EMAIL_DOMAIN . ' government email, so a sign-in code cannot be sent. Ask the administrator to update it.';
    }
    startOtp($user);
    return null;
}

/** Record a failed attempt and lock the account after too many. */
function loginFailure(string $identity, ?array $user, string $reason = ''): string
{
    $key = loginIdentityKey($identity, $user);
    recordLoginAttempt($key, false);
    $count = (int) dbValue('SELECT COUNT(*) FROM login_attempts WHERE identity = ? AND success = 0 AND attempted_at > ?',
        [$key, date('Y-m-d H:i:s', time() - LOGIN_LOCK_SECONDS)]);
    $actor = ['name' => $user['name'] ?? 'Unknown user', 'username' => $user['username'] ?? $identity, 'role' => $user['role'] ?? '—'];
    auditLog('auth.login_failed', $user['username'] ?? $identity, ($reason !== '' ? $reason . ' · ' : '') . 'Attempt ' . min($count, LOGIN_MAX_ATTEMPTS) . ' of ' . LOGIN_MAX_ATTEMPTS, $actor);
    if (loginLockRemaining($key) > 0) {
        cancelOtp();
        auditLog('auth.login_locked', $user['username'] ?? $identity, 'Locked for ' . (LOGIN_LOCK_SECONDS / 60) . ' minutes', $actor);
        return 'Too many failed attempts. Sign-in is locked for ' . (LOGIN_LOCK_SECONDS / 60) . ' minutes.';
    }
    return 'The username or password is incorrect.';
}

/** Create a new one-time code for the user and send it to their government email. */
function startOtp(array $user): void
{
    $code = (string) random_int(100000, 999999);
    // Any earlier unused code for this user stops working.
    dbExec('UPDATE login_otps SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$user['id']]);
    dbExec('INSERT INTO login_otps (user_id, code_hash, sent_at, expires_at) VALUES (?, ?, NOW(), ?)',
        [$user['id'], password_hash($code, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + OTP_MINUTES * 60)]);
    $_SESSION['otp_pending'] = (int) db()->lastInsertId();
    $sent = deliverOtp($user, $code, OTP_MINUTES);
    auditLog('auth.otp_sent', $user['username'], ($sent ? 'Emailed to ' : 'Development mode, shown on screen for ') . maskEmail($user['email']), $user);
}

/** The sign-in waiting for its code, with its user, or null. */
function otpPending(): ?array
{
    $id = $_SESSION['otp_pending'] ?? null;
    if (!is_int($id)) {
        return null;
    }
    $row = dbOne('SELECT * FROM login_otps WHERE id = ? AND used_at IS NULL', [$id]);
    $user = $row ? storeFindUser((int) $row['user_id']) : null;
    if (!$row || !$user || $user['status'] !== 'Active') {
        cancelOtp();
        return null;
    }
    return ['id' => $id, 'hash' => $row['code_hash'], 'expires' => strtotime($row['expires_at']),
            'sent_at' => strtotime($row['sent_at']), 'attempts' => (int) $row['attempts'], 'user' => $user];
}

function cancelOtp(): void
{
    unset($_SESSION['otp_pending'], $_SESSION['dev_otp_code']);
}

/**
 * Step 2 of sign-in: check the code. Returns null when signed in, or an error.
 */
function verifyOtp(string $code): ?string
{
    $pending = otpPending();
    if (!$pending) {
        return 'Your sign-in has expired. Enter your username and password again.';
    }
    $user = $pending['user'];
    if ($pending['expires'] < time()) {
        dbExec('UPDATE login_otps SET used_at = NOW() WHERE id = ?', [$pending['id']]);
        cancelOtp();
        return 'The code has expired. Sign in again to receive a new one.';
    }
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $pending['hash'])) {
        $attempts = $pending['attempts'] + 1;
        dbExec('UPDATE login_otps SET attempts = ? WHERE id = ?', [$attempts, $pending['id']]);
        auditLog('auth.otp_failed', $user['username'], 'Attempt ' . $attempts . ' of ' . OTP_MAX_ATTEMPTS, $user);
        if ($attempts >= OTP_MAX_ATTEMPTS) {
            dbExec('UPDATE login_otps SET used_at = NOW() WHERE id = ?', [$pending['id']]);
            cancelOtp();
            // Counts as one failed sign-in, so repeated guessing also leads to the lockout.
            $message = loginFailure($user['username'], $user, 'Too many wrong sign-in codes');
            return str_starts_with($message, 'Too many') ? $message : 'Too many wrong codes. Sign in again to receive a new code.';
        }
        $left = OTP_MAX_ATTEMPTS - $attempts;
        return 'That code is not correct. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.';
    }

    dbExec('UPDATE login_otps SET used_at = NOW() WHERE id = ?', [$pending['id']]);
    cancelOtp();
    recordLoginAttempt(loginIdentityKey($user['username'], $user), true);
    session_regenerate_id(true);
    unset($_SESSION['signed_out_reason']);
    $_SESSION['user_id'] = $user['id'];
    storeTouchUser($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
    auditLog('auth.login', $user['username'], 'Signed in as ' . $user['role'] . ' (password and email code)', $user);
    return null;
}

/** Send a new code for the pending sign-in. Returns an error message or null. */
function resendOtp(): ?string
{
    $pending = otpPending();
    if (!$pending) {
        return 'Your sign-in has expired. Enter your username and password again.';
    }
    $wait = $pending['sent_at'] + OTP_RESEND_SECONDS - time();
    if ($wait > 0) {
        return 'Please wait ' . $wait . ' seconds before requesting another code.';
    }
    startOtp($pending['user']);
    return null;
}

/** Sign out. */
function logoutUser(): void
{
    $user = currentUser();
    if ($user) {
        auditLog('auth.logout', $user['username']);
    }
    unset($_SESSION['user_id'], $_SESSION['csrf_token']);
    session_regenerate_id(true);
}
