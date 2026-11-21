<?php
require_once __DIR__ . '/../includes/functions.php';

$announcements = published_content('announcement', 100);
page_start('Announcements');
?>
<main>
  <section class="section announcements-page">
    <div class="section-heading">
      <div>
        <span class="eyebrow">Be updated with RC2000</span>
        <h1>Announcements</h1>
        <p>News, events, and updates from the Reading Club 2000 community.</p>
      </div>
      <a class="text-link" href="<?= e(app_url('public/explore.php')) ?>">← Back to Explore</a>
    </div>
    <?php if ($announcements): ?>
      <div class="announcements-list">
        <?php foreach ($announcements as $item): ?>
          <article class="announcement-page-card" id="announcement-<?= (int) ($item['content_id'] ?? 0) ?>">
            <h2><?= e($item['title'] ?? 'Announcement') ?></h2>
            <?php if (!empty($item['published_at'])): ?><time datetime="<?= e(date('c', strtotime((string) $item['published_at']))) ?>"><?= e(date('M j, Y', strtotime((string) $item['published_at']))) ?></time><?php endif; ?>
            <p><?= nl2br(e((string) ($item['body'] ?? ''))) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p>There are no published announcements at the moment.</p>
    <?php endif; ?>
  </section>
</main>
<?php page_end(); ?>
