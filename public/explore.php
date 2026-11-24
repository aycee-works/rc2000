<?php
require_once __DIR__ . '/../includes/functions.php';

$query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$results = $query !== ''
    ? search_published_content($query, 24)
    : [];

$gallery = active_gallery(6);
$articles = published_content('article', 3);
$stories = published_content('story', 3);

page_start('Explore');
?>

<main class="explore-page">

    <section class="page-hero explore-hero">
        <div class="section">
            <span class="eyebrow">Read, learn, discover</span>
            <h1>Explore Our Stories</h1>
            <p>Stories, articles, and activities from the RC2000 community.</p>
            <form class="explore-search" action="<?= e(app_url('public/explore.php')) ?>" method="get" role="search">
                <label class="sr-only" for="explore-query">Search stories and articles</label>
                <input id="explore-query" type="search" name="q" placeholder="Search stories and articles" value="<?= e($query) ?>">
                <button class="button" type="submit"><img src="<?= e(app_url('public/assets/images/icons/search_icon.png')) ?>" alt="" aria-hidden="true" width="18" height="18"> Search</button>
            </form>
            <nav class="explore-categories" aria-label="Explore sections"><a href="#gallery">Gallery</a><a href="#stories">Stories</a><a href="#articles">Articles</a><a href="<?= e(app_url('public/daily-quiz.php')) ?>">Daily Quiz</a><a href="<?= e(app_url('public/announcements.php')) ?>">Announcements</a></nav>
        </div>
    </section>

    <!-- SEARCH RESULTS -->

    <?php if ($query !== ''): ?>

        <section class="section">

            <div class="section-heading">

                <div>
                    <span class="eyebrow">Search</span>
                    <h2>Results for “<?= e($query) ?>”</h2>
                </div>

            </div>

            <?php if (!$results): ?>

                <p>No results matched your search.</p>

            <?php else: ?>

                <div class="card-grid three">

                    <?php foreach ($results as $item): ?>

                        <?php
                        $resultType = (string) ($item['content_type'] ?? 'story');
                        $resultTitle = $item['title'] ?? 'Untitled';
                        $resultBody = $item['body'] ?? '';
                        ?>

                        <article class="story-card">

                            <?php
                            $resultCover = $item['cover_image_path'] ?? '';
                            ?>

                            <?php if (
                                $resultCover !== '' &&
                                media_file_exists($resultCover)
                            ): ?>

                                <img
                                    src="<?= e(media_url($resultCover)) ?>"
                                    alt="<?= e($resultTitle) ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div
                                    class="card-art art-1"
                                    aria-hidden="true"
                                ></div>

                            <?php endif; ?>

                            <div class="story-card-content">

                                <p class="eyebrow">
                                    <?= e(str_replace('_', ' ', $resultType)) ?>
                                </p>

                                <h2><?= e($resultTitle) ?></h2>

                                <p>
                                    <?= e(excerpt($resultBody, 160)) ?>
                                </p>

                                <a
                                    class="text-link"
                                    href="<?= e(app_url('public/content.php') . '?id=' . (int) $item['content_id']) ?>"
                                >
                                    Read more →
                                </a>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>


    <!-- GALLERY -->

    <section class="section" id="gallery">

        <div class="section-heading">

            <div>
                <span class="eyebrow">Around the library</span>
                <h1>Gallery</h1>
            </div>

            <a href="<?= e(app_url('public/gallery.php')) ?>">
                Open gallery →
            </a>

        </div>

        <?php if ($gallery): ?>
            <div class="gallery-grid explore-gallery-grid">
                <?php foreach ($gallery as $image): ?>
                    <figure>
                        <img
                            src="<?= e(media_url($image['image_path'])) ?>"
                            alt="<?= e($image['alt_text'] ?? '') ?>"
                            loading="lazy"
                        >
                        <?php if (!empty($image['caption'])): ?>
                            <figcaption><?= e($image['caption']) ?></figcaption>
                        <?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No gallery photos are available yet.</p>
        <?php endif; ?>

    </section>


    <!-- STORIES -->

    <section class="section" id="stories">

        <div class="section-heading">

            <div>
                <span class="eyebrow">From the community</span>
                <h1>Stories</h1>
                <p>Discover stories shared by Reading Club 2000.</p>
            </div>
            <a href="<?= e(app_url('public/reading.php') . '?type=story') ?>">Open stories →</a>

        </div>

        <?php
        $items = $stories;
        $contentType = 'story';
        require __DIR__ . '/../includes/explore-reading-cards.php';
        ?>

    </section>


    <!-- ARTICLES -->

    <section class="section" id="articles">

        <div class="section-heading">

            <div>
                <span class="eyebrow">Reading and learning</span>
                <h1>Articles</h1>
                <p>Explore articles published by Reading Club 2000.</p>
            </div>
            <a href="<?= e(app_url('public/reading.php') . '?type=article') ?>">Open articles →</a>

        </div>

        <?php
        $items = $articles;
        $contentType = 'article';
        require __DIR__ . '/../includes/explore-reading-cards.php';
        ?>

    </section>


</main>

<?php page_end(); ?>
