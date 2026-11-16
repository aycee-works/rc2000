<?php
// admin/feedback/index.php - Website feedback sent from the footer pop-up
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$rows = [];
$loadError = null;
try {
    $stmt = db()->query(
        'SELECT id, name, email, message, created_at
         FROM rc2000_admin.website_feedback
         ORDER BY created_at DESC, id DESC
         LIMIT 100'
    );
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('Feedback load failed: ' . $e->getMessage());
    $loadError = 'The feedback could not be loaded. Please check the database connection and try again.';
}

$pageTitle = 'Website feedback';
$activeNav = 'feedback';
require dirname(__DIR__, 3) . '/includes/portal/admin-header.php';
?>
<header class="page-header">
    <h1 class="page-title">Website feedback</h1>
    <p class="page-subtitle">Experiences and suggestions from visitors about the website.</p>
</header>

<?php if ($loadError): ?>
    <div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div>
<?php endif; ?>

<section class="panel" aria-label="Website feedback">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Date</th><th>Name</th><th>Email</th><th>Feedback</th></tr>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="4" class="table-empty">No feedback has been submitted yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e(format_date_short($row['created_at'])) ?></td>
                    <td><?= e($row['name']) ?></td>
                    <td><?= e($row['email']) ?></td>
                    <td style="white-space: pre-line;"><?= e($row['message']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require dirname(__DIR__, 3) . '/includes/portal/admin-footer.php'; ?>