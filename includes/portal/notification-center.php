<?php
$notificationUser = current_admin() ?? ['id' => 0, 'role' => 'admin'];
$notificationIsSuper = $notificationUser['role'] === 'super_admin';
$notificationEndpoint = $notificationIsSuper
    ? url('superadmin/api/notifications.php')
    : url('admin/api/notifications.php');
$notificationPanelId = $notificationIsSuper ? 'superadmin-notification-panel' : 'admin-notification-panel';
?>
<div class="portal-toolbar">
    <button class="admin-theme-toggle" type="button" data-admin-theme-toggle aria-pressed="false" aria-label="Switch to dark mode">
        <span data-admin-theme-icon aria-hidden="true">☾</span><span data-admin-theme-label>Dark mode</span>
    </button>
    <div class="notification-center"
         data-notifications
         data-endpoint="<?= e($notificationEndpoint) ?>"
         data-role="<?= e($notificationUser['role']) ?>"
         data-user-id="<?= (int) $notificationUser['id'] ?>">
        <button class="notification-trigger" type="button" data-notification-toggle
                aria-expanded="false" aria-controls="<?= e($notificationPanelId) ?>" aria-label="Notifications">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" />
            </svg>
            <span class="notification-count" data-notification-count hidden></span>
        </button>
        <section class="notification-panel" id="<?= e($notificationPanelId) ?>" data-notification-panel hidden aria-label="Notifications">
            <header class="notification-panel-head">
                <h2>Notifications</h2>
                <button type="button" data-notification-read>Mark all read</button>
            </header>
            <p class="notification-empty" data-notification-empty hidden>No recent notifications.</p>
            <ul class="notification-list" data-notification-list></ul>
            <p class="notification-status" data-notification-status role="status" hidden></p>
        </section>
    </div>
</div>
