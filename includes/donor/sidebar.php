<?php
$donorActive = $donorActive ?? 'overview';
$donorNavigation = [
    'overview' => ['public/dashboard.php', 'dashboard.png', 'admin_dashboard_dark.png', 'My Overview'],
    'donations' => ['public/my-donations.php', 'manage_donations.png', 'manage_donations_dark.png', 'My Donations'],
    'campaigns' => ['public/my-campaigns.php', 'manage_campaigns.png', 'manage_campaigns_dark.png', 'My Campaigns'],
    'inquiries' => ['public/my-inquiries.php', 'manage-inquiries-light.png', 'manage-inquiries-dark.png', 'My Inquiries'],
    'profile' => ['public/profile.php', 'profile-light.png', 'profile-dark.png', 'Profile'],
];
?>
<aside class="donor-dashboard-sidebar" aria-label="Donor account navigation">
  <?php foreach ($donorNavigation as $key => [$path, $lightIcon, $darkIcon, $label]): ?>
    <a class="donor-dashboard-nav<?= $donorActive === $key ? ' is-active' : '' ?>"
       href="<?= e(app_url($path)) ?>"<?= $donorActive === $key ? ' aria-current="page"' : '' ?>>
      <span class="donor-nav-icons" aria-hidden="true">
        <img class="donor-nav-icon donor-nav-icon-light" src="<?= e(app_url('public/assets/images/icons/' . $lightIcon)) ?>" alt="" width="22" height="22">
        <img class="donor-nav-icon donor-nav-icon-dark" src="<?= e(app_url('public/assets/images/icons/' . $darkIcon)) ?>" alt="" width="22" height="22">
      </span>
      <span class="donor-nav-label"><?= e($label) ?></span>
    </a>
  <?php endforeach; ?>
  <div class="donor-sidebar-note">Thank you for supporting your community.</div>
</aside>
