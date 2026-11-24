<?php
// admin/donations/index.php - Manage monetary donations, ?id= selects a row.
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/monetary-donation-service.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

/** Build a link back to this page while omitting empty filter values. */
function donations_url(array $params): string
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return url('admin/donations/index.php' . ($params ? '?' . http_build_query($params) : ''));
}

$loadError = null;
$moneySummary = ['total' => 0, 'reported' => 0.0, 'verified' => 0.0, 'awaiting' => 0];
$moneyList = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
$moneySelected = null;
$moneyHistory = ['previous' => 0, 'since' => null];
$moneyFilters = money_filters_from_request();

try {
    $pdo = db();
    $selectedId = (int) ($_GET['id'] ?? 0);

    $moneySummary = get_money_summary($pdo);
    $moneyFilters = money_filters_from_request();
    $moneyList = list_money_donations($pdo, $moneyFilters);
    $moneyFilters['page'] = $moneyList['page'];
    $moneySelected = $selectedId > 0 ? get_money_donation($pdo, $selectedId) : null;
    $moneyHistory = $moneySelected ? get_money_donor_history($pdo, $moneySelected) : ['previous' => 0, 'since' => null];

    if ($selectedId > 0 && !$moneySelected) {
        flash('error', 'That donation could not be found.');
    }
} catch (Throwable $e) {
    error_log('Donations load failed: ' . $e->getMessage());
    $loadError = 'The donations could not be loaded. Please check the database connection and try again.';
}

$pageTitle = 'Manage donations';
$activeNav = 'donations';
$extraCss  = ['donations.css'];
$extraJs   = ['review-dialog.js', 'monetary-donations.js'];
require dirname(__DIR__, 3) . '/includes/portal/admin-header.php';
?>
<header class="page-header">
    <h1 class="page-title">Manage Donations</h1>
<p class="page-subtitle">Review general monetary and campaign donation reports.</p>
</header>

<?php if ($loadError): ?>
    <div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div>
<?php endif; ?>

<?php require dirname(__DIR__, 3) . '/includes/portal/partials/monetary-donations-tab.php'; ?>
<?php require dirname(__DIR__, 3) . '/includes/portal/admin-footer.php'; ?>
