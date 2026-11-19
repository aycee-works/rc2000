<?php
/**
 * includes/admin-sidebar.php
 * Included by admin-header.php. Uses $activeNav to highlight the current page.
 */
$admin = current_admin();
$navItems = [
    'dashboard' => ['Dashboard',        'admin/dashboard.php',       'dashboard.png'],
    'donations' => ['Manage Donations', 'admin/donations/index.php',  'manage_donations.png'],
    'community' => ['Manage Campaigns', 'admin/community.php',        'manage_campaigns.png'],
    'content'   => ['Manage Content',   'admin/content/index.php',    'manage_content.png'],
    'inquiries' => ['Manage Inquiries', 'admin/inquiries/index.php',  'manage_inquiries.png'],
    'feedback'  => ['Website Feedback', 'admin/feedback/index.php',   'manage_feedback.png'],
];
?>
<aside class="sidebar">
    <a class="sidebar-brand" href="<?= e(url('admin/dashboard.php')) ?>">
        <img src="<?= e(asset('images/logo.png')) ?>" alt="" width="26" height="26" onerror="this.style.display='none'">
        <span>Reading Club 2000</span>
    </a>

    <nav class="sidebar-nav" aria-label="Main">
        <?php foreach ($navItems as $key => [$label, $path, $icon]): ?>
            <a href="<?= e(url($path)) ?>" title="<?= e($label) ?>"
               class="nav-link<?= $key === ($activeNav ?? '') ? ' is-active' : '' ?>"
               <?= $key === ($activeNav ?? '') ? 'aria-current="page"' : '' ?>><img class="nav-icon" src="<?= e(asset('images/icons/' . $icon)) ?>" alt="" aria-hidden="true" width="22" height="22"><span><?= e($label) ?></span></a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user"><?= e($admin['name'] ?? '') ?></div>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="link-button">Log Out</button>
        </form>
    </div>
</aside>
