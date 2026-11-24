<?php
require_once __DIR__ . '/../includes/functions.php';

$contentType = ($_GET['type'] ?? '') === 'article' ? 'article' : 'story';
$isArticle = $contentType === 'article';
$title = $isArticle ? 'Articles' : 'Stories';
$section = strtolower($title);
$items = published_content($contentType, null);

page_start($title);
?>
<main class="explore-page">
    <section class="section" id="<?= e($section) ?>">
        <div class="section-heading">
            <div>
                <span class="eyebrow"><?= $isArticle ? 'Reading and learning' : 'From the community' ?></span>
                <h1><?= e($title) ?></h1>
            </div>
            <a class="button button-gold" href="<?= e(app_url('public/explore.php')) ?>#<?= e($section) ?>">← Back to Explore</a>
        </div>
        <?php require __DIR__ . '/../includes/explore-reading-cards.php'; ?>
    </section>
</main>
<?php page_end(); ?>
