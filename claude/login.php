<?php
/**
 * Sign-in page.
 *
 * DEV-ONLY: accounts come from the session-backed mock store. The test
 * accounts listed on this page exist only for the development version.
 */
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$notice = $_SESSION['signed_out_reason'] ?? '';
$identity = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $identity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($identity === '' || $password === '') {
        $error = 'Enter your username or email and your password.';
    } else {
        $error = attemptLogin($identity, $password) ?? '';
        if ($error === '') {
            header('Location: dashboard.php');
            exit;
        }
    }
    $notice = '';
}
$testAccounts = array_filter(storeUsers(), fn ($u) => $u['status'] === 'Active');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | AIP Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/components.css">
</head>
<body class="login-body">
    <main class="login-layout">
        <section class="login-visual">
            <div class="login-visual-content">
                <div class="brand-lockup"><div class="brand-mark large">AIP</div><span>AIP Manager</span></div>
                <div class="visual-copy">
                    <p class="eyebrow">Annual Investment Program</p>
                    <h1>Plan with clarity.<br><em>Deliver with purpose.</em></h1>
                    <p>A unified workspace for the Municipal Planning and Development Office of Indang, Cavite to plan, review and deliver the Annual Investment Program.</p>
                </div>
                <div class="visual-footer"><span><i class="status-dot"></i>Development version</span><span>FY <?= AIP_FISCAL_YEAR ?> AIP Cycle</span></div>
            </div>
            <div class="blueprint-shape shape-one"></div><div class="blueprint-shape shape-two"></div><div class="blueprint-shape shape-three"></div>
        </section>
        <section class="login-panel">
            <div class="login-card">
                <div class="mobile-brand"><div class="brand-mark">AIP</div><strong>AIP Manager</strong></div>
                <div class="login-heading"><p class="eyebrow">Authorized access</p><h2>Welcome back</h2><p>Sign in to continue to your planning workspace.</p></div>
                <?php if ($error): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
                <?php if ($notice): ?><div class="alert info" role="status"><?= e($notice) ?></div><?php endif; ?>
                <form method="post" class="login-form">
                    <?= csrfField() ?>
                    <label for="identity">Email or username</label>
                    <div class="input-wrap"><span>◉</span><input id="identity" name="identity" type="text" placeholder="Username" autocomplete="username" value="<?= e($identity) ?>" required></div>
                    <label for="password">Password</label>
                    <div class="input-wrap"><span>▣</span><input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="password-toggle" data-password-toggle aria-label="Show password">◉</button></div>
                    <div class="form-options"><span></span><a href="#" data-forgot>Password help?</a></div>
                    <button class="primary-button login-button" type="submit">Sign in to dashboard <span>→</span></button>
                </form>
                <div class="support-note"><span>ⓘ</span> Need assistance? Contact the MPDO system administrator.</div>
                <details class="dev-accounts">
                    <summary>Development test accounts</summary>
                    <p>Password for the sample accounts: <strong><?= e(MOCK_DEV_PASSWORD) ?></strong>. Click an account to fill in the form.</p>
                    <ul>
                        <?php foreach ($testAccounts as $account): ?>
                            <li><button type="button" data-fill-login="<?= e($account['username']) ?>"><strong><?= e($account['username']) ?></strong><span><?= e($account['role']) ?> · <?= e($account['office']) ?></span></button></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            </div>
            <p class="login-legal">Authorized personnel only. Sign-ins and account changes are recorded in the activity log.</p>
        </section>
    </main>
    <script src="assets/js/script.js"></script>
</body>
</html>
