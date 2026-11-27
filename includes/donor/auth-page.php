<?php
declare(strict_types=1);

$isRegisterMode = $authMode === 'register';
$loginUrl = app_url('public/login.php');
$registerUrl = app_url('public/register.php');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $isRegisterMode ? 'Create an account' : 'Sign in' ?> | Reading Club 2000</title>
  <link rel="stylesheet" href="<?= e(app_url('public/assets/css/style.css?v=70')) ?>">
  <link rel="stylesheet" href="<?= e(app_url('public/assets/css/pages/donor-auth.css?v=2')) ?>">
</head>
<body class="donor-auth-page">
  <header class="donor-brandbar">
    <a href="<?= e(app_url('public/home.php')) ?>" class="donor-brand">
      <img src="<?= e(app_url('public/assets/images/icons/logo.png')) ?>" alt="" width="42" height="42">
      <span>Reading Club 2000</span>
    </a>
  </header>

  <main class="donor-auth-layout<?= $isRegisterMode ? ' is-register-mode' : '' ?>"
        data-auth-layout data-mode="<?= $isRegisterMode ? 'register' : 'login' ?>"
        data-login-url="<?= e($loginUrl) ?>" data-register-url="<?= e($registerUrl) ?>">
    <section class="donor-auth-form-panel donor-auth-login-panel" data-auth-panel="login"
             aria-labelledby="login-title"<?= $isRegisterMode ? ' aria-hidden="true" inert' : '' ?>>
      <p class="eyebrow">Welcome back</p>
      <h1 id="login-title">Sign in</h1>
      <p class="donor-auth-intro">Sign in to continue to your donor account.</p>
      <?php if (!$isRegisterMode && $authNotice): ?>
        <div class="donor-auth-message donor-auth-notice" role="status"><?= e($authNotice) ?></div>
      <?php endif; ?>
      <?php if (!$isRegisterMode && $authError): ?>
        <div class="donor-auth-message donor-auth-error" role="alert"><?= e($authError) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= e($loginUrl) ?>" class="donor-auth-form" autocomplete="on">
        <?= csrf_field() ?>
        <label for="login-email">Email</label>
        <input id="login-email" name="email" type="email" maxlength="250" autocomplete="email"
               value="<?= e(!$isRegisterMode ? $authEmail : '') ?>" required<?= !$isRegisterMode ? ' autofocus' : '' ?>>
        <label for="login-password">Password</label>
        <div class="donor-password-wrap">
          <input id="login-password" name="password" type="password" autocomplete="current-password" required>
          <button class="donor-password-toggle" type="button" data-password-toggle="login-password" aria-pressed="false">Show</button>
        </div>
        <button class="donor-auth-submit" type="submit">Sign in</button>
      </form>
      <p class="donor-auth-help">Forgot your password? <a href="<?= e(app_url('public/forgot-password.php')) ?>">Reset your password</a>.</p>
      <p class="donor-auth-switch">New to Reading Club 2000? <a href="<?= e($registerUrl) ?>" data-auth-mode="register">Create an account</a></p>
      <a class="donor-auth-home" href="<?= e(app_url('public/home.php')) ?>">← Back to Reading Club 2000</a>
    </section>

    <section class="donor-auth-form-panel donor-auth-register-panel" data-auth-panel="register"
             aria-labelledby="register-title"<?= $isRegisterMode ? '' : ' aria-hidden="true" inert' ?>>
      <p class="eyebrow">Join our community</p>
      <h1 id="register-title">Create your account</h1>
      <p class="donor-auth-intro">Create an account to manage your donations and inquiries.</p>
      <?php if ($isRegisterMode && $authError): ?>
        <div class="donor-auth-message donor-auth-error" role="alert"><?= e($authError) ?></div>
      <?php endif; ?>
      <form method="post" action="<?= e($registerUrl) ?>" class="donor-auth-form" autocomplete="on">
        <?= csrf_field() ?>
        <label for="register-name">Full name</label>
        <input id="register-name" name="name" type="text" maxlength="200" autocomplete="name"
               value="<?= e($authName) ?>" required<?= $isRegisterMode ? ' autofocus' : '' ?>>
        <label for="register-email">Email</label>
        <input id="register-email" name="email" type="email" maxlength="250" autocomplete="email"
               value="<?= e($isRegisterMode ? $authEmail : '') ?>" required>
        <label for="register-password">Password</label>
        <div class="donor-password-wrap">
          <input id="register-password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
          <button class="donor-password-toggle" type="button" data-password-toggle="register-password" aria-pressed="false">Show</button>
        </div>
        <small class="donor-password-hint">Use at least 12 characters.</small>
        <label for="register-password-confirmation">Confirm password</label>
        <div class="donor-password-wrap">
          <input id="register-password-confirmation" name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
          <button class="donor-password-toggle" type="button" data-password-toggle="register-password-confirmation" aria-pressed="false">Show</button>
        </div>
        <button class="donor-auth-submit" type="submit">Create account</button>
      </form>
      <p class="donor-auth-switch">Already have an account? <a href="<?= e($loginUrl) ?>" data-auth-mode="login">Sign in</a></p>
      <a class="donor-auth-home" href="<?= e(app_url('public/home.php')) ?>">← Back to Reading Club 2000</a>
    </section>

    <aside class="donor-auth-story" aria-label="Reading Club 2000 community library">
      <div class="donor-auth-story-copy donor-story-login-copy">
        <span class="donor-auth-kicker">A neighborhood library built around sharing stories</span>
        <h2>Every story starts with a community.</h2>
        <p>New to Reading Club 2000?</p>
        <a class="donor-story-button" href="<?= e($registerUrl) ?>" data-auth-mode="register">Register</a>
      </div>
      <div class="donor-auth-story-copy donor-story-register-copy">
        <span class="donor-auth-kicker">Welcome to the Reading Club 2000 community</span>
        <h2>Good to have you with us.</h2>
        <p>Already have an account?</p>
        <a class="donor-story-button" href="<?= e($loginUrl) ?>" data-auth-mode="login">Sign in</a>
      </div>
    </aside>
  </main>
  <script src="<?= e(app_url('public/assets/js/donor-auth.js?v=2')) ?>" defer></script>
</body>
</html>
