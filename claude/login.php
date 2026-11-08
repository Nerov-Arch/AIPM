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
    $step = (string) ($_POST['step'] ?? 'password');
    if ($step === 'otp') {
        $error = verifyOtp((string) ($_POST['code'] ?? '')) ?? '';
        if ($error === '') {
            header('Location: dashboard.php');
            exit;
        }
    } elseif ($step === 'resend') {
        $error = resendOtp() ?? '';
        if ($error === '') {
            $notice = 'A new code was sent.';
        }
    } elseif ($step === 'cancel') {
        cancelOtp();
    } else {
        cancelOtp();
        $identity = trim((string) ($_POST['identity'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($identity === '' || $password === '') {
            $error = 'Enter your username or email and your password.';
        } else {
            $error = attemptLogin($identity, $password) ?? '';
        }
    }
    if ($step !== 'resend') {
        $notice = '';
    }
}
$pending = otpPending();
$devCode = $pending ? ($_SESSION['dev_otp_code'] ?? '') : '';
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
                <?php if ($pending): ?>
                    <form method="post" class="login-form" data-otp-form>
                        <?= csrfField() ?><input type="hidden" name="step" value="otp">
                        <p class="otp-intro">We sent a 6-digit sign-in code to your government email <strong><?= e(maskEmail($pending['user']['email'])) ?></strong>. It expires in <?= OTP_MINUTES ?> minutes.</p>
                        <?php if ($devCode !== ''): ?>
                            <div class="alert info dev-otp" role="status">Development mode: no email server is configured, so the code is shown here instead of being emailed: <strong data-dev-otp><?= e($devCode) ?></strong></div>
                        <?php endif; ?>
                        <label for="code">Sign-in code</label>
                        <div class="input-wrap"><span>✉</span><input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="6-digit code" autocomplete="one-time-code" autofocus required></div>
                        <button class="primary-button login-button" type="submit">Verify and sign in <span>→</span></button>
                    </form>
                    <div class="otp-actions">
                        <form method="post"><?= csrfField() ?><input type="hidden" name="step" value="resend"><button class="text-button" type="submit">Send a new code</button></form>
                        <form method="post"><?= csrfField() ?><input type="hidden" name="step" value="cancel"><button class="text-button" type="submit">Use a different account</button></form>
                    </div>
                <?php else: ?>
                <form method="post" class="login-form">
                    <?= csrfField() ?><input type="hidden" name="step" value="password">
                    <label for="identity">Government email or username</label>
                    <div class="input-wrap"><span>◉</span><input id="identity" name="identity" type="text" placeholder="name@<?= e(GOV_EMAIL_DOMAIN) ?> or username" autocomplete="username" value="<?= e($identity) ?>" required></div>
                    <label for="password">Password</label>
                    <div class="input-wrap"><span>▣</span><input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="password-toggle" data-password-toggle aria-label="Show password">◉</button></div>
                    <div class="form-options"><span></span><a href="#" data-forgot>Password help?</a></div>
                    <button class="primary-button login-button" type="submit">Continue <span>→</span></button>
                    <p class="form-note otp-note">After your password, a one-time code is sent to your @<?= e(GOV_EMAIL_DOMAIN) ?> email.</p>
                </form>
                <?php endif; ?>
                <div class="support-note"><span>ⓘ</span> Need assistance? Contact the MPDO system administrator.</div>
                <?php if (!$pending): ?><details class="dev-accounts">
                    <summary>Development test accounts</summary>
                    <p>Password for the sample accounts: <strong><?= e(MOCK_DEV_PASSWORD) ?></strong>. Click an account to fill in the form. The sign-in code is shown on the next screen because email delivery is off in this version.</p>
                    <ul>
                        <?php foreach ($testAccounts as $account): ?>
                            <li><button type="button" data-fill-login="<?= e($account['username']) ?>"><strong><?= e($account['username']) ?></strong><span><?= e($account['role']) ?> · <?= e($account['office']) ?> · <?= e($account['email']) ?></span></button></li>
                        <?php endforeach; ?>
                    </ul>
                </details><?php endif; ?>
            </div>
            <p class="login-legal">Authorized personnel only. Sign-ins and account changes are recorded in the activity log.</p>
        </section>
    </main>
    <script src="assets/js/script.js"></script>
</body>
</html>
