<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';

$donor = current_donor_user();
if (!$donor) {
    $_SESSION['donor_return_to'] = '/public/profile.php';
    flash_set('donor_auth_notice', 'Sign in to view your profile.');
    redirect('/public/login.php');
}

$actionError = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $actionError = 'Your session expired. Refresh the page and try again.';
    } elseif (post_str('action') !== 'data_request') {
        $actionError = 'Choose a valid account request.';
    } else {
        $requestType = post_str('request_type');
        $requestedName = trim(post_str('requested_name'));
        $requestedEmail = mb_strtolower(trim(post_str('requested_email')));
        $details = trim(post_str('details'));
        if (!in_array($requestType, ['correction', 'removal'], true)) {
            $actionError = 'Choose a valid account data request.';
        } elseif (mb_strlen($details) > 2000) {
            $actionError = 'Your request details must be 2,000 characters or fewer.';
        } elseif ($requestType === 'correction' && (!required_text($requestedName, 200) || !valid_email($requestedEmail) || mb_strlen($requestedEmail) > 250)) {
            $actionError = 'Enter the corrected name and a valid email address.';
        } else {
            try {
                $pdo = db();
                $pending = $pdo->prepare("SELECT 1 FROM rc2000_admin.donor_data_requests WHERE donor_user_id = ? AND status = 'pending' LIMIT 1");
                $pending->execute([(int) $donor['id']]);
                if ($pending->fetchColumn()) {
                    $actionError = 'You already have a pending account data request.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO rc2000_admin.donor_data_requests
                        (donor_user_id, donor_name_snapshot, donor_email_snapshot, request_type, requested_name, requested_email, details)
                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        (int) $donor['id'], $donor['name'], $donor['email'], $requestType,
                        $requestType === 'correction' ? $requestedName : null,
                        $requestType === 'correction' ? $requestedEmail : null,
                        $details === '' ? null : $details,
                    ]);
                    flash_set('donor_data_request_notice', 'Your account data request was sent to the admin team.');
                    redirect('/public/profile.php#requests');
                }
            } catch (Throwable $e) {
                error_log('Donor data request failed: ' . $e->getMessage());
                $actionError = 'We could not submit your request right now. Please try again.';
            }
        }
    }
}

$notice = flash_get('donor_data_request_notice');
$profile = $donor;
$dataRequests = [];
$pageError = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT name, email, date_of_birth, email_verified_at, created_at, last_login_at FROM rc2000_admin.donor_users WHERE id = ?');
    $stmt->execute([(int) $donor['id']]);
    $profile = $stmt->fetch() ?: $donor;
    $stmt = $pdo->prepare('SELECT id, request_type, status, requested_name, requested_email, details, admin_note, created_at FROM rc2000_admin.donor_data_requests WHERE donor_user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10');
    $stmt->execute([(int) $donor['id']]);
    $dataRequests = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Donor profile page failed: ' . $e->getMessage());
    $pageError = 'Some profile information could not be loaded right now.';
}

$GLOBALS['pageTitle'] = 'My profile';
$GLOBALS['pageClass'] = 'donor-dashboard';
require __DIR__ . '/../includes/header.php';
$donorActive = 'profile';
?>
<main class="donor-dashboard-shell">
  <?php require __DIR__ . '/../includes/donor/sidebar.php'; ?>
  <div class="donor-dashboard-content">
    <header class="donor-page-heading"><div><p class="donor-eyebrow">Your donor account</p><h1>Profile</h1><p>Review your account details and request a correction or removal.</p></div></header>
    <?php if ($notice): ?><div class="donor-dashboard-alert is-success" role="status"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($actionError): ?><div class="donor-dashboard-alert" role="alert"><?= e($actionError) ?></div><?php endif; ?>
    <?php if ($pageError): ?><div class="donor-dashboard-alert" role="alert"><?= e($pageError) ?></div><?php endif; ?>
    <div class="donor-profile-page-grid">
      <section class="donor-dashboard-card donor-profile-page-card">
        <div class="donor-profile-heading"><span class="donor-profile-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($profile['name'], 0, 1))) ?></span><div><h2><?= e($profile['name']) ?></h2><p><?= e($profile['email']) ?></p></div></div>
        <dl class="donor-profile-details">
          <div><dt>Account status</dt><dd>Active</dd></div>
          <div><dt>Email status</dt><dd><?= !empty($profile['email_verified_at']) ? 'Verified' : 'Not verified' ?></dd></div>
          <div><dt>Date of birth</dt><dd><?= !empty($profile['date_of_birth']) ? e(date('M j, Y', strtotime($profile['date_of_birth']))) : 'Not on file' ?></dd></div>
          <div><dt>Joined</dt><dd><?= !empty($profile['created_at']) ? e(donor_format_date_short($profile['created_at'])) : '—' ?></dd></div>
          <div><dt>Last sign in</dt><dd><?= !empty($profile['last_login_at']) ? e(donor_format_date_short($profile['last_login_at'])) : 'No sign in recorded' ?></dd></div>
        </dl>
        <p class="donor-profile-note">To change your name or email, or remove your account, submit a request below. The admin team will review it.</p>
      </section>

      <section class="donor-dashboard-card donor-profile-page-card" id="requests">
        <header class="donor-section-heading"><div><span aria-hidden="true">✉</span><h2>Account data request</h2></div></header>
        <form method="post" class="donor-profile-request-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="data_request">
          <label>Request type<select name="request_type" required><option value="correction">Correct my name or email</option><option value="removal">Remove my account</option></select></label>
          <label>Corrected name<input name="requested_name" maxlength="200" value="<?= e($profile['name']) ?>"></label>
          <label>Corrected email<input name="requested_email" type="email" maxlength="250" value="<?= e($profile['email']) ?>"></label>
          <label>Details (optional)<textarea name="details" rows="4" maxlength="2000" placeholder="Add any context for the admin team"></textarea></label>
          <button class="donor-primary-link" type="submit">Submit request</button>
        </form>
        <p class="donor-profile-note">You can have one pending request at a time. Removal requests are reviewed by an administrator.</p>
      </section>
    </div>

    <section class="donor-dashboard-card donor-profile-requests-history">
      <header class="donor-section-heading"><div><span aria-hidden="true">◷</span><h2>Request history</h2></div></header>
      <?php if (!$dataRequests): ?><p class="donor-inquiry-note">You haven’t submitted any account data requests.</p>
      <?php else: ?><div class="donor-table-wrap"><table class="donor-history-table"><thead><tr><th>Request</th><th>Submitted</th><th>Status</th><th>Details</th><th>Admin note</th></tr></thead><tbody>
        <?php foreach ($dataRequests as $request): ?><tr><td><?= e(ucfirst($request['request_type'])) ?></td><td><?= e(donor_format_date_short($request['created_at'])) ?></td><td><?= e(ucfirst($request['status'])) ?></td><td><?= e($request['details'] ?: '—') ?></td><td><?= e($request['admin_note'] ?: '—') ?></td></tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
