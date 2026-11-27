<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';
require_once __DIR__ . '/../includes/donor/password-reset.php';

$errors = [];
$sent = false;
$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $email = mb_strtolower(trim(post_string('email')));
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Refresh the page and try again.';
    } elseif (!valid_email($email) || mb_strlen($email) > 250) {
        $errors[] = 'Enter a valid email address.';
    } else {
        try {
            $stmt = db()->prepare("SELECT id FROM rc2000_admin.donor_users WHERE LOWER(BTRIM(email)) = ? AND status = 'active'");
            $stmt->execute([$email]);
            $donorId = (int) ($stmt->fetchColumn() ?: 0);
            if ($donorId > 0) { send_donor_password_reset(db(), $donorId); }
            $sent = true;
        } catch (Throwable $e) {
            error_log('Donor password reset request failed: ' . $e->getMessage());
            // Keep the same response regardless of address and email configuration.
            $sent = true;
        }
    }
}
$GLOBALS['pageTitle'] = 'Reset password';
require __DIR__ . '/../includes/header.php';
?>
<main class="donor-reset-page"><section class="form-card donor-reset-card">
  <p class="eyebrow">Donor account</p><h1>Forgot your password?</h1>
  <?php if ($sent): ?><p class="donor-reset-message" role="status">If an active donor account uses that email address and email delivery is available, you’ll receive a password reset link. Check your inbox.</p>
  <?php else: ?><p>Enter the email address on your account. We’ll send a one-time reset link if the account is active.</p>
    <?php if ($errors): ?><div class="alert alert-error" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><label>Email<input type="email" name="email" maxlength="250" autocomplete="email" required value="<?= e($email) ?>"></label><button class="button wide" type="submit">Send reset link</button></form>
  <?php endif; ?>
  <a class="donor-reset-back" href="<?= e(app_url('public/login.php')) ?>">← Back to sign in</a>
</section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
