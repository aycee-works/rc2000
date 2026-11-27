<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';
require_once __DIR__ . '/../includes/donor/password-reset.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$errors = [];
$complete = false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirmation'] ?? '');
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) { $errors[] = 'Your session expired. Refresh the page and try again.'; }
    elseif ($password !== $confirm) { $errors[] = 'The passwords do not match.'; }
    else {
        try {
            $complete = consume_donor_password_reset(db(), $token, $password);
            if (!$complete) { $errors[] = 'This reset link is invalid, expired, or already used.'; }
        } catch (InvalidArgumentException $e) { $errors[] = $e->getMessage(); }
        catch (Throwable $e) { error_log('Donor password reset failed: ' . $e->getMessage()); $errors[] = 'We could not reset your password right now. Please try again later.'; }
    }
}
$validToken = false;
if (!$complete && preg_match('/^[a-f0-9]{64}$/i', $token)) {
    try {
        $stmt = db()->prepare("SELECT 1 FROM rc2000_admin.donor_auth_tokens
            WHERE token_hash = ? AND purpose = 'reset_password' AND used_at IS NULL AND expires_at > NOW()");
        $stmt->execute([hash('sha256', $token)]);
        $validToken = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) { error_log('Donor reset link check failed: ' . $e->getMessage()); }
}
$GLOBALS['pageTitle'] = 'Choose a new password';
require __DIR__ . '/../includes/header.php';
?>
<main class="donor-reset-page"><section class="form-card donor-reset-card">
  <p class="eyebrow">Donor account</p><h1>Choose a new password</h1>
  <?php if ($complete): ?><p class="donor-reset-message" role="status">Your password has been reset. You can sign in now.</p><a class="button wide" href="<?= e(app_url('public/login.php')) ?>">Sign in</a>
  <?php elseif ($validToken): ?><?php if ($errors): ?><div class="alert alert-error" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?><form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><label>New password<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><label>Confirm new password<input type="password" name="password_confirmation" minlength="12" maxlength="72" autocomplete="new-password" required></label><button class="button wide" type="submit">Reset password</button></form>
  <?php else: ?><p class="donor-reset-message">This reset link is invalid, expired, or already used. Request a new one to continue.</p><a class="button wide" href="<?= e(app_url('public/forgot-password.php')) ?>">Request a new link</a><?php endif; ?>
</section></main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
