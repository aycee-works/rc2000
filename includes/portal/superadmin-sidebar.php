<?php
/** includes/superadmin-sidebar.php - dark sidebar. Uses $activeNav to highlight the current page. */
$navItems = [
    'dashboard' => ['Dashboard',        'staff-console/dashboard.php',  ['dashboard.png', 'admin_dashboard_dark.png']],
    'admins'    => ['Admin Management', 'staff-console/admins/index.php', ['admin_management_light.png', 'admin_management_dark.png']],
    'audit'     => ['Audit Logs',       'staff-console/audit-logs.php',  ['audit_logs_light.png', 'audit_logs_dark.png']],
    'archive'   => ['Archive',          'staff-console/archive.php',     ['archive-light.png', 'archive-dark.png']],
];
$sidebarUser = current_admin() ?? [];
?>
<aside class="sidebar">
    <a class="sidebar-brand" href="<?= e(url('staff-console/dashboard.php')) ?>">
        <img src="<?= e(asset('images/logo.png')) ?>" alt="" width="26" height="26" onerror="this.style.display='none'">
        <span>Reading Club 2000</span>
    </a>

    <nav class="sidebar-nav" aria-label="Main">
        <?php foreach ($navItems as $key => [$label, $path, $icon]): ?>
            <a href="<?= e(url($path)) ?>" title="<?= e($label) ?>"
               class="nav-link<?= $key === $activeNav ? ' is-active' : '' ?>"
               <?= $key === $activeNav ? 'aria-current="page"' : '' ?>>
                <img class="nav-icon nav-icon-light" src="<?= e(asset('images/icons/' . $icon[0])) ?>" alt="" aria-hidden="true" width="22" height="22" onerror="this.style.visibility='hidden'">
                <img class="nav-icon nav-icon-dark" src="<?= e(asset('images/icons/' . $icon[1])) ?>" alt="" aria-hidden="true" width="22" height="22" onerror="this.style.visibility='hidden'">
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <img class="profile-icon profile-icon-light" src="<?= e(asset('images/icons/profile-light.png')) ?>" alt="" aria-hidden="true" width="20" height="20">
            <img class="profile-icon profile-icon-dark" src="<?= e(asset('images/icons/profile-dark.png')) ?>" alt="" aria-hidden="true" width="20" height="20">
            <span><?= e($sidebarUser['name'] ?? 'Super Admin') ?></span>
        </div>
        <form method="post" action="<?= e(url('staff-console/logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="link-button">Log Out</button>
        </form>
    </div>
</aside>
