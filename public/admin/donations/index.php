<?php
// admin/donations/index.php - Manage donations (tabs: ?tab=book | ?tab=monetary, ?id= selects a row)
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/book-donation-service.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/monetary-donation-service.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

/** Build a link back to this page, e.g. donations_url(['tab' => 'book', 'id' => 3]). Empty values are dropped. */
function donations_url(array $params): string
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return url('admin/donations/index.php' . ($params ? '?' . http_build_query($params) : ''));
}

$tab = ($_GET['tab'] ?? '') === 'book' ? 'book' : 'monetary';
$loadError = null;
$summary = ['total' => 0, 'approved' => 0, 'books_received' => 0];
$filters = book_filters_from_request();
$list = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
$selected = null;
$history = ['previous' => 0, 'since' => null];
$moneySummary = ['total' => 0, 'reported' => 0.0, 'verified' => 0.0, 'awaiting' => 0];
$moneyList = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
$moneySelected = null;
$moneyHistory = ['previous' => 0, 'since' => null];
$moneyFilters = money_filters_from_request();

try {
    $pdo = db();
    $selectedId = (int) ($_GET['id'] ?? 0);

    if ($tab === 'book') {
        $summary = get_book_summary($pdo);
        $filters = book_filters_from_request();
        $list = list_book_donations($pdo, $filters);
        $filters['page'] = $list['page'];
        $selected = $selectedId > 0 ? get_book_donation($pdo, $selectedId) : null;
        $history = $selected ? get_donor_history($pdo, (string) $selected['donor_email']) : ['previous' => 0, 'since' => null];
        if ($selectedId > 0 && !$selected) {
            flash('error', 'That donation could not be found.');
        }
    } else {
        $moneySummary = get_money_summary($pdo);
        $moneyFilters = money_filters_from_request();
        $moneyList = list_money_donations($pdo, $moneyFilters);
        $moneyFilters['page'] = $moneyList['page'];
        $moneySelected = $selectedId > 0 ? get_money_donation($pdo, $selectedId) : null;
        $moneyHistory = $moneySelected ? get_money_donor_history($pdo, $moneySelected) : ['previous' => 0, 'since' => null];

        if ($selectedId > 0 && !$moneySelected) {
            flash('error', 'That donation could not be found.');
        }
    }
} catch (Throwable $e) {
    error_log('Donations load failed: ' . $e->getMessage());
    $loadError = 'The donations could not be loaded. Please check the database connection and try again.';
}

$pageTitle = 'Manage donations';
$activeNav = 'donations';
$extraCss  = ['donations.css'];
$extraJs   = ['review-dialog.js', $tab === 'book' ? 'book-donations.js' : 'monetary-donations.js'];
require dirname(__DIR__, 3) . '/includes/portal/admin-header.php';
?>
<header class="page-header">
    <h1 class="page-title">Manage Donations</h1>
<p class="page-subtitle">Review <?= $tab === 'book' ? 'Book' : 'Monetary' ?> Donation Submissions.</p>
</header>

<?php if ($loadError): ?>
    <div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div>
<?php endif; ?>

<nav class="tabs" aria-label="Donation type">
    <a class="tab<?= $tab === 'monetary' ? ' is-active' : '' ?>" href="<?= e(url('admin/donations/index.php?tab=monetary')) ?>">Monetary donations</a>
    <a class="tab<?= $tab === 'book' ? ' is-active' : '' ?>" href="<?= e(url('admin/donations/index.php?tab=book')) ?>">Book donations</a>
</nav>

<?php if ($tab === 'book'): ?>
    <?php require dirname(__DIR__, 3) . '/includes/portal/partials/book-donations-tab.php'; ?>
<?php else: ?>
    <?php require dirname(__DIR__, 3) . '/includes/portal/partials/monetary-donations-tab.php'; ?>
<?php endif; ?>
<?php require dirname(__DIR__, 3) . '/includes/portal/admin-footer.php'; ?>
