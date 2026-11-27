<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';

$donor = current_donor_user();
if (!$donor) {
    $_SESSION['donor_return_to'] = '/public/my-campaigns.php';
    flash_set('donor_auth_notice', 'Sign in to view your campaigns.');
    redirect('/public/login.php');
}

$campaigns = [];
$campaignReportCount = 0;
$pageError = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT c.id, c.title, c.description, c.goal_amount, c.image_path,
            mine.report_count, mine.last_reported_at, COALESCE(raised.raised, 0) AS raised
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
            WHERE campaign_id IS NOT NULL GROUP BY campaign_id
        ) raised ON raised.campaign_id = c.id
        ORDER BY mine.last_reported_at DESC, c.id DESC");
    $stmt->execute([(int) $donor['id']]);
    $campaigns = $stmt->fetchAll();
    $campaignReportCount = array_sum(array_map(static fn($row) => (int) $row['report_count'], $campaigns));
} catch (Throwable $e) {
    error_log('Donor campaigns page failed: ' . $e->getMessage());
    $pageError = 'Your campaigns could not be loaded right now. Please refresh in a moment.';
}

$GLOBALS['pageTitle'] = 'My campaigns';
$GLOBALS['pageClass'] = 'donor-dashboard';
require __DIR__ . '/../includes/header.php';
$donorActive = 'campaigns';
?>
<main class="donor-dashboard-shell">
  <?php require __DIR__ . '/../includes/donor/sidebar.php'; ?>
  <div class="donor-dashboard-content">
    <header class="donor-page-heading"><div><p class="donor-eyebrow">Your donor account</p><h1>My Campaigns</h1><p>Campaigns connected to donation reports submitted from your account.</p></div><a class="donor-primary-link" href="<?= e(app_url('public/donate.php?tab=campaign')) ?>">Support a campaign</a></header>
    <?php if ($pageError): ?><div class="donor-dashboard-alert" role="alert"><?= e($pageError) ?></div><?php endif; ?>
    <section class="donor-dashboard-stats donor-campaign-stats" aria-label="Campaign summary">
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon" aria-hidden="true">⚑</span><div><p>Campaigns supported</p><strong><?= number_format(count($campaigns)) ?></strong><small>With linked reports</small></div></article>
      <article class="donor-dashboard-card donor-stat-card"><span class="donor-stat-icon gold" aria-hidden="true">▤</span><div><p>Campaign reports</p><strong><?= number_format($campaignReportCount) ?></strong><small>Submitted from your account</small></div></article>
    </section>
    <?php if (!$campaigns): ?><section class="donor-dashboard-card donor-empty-state donor-page-empty"><p>You haven’t submitted a donation report for a campaign yet.</p><a class="donor-primary-link" href="<?= e(app_url('public/donate.php?tab=campaign')) ?>">Explore campaigns</a></section>
    <?php else: ?><section class="donor-campaign-page-list" aria-label="Campaigns you support">
      <?php foreach ($campaigns as $campaign):
        $goal = (float) $campaign['goal_amount'];
        $raised = (float) $campaign['raised'];
        $percent = $goal > 0 ? min(100, (int) round($raised / $goal * 100)) : 0;
      ?>
        <article class="donor-dashboard-card donor-campaign-page-card">
          <?php if (!empty($campaign['image_path'])): ?><img src="<?= e(media_url($campaign['image_path'])) ?>" alt="" loading="lazy"><?php else: ?><span class="donor-campaign-placeholder" aria-hidden="true">📖</span><?php endif; ?>
          <div class="donor-campaign-page-info"><div class="donor-campaign-page-title"><div><h2><?= e($campaign['title']) ?></h2><small><?= number_format((int) $campaign['report_count']) ?> linked donation report<?= (int) $campaign['report_count'] === 1 ? '' : 's' ?> · last reported <?= e(donor_format_date_short($campaign['last_reported_at'])) ?></small></div><a href="<?= e(app_url('public/donate.php?tab=campaign')) ?>">Donate again</a></div>
            <?php if (!empty($campaign['description'])): ?><p><?= nl2br(e((string) $campaign['description'])) ?></p><?php endif; ?>
            <div class="donor-progress" role="progressbar" aria-label="Campaign funding progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $percent ?>"><span style="width:<?= $percent ?>%"></span></div>
            <div class="donor-campaign-page-totals"><span><?= e(donor_format_peso($raised)) ?> raised of <?= e(donor_format_peso($goal)) ?></span><strong><?= $percent ?>%</strong></div>
          </div>
        </article>
      <?php endforeach; ?>
    </section><?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
