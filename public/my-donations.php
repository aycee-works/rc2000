<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';

$donor = current_donor_user();
if (!$donor) {
    $_SESSION['donor_return_to'] = '/public/my-donations.php';
    flash_set('donor_auth_notice', 'Sign in to view your donations.');
    redirect('/public/login.php');
}

$statusFilter = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '';
if (!in_array($statusFilter, ['pending', 'verified', 'not_verified'], true)) { $statusFilter = ''; }
$typeFilter = isset($_GET['type']) && is_string($_GET['type']) ? trim($_GET['type']) : '';
if (!in_array($typeFilter, ['general', 'campaign'], true)) { $typeFilter = ''; }
$donations = [];
$summary = ['report_count' => 0, 'verified_total' => 0.0, 'pending_count' => 0];
$pageError = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT COUNT(*) AS report_count,
            COALESCE(SUM(verified_amount) FILTER (WHERE status = 'verified'), 0) AS verified_total,
            COUNT(*) FILTER (WHERE status = 'pending') AS pending_count
        FROM rc2000_admin.monetary_donations WHERE donor_user_id = ?");
    $stmt->execute([(int) $donor['id']]);
    $summaryRow = $stmt->fetch() ?: [];
    $summary = [
        'report_count' => (int) ($summaryRow['report_count'] ?? 0),
        'verified_total' => (float) ($summaryRow['verified_total'] ?? 0),
        'pending_count' => (int) ($summaryRow['pending_count'] ?? 0),
    ];

    $where = ['d.donor_user_id = :donor_id'];
    $params = ['donor_id' => (int) $donor['id']];
    if ($statusFilter !== '') { $where[] = 'd.status = :status'; $params['status'] = $statusFilter; }
    if ($typeFilter === 'general') { $where[] = 'd.campaign_id IS NULL'; }
    elseif ($typeFilter === 'campaign') { $where[] = 'd.campaign_id IS NOT NULL'; }
    $stmt = $pdo->prepare("SELECT d.reference_no, d.reported_amount, d.verified_amount, d.status,
            d.created_at, d.campaign_id, c.title AS campaign_title
        FROM rc2000_admin.monetary_donations d
        LEFT JOIN rc2000_admin.campaigns c ON c.id = d.campaign_id
        WHERE " . implode(' AND ', $where) . " ORDER BY d.created_at DESC, d.id DESC");
    $stmt->execute($params);
    $donations = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Donor donations page failed: ' . $e->getMessage());
    $pageError = 'Your donation reports could not be loaded right now. Please refresh in a moment.';
}

$GLOBALS['pageTitle'] = 'My donations';
$GLOBALS['pageClass'] = 'donor-dashboard';
require __DIR__ . '/../includes/header.php';
$donorActive = 'donations';
?>
<main class="donor-dashboard-shell">
  <?php require __DIR__ . '/../includes/donor/sidebar.php'; ?>
  <div class="donor-dashboard-content">
    <header class="donor-page-heading"><div><p class="donor-eyebrow">Your donor account</p><h1>My Donations</h1><p>Review general and campaign donation reports submitted from your account.</p></div><a class="donor-primary-link" href="<?= e(app_url('public/donate.php')) ?>">Make a donation</a></header>
    <?php if ($pageError): ?><div class="donor-dashboard-alert" role="alert"><?= e($pageError) ?></div><?php endif; ?>
    <section class="donor-dashboard-stats donor-donation-stats" aria-label="Donation totals">
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon" aria-hidden="true">▤</span><div><p>Donation reports</p><strong><?= number_format($summary['report_count']) ?></strong><small>General and campaign</small></div></article>
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon gold" aria-hidden="true">♡</span><div><p>Verified total</p><strong><?= e(donor_format_peso($summary['verified_total'])) ?></strong><small>Confirmed donations</small></div></article>
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon gold" aria-hidden="true">◷</span><div><p>Pending reports</p><strong><?= number_format($summary['pending_count']) ?></strong><small>Awaiting review</small></div></article>
    </section>
    <section class="donor-dashboard-card donor-page-card">
      <form class="donor-page-filters" method="get">
        <label>Status<select name="status"><option value="">All statuses</option><option value="pending"<?= $statusFilter === 'pending' ? ' selected' : '' ?>>Pending</option><option value="verified"<?= $statusFilter === 'verified' ? ' selected' : '' ?>>Verified</option><option value="not_verified"<?= $statusFilter === 'not_verified' ? ' selected' : '' ?>>Not verified</option></select></label>
        <label>Donation type<select name="type"><option value="">All types</option><option value="general"<?= $typeFilter === 'general' ? ' selected' : '' ?>>General</option><option value="campaign"<?= $typeFilter === 'campaign' ? ' selected' : '' ?>>Campaign</option></select></label>
        <button class="donor-primary-link" type="submit">Apply filters</button>
        <?php if ($statusFilter !== '' || $typeFilter !== ''): ?><a class="donor-filter-reset" href="<?= e(app_url('public/my-donations.php')) ?>">Clear</a><?php endif; ?>
      </form>
      <?php if (!$donations): ?><div class="donor-empty-state"><p><?= $summary['report_count'] ? 'No donation reports match these filters.' : 'You haven’t submitted a donation report yet.' ?></p><?php if (!$summary['report_count']): ?><a class="donor-primary-link" href="<?= e(app_url('public/donate.php')) ?>">Make a donation</a><?php endif; ?></div>
      <?php else: ?><div class="donor-table-wrap"><table class="donor-history-table donor-all-donations-table"><thead><tr><th>Date</th><th>Reference</th><th>Type</th><th>Campaign</th><th>Reported</th><th>Verified</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($donations as $item):
          $verified = $item['status'] === 'verified' && $item['verified_amount'] !== null;
          $statusLabel = $item['status'] === 'not_verified' ? 'Not verified' : ucfirst($item['status']);
          $statusClass = $item['status'] === 'verified' ? 'is-good' : ($item['status'] === 'pending' ? 'is-pending' : 'is-bad');
        ?>
          <tr><td><?= e(donor_format_date_short($item['created_at'])) ?></td><td><?= e($item['reference_no']) ?></td><td><span class="donor-type-pill <?= $item['campaign_id'] ? 'is-campaign' : 'is-general' ?>"><?= $item['campaign_id'] ? 'Campaign' : 'General' ?></span></td><td><?= e($item['campaign_title'] ?: '—') ?></td><td><?= e(donor_format_peso($item['reported_amount'])) ?></td><td><?= $verified ? e(donor_format_peso($item['verified_amount'])) : '—' ?></td><td><span class="donor-status-pill <?= e($statusClass) ?>"><?= e($statusLabel) ?></span></td></tr>
        <?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
    </section>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
