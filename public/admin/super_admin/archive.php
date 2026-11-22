<?php
// superadmin/archive.php - Archive: read-only view for the super admin
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/archive-service.php';

try {
    require_superadmin_portal();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$filters = archive_filters_from_request();
$archive = archive_page_data($filters) + ['filters' => $filters, 'base' => 'superadmin/archive.php', 'can_manage' => false];

$pageTitle = 'Archive';
$activeNav = 'archive';
$extraCss  = ['archive.css?v=3'];
$extraJs   = ['review-dialog.js'];
require dirname(__DIR__, 3) . '/includes/portal/superadmin-header.php';
require dirname(__DIR__, 3) . '/includes/portal/partials/archive-view.php';
require dirname(__DIR__, 3) . '/includes/portal/superadmin-footer.php';
