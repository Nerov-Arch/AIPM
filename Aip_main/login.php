<?php
session_start();

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim($_POST['identity'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identity === 'admin' && $password === 'admin123') {
        $_SESSION['user'] = [
            'name' => 'AIP Administrator',
            'identity' => $identity,
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
                    <p>A unified workspace for planning, reviewing, and delivering programs that move communities forward.</p>
                </div>
                <div class="visual-footer"><span><i class="status-dot"></i>System operational</span><span>2025 Program Cycle</span></div>
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
                    <div class="input-wrap"><span>◉</span><input id="identity" name="identity" type="text" placeholder="username@government.gov" autocomplete="username" required></div>
                    <label for="password">Password</label>
                    <div class="input-wrap"><span>▣</span><input id="password" name="password" type="password" placeholder="Enter your password" autocomplete="current-password" required><button type="button" class="password-toggle" data-password-toggle aria-label="Show password">◉</button></div>
                    <div class="form-options"><label class="check-label"><input type="checkbox" name="remember"> <span>Remember this device</span></label><a href="#" data-forgot>Password help?</a></div>
                    <button class="primary-button login-button" type="submit">Sign in to dashboard <span>→</span></button>
                </form>
                <div class="support-note"><span>ⓘ</span> Need assistance? <a href="mailto:support@example.gov">Contact IT support</a></div>
                <p class="demo-note">Demo access: <strong>admin</strong> / <strong>admin123</strong></p>
            </div>
            <p class="login-legal">Authorized personnel only. Activity may be monitored for security and compliance.</p>
        </section>
    </main>
    <script src="assets/js/script.js"></script>
</body>
</html>
