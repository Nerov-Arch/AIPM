<?php
/**
 * DEV-ONLY login. The single hardcoded account below exists only so the
 * interface can be tested before the database is connected. It is NOT
 * secure production authentication and must be replaced in the database
 * phase with hashed passwords stored in MySQL.
 */
require_once __DIR__ . '/includes/auth.php';

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identity === 'admin' && $password === 'admin123') { // DEV-ONLY
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'name' => 'AIP Administrator',
            'identity' => $identity,
            'role' => 'Administrator',
        ];
        header('Location: dashboard.php');
        exit;
    }

    $error = 'The credentials do not match our records.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In | AIP Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
                <div class="visual-footer"><span><i class="status-dot"></i>Development version</span><span>FY 2027 AIP Cycle</span></div>
            </div>
            <div class="blueprint-shape shape-one"></div><div class="blueprint-shape shape-two"></div><div class="blueprint-shape shape-three"></div>
        </section>
        <section class="login-panel">
            <div class="login-card">
                <div class="mobile-brand"><div class="brand-mark">AIP</div><strong>AIP Manager</strong></div>
                <div class="login-heading"><p class="eyebrow">Authorized access</p><h2>Welcome back</h2><p>Sign in to continue to your planning workspace.</p></div>
                <?php if ($error): ?><div class="alert error" role="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="post" class="login-form">
                    <label for="identity">Email or username</label>
                    <div class="input-wrap"><span>◉</span><input id="identity" name="identity" type="text" placeholder="Username" autocomplete="username" required></div>
                    <label for="password">Password</label>
                    <div class="input-wrap"><span>▣</span><input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="password-toggle" data-password-toggle aria-label="Show password">◉</button></div>
                    <div class="form-options"><span></span><a href="#" data-forgot>Password help?</a></div>
                    <button class="primary-button login-button" type="submit">Sign in to dashboard <span>→</span></button>
                </form>
                <div class="support-note"><span>ⓘ</span> Need assistance? Contact the MPDO system administrator.</div>
                <p class="demo-note">Development login: <strong>admin</strong> / <strong>admin123</strong> (sample data only)</p>
            </div>
            <p class="login-legal">Authorized personnel only. Activity may be monitored for security and compliance.</p>
        </section>
    </main>
    <script src="assets/js/script.js"></script>
</body>
</html>
