<?php
// admin/archive/index.php - Archive: read-only review of completed records (admins only)
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/archive-service.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$filters = archive_filters_from_request();
$archive = archive_page_data($filters) + ['filters' => $filters, 'base' => 'admin/archive/index.php', 'can_manage' => true];

$pageTitle = 'Archive';
$activeNav = 'archive';
$extraCss  = ['archive.css'];
$extraJs   = ['review-dialog.js'];
require dirname(__DIR__, 3) . '/includes/portal/admin-header.php';
require dirname(__DIR__, 3) . '/includes/portal/partials/archive-view.php';
require dirname(__DIR__, 3) . '/includes/portal/admin-footer.php';