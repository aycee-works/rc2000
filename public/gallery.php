<?php
require_once __DIR__ . '/../includes/functions.php';

$images = active_gallery(null);
page_start('Gallery');
?>
<main class="section explore-gallery-page">
  <div class="section-heading">
    <div>
      <span class="eyebrow">Around the library</span>
      <h1>Gallery</h1>
      <p>People, books, and moments from Reading Club 2000.</p>
    </div>
    <a class="button button-gold" href="<?= e(app_url('public/explore.php')) ?>#gallery">← Back to Explore</a>
  </div>
  <?php if ($images): ?>
    <div class="gallery-grid explore-gallery-grid">
    <?php foreach ($images as $image): ?>
      <figure>
        <img src="<?= e(media_url($image['image_path'])) ?>" alt="<?= e($image['alt_text'] ?? '') ?>" loading="lazy">
        <?php if (!empty($image['caption'])): ?>
          <figcaption><?= e($image['caption']) ?></figcaption>
        <?php endif; ?>
      </figure>
    <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p>No gallery photos are available yet.</p>
  <?php endif; ?>
</main>
<?php page_end(); ?>
