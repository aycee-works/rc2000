<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/community.php';

$quiz = daily_quiz();
$questions = [];
$errors = [];
$result = null;
$pendingKey = 'daily_quiz_pending';
$completeKey = 'daily_quiz_complete';

if ($quiz) {
    $stmt = db()->prepare('SELECT id, question_order, question, options FROM rc2000_admin.daily_quiz_questions WHERE quiz_id = ? ORDER BY question_order');
    $stmt->execute([(int) $quiz['id']]);
    $questions = $stmt->fetchAll();
    foreach ($questions as &$question) {
        $question['options'] = json_decode((string) $question['options'], true) ?: [];
    }
    unset($question);
}

$quizId = (int) ($quiz['id'] ?? 0);
$pending = $_SESSION[$pendingKey] ?? null;
$completed = $_SESSION[$completeKey] ?? null;
if (!$quiz || (is_array($pending) && (int) ($pending['quiz_id'] ?? 0) !== $quizId)) {
    unset($_SESSION[$pendingKey]);
    $pending = null;
}
if (!$quiz || (is_array($completed) && (int) ($completed['quiz_id'] ?? 0) !== $quizId)) {
    unset($_SESSION[$completeKey]);
    $completed = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz) {
    $action = (string) ($_POST['action'] ?? '');
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif (count($questions) !== 5) {
        $errors[] = 'Today’s quiz is being prepared. Please check back soon.';
    } elseif ($action === 'calculate_score') {
        $answers = $_POST['answers'] ?? [];
        $score = 0;
        foreach ($questions as $question) {
            $selected = filter_var($answers[(string) $question['id']] ?? null, FILTER_VALIDATE_INT);
            if ($selected === false || !array_key_exists((int) $selected, $question['options'])) {
                $errors[] = 'Please answer all five questions.';
                break;
            }
            $correct = db()->prepare('SELECT correct_option FROM rc2000_admin.daily_quiz_questions WHERE id = ?');
            $correct->execute([(int) $question['id']]);
            if ((int) $correct->fetchColumn() === (int) $selected) $score++;
        }
        if (!$errors) {
            $_SESSION[$pendingKey] = ['quiz_id' => $quizId, 'score' => $score];
            unset($_SESSION[$completeKey]);
            header('Location: ' . app_url('public/daily-quiz.php?result=1'));
            exit;
        }
    } elseif ($action === 'join_leaderboard' || $action === 'skip_leaderboard') {
        $pending = $_SESSION[$pendingKey] ?? null;
        if (!is_array($pending) || (int) ($pending['quiz_id'] ?? 0) !== $quizId) {
            $errors[] = 'Please complete the quiz before continuing.';
        } elseif ($action === 'skip_leaderboard') {
            $_SESSION[$completeKey] = ['quiz_id' => $quizId, 'score' => (int) $pending['score'], 'joined' => false];
            unset($_SESSION[$pendingKey]);
            header('Location: ' . app_url('public/daily-quiz.php?result=1'));
            exit;
        } else {
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $username = trim((string) ($_POST['username'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) $errors[] = 'Enter a valid email address.';
            if (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) $errors[] = 'Username must be 3–40 letters, numbers, or underscores.';
            if (!$errors) {
                try {
                    $save = db()->prepare('INSERT INTO rc2000_admin.quiz_submissions (quiz_id, email, username, score) VALUES (?, ?, ?, ?)');
                    $save->execute([$quizId, $email, $username, (int) $pending['score']]);
                    $_SESSION[$completeKey] = ['quiz_id' => $quizId, 'score' => (int) $pending['score'], 'joined' => true, 'username' => $username];
                    unset($_SESSION[$pendingKey]);
                    header('Location: ' . app_url('public/daily-quiz.php?result=1'));
                    exit;
                } catch (PDOException $e) {
                    if ($e->getCode() === '23505') $errors[] = 'This email or username has already entered today’s quiz.';
                    else { error_log('Quiz submission failed: ' . $e->getMessage()); $errors[] = 'Your score could not be saved. Please try again.'; }
                }
            }
        }
    }
}

$pending = $_SESSION[$pendingKey] ?? null;
$completed = $_SESSION[$completeKey] ?? null;
if (is_array($pending) && (int) ($pending['quiz_id'] ?? 0) === $quizId) {
    $result = ['score' => (int) $pending['score'], 'joined' => false];
} elseif (is_array($completed) && (int) ($completed['quiz_id'] ?? 0) === $quizId) {
    $result = $completed;
}

$leaders = [];
if ($quiz) {
    $stmt = db()->prepare('SELECT username, score FROM rc2000_admin.quiz_submissions WHERE quiz_id = ? ORDER BY score DESC, created_at ASC LIMIT 10');
    $stmt->execute([$quizId]);
    $leaders = $stmt->fetchAll();
}
page_start('Daily quiz', ['class' => 'daily-quiz-page']);
?>
<main class="section quiz-page">
  <header class="quiz-page-header"><span class="eyebrow">Reading together · daily quiz</span><h1>Five Questions, One at a Time</h1><p>Answer all five questions to reveal your score. Joining the leaderboard is optional.</p></header>
  <?php render_alerts($errors); ?>
  <?php if ($result): ?>
    <section class="quiz-result" aria-live="polite"><span class="eyebrow">Quiz complete · your score</span><div class="quiz-result-score"><?= (int) $result['score'] ?>/5</div><h2><?= !empty($result['joined']) ? 'Thanks for joining the leaderboard, ' . e((string) $result['username']) . '!' : 'Here is your result.' ?></h2><p>You answered <?= (int) $result['score'] ?> out of 5 questions correctly.</p><p>Quiz date: <?= e(date('M j, Y', strtotime((string) $quiz['quiz_date']))) ?></p></section>
    <?php if (!empty($result['joined'])): ?>
      <p class="quiz-entry-confirmation"><span aria-hidden="true">✓</span> Your score has been added to today’s leaderboard.</p>
    <?php elseif (is_array($pending)): ?>
      <section class="quiz-entry-panel"><h2>Join today’s leaderboard <span>(optional)</span></h2><p>Your score is ready. Add a username and email if you’d like it listed.</p>
        <form method="post" class="quiz-entry" id="quiz-join-form"><?= csrf_field() ?><input type="hidden" name="action" value="join_leaderboard"><label>Unique username<input name="username" minlength="3" maxlength="40" pattern="[A-Za-z0-9_]+" required autocomplete="username"></label><label>Email address<input type="email" name="email" maxlength="150" required autocomplete="email"></label><p>One entry per email and username for today’s quiz.</p></form>
        <div class="quiz-leaderboard-actions"><button type="submit" form="quiz-join-form" class="button">Join leaderboard</button><form method="post" class="quiz-skip-form"><?= csrf_field() ?><input type="hidden" name="action" value="skip_leaderboard"><button type="submit" class="button">No thanks, skip</button></form></div>
      </section>
    <?php endif; ?>
  <?php elseif (!$quiz || count($questions) !== 5): ?><section class="quiz-result"><h2>Today's Quiz Is Not Yet Available</h2><p>Please check back later.</p></section>
  <?php else: ?>
    <form method="post" class="daily-quiz-form" data-quiz-steps><?= csrf_field() ?><input type="hidden" name="action" value="calculate_score">
      <?php foreach ($questions as $i => $q): ?><fieldset class="quiz-step<?= $i === 0 ? ' is-current' : '' ?>" data-quiz-step="<?= $i ?>" <?= $i ? 'hidden' : '' ?>><legend><span>Question <?= $i + 1 ?> of 5</span><h2><?= e($q['question']) ?></h2></legend><div class="quiz-options"><?php foreach ($q['options'] as $index => $option): ?><label><input type="radio" name="answers[<?= (int) $q['id'] ?>]" value="<?= (int) $index ?>"><span><?= e((string) $option) ?></span></label><?php endforeach; ?></div><div class="quiz-step-actions"><?php if ($i > 0): ?><button type="button" class="button button-outline" data-quiz-prev>← Back</button><?php endif; ?><?php if ($i < 4): ?><button type="button" class="button" data-quiz-next>Next →</button><?php else: ?><button type="submit" class="button" data-quiz-submit-score>See score</button><?php endif; ?></div></fieldset><?php endforeach; ?>
      <div class="quiz-progress"><span data-quiz-progress></span></div>
    </form>
  <?php endif; ?>
  <section class="quiz-leaderboard"><h2>Today’s Leaderboard</h2><?php if ($leaders): ?><ol><?php foreach ($leaders as $leader): ?><li><span><?= e($leader['username']) ?></span><strong><?= (int) $leader['score'] ?>/5</strong></li><?php endforeach; ?></ol><?php else: ?><p>The leaderboard will update as participants complete today's quiz.</p><?php endif; ?></section>
</main>
<?php page_end(); ?>
