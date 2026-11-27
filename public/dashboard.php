<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';
require_once __DIR__ . '/../includes/donor/inquiries.php';

$donor = current_donor_user();
if (!$donor) {
    $_SESSION['donor_return_to'] = '/public/dashboard.php';
    flash_set('donor_auth_notice', 'Sign in to view your donor dashboard.');
    redirect('/public/login.php');
}

$actionNotice = null;
$actionError = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $actionError = 'Your session expired. Refresh the page and try again.';
    } elseif (post_str('action') === 'data_request') {
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
                    redirect('/public/dashboard.php#my-profile');
                }
            } catch (Throwable $e) {
                error_log('Donor data request failed: ' . $e->getMessage());
                $actionError = 'We could not submit your request right now. Please try again.';
            }
        }
    }
}
$actionNotice = flash_get('donor_data_request_notice');

$stats = ['verified_total' => 0, 'report_count' => 0, 'campaign_count' => 0, 'open_inquiries' => 0];
$donations = [];
$campaigns = [];
$myInquiries = [];
$dataRequests = [];
$dashboardError = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT
            COALESCE(SUM(verified_amount) FILTER (WHERE status = 'verified'), 0) AS verified_total,
            COUNT(*) AS report_count,
            COUNT(DISTINCT campaign_id) FILTER (WHERE campaign_id IS NOT NULL) AS campaign_count
        FROM rc2000_admin.monetary_donations
        WHERE donor_user_id = ?");
    $stmt->execute([(int) $donor['id']]);
    $row = $stmt->fetch() ?: [];
    $stats = [
        'verified_total' => (float) ($row['verified_total'] ?? 0),
        'report_count' => (int) ($row['report_count'] ?? 0),
        'campaign_count' => (int) ($row['campaign_count'] ?? 0),
        'open_inquiries' => 0,
    ];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rc2000_admin.inquiries WHERE donor_user_id = ? AND status IN ('open', 'in_progress')");
    $stmt->execute([(int) $donor['id']]);
    $stats['open_inquiries'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT d.id, d.reference_no, d.reported_amount, d.verified_amount,
            d.status, d.created_at, d.campaign_id, c.title AS campaign_title
        FROM rc2000_admin.monetary_donations d
        LEFT JOIN rc2000_admin.campaigns c ON c.id = d.campaign_id
        WHERE d.donor_user_id = ?
        ORDER BY d.created_at DESC, d.id DESC LIMIT 6");
    $stmt->execute([(int) $donor['id']]);
    $donations = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT c.id, c.title, c.description, c.goal_amount, c.image_path,
            mine.report_count AS my_reports, COALESCE(raised.raised, 0) AS raised,
            mine.last_reported_at
        FROM rc2000_admin.campaigns c
        JOIN (
            SELECT campaign_id, COUNT(*) AS report_count, MAX(created_at) AS last_reported_at
            FROM rc2000_admin.monetary_donations
            WHERE donor_user_id = ? AND campaign_id IS NOT NULL
            GROUP BY campaign_id
        ) mine ON mine.campaign_id = c.id
        LEFT JOIN (
            SELECT campaign_id, SUM(verified_amount) FILTER (WHERE status = 'verified') AS raised
            FROM rc2000_admin.monetary_donations
            WHERE campaign_id IS NOT NULL
            GROUP BY campaign_id
        ) raised ON raised.campaign_id = c.id
        ORDER BY mine.last_reported_at DESC, c.id DESC LIMIT 3");
    $stmt->execute([(int) $donor['id']]);
    $campaigns = $stmt->fetchAll();
    $myInquiries = list_my_inquiries($pdo, (int) $donor['id']);
    $dataStmt = $pdo->prepare('SELECT id, request_type, status, created_at FROM rc2000_admin.donor_data_requests WHERE donor_user_id = ? ORDER BY created_at DESC, id DESC LIMIT 5');
    $dataStmt->execute([(int) $donor['id']]);
    $dataRequests = $dataStmt->fetchAll();
} catch (Throwable $e) {
    error_log('Donor dashboard load failed: ' . $e->getMessage());
    $dashboardError = 'Some dashboard information could not be loaded. Please refresh in a moment.';
}

$GLOBALS['pageTitle'] = 'My donor dashboard';
$GLOBALS['pageClass'] = 'donor-dashboard';
require __DIR__ . '/../includes/header.php';
?>
<main class="donor-dashboard-shell">
  <aside class="donor-dashboard-sidebar" aria-label="Donor account navigation">
    <a class="donor-dashboard-nav is-active" href="<?= e(app_url('public/dashboard.php')) ?>" aria-current="page"><span aria-hidden="true">⌂</span> My Overview</a>
    <a class="donor-dashboard-nav" href="#my-donations"><span aria-hidden="true">♡</span> My Donations</a>
    <a class="donor-dashboard-nav" href="#my-campaigns"><span aria-hidden="true">▱</span> My Campaigns</a>
    <a class="donor-dashboard-nav" href="<?= e(app_url('public/my-inquiries.php')) ?>"><span aria-hidden="true">☏</span> My Inquiries</a>
    <a class="donor-dashboard-nav" href="#my-profile"><span aria-hidden="true">♙</span> Profile</a>
    <div class="donor-sidebar-note">Thank you for supporting your community.</div>
  </aside>

  <div class="donor-dashboard-content">
    <section class="donor-welcome">
      <div><p class="donor-eyebrow">Your donor account</p><h1>Welcome back, <?= e($donor['name']) ?></h1><p>Thank you for supporting your community.</p></div>
      <div class="donor-welcome-art" aria-hidden="true"><span>📚</span><span>♡</span></div>
    </section>

    <?php if ($dashboardError): ?><div class="donor-dashboard-alert" role="status"><?= e($dashboardError) ?></div><?php endif; ?>

    <section class="donor-dashboard-stats" aria-label="Your donation summary">
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon" aria-hidden="true">♡</span><div><p>Total donated</p><strong><?= e(format_peso($stats['verified_total'])) ?></strong><small>Verified donations</small></div></article>
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon gold" aria-hidden="true">▤</span><div><p>Donation reports</p><strong><?= number_format($stats['report_count']) ?></strong><small>General and campaign reports</small></div></article>
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon gold" aria-hidden="true">☏</span><div><p>Open inquiries</p><strong><?= number_format($stats['open_inquiries']) ?></strong><small>Open or in progress</small></div></article>
    </section>

    <div class="donor-dashboard-columns">
      <section class="donor-dashboard-card donor-section-card" id="my-donations">
        <header class="donor-section-heading"><div><span aria-hidden="true">♡</span><h2>Recent donations</h2></div><a href="<?= e(app_url('public/donate.php?tab=status')) ?>">Track a report</a></header>
        <?php if (!$donations): ?>
          <div class="donor-empty-state"><p>You haven’t submitted a donation report yet.</p><a class="donor-primary-link" href="<?= e(app_url('public/donate.php')) ?>">Make a donation</a></div>
        <?php else: ?>
          <div class="donor-table-wrap"><table class="donor-history-table"><thead><tr><th>Date</th><th>Donation</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($donations as $item):
              $verified = $item['status'] === 'verified' && $item['verified_amount'] !== null;
              $label = $item['status'] === 'verified' ? 'Verified' : ($item['status'] === 'not_verified' ? 'Not verified' : 'Pending');
              $statusClass = $item['status'] === 'verified' ? 'is-good' : ($item['status'] === 'pending' ? 'is-pending' : 'is-bad');
            ?>
              <tr><td><?= e(format_date_short($item['created_at'])) ?></td><td><?= e($item['campaign_title'] ?: 'General donation') ?><small><?= e($item['reference_no']) ?></small></td><td><span class="donor-type-pill <?= $item['campaign_id'] ? 'is-campaign' : 'is-general' ?>"><?= $item['campaign_id'] ? 'Campaign' : 'General' ?></span></td><td><?= e(format_peso($verified ? $item['verified_amount'] : $item['reported_amount'])) ?></td><td><span class="donor-status-pill <?= e($statusClass) ?>"><?= e($label) ?></span></td></tr>
            <?php endforeach; ?>
          </tbody></table></div>
          <a class="donor-mobile-donations-link" href="<?= e(app_url('public/donate.php?tab=status')) ?>">Check report status</a>
        <?php endif; ?>
      </section>

      <div class="donor-dashboard-sidecards">
        <section class="donor-dashboard-card donor-section-card" id="my-campaigns">
          <header class="donor-section-heading"><div><span aria-hidden="true">⚑</span><h2>Campaigns you support</h2></div><a href="<?= e(app_url('public/donate.php?tab=campaign')) ?>">Support a campaign</a></header>
          <?php if (!$campaigns): ?><div class="donor-empty-state"><p>Your campaign donation reports will appear here.</p><a class="donor-primary-link" href="<?= e(app_url('public/donate.php?tab=campaign')) ?>">Explore campaigns</a></div>
          <?php else: ?><div class="donor-campaign-list">
            <?php foreach ($campaigns as $campaign): $goal=(float)$campaign['goal_amount']; $raised=(float)$campaign['raised']; $percent=$goal > 0 ? min(100, (int) round($raised / $goal * 100)) : 0; ?>
              <article class="donor-campaign-item">
                <?php if (!empty($campaign['image_path'])): ?><img src="<?= e(media_url($campaign['image_path'])) ?>" alt="" loading="lazy"><?php else: ?><span class="donor-campaign-placeholder" aria-hidden="true">📖</span><?php endif; ?>
                <div class="donor-campaign-info"><h3><?= e($campaign['title']) ?></h3><p><?= e(mb_strimwidth(strip_tags((string)$campaign['description']), 0, 105, '…')) ?></p><div class="donor-progress"><span style="width:<?= $percent ?>%"></span></div><small><?= e(format_peso($raised)) ?> raised of <?= e(format_peso($goal)) ?> · <?= $percent ?>%</small></div>
              </article>
            <?php endforeach; ?>
          </div><?php endif; ?>
        </section>

        <section class="donor-dashboard-card donor-section-card" id="my-inquiries">
          <header class="donor-section-heading"><div><span aria-hidden="true">☏</span><h2>Latest inquiry</h2></div><a href="<?= e(app_url('public/my-inquiries.php')) ?>">View inbox</a></header>
          <?php if (!$myInquiries): ?><p class="donor-inquiry-note">Your account conversations with the team will appear here.</p>
          <?php else: $latestInquiry = $myInquiries[0]; ?><div class="donor-latest-inquiry"><strong><?= e($latestInquiry['subject']) ?></strong><small><?= e($latestInquiry['reference_no']) ?> · <?= e(ucwords(str_replace('_', ' ', $latestInquiry['status']))) ?></small><p><?= e(format_date_short($latestInquiry['last_activity'] ?: $latestInquiry['created_at'])) ?> · <?= (int) $stats['open_inquiries'] > 0 ? 'We’ll reply here when there is an update.' : 'Open the conversation to see the latest messages.' ?></p></div><?php endif; ?>
          <a class="donor-primary-link donor-full-link" href="<?= e(app_url('public/my-inquiries.php')) ?>">View conversations <span aria-hidden="true">→</span></a>
        </section>

        <section class="donor-dashboard-card donor-profile-card" id="my-profile">
          <div class="donor-profile-heading"><span class="donor-profile-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($donor['name'], 0, 1))) ?></span><div><h2><?= e($donor['name']) ?></h2><p><?= e($donor['email']) ?></p></div></div>
          <div class="donor-profile-footer"><span>Donor account</span><a href="#my-profile">Account details</a></div>
          <?php if ($actionNotice): ?><p class="donor-data-request-message is-success" role="status"><?= e($actionNotice) ?></p><?php endif; ?>
          <?php if ($actionError): ?><p class="donor-data-request-message" role="alert"><?= e($actionError) ?></p><?php endif; ?>
          <details class="donor-data-request"><summary>Request an account correction or removal</summary>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="data_request"><label>Request type<select name="request_type" required><option value="correction">Correct my name or email</option><option value="removal">Remove my account</option></select></label><label>Corrected name<input name="requested_name" maxlength="200" value="<?= e($donor['name']) ?>"></label><label>Corrected email<input name="requested_email" type="email" maxlength="250" value="<?= e($donor['email']) ?>"></label><label>Details (optional)<textarea name="details" rows="3" maxlength="2000"></textarea></label><button class="donor-primary-link" type="submit">Submit request</button></form>
          </details>
          <?php if ($dataRequests): ?><div class="donor-data-request-history"><strong>Recent requests</strong><?php foreach ($dataRequests as $request): ?><small><?= e(ucfirst($request['request_type'])) ?> · <?= e(ucfirst($request['status'])) ?> · <?= e(format_date_short($request['created_at'])) ?></small><?php endforeach; ?></div><?php endif; ?>
        </section>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
