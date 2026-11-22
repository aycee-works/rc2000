<?php
// admin/content/index.php - Manage content: Gallery, Stories, Articles, Announcements, Trivia, Challenges and Quizzes
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/content-service.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$gallery = $stories = $announcements = $trivia = [];
$counts = ['gallery' => 0, 'story' => 0, 'announcement' => 0, 'trivia' => 0];
$loadError = null;

try {
    $pdo = db();
    $gallery       = list_gallery($pdo);
    $stories       = list_stories($pdo);
    $announcements = list_announcements($pdo);
    $trivia        = list_trivia($pdo);
    foreach ($counts as $entity => $_) {
        $counts[$entity] = content_count($pdo, $entity);
    }
} catch (Throwable $e) {
    error_log('Content load failed: ' . $e->getMessage());
    $loadError = 'The content could not be loaded. Please check the database connection and try again.';
}

$communityFeaturesAvailable = false;
$challengeRows = $quizRows = [];
$activeChallenge = [];
$challengeEditId = 0;
$challengeEditorOpen = isset($_GET['challenge']) || isset($_GET['new_challenge']);
$quizEditorOpen = isset($_GET['quiz']) || isset($_GET['new_quiz']);
$quizEditId = (int) ($_GET['quiz'] ?? 0);
$quizFormDate = isset($_GET['new_quiz']) ? date('Y-m-d', strtotime('+1 day')) : date('Y-m-d');
$quizFormStatus = 'published';
$quizFormQuestions = array_fill(0, 5, ['question' => '', 'options' => ['', ''], 'correct' => 0]);
$todayQuiz = null;
try {
    $challengeRows = $pdo->query("SELECT c.*,COUNT(v.id) FILTER(WHERE v.choice='accept') AS accepts,COUNT(v.id) FILTER(WHERE v.choice='not_for_me') AS declines FROM rc2000_admin.challenge_posts c LEFT JOIN rc2000_admin.challenge_votes v ON v.challenge_id=c.id GROUP BY c.id ORDER BY c.starts_at DESC LIMIT 10")->fetchAll();
    $quizRows = $pdo->query("SELECT q.id,q.quiz_date,q.status,COUNT(s.id) AS submissions,p.quiz_id AS leaderboard_source_quiz_id,(p.quiz_id IS NOT NULL) AS posted
        FROM rc2000_admin.daily_quizzes q
        LEFT JOIN LATERAL (
            SELECT lp.quiz_id
            FROM rc2000_admin.quiz_leaderboard_posts lp
            WHERE lp.week_start=date_trunc('week',q.quiz_date::timestamp)::date OR lp.quiz_id=q.id
            ORDER BY lp.posted_at DESC
            LIMIT 1
        ) p ON TRUE
        LEFT JOIN rc2000_admin.quiz_submissions s ON s.quiz_id=q.id
        GROUP BY q.id,p.quiz_id
        ORDER BY q.quiz_date DESC LIMIT 10")->fetchAll();
    $activeChallenge = $pdo->query("SELECT * FROM rc2000_admin.challenge_posts WHERE status='published' AND starts_at<=now() AND ends_at>now() ORDER BY starts_at DESC LIMIT 1")->fetch() ?: [];
    $challengeEditId = (int) ($_GET['challenge'] ?? (isset($_GET['new_challenge']) ? 0 : ($activeChallenge['id'] ?? 0)));
    if (isset($_GET['new_challenge'])) { $activeChallenge = []; }
    if ($challengeEditId > 0) {
        $challengeStmt = $pdo->prepare('SELECT * FROM rc2000_admin.challenge_posts WHERE id=?');
        $challengeStmt->execute([$challengeEditId]);
        $activeChallenge = $challengeStmt->fetch() ?: $activeChallenge;
    }
    foreach ($challengeRows as $challengeRow) {
        if ((int) $challengeRow['id'] === (int) ($activeChallenge['id'] ?? 0)) {
            $activeChallenge['accepts'] = (int) $challengeRow['accepts'];
            $activeChallenge['declines'] = (int) $challengeRow['declines'];
            break;
        }
    }
    $pastChallengeRows = array_values(array_filter($challengeRows, static fn($row) => (int) $row['id'] !== (int) ($activeChallenge['id'] ?? 0)));
    if (isset($_GET['new_quiz'])) {
        $usedDates = $pdo->query("SELECT quiz_date::text FROM rc2000_admin.daily_quizzes WHERE quiz_date >= (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date")->fetchAll(PDO::FETCH_COLUMN);
        $usedDates = array_fill_keys($usedDates, true);
        $candidateDate = new DateTimeImmutable('tomorrow', new DateTimeZone('Asia/Manila'));
        while (isset($usedDates[$candidateDate->format('Y-m-d')])) {
            $candidateDate = $candidateDate->modify('+1 day');
        }
        $quizFormDate = $candidateDate->format('Y-m-d');
    }
    $todayStmt = $pdo->query("SELECT id, quiz_date, status FROM rc2000_admin.daily_quizzes WHERE quiz_date=(CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date LIMIT 1");
    $todayQuiz = $todayStmt->fetch() ?: null;
    if ($quizEditId === 0 && $todayQuiz && !isset($_GET['new_quiz'])) { $quizEditId = (int) $todayQuiz['id']; }
    if ($quizEditId > 0) {
        $quizStmt = $pdo->prepare('SELECT id,quiz_date,status FROM rc2000_admin.daily_quizzes WHERE id=?');
        $quizStmt->execute([$quizEditId]);
        $quizToEdit = $quizStmt->fetch();
        if ($quizToEdit) {
            $quizFormDate = (string) $quizToEdit['quiz_date'];
            $quizFormStatus = (string) $quizToEdit['status'];
            $questionStmt = $pdo->prepare('SELECT question,options,correct_option FROM rc2000_admin.daily_quiz_questions WHERE quiz_id=? ORDER BY question_order');
            $questionStmt->execute([$quizEditId]);
            $quizFormQuestions = [];
            foreach ($questionStmt->fetchAll() as $index => $questionRow) {
                $options = json_decode((string) $questionRow['options'], true) ?: [];
                $quizFormQuestions[] = ['question' => (string) $questionRow['question'], 'options' => array_values($options), 'correct' => (int) $questionRow['correct_option']];
            }
            while (count($quizFormQuestions) < 5) { $quizFormQuestions[] = ['question' => '', 'options' => ['', ''], 'correct' => 0]; }
        }
    }
    $communityFeaturesAvailable = true;
} catch (Throwable $e) {
    error_log('Community content tools unavailable: ' . $e->getMessage());
}

function form_url(string $entity, int $id = 0, string $kind = ''): string
{
    $p = ['entity' => $entity];
    if ($id > 0) { $p['id'] = $id; }
    if ($kind !== '') { $p['kind'] = $kind; }
    return url('admin/content/form.php?' . http_build_query($p));
}

/** "Archive" for published items, "Publish" for everything else. */
function toggle_button(string $entity, array $row): string
{
    $publish = $row['status'] !== 'published';
    return '<button type="button" class="link-button" data-set-status data-entity="' . e($entity)
         . '" data-id="' . (int) $row['id'] . '" data-status="' . ($publish ? 'published' : 'archived') . '">'
         . ($publish ? 'Publish' : 'Archive') . '</button>';
}

function limit_note(int $shown, int $total): string
{
    return $total > $shown ? '<p class="panel-note">Showing the newest ' . $shown . ' of ' . $total . '.</p>' : '';
}

$pageTitle = 'Manage content';
$activeNav = 'content';
$extraCss  = ['content.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/content.css'), 'community.css'];
$extraJs   = ['content.js?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/js/content.js')];
require dirname(__DIR__, 3) . '/includes/portal/admin-header.php';
?>
<header class="page-header">
    <h1 class="page-title">Manage Content</h1>
    <p class="page-subtitle">Update the public site and reading activities.</p>
</header>

<?php if ($loadError): ?>
    <div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div>
<?php endif; ?>

<div class="content-tabs" role="tablist" aria-label="Content areas">
    <button type="button" class="content-tab" id="website-content-tab" role="tab" aria-selected="false" aria-controls="website-content-panel" data-content-tab="website" tabindex="-1">Website Content</button>
    <button type="button" class="content-tab is-active" id="reading-activities-tab" role="tab" aria-selected="true" aria-controls="reading-activities-panel" data-content-tab="activities">Reading Activities</button>
</div>
<div class="content-grid website-content-grid" id="website-content-panel" data-content-panel="website" role="tabpanel" aria-labelledby="website-content-tab" hidden>

    <!-- Gallery -->
    <section class="panel" aria-labelledby="gal-title">
        <div class="panel-header"><h2 class="panel-title" id="gal-title">Gallery</h2></div>
        <div class="panel-body">
            <div class="gallery-grid">
                <?php foreach ($gallery as $g): ?>
                    <button type="button" class="gallery-tile" data-gallery-edit
                            data-id="<?= (int) $g['id'] ?>" data-title="<?= e($g['title']) ?>"
                            data-caption="<?= e($g['caption'] ?? '') ?>" data-status="<?= e($g['status']) ?>"
                            data-version="<?= e($g['updated_at']) ?>" data-src="<?= e(portal_media_url($g['file_path'])) ?>"
                            title="<?= e($g['title']) ?>">
                        <img src="<?= e(portal_media_url($g['file_path'])) ?>" alt="<?= e($g['title']) ?>" loading="lazy">
                        <?php if ($g['status'] !== 'published'): ?>
                            <span class="tile-badge"><?= e(CONTENT_STATUSES[$g['status']]) ?></span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>
                <button type="button" class="gallery-tile gallery-add" data-gallery-new aria-label="Add a gallery image">+</button>
            </div>
            <?= limit_note(count($gallery), $counts['gallery']) ?>
        </div>
    </section>

    <!-- Stories and articles -->
    <section class="panel" aria-labelledby="story-title">
        <div class="panel-header">
            <h2 class="panel-title" id="story-title">Stories and Articles</h2>
            <span class="panel-buttons">
                <a class="btn btn-maroon btn-sm" href="<?= e(form_url('story')) ?>">New Story</a>
                <a class="btn btn-light btn-sm" href="<?= e(form_url('story', 0, 'article')) ?>">New Article</a>
            </span>
        </div>
        <ul class="item-list">
            <?php if (!$stories): ?><li class="item-empty">No stories or articles yet.</li><?php endif; ?>
            <?php foreach ($stories as $s): ?>
                <li class="item-row">
                    <div class="item-main">
                        <span class="item-title" title="<?= e($s['title']) ?>"><?= e($s['title']) ?></span>
                        <span class="item-sub"><?= $s['type'] === 'article' ? 'Article' : 'Story' ?> · <?= e(format_date($s['published_at'] ?: $s['created_at'])) ?></span>
                    </div>
                    <?= status_badge($s['status'], 'content') ?>
                    <span class="item-actions"><a href="<?= e(form_url('story', (int) $s['id'])) ?>">Edit</a> · <?= toggle_button('story', $s) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= limit_note(count($stories), $counts['story']) ?>
    </section>

    <!-- Announcements -->
    <section class="panel" aria-labelledby="ann-title">
        <div class="panel-header">
            <h2 class="panel-title" id="ann-title">Announcements</h2>
            <a class="btn btn-maroon btn-sm" href="<?= e(form_url('announcement')) ?>">New Announcement</a>
        </div>
        <ul class="item-list">
            <?php if (!$announcements): ?><li class="item-empty">No announcements yet.</li><?php endif; ?>
            <?php foreach ($announcements as $a): ?>
                <li class="item-row">
                    <div class="item-main">
                        <span class="item-title" title="<?= e($a['title']) ?>"><?= e($a['title']) ?></span>
                        <span class="item-sub"><?= e(format_date($a['published_at'] ?: $a['created_at'])) ?></span>
                    </div>
                    <?= status_badge($a['status'], 'content') ?>
                    <span class="item-actions"><a href="<?= e(form_url('announcement', (int) $a['id'])) ?>">Edit</a> · <?= toggle_button('announcement', $a) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= limit_note(count($announcements), $counts['announcement']) ?>
    </section>

    <!-- Trivia -->
    <section class="panel" aria-labelledby="triv-title">
        <div class="panel-header">
            <h2 class="panel-title" id="triv-title">Trivia</h2>
            <a class="btn btn-maroon btn-sm" href="<?= e(form_url('trivia')) ?>">Add Trivia</a>
        </div>
        <ul class="item-list">
            <?php if (!$trivia): ?><li class="item-empty">No trivia yet.</li><?php endif; ?>
            <?php foreach ($trivia as $t): ?>
                <li class="item-row">
                    <div class="item-main"><span class="item-title" title="<?= e($t['question']) ?>"><?= e($t['question']) ?></span></div>
                    <?= status_badge($t['status'], 'content') ?>
                    <span class="item-actions"><a href="<?= e(form_url('trivia', (int) $t['id'])) ?>">Edit</a> · <?= toggle_button('trivia', $t) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?= limit_note(count($trivia), $counts['trivia']) ?>
    </section>

    </div>
<div class="content-grid reading-activities-grid" id="reading-activities-panel" data-content-panel="activities" role="tabpanel" aria-labelledby="reading-activities-tab">
    <?php if ($communityFeaturesAvailable): ?>
    <section class="panel activity-card" id="weekly-challenge" aria-labelledby="challenge-title">
        <div class="panel-header activity-card-header"><h2 class="panel-title" id="challenge-title">Weekly Reading Challenge</h2><span class="activity-status <?= e(($activeChallenge['status'] ?? '') === 'published' ? 'is-published' : (($activeChallenge['status'] ?? '') === 'closed' ? 'is-archived' : 'is-draft')) ?>"><?= e(($activeChallenge['status'] ?? '') === 'closed' ? 'Archived' : ($activeChallenge['status'] ?? 'Not scheduled')) ?></span></div>
        <div class="activity-feature">
            <?php if (!empty($activeChallenge['image_path'])): ?><img class="activity-art challenge-art" src="<?= e(portal_media_url($activeChallenge['image_path'])) ?>" alt="Challenge artwork">
            <?php else: ?><div class="activity-art challenge-art placeholder-art" aria-hidden="true"><span>▤</span></div><?php endif; ?>
            <div class="activity-feature-copy"><h3><?= e($activeChallenge['title'] ?? 'No challenge scheduled') ?></h3><p><?= e($activeChallenge['prompt'] ?? 'Create a weekly prompt to invite the community to read together.') ?></p>
                <div class="activity-facts"><?php if (!empty($activeChallenge['starts_at'])): ?><span>▢ &nbsp;<?= e(format_date_short($activeChallenge['starts_at'])) ?> – <?= e(format_date_short($activeChallenge['ends_at'])) ?></span><?php endif; ?><span>♧ &nbsp;<?= (int) ($activeChallenge['accepts'] ?? 0) ?> joining &nbsp;·&nbsp; <?= (int) ($activeChallenge['declines'] ?? 0) ?> not now</span></div>
            </div>
        </div>
        <div class="activity-card-actions">
            <button class="btn btn-maroon" type="button" data-editor-toggle="challenge-editor" data-open-label="Edit challenge" data-close-label="Cancel" aria-controls="challenge-editor" aria-expanded="<?= $challengeEditorOpen ? 'true' : 'false' ?>"><?= $challengeEditorOpen ? 'Cancel' : ($challengeEditId ? 'Edit challenge' : 'Create challenge') ?></button>
            <?php if (!empty($activeChallenge['id']) && ($activeChallenge['status'] ?? '') !== 'closed'): ?>
                <form method="post" action="<?= e(url('admin/community.php')) ?>" onsubmit="return confirm('Archive this weekly challenge? It will be removed from the public site and kept in Archive.');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="archive_challenge">
                    <input type="hidden" name="id" value="<?= (int) $activeChallenge['id'] ?>">
                    <button class="btn btn-light" type="submit">Archive challenge</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="activity-editor" id="challenge-editor" <?= $challengeEditorOpen ? '' : 'hidden' ?>>
        <form class="panel-body community-form" method="post" enctype="multipart/form-data" action="<?= e(url('admin/community.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_challenge"><input type="hidden" name="id" value="<?= (int) $challengeEditId ?>">
            <label>Challenge title<input name="title" maxlength="160" required value="<?= e($activeChallenge['title'] ?? '') ?>"></label>
            <label>Challenge prompt<textarea name="prompt" rows="3" maxlength="3000" required><?= e($activeChallenge['prompt'] ?? '') ?></textarea></label>
            <label>Challenge image (JPG, PNG or WebP)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
            <div class="community-inline-fields">
                <label>Starts at<input type="datetime-local" name="starts_at" required value="<?= e(isset($activeChallenge['starts_at']) ? date('Y-m-d\TH:i', strtotime($activeChallenge['starts_at'])) : date('Y-m-d\TH:i')) ?>"></label>
                <label>Ends at<input type="datetime-local" name="ends_at" required value="<?= e(isset($activeChallenge['ends_at']) ? date('Y-m-d\TH:i', strtotime($activeChallenge['ends_at'])) : date('Y-m-d\TH:i', strtotime('+7 days'))) ?>"></label>
            </div>
            <label>Visibility<select name="status"><option value="published" <?= ($activeChallenge['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Publish</option><option value="draft" <?= ($activeChallenge['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Save draft</option><option value="closed" <?= ($activeChallenge['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Archived</option></select></label>
            <button class="btn btn-maroon" type="submit">Save challenge</button>
        </form></div>
        <?php if ($pastChallengeRows): ?>
            <div class="activity-previous">
                <button class="link-button" type="button" data-editor-toggle="previous-challenges" data-open-label="Previous challenges (<?= count($pastChallengeRows) ?>)" data-close-label="Hide previous challenges" aria-controls="previous-challenges" aria-expanded="false">Previous challenges (<?= count($pastChallengeRows) ?>)</button>
                <div id="previous-challenges" hidden>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead><tr><th>Challenge</th><th>Dates</th><th>Responses</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($pastChallengeRows as $challengeRow): ?>
                                <tr>
                                    <td><?= e($challengeRow['title']) ?> · <?= e($challengeRow['status'] === 'closed' ? 'Archived' : $challengeRow['status']) ?></td>
                                    <td><?= e(format_date_short($challengeRow['starts_at'])) ?>–<?= e(format_date_short($challengeRow['ends_at'])) ?></td>
                                    <td><?= (int) $challengeRow['accepts'] ?> joining · <?= (int) $challengeRow['declines'] ?> not now</td>
                                    <td>
                                        <a href="<?= e(url('admin/content/index.php?challenge=' . (int) $challengeRow['id'] . '#weekly-challenge')) ?>">Edit</a>
                                        <?php if ($challengeRow['status'] !== 'closed'): ?>
                                            <form method="post" class="inline-form" action="<?= e(url('admin/community.php')) ?>" onsubmit="return confirm('Archive this weekly challenge? It will be removed from the public site and kept in Archive.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="archive_challenge">
                                                <input type="hidden" name="id" value="<?= (int) $challengeRow['id'] ?>">
                                                <button class="link-button" type="submit">Archive</button>
                                            </form>
                                        <?php else: ?>
                                            · Archived
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel activity-card" id="daily-quiz" aria-labelledby="quiz-title">
        <div class="panel-header activity-card-header"><h2 class="panel-title" id="quiz-title">Daily Quiz</h2><span class="activity-status <?= e(($todayQuiz['status'] ?? '') === 'published' ? 'is-published' : 'is-draft') ?>"><?= e($todayQuiz['status'] ?? 'Not scheduled') ?></span></div>
        <div class="activity-feature quiz-feature"><div class="activity-art quiz-art" aria-hidden="true"><svg viewBox="0 0 100 100" role="presentation"><rect x="17" y="10" width="66" height="80" rx="8" fill="#fff"/><g fill="none" stroke="#244666" stroke-width="3"><path d="M30 31l5 5 9-10M49 32h22M30 52l5 5 9-10M49 53h22M30 73l5 5 9-10M49 74h22"/></g></svg></div><div class="activity-feature-copy"><p><?= $todayQuiz ? 'A five-question quiz is scheduled for ' . e(format_date_short($todayQuiz['quiz_date'])) . '.' : 'Prepare a five-question quiz for the public site. You can create it now and schedule it for a future date.' ?></p></div></div>
        <div class="activity-card-actions">
            <button class="btn btn-maroon" type="button" data-editor-toggle="quiz-editor" data-open-label="<?= $todayQuiz ? 'Edit today’s quiz' : 'Create today’s quiz' ?>" data-close-label="Cancel" aria-controls="quiz-editor" aria-expanded="<?= $quizEditorOpen ? 'true' : 'false' ?>"><?= $quizEditorOpen ? 'Cancel' : ($todayQuiz ? 'Edit today’s quiz' : 'Create today’s quiz') ?></button>
            <a class="btn btn-light" href="<?= e(url('admin/content/index.php?new_quiz=1#daily-quiz')) ?>">Create scheduled quiz</a>
        </div>
        <div class="activity-editor" id="quiz-editor" <?= $quizEditorOpen ? '' : 'hidden' ?>>
        <form class="panel-body community-form" method="post" action="<?= e(url('admin/community.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_quiz"><input type="hidden" name="id" value="<?= (int) $quizEditId ?>">
            <label>Quiz date<input type="date" name="quiz_date" required value="<?= e($quizFormDate) ?>"><small>Each date has its own quiz. Future published quizzes will appear automatically on their scheduled Manila date.</small></label>
            <div class="quiz-question-maker" data-quiz-question-maker>
                <?php foreach ($quizFormQuestions as $questionIndex => $quizQuestion): ?>
                <fieldset class="quiz-question-editor" data-question-editor>
                    <legend>Question <?= $questionIndex + 1 ?></legend>
                    <label>Question text<input type="text" name="questions[<?= $questionIndex ?>][question]" maxlength="1000" required value="<?= e($quizQuestion['question']) ?>" placeholder="Write the question"></label>
                    <div class="quiz-options-maker" data-option-list>
                        <?php foreach ($quizQuestion['options'] as $optionIndex => $optionText): ?>
                        <div class="quiz-option-maker" data-option-row>
                            <label class="quiz-correct-choice"><input type="radio" name="questions[<?= $questionIndex ?>][correct]" value="<?= $optionIndex ?>" <?= $optionIndex === (int) $quizQuestion['correct'] ? 'checked' : '' ?> required aria-label="Mark option <?= $optionIndex + 1 ?> as correct"><span>Correct</span></label>
                            <input type="text" name="questions[<?= $questionIndex ?>][options][]" maxlength="500" required value="<?= e($optionText) ?>" placeholder="Answer option <?= $optionIndex + 1 ?>">
                            <button type="button" class="quiz-option-remove" data-option-remove aria-label="Remove option <?= $optionIndex + 1 ?>" <?= count($quizQuestion['options']) <= 2 ? 'disabled' : '' ?>>Remove</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-light btn-sm quiz-option-add" data-option-add <?= count($quizQuestion['options']) >= 5 ? 'disabled' : '' ?>>Add answer option</button>
                </fieldset>
                <?php endforeach; ?>
            </div>
            <label>Publication<select name="quiz_status"><option value="published" <?= $quizFormStatus === 'published' ? 'selected' : '' ?>>Publish for this date</option><option value="draft" <?= $quizFormStatus === 'draft' ? 'selected' : '' ?>>Save as draft</option></select></label>
            <button class="btn btn-maroon" type="submit">Save quiz</button>
        </form></div>
    </section>
    <section class="panel community-content-panel activity-history" aria-labelledby="quiz-history-title"><div class="panel-header"><div><span class="activity-eyebrow">SCHEDULE AND RESULTS</span><h2 class="panel-title" id="quiz-history-title">Recent quizzes</h2></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Date</th><th>Status</th><th>Entries</th><th>Weekly Leaderboard</th><th>Action</th></tr></thead><tbody>
            <?php foreach ($quizRows as $quizRow): ?><tr><td><a href="<?= e(url('admin/content/index.php?quiz=' . (int) $quizRow['id'] . '#daily-quiz')) ?>"><?= e($quizRow['quiz_date']) ?></a></td><td><?= e($quizRow['status']) ?></td><td><?= (int) $quizRow['submissions'] ?></td><td><?php if (!$quizRow['posted'] && (int) $quizRow['submissions'] > 0): ?><form method="post" action="<?= e(url('admin/community.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="publish_leaderboard"><input type="hidden" name="quiz_id" value="<?= (int) $quizRow['id'] ?>"><button class="link-button" type="submit">Publish results</button></form><?php else: ?><?= !empty($quizRow['posted']) ? 'Published' : '—' ?><?php endif; ?></td><td><?php if (!empty($quizRow['posted']) && (int) $quizRow['leaderboard_source_quiz_id'] === (int) $quizRow['id']): ?><form method="post" action="<?= e(url('admin/community.php')) ?>" onsubmit="return confirm('Move this weekly leaderboard article back to Draft? Quiz submissions will remain unchanged.');"><?= csrf_field() ?><input type="hidden" name="action" value="unpublish_leaderboard"><input type="hidden" name="quiz_id" value="<?= (int) $quizRow['id'] ?>"><button class="link-button" type="submit">Unpublish results</button></form><?php else: ?>—<?php endif; ?></td></tr><?php endforeach; ?>
            <?php if (!$quizRows): ?><tr><td colspan="5" class="table-empty"><span aria-hidden="true">▤</span><br>No quizzes scheduled yet.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    <?php else: ?>
    <section class="panel community-content-panel"><div class="panel-body"><h2>Weekly Challenges and Quizzes</h2><p>Run <code>database/community_features.sql</code> to enable these content tools.</p></div></section>
    <?php endif; ?>
</div>

<!-- Gallery add/edit popup -->
<dialog id="gallery-dialog" class="dialog" aria-labelledby="gallery-dialog-title">
    <form id="gallery-form" novalidate>
        <h2 class="dialog-title" id="gallery-dialog-title">Add Image</h2>
        <input type="hidden" name="id" value="0">
        <input type="hidden" name="version" value="">

        <img id="gallery-preview" class="gallery-preview" alt="" hidden>

        <div class="field">
            <label class="field-label-sm" for="gal-image" id="gal-image-label">Image (JPG, PNG or WebP, max 5 MB)</label>
            <input class="input" type="file" id="gal-image" name="image" accept="image/jpeg,image/png,image/webp">
        </div>
        <div class="field">
            <label class="field-label-sm" for="gal-title-input">Title</label>
            <input class="input" type="text" id="gal-title-input" name="title" maxlength="150" required>
        </div>
        <div class="field">
            <label class="field-label-sm" for="gal-caption">Caption</label>
            <input class="input" type="text" id="gal-caption" name="caption" maxlength="255">
        </div>
        <div class="field">
            <label class="field-label-sm" for="gal-status">Status</label>
            <select class="select" id="gal-status" name="status">
                <?php foreach (CONTENT_STATUSES as $key => $label): ?>
                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flash flash-error" id="gallery-message" role="alert" hidden></div>
        <div class="dialog-actions">
            <button type="button" class="btn btn-red" id="gallery-delete" hidden>Delete</button>
            <span class="spacer"></span>
            <button type="button" class="btn btn-light" id="gallery-cancel">Cancel</button>
            <button type="submit" class="btn btn-dark" id="gallery-save">Save</button>
        </div>
    </form>
</dialog>
<?php require dirname(__DIR__, 3) . '/includes/portal/admin-footer.php'; ?>
