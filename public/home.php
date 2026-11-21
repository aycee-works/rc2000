<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/community.php';

$challengeNotice = '';
$challenge = current_challenge();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'challenge_vote') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $challengeNotice = 'Your session expired. Please try again.';
    } elseif ($challenge && (int) ($_POST['challenge_id'] ?? 0) === (int) $challenge['id']) {
        $choice = (string) ($_POST['choice'] ?? '');
        if (in_array($choice, ['accept', 'not_for_me'], true)) {
            try {
                $voterHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
                $stmt = db()->prepare('INSERT INTO rc2000_admin.challenge_votes (challenge_id, voter_hash, choice) VALUES (?, ?, ?) ON CONFLICT (challenge_id, voter_hash) DO NOTHING');
                $stmt->execute([(int) $challenge['id'], $voterHash, $choice]);
                $challengeNotice = $stmt->rowCount() ? 'Thanks for sharing your response.' : 'Your response has already been counted for this challenge.';
                $challenge = current_challenge();
            } catch (Throwable $e) {
                error_log('Challenge vote failed: ' . $e->getMessage());
                $challengeNotice = 'We could not save your vote right now. Please try again.';
            }
        }
    }
}
$campaign = featured_campaign();
$quizToday = daily_quiz();

/*
|--------------------------------------------------------------------------
| Retrieve published content
|--------------------------------------------------------------------------
*/

// Retrieve featured stories first.
// If no featured stories exist, display regular published stories.
$stories = published_content('featured_story', 8);

if (empty($stories)) {
    $stories = published_content('story', 8);
}

// Retrieve published articles and announcements.
$articles = published_content('article', 4);
$announcements = published_content('announcement', 6);
$trivia = daily_trivia();
$trivia = array_slice(array_merge($trivia, [
    [
        'title' => 'What is a Book’s Colophon?',
        'body' => 'It is a note that gives details about how the book was produced, such as the typeface or printer.',
    ],
    [
        'title' => 'What is a book’s spine?',
        'body' => 'It is the edge of the binding where the pages are joined. The title is often printed there so it can be read on a shelf.',
    ],
]), 0, 2);
$facebookPageUrl = facebook_page_url();

/*
|--------------------------------------------------------------------------
| Homepage images
|--------------------------------------------------------------------------
*/

$homeImages = [
    'banner' => 'home_banner.jpg',
    'stories' => [
        'stories/sfeatured1.png',
        'stories/sfeatured2.png',
        'stories/sfeatured3.png',
        'stories/sfeatured4.png',
    ],
    'articles' => [
        'articles/afeatured1.png',
        'articles/afeatured1-2.png',
        'articles/afeatured2.png',
    ],
];

/*
|--------------------------------------------------------------------------
| Homepage image helper
|--------------------------------------------------------------------------
*/

$homeImageUrl = static function (string $filename): ?string {
    $filename = ltrim($filename, '/\\');

    $filePath = __DIR__ . '/assets/images/' . $filename;

    if (is_file($filePath)) {
        return app_url('public/assets/images/' . $filename);
    }

    return null;
};

$heroImage = $homeImageUrl($homeImages['banner']);

page_start('Home', ['class' => 'home-page']);
?>

<main>

    <!-- HERO SECTION -->

    <section class="hero<?= $heroImage ? ' has-photo' : '' ?>">

        <div class="hero-copy" data-reveal>

            <h1>
                Give a Book<br>
                a Second Story.
            </h1>

            <p>
                Discover the joy of reading at Reading Club 2000, a community library founded by Mang Nanie in Barangay La Paz, Makati, where everyone is welcome to read, borrow, and take home books for free or donate books to share with others.
            </p>

            <div class="button-row">

                <a
                    class="button button-gold"
                    href="<?= e(app_url('public/donate.php')) ?>"
                >
                    Donate Now
                </a>

                <a
                    class="button button-ghost"
                    href="<?= e(app_url('public/about.php')) ?>"
                >
                    Learn More
                </a>

            </div>

        </div>

        <div class="hero-art" aria-hidden="true">

            <?php if ($heroImage): ?>

                <img
                    src="<?= e($heroImage) ?>"
                    alt=""
                >

            <?php else: ?>

                <div class="book-stack">
                    <span></span>
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <p>Stories are better when shared.</p>

            <?php endif; ?>

        </div>

    </section>

    <!-- FEATURED STORIES -->

    <section
        class="section featured-stories"
        id="featured-stories"
        data-reveal
    >

        <div class="section-heading">

            <div>
                <span class="eyebrow">From our shelves</span>
                <h2>Featured Stories</h2>
            </div>

            <a href="<?= e(app_url('public/explore.php')) ?>">
                See all →
            </a>

        </div>

        <?php if (!empty($stories)): ?>

            <div class="featured-story-grid">

                <?php foreach ($stories as $index => $item): ?>

                    <?php
                    $storyId = (int) ($item['content_id'] ?? 0);
                    $storyTitle = $item['title'] ?? 'Untitled Story';
                    $storyBody = $item['body'] ?? '';
                    $storyCover = $item['cover_image_path'] ?? '';
                    ?>

                    <a
                        class="story-card"
                        data-reveal
                        href="<?= e(app_url('public/content.php')) ?>?id=<?= $storyId ?>"
                    >

                        <?php if (
                            $storyCover !== '' &&
                            media_file_exists($storyCover)
                        ): ?>

                            <img
                                src="<?= e(media_url($storyCover)) ?>"
                                alt="<?= e($storyTitle) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <?php
                            $decorativeImage = $homeImageUrl(
                                $homeImages['stories'][
                                    $index % count($homeImages['stories'])
                                ]
                            );
                            ?>

                            <?php if ($decorativeImage): ?>

                                <img
                                    src="<?= e($decorativeImage) ?>"
                                    alt=""
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div
                                    class="card-art art-<?= ($index % 4) + 1 ?>"
                                    aria-hidden="true"
                                ></div>

                            <?php endif; ?>

                        <?php endif; ?>

                        <div class="story-card-content">

                            <h3><?= e($storyTitle) ?></h3>

                            <p>
                                <?= e(excerpt($storyBody, 110)) ?>
                            </p>

                            <span class="story-read-link">
                                Read story
                                <span aria-hidden="true">→</span>
                            </span>

                        </div>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p>No published stories are available yet.</p>

        <?php endif; ?>

    </section>


    <!-- FEATURED ARTICLES -->

    <section
        class="section"
        id="featured-articles"
        data-reveal
    >

        <div class="section-heading">

            <div>
                <span class="eyebrow">Read and discover</span>
                <h2>Featured Articles</h2>
            </div>

            <a href="<?= e(app_url('public/explore.php')) ?>#articles">
                Read more →
            </a>

        </div>

        <?php if (!empty($articles)): ?>

            <!-- Reuse the Stories card grid for a consistent appearance. -->
            <div class="featured-story-grid">

                <?php foreach ($articles as $index => $item): ?>

                    <?php
                    $articleId = (int) ($item['content_id'] ?? 0);
                    $articleTitle = $item['title'] ?? 'Untitled Article';
                    $articleBody = $item['body'] ?? '';
                    $articleCover = $item['cover_image_path'] ?? '';
                    ?>

                    <a
                        class="story-card"
                        data-reveal
                        href="<?= e(app_url('public/content.php')) ?>?id=<?= $articleId ?>"
                    >

                        <?php if (
                            $articleCover !== '' &&
                            media_file_exists($articleCover)
                        ): ?>

                            <img
                                src="<?= e(media_url($articleCover)) ?>"
                                alt="<?= e($articleTitle) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <?php
                            $decorativeImage = $homeImageUrl(
                                $homeImages['articles'][
                                    $index % count($homeImages['articles'])
                                ]
                            );
                            ?>

                            <?php if ($decorativeImage): ?>

                                <img
                                    src="<?= e($decorativeImage) ?>"
                                    alt=""
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <div
                                    class="card-art art-<?= ($index % 4) + 1 ?>"
                                    aria-hidden="true"
                                ></div>

                            <?php endif; ?>

                        <?php endif; ?>

                        <div class="story-card-content">

                            <h3><?= e($articleTitle) ?></h3>

                            <p>
                                <?= e(excerpt($articleBody, 120)) ?>
                            </p>

                            <span class="story-read-link">
                                Read article
                                <span aria-hidden="true">→</span>
                            </span>

                        </div>

                    </a>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <p>No published articles are available yet.</p>

        <?php endif; ?>

    </section>

    <?php if ($campaign): $goal = (float) $campaign['goal_amount']; $raised = (float) $campaign['raised']; $percent = $goal > 0 ? min(100, (int) round($raised / $goal * 100)) : 0; ?>
    <section class="section community-programs" id="community-programs">
        <div class="section-heading"><div><span class="eyebrow">Your support in action</span><h2>Community campaign</h2></div><span class="campaign-open-badge">Active campaign</span></div>
        <article class="campaign-summary">
            <?php if (!empty($campaign['image_path']) && media_file_exists($campaign['image_path'])): ?><img class="campaign-summary-image" src="<?= e(media_url($campaign['image_path'])) ?>" alt="<?= e($campaign['title']) ?>" loading="lazy"><?php else: ?><div class="campaign-summary-art" aria-hidden="true">RC2000</div><?php endif; ?>
            <div class="campaign-summary-copy"><h3><?= e($campaign['title']) ?></h3><p><?= nl2br(e($campaign['description'])) ?></p>
                <div class="campaign-stats"><strong>₱<?= e(number_format($raised, 2)) ?> raised</strong><span>₱<?= e(number_format($goal, 2)) ?> goal</span></div>
                <div class="campaign-progress" role="progressbar" aria-label="Campaign funding progress" aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= $percent ?>%"></span></div>
                <p class="campaign-meta"><strong><?= $percent ?>% funded</strong><span><?= (int) $campaign['donor_count'] ?> verified donors</span></p>
                <a class="button" href="<?= e(app_url('public/donate.php?tab=campaign')) ?>">Donate Now →</a>
            </div>
        </article>
    </section>
    <?php endif; ?>

    <?php if ($challenge): $votesTotal = (int) $challenge['accept_count'] + (int) $challenge['decline_count']; $acceptPct = $votesTotal ? (int) round((int) $challenge['accept_count'] / $votesTotal * 100) : 0; ?>
    <section class="section weekly-challenge" id="weekly-challenge">
        <div class="section-heading"><div><span class="eyebrow">Reading together</span><h2>This week’s reading challenge</h2></div></div>
        <div class="challenge-card">
            <?php if (!empty($challenge['image_path']) && media_file_exists($challenge['image_path'])): ?><img src="<?= e(media_url($challenge['image_path'])) ?>" alt="<?= e($challenge['title']) ?>" loading="lazy"><?php endif; ?>
            <div class="challenge-copy"><h3><?= e($challenge['title']) ?></h3><p><?= nl2br(e($challenge['prompt'])) ?></p>
                <?php if ($challengeNotice !== ''): ?><p class="challenge-notice" role="status"><?= e($challengeNotice) ?></p><?php endif; ?>
                <form method="post" class="challenge-vote-form"><?= csrf_field() ?><input type="hidden" name="action" value="challenge_vote"><input type="hidden" name="challenge_id" value="<?= (int) $challenge['id'] ?>"><button class="button challenge-accept challenge-vote-button challenge-vote-button--like" name="choice" value="accept"><img src="<?= e(app_url('public/assets/images/icons/challenge-like.png')) ?>" alt="" aria-hidden="true">I'm in</button><button class="button button-outline challenge-vote-button challenge-vote-button--dislike" name="choice" value="not_for_me"><img src="<?= e(app_url('public/assets/images/icons/challenge-dislike.png')) ?>" alt="" aria-hidden="true">Not now</button></form>
                <div class="challenge-results"><div class="challenge-donut" style="--vote-percent:<?= $acceptPct ?>%"><span><?= $acceptPct ?>%</span></div><div><strong>Community response</strong><p><span><?= (int) $challenge['accept_count'] ?> joining</span><span><?= (int) $challenge['decline_count'] ?> not now</span></p></div></div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($quizToday): ?><section class="section quiz-callout"><span class="eyebrow">Daily quiz</span><h2>Five questions, one at a time</h2><a class="button" href="<?= e(app_url('public/daily-quiz.php')) ?>">Take today’s quiz →</a></section><?php endif; ?>


    <!-- TRIVIA -->
       <section class="section tinted" id="trivia" data-reveal>
    <div class="section-heading">
      <div>
        <h2>Daily Trivia</h2>
      </div>
    </div>
    <div class="trivia-list">
      <?php $triviaIcons = ['books.svg', 'sparkles.svg', 'quill.svg', 'search-book.svg', 'lightbulb.svg', 'open-book.svg']; ?>
      <?php foreach ($trivia as $index => $item): $answerId = 'trivia-answer-' . $index; ?>
        <article class="trivia-card" data-trivia-card>
          <div class="trivia-icon" aria-hidden="true"><img src="<?= e(app_url('public/assets/images/icons/trivia/' . $triviaIcons[$index % count($triviaIcons)])) ?>" alt="" width="48" height="48"></div>
          <div class="trivia-copy">
            <h3><?= e($item['title'] ?: 'Did you know?') ?></h3>
            <button class="trivia-answer-toggle" type="button" aria-expanded="false" aria-controls="<?= e($answerId) ?>" data-trivia-toggle>Show answer</button>
            <div class="trivia-answer" id="<?= e($answerId) ?>" hidden>
              <p><?= nl2br(e($item['body'])) ?></p>
              <?php if (!empty($item['source'])): ?>
                <small>Source: <?= e($item['source']) ?></small>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

    <!-- ANNOUNCEMENTS -->

    <section
        class="section announcement-band"
        id="announcements"
        data-reveal
    >

        <div>
            <span class="eyebrow">BE UPDATED WITH RC2000</span>
            <h2>Announcements</h2>
        </div>

        <div class="announcement-slider" data-carousel>

            <div class="carousel-track">

                <?php if (!empty($announcements)): ?>

                    <?php foreach ($announcements as $item): ?>

                        <article class="announcement">

                            <h3>
                                <?= e($item['title'] ?? 'Announcement') ?>
                            </h3>

                            <?php $announcementBody = (string) ($item['body'] ?? ''); ?>
                            <p>
                                <?= e(excerpt($announcementBody, 280)) ?><?php if (mb_strlen(trim(preg_replace('/\s+/', ' ', $announcementBody) ?? $announcementBody)) > 280): ?> <a class="announcement-read-more" href="<?= e(app_url('public/announcements.php#announcement-' . (int) ($item['content_id'] ?? 0))) ?>">Read more</a><?php endif; ?>
                            </p>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <article class="announcement">

                        <p>
                            Reading events, new stories, and community
                            updates will appear here.
                        </p>

                    </article>

                <?php endif; ?>

            </div>

            <div class="carousel-controls">

                <button
                    type="button"
                    data-prev
                    aria-label="Previous announcement"
                >
                    <img data-theme-image src="<?= e(app_url('public/assets/images/icons/left-arrow-light.png')) ?>" data-light-src="<?= e(app_url('public/assets/images/icons/left-arrow-light.png')) ?>" data-dark-src="<?= e(app_url('public/assets/images/icons/left-arrow-dark.png')) ?>" alt="" aria-hidden="true">
                </button>

                <button
                    type="button"
                    data-next
                    aria-label="Next announcement"
                >
                    <img data-theme-image src="<?= e(app_url('public/assets/images/icons/arrow-light.png')) ?>" data-light-src="<?= e(app_url('public/assets/images/icons/arrow-light.png')) ?>" data-dark-src="<?= e(app_url('public/assets/images/icons/arrow-dark.png')) ?>" alt="" aria-hidden="true">
                </button>

            </div>
        </div>

    </section>

    <?php if ($facebookPageUrl !== ''): ?>
    <section class="section facebook-updates" id="facebook-updates" data-reveal>
        <div class="section-heading">
            <div>
                <span class="eyebrow">From our Facebook page</span>
                <h2>Community Updates</h2>
            </div>
            <a href="<?= e($facebookPageUrl) ?>" target="_blank" rel="noopener noreferrer">Visit our Facebook page →</a>
        </div>
        <a class="facebook-post-preview" href="<?= e($facebookPageUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Visit the Reading Club 2000 Facebook page">
            <img
                src="<?= e(app_url('public/assets/images/facebook-latest-post.png?v=2')) ?>"
                alt="Reading Club 2000 Facebook Page preview with its book-filled cover photo and profile"
                loading="lazy"
            >
            <span class="facebook-post-cta">Visit the Facebook page <span aria-hidden="true">↗</span></span>
        </a>
    </section>
    <?php endif; ?>

</main>

<?php page_end(); ?>
