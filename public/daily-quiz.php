<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/community.php';

$quiz = daily_quiz();
$questions = [];
$errors = [];
$result = null;
if ($quiz) {
    $stmt = db()->prepare('SELECT id, question_order, question, options FROM rc2000_admin.daily_quiz_questions WHERE quiz_id = ? ORDER BY question_order');
    $stmt->execute([(int) $quiz['id']]);
    $questions = $stmt->fetchAll();
    foreach ($questions as &$question) {
        $question['options'] = json_decode((string) $question['options'], true) ?: [];
    }
    unset($question);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $quiz) {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) $errors[] = 'Your session expired. Please try again.';
    if (count($questions) !== 5) $errors[] = 'Today’s quiz is being prepared. Please check back soon.';
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $username = trim((string) ($_POST['username'] ?? ''));
    $answers = $_POST['answers'] ?? [];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) $errors[] = 'Enter a valid email address.';
    if (!preg_match('/^[A-Za-z0-9_]{3,40}$/', $username)) $errors[] = 'Username must be 3–40 letters, numbers, or underscores.';
    $score = 0;
    foreach ($questions as $q) {
        $selected = filter_var($answers[(string) $q['id']] ?? null, FILTER_VALIDATE_INT);
        if ($selected === false || !array_key_exists((int) $selected, $q['options'])) { $errors[] = 'Please answer all five questions.'; break; }
        $correct = db()->prepare('SELECT correct_option FROM rc2000_admin.daily_quiz_questions WHERE id = ?');
        $correct->execute([(int) $q['id']]);
        if ((int) $correct->fetchColumn() === (int) $selected) $score++;
    }
    if (!$errors) {
        try {
            $save = db()->prepare('INSERT INTO rc2000_admin.quiz_submissions (quiz_id, email, username, score) VALUES (?, ?, ?, ?)');
            $save->execute([(int) $quiz['id'], $email, $username, $score]);
            $result = ['score' => $score, 'username' => $username, 'email' => $email];
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') $errors[] = 'This email or username has already entered today’s quiz.';
            else { error_log('Quiz submission failed: ' . $e->getMessage()); $errors[] = 'Your score could not be saved. Please try again.'; }
        }
    }
}

$leaders = [];
if ($quiz) {
    $stmt = db()->prepare('SELECT username, score FROM rc2000_admin.quiz_submissions WHERE quiz_id = ? ORDER BY score DESC, created_at ASC LIMIT 10');
    $stmt->execute([(int) $quiz['id']]);
    $leaders = $stmt->fetchAll();
}
page_start('Daily quiz', ['class' => 'daily-quiz-page']);
?>
<main class="section quiz-page">
  <header class="quiz-page-header"><span class="eyebrow">Reading together · daily quiz</span><h1>Five questions, one at a time</h1><p>Answer all five and submit a unique username and email to record your score.</p></header>
  <?php render_alerts($errors); ?>
  <?php if ($result): ?><section class="quiz-result"><span class="eyebrow">Quiz complete · score recorded</span><div class="quiz-result-score"><?= (int) $result['score'] ?>/5</div><h2><?= e($result['username']) ?>, here is your result.</h2><p>You answered <?= (int) $result['score'] ?> out of 5 questions correctly. Your score is on today’s community leaderboard.</p><dl class="quiz-result-details"><div><dt>Username</dt><dd><?= e($result['username']) ?></dd></div><div><dt>Email</dt><dd><?= e($result['email']) ?></dd></div><div><dt>Quiz date</dt><dd><?= e(date('M j, Y', strtotime((string) $quiz['quiz_date']))) ?></dd></div></dl></section>
  <?php elseif (!$quiz || count($questions) !== 5): ?><section class="quiz-result"><h2>No quiz is published today</h2><p>Please check back later for the next daily quiz.</p></section>
  <?php else: ?>
    <form method="post" class="daily-quiz-form" data-quiz-steps><?= csrf_field() ?>
      <?php foreach ($questions as $i => $q): ?><fieldset class="quiz-step<?= $i === 0 ? ' is-current' : '' ?>" data-quiz-step="<?= $i ?>" <?= $i ? 'hidden' : '' ?>><legend><span>Question <?= $i + 1 ?> of 5</span><h2><?= e($q['question']) ?></h2></legend><div class="quiz-options"><?php foreach ($q['options'] as $index => $option): ?><label><input type="radio" name="answers[<?= (int) $q['id'] ?>]" value="<?= (int) $index ?>"><span><?= e((string) $option) ?></span></label><?php endforeach; ?></div><div class="quiz-step-actions"><?php if ($i > 0): ?><button type="button" class="button button-outline" data-quiz-prev>← Back</button><?php endif; ?><?php if ($i < 4): ?><button type="button" class="button" data-quiz-next>Next →</button><?php endif; ?></div></fieldset><?php endforeach; ?>
      <fieldset class="quiz-step quiz-entry" data-quiz-step="5" hidden><legend><span>Final step</span><h2>Save your score</h2></legend><label>Unique username<input name="username" minlength="3" maxlength="40" pattern="[A-Za-z0-9_]+" required autocomplete="username"></label><label>Email address<input type="email" name="email" maxlength="150" required autocomplete="email"></label><p>One entry per email and username for today’s quiz.</p><div class="quiz-step-actions"><button type="button" class="button button-outline" data-quiz-prev>← Back</button><button type="submit" class="button">Submit score</button></div></fieldset>
      <div class="quiz-progress"><span data-quiz-progress></span></div>
    </form>
  <?php endif; ?>
  <section class="quiz-leaderboard"><h2>Today’s leaderboard</h2><?php if ($leaders): ?><ol><?php foreach ($leaders as $leader): ?><li><span><?= e($leader['username']) ?></span><strong><?= (int) $leader['score'] ?>/5</strong></li><?php endforeach; ?></ol><?php else: ?><p>Scores will appear here after visitors complete today’s quiz.</p><?php endif; ?></section>
</main>
<?php page_end(); ?>
