<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';
require_once __DIR__ . '/../includes/donor/inquiries.php';

$donor = current_donor_user();
if (!$donor) {
    $_SESSION['donor_return_to'] = '/public/dashboard.php';
    flash_set('donor_auth_notice', 'Sign in to view your donor dashboard.');
    redirect('/public/login.php');
}

$stats = ['verified_total' => 0.0, 'report_count' => 0, 'campaign_count' => 0, 'open_inquiries' => 0];
$recentDonations = [];
$latestInquiry = null;
$dashboardError = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT
            COALESCE(SUM(verified_amount) FILTER (WHERE status = 'verified'), 0) AS verified_total,
            COUNT(*) AS report_count,
            COUNT(DISTINCT campaign_id) FILTER (WHERE campaign_id IS NOT NULL) AS campaign_count
        FROM rc2000_admin.monetary_donations WHERE donor_user_id = ?");
    $stmt->execute([(int) $donor['id']]);
    $row = $stmt->fetch() ?: [];
    $stats['verified_total'] = (float) ($row['verified_total'] ?? 0);
    $stats['report_count'] = (int) ($row['report_count'] ?? 0);
    $stats['campaign_count'] = (int) ($row['campaign_count'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rc2000_admin.inquiries
        WHERE donor_user_id = ? AND status IN ('open', 'in_progress')");
    $stmt->execute([(int) $donor['id']]);
    $stats['open_inquiries'] = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT d.reference_no, d.reported_amount, d.verified_amount, d.status,
            d.created_at, d.campaign_id, c.title AS campaign_title
        FROM rc2000_admin.monetary_donations d
        LEFT JOIN rc2000_admin.campaigns c ON c.id = d.campaign_id
        WHERE d.donor_user_id = ? ORDER BY d.created_at DESC, d.id DESC LIMIT 4");
    $stmt->execute([(int) $donor['id']]);
    $recentDonations = $stmt->fetchAll();
    $inquiries = list_my_inquiries($pdo, (int) $donor['id']);
    $latestInquiry = $inquiries[0] ?? null;
} catch (Throwable $e) {
    error_log('Donor dashboard load failed: ' . $e->getMessage());
    $dashboardError = 'Some dashboard information could not be loaded. Please refresh in a moment.';
}

$GLOBALS['pageTitle'] = 'My donor dashboard';
$GLOBALS['pageClass'] = 'donor-dashboard';
require __DIR__ . '/../includes/header.php';
$donorActive = 'overview';
?>
<main class="donor-dashboard-shell">
  <?php require __DIR__ . '/../includes/donor/sidebar.php'; ?>
  <div class="donor-dashboard-content">
    <section class="donor-welcome">
      <div><p class="donor-eyebrow">Your donor account</p><h1>Welcome back, <?= e($donor['name']) ?></h1><p>Thank you for supporting your community.</p></div>
      <div class="donor-welcome-art" aria-hidden="true"><span>📚</span></div>
    </section>
    <?php if ($dashboardError): ?><div class="donor-dashboard-alert" role="alert"><?= e($dashboardError) ?></div><?php endif; ?>

    <section class="donor-dashboard-stats" aria-label="Your account summary">
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon" aria-hidden="true">♡</span><div><p>Total donated</p><strong><?= e(donor_format_peso($stats['verified_total'])) ?></strong><small>Verified donations</small></div></article>
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon gold" aria-hidden="true">▤</span><div><p>Donation reports</p><strong><?= number_format($stats['report_count']) ?></strong><small><?= number_format($stats['campaign_count']) ?> campaigns supported</small></div></article>
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon gold" aria-hidden="true">☏</span><div><p>Open inquiries</p><strong><?= number_format($stats['open_inquiries']) ?></strong><small>Open or in progress</small></div></article>
    </section>

    <div class="donor-overview-grid">
      <section class="donor-dashboard-card donor-section-card">
        <header class="donor-section-heading"><div><span aria-hidden="true">♡</span><h2>Recent donations</h2></div><a href="<?= e(app_url('public/my-donations.php')) ?>">View all</a></header>
        <?php if (!$recentDonations): ?>
          <div class="donor-empty-state"><p>You haven’t submitted a donation report yet.</p><a class="donor-primary-link" href="<?= e(app_url('public/donate.php')) ?>">Make a donation</a></div>
        <?php else: ?>
          <div class="donor-table-wrap"><table class="donor-history-table"><thead><tr><th>Date</th><th>Donation</th><th>Amount</th><th>Status</th></tr></thead><tbody>
            <?php foreach ($recentDonations as $item):
              $verified = $item['status'] === 'verified' && $item['verified_amount'] !== null;
              $label = $item['status'] === 'verified' ? 'Verified' : ($item['status'] === 'not_verified' ? 'Not verified' : 'Pending');
              $statusClass = $item['status'] === 'verified' ? 'is-good' : ($item['status'] === 'pending' ? 'is-pending' : 'is-bad');
            ?>
              <tr><td><?= e(donor_format_date_short($item['created_at'])) ?></td><td><?= e($item['campaign_title'] ?: 'General donation') ?><small><?= e($item['reference_no']) ?></small></td><td><?= e(donor_format_peso($verified ? $item['verified_amount'] : $item['reported_amount'])) ?></td><td><span class="donor-status-pill <?= e($statusClass) ?>"><?= e($label) ?></span></td></tr>
            <?php endforeach; ?>
          </tbody></table></div>
        <?php endif; ?>
      </section>

      <div class="donor-overview-sidecards">
        <a class="donor-dashboard-card donor-overview-link" href="<?= e(app_url('public/my-campaigns.php')) ?>"><span class="donor-overview-link-icon" aria-hidden="true">⚑</span><span><strong>My campaigns</strong><small><?= number_format($stats['campaign_count']) ?> supported · see your campaign reports</small></span><span class="donor-overview-arrow" aria-hidden="true">→</span></a>
        <a class="donor-dashboard-card donor-overview-link" href="<?= e(app_url('public/my-inquiries.php')) ?>"><span class="donor-overview-link-icon" aria-hidden="true">☏</span><span><strong>My inquiries</strong><small><?= $latestInquiry ? e($latestInquiry['subject']) : 'Start or continue a conversation' ?></small></span><span class="donor-overview-arrow" aria-hidden="true">→</span></a>
        <a class="donor-dashboard-card donor-overview-link" href="<?= e(app_url('public/profile.php')) ?>"><span class="donor-overview-link-icon" aria-hidden="true">♙</span><span><strong>Profile</strong><small>Review account details and data requests</small></span><span class="donor-overview-arrow" aria-hidden="true">→</span></a>
      </div>
    </div>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
