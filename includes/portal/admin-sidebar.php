<?php
/**
 * includes/admin-sidebar.php
 * Included by admin-header.php. Uses $activeNav to highlight the current page.
 */
$admin = current_admin();
$navItems = [
    'dashboard' => ['Dashboard',        'admin/dashboard.php'],
    'donations' => ['Manage Donations', 'admin/donations/index.php'],
    'community' => ['Manage Campaigns', 'admin/community.php'],
    'content'   => ['Manage Content',   'admin/content/index.php'],
    'inquiries' => ['Manage Inquiries', 'admin/inquiries/index.php'],
    'feedback'  => ['Website Feedback', 'admin/feedback/index.php'],
];
?>
<aside class="sidebar">
    <button class="sidebar-reveal" type="button" aria-label="Open admin navigation" aria-expanded="false" data-sidebar-toggle><span aria-hidden="true">☰</span></button>
    <a class="sidebar-brand" href="<?= e(url('admin/dashboard.php')) ?>">
        <img src="<?= e(asset('images/logo.png')) ?>" alt="" width="26" height="26" onerror="this.style.display='none'">
        <span>Reading Club 2000</span>
    </a>

    <nav class="sidebar-nav" aria-label="Main">
        <?php foreach ($navItems as $key => [$label, $path]): ?>
            <a href="<?= e(url($path)) ?>"
               class="nav-link<?= $key === ($activeNav ?? '') ? ' is-active' : '' ?>"
               <?= $key === ($activeNav ?? '') ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
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
