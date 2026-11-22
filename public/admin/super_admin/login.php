<?php
// staff-console/login.php - Super Admin Portal login page
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';

$error = null;

try {
    if (superadmin_session_is_valid()) {
        redirect('staff-console/dashboard.php');
    }

    if (is_post()) {
        csrf_require();
        // Only accounts with the super_admin role can log in here
        $password = $_POST['password'] ?? '';
        $error = admin_login(post_str('email'), is_string($password) ? $password : '', 'super_admin');
        if ($error === null) {
            redirect('staff-console/dashboard.php');
        }
    }
} catch (Throwable $e) {
    error_log('Super admin login error: ' . $e->getMessage());
    $error = 'Something went wrong. Please try again.';
}

$emailValue = is_post() ? post_str('email') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Super Admin Portal | Reading Club 2000</title>
    <link rel="icon" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Public+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/superadmin.css')) ?>?v=3">
</head>
<body class="sa-login-body">
<div class="sa-login-page">
    <header class="sa-login-welcome">
        <div class="sa-login-brand">
            <img src="<?= e(asset('images/logo.png')) ?>" alt="" width="40" height="40">
            <span>Reading Club 2000</span>
        </div>
        <h1 class="sa-login-title">Super Admin Portal</h1>
        <p>Authorized personnel only.</p>
    </header>

    <main class="sa-login-panel" aria-label="Super Admin sign-in">
        <div class="sa-login-box">

            <?php if ($error): ?>
                <div class="flash flash-error" role="alert"><span><?= e($error) ?></span></div>
            <?php endif; ?>
            <?php render_flash_messages(); ?>

            <form method="post" action="<?= e(url('staff-console/login.php')) ?>" autocomplete="on" novalidate>
                <?= csrf_field() ?>

                <label class="sa-label" for="email">Administrator email</label>
                <input class="input sa-input" type="email" id="email" name="email" autocomplete="username" placeholder="name@gmail.com"
                       value="<?= e($emailValue) ?>" required autofocus>

                <label class="sa-label" for="password">Password</label>
                <div class="sa-password-field">
                    <input class="input sa-input" type="password" id="password" name="password"
                           autocomplete="current-password" placeholder="Enter your password" required>
                    <button type="button" class="btn btn-light" data-password-toggle aria-controls="password" hidden>Show</button>
                </div>

                <details class="sa-forgot">
                    <summary>Forgot password?</summary>
                    <p class="login-notice">Contact the system owner to reset your Super Admin password.</p>
                </details>

                <button type="submit" class="btn btn-maroon btn-block">Log in</button>
            </form>
            <a class="sa-login-home" href="<?= e(url('public/home.php')) ?>">← Go back to home</a>
        </div>
    </main>
</div>
<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
