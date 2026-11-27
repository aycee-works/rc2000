<header class="site-header">
  <?php
  require_once __DIR__ . '/donor/auth.php';
  try {
      $navDonor = current_donor_user();
  } catch (Throwable $error) {
      error_log('Donor navigation state could not be loaded: ' . $error->getMessage());
      $navDonor = null;
  }
  ?>
  <a class="brand" href="<?= e(app_url('public/home.php')) ?>" aria-label="Reading Club 2000 home">
    <img class="brand-logo" src="<?= e(app_url('public/assets/images/icons/logo.png')) ?>" alt="" width="24" height="24">
    <span class="brand-name">Reading Club 2000</span>
  </a>
  <nav class="main-nav" id="public-main-nav" aria-label="Main navigation">
    <a href="<?= e(app_url('public/home.php')) ?>"<?= nav_active('home.php') ?>>Home</a>
    <a href="<?= e(app_url('public/explore.php')) ?>"<?= nav_active('explore.php') ?>>Explore</a>
    <a href="<?= e(app_url('public/donate.php')) ?>"<?= nav_active('donate.php') ?>>Donate</a>
    <a href="<?= e(app_url('public/inquiry.php')) ?>"<?= nav_active('inquiry.php') ?>>Inquiry</a>
    <a href="<?= e(app_url('public/about.php')) ?>"<?= nav_active('about.php') ?>>About</a>
  </nav>
  <div class="nav-actions">
    <div class="donor-nav-actions">
      <?php if ($navDonor): ?>
        <a class="donor-nav-greeting" href="<?= e(app_url('public/dashboard.php')) ?>">Hi, <?= e($navDonor['name']) ?> · My account</a>
        <form method="post" action="<?= e(app_url('public/logout.php')) ?>">
          <?= csrf_field() ?>
          <button type="submit">Sign out</button>
        </form>
      <?php else: ?>
        <a href="<?= e(app_url('public/login.php')) ?>">Sign in</a>
      <?php endif; ?>
    </div>
    <button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" aria-pressed="false">
      <img data-theme-icon src="<?= e(app_url('public/assets/images/icons/theme-moon.svg')) ?>" data-light-src="<?= e(app_url('public/assets/images/icons/theme-moon.svg')) ?>" data-dark-src="<?= e(app_url('public/assets/images/icons/theme-sun.svg')) ?>" alt="" aria-hidden="true" width="24" height="24">
      <span data-theme-label>Dark mode</span>
    </button>
    <button class="mobile-nav-toggle" type="button" data-mobile-nav-toggle aria-controls="public-main-nav" aria-expanded="false" hidden>
      <span aria-hidden="true">☰</span> Menu
    </button>
  </div>
</header>
