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
    'content'   => ['Manage Content',   'admin/content/index.php',    ['manage-content-light.png', 'manage-content-dark.png']],
    'inquiries' => ['Manage Inquiries', 'admin/inquiries/index.php',  ['manage-inquiries-light.png', 'manage-inquiries-dark.png']],
    'feedback'  => ['Website Feedback', 'admin/feedback/index.php',   ['manage-feedback-light.png', 'manage-feedback-dark.png']],
    'archive'   => ['Archive',          'admin/archive/index.php',    ['archive-light.png', 'archive-dark.png']],
];
?>
<aside class="sidebar">
    <a class="sidebar-brand" href="<?= e(url('admin/dashboard.php')) ?>">
        <img src="<?= e(asset('images/logo.png')) ?>" alt="" width="26" height="26" onerror="this.style.display='none'">
        <span>Reading Club 2000</span>
    </a>

    <nav class="sidebar-nav" aria-label="Main">
        <?php foreach ($navItems as $key => $item):
            $label    = $item[0];
            $path     = $item[1];
            $icon     = $item[2] ?? null;
            $isThemeIcon = is_array($icon);
            $isActive = $key === ($activeNav ?? '');
        ?>
            <a href="<?= e(url($path)) ?>" title="<?= e($label) ?>"
               class="nav-link<?= $isActive ? ' is-active' : '' ?>"
               <?= $isActive ? 'aria-current="page"' : '' ?>>
                <?php if ($isThemeIcon): ?>
                    <img class="nav-icon nav-icon-light" src="<?= e(asset('images/icons/' . $icon[0])) ?>" alt="" aria-hidden="true" width="22" height="22" onerror="this.style.visibility='hidden'">
                    <img class="nav-icon nav-icon-dark" src="<?= e(asset('images/icons/' . $icon[1])) ?>" alt="" aria-hidden="true" width="22" height="22" onerror="this.style.visibility='hidden'">
                <?php elseif ($icon): ?>
                    <img class="nav-icon" src="<?= e(asset('images/icons/' . $icon)) ?>" alt="" aria-hidden="true" width="22" height="22" onerror="this.style.visibility='hidden'">
                <?php endif; ?>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <img class="profile-icon profile-icon-light" src="<?= e(asset('images/icons/profile-light.png')) ?>" alt="" aria-hidden="true" width="20" height="20">
            <img class="profile-icon profile-icon-dark" src="<?= e(asset('images/icons/profile-dark.png')) ?>" alt="" aria-hidden="true" width="20" height="20">
            <span><?= e($admin['name'] ?? '') ?></span>
        </div>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="link-button">Log Out</button>
        </form>
    </div>
</aside>
