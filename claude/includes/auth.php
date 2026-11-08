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

/**
 * Step 1 of sign-in: check the password. On success a one-time code is sent
 * to the account's @indang.gov.ph email and step 2 (verifyOtp) must follow.
 * Returns null when a code was sent, or an error message.
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

/** Count a failed attempt and lock sign-in after too many. */
function loginFailure(string $identity, ?array $user, string $reason = ''): string
{
    $_SESSION['login_failures'] = ($_SESSION['login_failures'] ?? 0) + 1;
    $actor = ['name' => $user['name'] ?? 'Unknown user', 'username' => $user['username'] ?? $identity, 'role' => $user['role'] ?? '—'];
    auditLog('auth.login_failed', $user['username'] ?? $identity, ($reason !== '' ? $reason . ' · ' : '') . 'Attempt ' . $_SESSION['login_failures'] . ' of ' . LOGIN_MAX_ATTEMPTS, $actor);
    if ($_SESSION['login_failures'] >= LOGIN_MAX_ATTEMPTS) {
        $_SESSION['login_locked_until'] = time() + LOGIN_LOCK_SECONDS;
        $_SESSION['login_failures'] = 0;
        unset($_SESSION['otp_pending'], $_SESSION['dev_otp_code']);
        auditLog('auth.login_locked', $user['username'] ?? $identity, 'Locked for ' . (LOGIN_LOCK_SECONDS / 60) . ' minutes', $actor);
        return 'Too many failed attempts. Sign-in is locked for ' . (LOGIN_LOCK_SECONDS / 60) . ' minutes.';
    }
    return 'The username or password is incorrect.';
}

/** Create a new one-time code for the user and send it to their government email. */
function startOtp(array $user): void
{
    $code = (string) random_int(100000, 999999);
    $_SESSION['otp_pending'] = [
        'user_id'  => $user['id'],
        'hash'     => password_hash($code, PASSWORD_DEFAULT),
        'expires'  => time() + OTP_MINUTES * 60,
        'sent_at'  => time(),
        'attempts' => 0,
    ];
    $sent = deliverOtp($user, $code, OTP_MINUTES);
    auditLog('auth.otp_sent', $user['username'], ($sent ? 'Emailed to ' : 'Development mode, shown on screen for ') . maskEmail($user['email']), $user);
}

/** The sign-in waiting for its code, with its user, or null. */
function otpPending(): ?array
{
    $pending = $_SESSION['otp_pending'] ?? null;
    if (!$pending) {
        return null;
    }
    $user = storeFindUser((int) $pending['user_id']);
    if (!$user || $user['status'] !== 'Active') {
        cancelOtp();
        return null;
    }
    return $pending + ['user' => $user];
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
        cancelOtp();
        return 'The code has expired. Sign in again to receive a new one.';
    }
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $pending['hash'])) {
        $_SESSION['otp_pending']['attempts']++;
        auditLog('auth.otp_failed', $user['username'], 'Attempt ' . $_SESSION['otp_pending']['attempts'] . ' of ' . OTP_MAX_ATTEMPTS, $user);
        if ($_SESSION['otp_pending']['attempts'] >= OTP_MAX_ATTEMPTS) {
            cancelOtp();
            // Counts as one failed sign-in, so repeated guessing also leads to the lockout.
            $message = loginFailure($user['username'], $user, 'Too many wrong sign-in codes');
            return str_starts_with($message, 'Too many') ? $message : 'Too many wrong codes. Sign in again to receive a new code.';
        }
        $left = OTP_MAX_ATTEMPTS - $_SESSION['otp_pending']['attempts'];
        return 'That code is not correct. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' left.';
    }

    cancelOtp();
    session_regenerate_id(true);
    unset($_SESSION['login_failures'], $_SESSION['login_locked_until'], $_SESSION['signed_out_reason']);
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
