<?php
/**
 * Session and login helpers.
 *
 * DEV-ONLY: this is a temporary development login (see login.php).
 * It is not secure production authentication.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

/**
 * Redirect to the login page when no user is signed in.
 *
 * @param string $rootPrefix Path from the current page back to the app root,
 *                           e.g. '' for root pages and '../' for pages/*.php.
 */
function requireLogin(string $rootPrefix = ''): void
{
    if (!isset($_SESSION['user'])) {
        header('Location: ' . $rootPrefix . 'login.php');
        exit;
    }
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}
