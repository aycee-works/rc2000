<?php
// Super Admin notifications: changes recorded in the admin activity log.
require_once dirname(__DIR__, 4) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 4) . '/includes/portal/services/notification-service.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    json_response(false, 'Method not allowed.', null, 405);
}

if (!admin_session_is_valid()) {
    json_response(false, 'Authentication required.', null, 401);
}
$admin = current_admin();
if (!$admin || $admin['role'] !== 'super_admin') {
    json_response(false, 'You do not have permission to view these notifications.', null, 403);
}

try {
    json_response(true, 'OK', portal_notifications(db(), 'super_admin', get_str('since')));
} catch (Throwable $e) {
    error_log('Super Admin notifications failed: ' . $e->getMessage());
    json_response(false, 'Notifications could not be loaded.', null, 500);
}
