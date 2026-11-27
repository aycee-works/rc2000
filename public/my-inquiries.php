<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';
require_once __DIR__ . '/../includes/donor/inquiries.php';

$donor = current_donor_user();
if (!$donor) {
    $_SESSION['donor_return_to'] = '/public/my-inquiries.php';
    flash_set('donor_auth_notice', 'Sign in to view your inquiry conversations.');
    redirect('/public/login.php');
}

$notice = null;
$error = null;
$selectedId = max(0, (int) ($_GET['id'] ?? 0));
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) { $error = 'Your session expired. Refresh the page and try again.'; }
    else {
        try {
            if (post_str('action') === 'new') {
                $ref = create_my_inquiry(db(), $donor, post_str('category'), post_str('subject'), post_str('message'));
                flash_set('donor_inquiry_notice', 'Your inquiry was sent. Reference: ' . $ref);
                redirect('/public/my-inquiries.php');
            }
            if (post_str('action') === 'reply') {
                $selectedId = max(0, (int) post_str('inquiry_id'));
                reply_to_my_inquiry(db(), (int) $donor['id'], $selectedId, post_str('message'));
                flash_set('donor_inquiry_notice', 'Your reply was added to the conversation.');
                redirect('/public/my-inquiries.php?id=' . $selectedId);
            }
            $error = 'Unknown action.';
        } catch (InvalidArgumentException | RuntimeException $e) { $error = $e->getMessage(); }
        catch (Throwable $e) { error_log('Donor inquiry action failed: ' . $e->getMessage()); $error = 'We could not save your inquiry. Please try again.'; }
    }
}

$notice = flash_get('donor_inquiry_notice');
$inquiries = [];
$selected = null;
$thread = [];
$pageError = null;
try {
    $pdo = db();
    $inquiries = list_my_inquiries($pdo, (int) $donor['id']);
    if ($selectedId > 0) {
        $selected = get_my_inquiry($pdo, (int) $donor['id'], $selectedId);
        if ($selected) {
            $thread = get_my_inquiry_thread($pdo, $selectedId);
        }
    }
} catch (Throwable $e) { error_log('Donor inquiry inbox failed: ' . $e->getMessage()); $pageError = 'Your inquiry inbox could not be loaded right now.'; }

$GLOBALS['pageTitle'] = 'My inquiries';
$GLOBALS['pageClass'] = 'donor-dashboard';
require __DIR__ . '/../includes/header.php';
?>
<main class="donor-dashboard-shell donor-inbox-shell">
  <aside class="donor-dashboard-sidebar" aria-label="Donor account navigation">
    <a class="donor-dashboard-nav" href="<?= e(app_url('public/dashboard.php')) ?>"><span aria-hidden="true">⌂</span> My Overview</a>
    <a class="donor-dashboard-nav" href="<?= e(app_url('public/dashboard.php#my-donations')) ?>"><span aria-hidden="true">♡</span> My Donations</a>
    <a class="donor-dashboard-nav" href="<?= e(app_url('public/dashboard.php#my-campaigns')) ?>"><span aria-hidden="true">▱</span> My Campaigns</a>
    <a class="donor-dashboard-nav is-active" href="<?= e(app_url('public/my-inquiries.php')) ?>" aria-current="page"><span aria-hidden="true">☏</span> My Inquiries</a>
    <a class="donor-dashboard-nav" href="<?= e(app_url('public/dashboard.php#my-profile')) ?>"><span aria-hidden="true">♙</span> Profile</a>
  </aside>
  <div class="donor-dashboard-content donor-inbox-content">
    <header class="donor-inbox-header"><div><p class="donor-eyebrow">Your account</p><h1>My Inquiries</h1><p>Continue a conversation with the Reading Club 2000 team.</p></div><a class="donor-primary-link" href="#new-inquiry">Start an inquiry</a></header>
    <?php if ($notice): ?><div class="donor-dashboard-alert is-success" role="status"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="donor-dashboard-alert" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($pageError): ?><div class="donor-dashboard-alert" role="alert"><?= e($pageError) ?></div><?php endif; ?>
    <div class="donor-inbox-grid">
      <section class="donor-dashboard-card donor-inbox-list"><header class="donor-section-heading"><div><span>☏</span><h2>Your conversations</h2></div></header>
        <?php if (!$inquiries): ?><p class="donor-inbox-empty">No account-linked inquiries yet.</p><?php endif; ?>
        <?php foreach ($inquiries as $inquiry): $href = app_url('public/my-inquiries.php?id=' . (int) $inquiry['id']); ?>
          <a class="donor-inbox-item<?= $selected && (int) $selected['id'] === (int) $inquiry['id'] ? ' is-selected' : '' ?>" href="<?= e($href) ?>"><span class="donor-inbox-item-status status-<?= e($inquiry['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $inquiry['status']))) ?></span><strong><?= e($inquiry['subject']) ?></strong><small><?= e($inquiry['reference_no']) ?> · <?= e(format_date_short($inquiry['last_activity'] ?: $inquiry['created_at'])) ?></small></a>
        <?php endforeach; ?>
      </section>
      <section class="donor-dashboard-card donor-inbox-thread">
        <?php if ($selected): ?>
          <header class="donor-section-heading"><div><span>☏</span><div><h2><?= e($selected['subject']) ?></h2><small><?= e($selected['reference_no']) ?> · <?= e(ucwords(str_replace('_', ' ', $selected['status']))) ?></small></div></div></header>
          <div class="donor-thread-messages">
            <article class="donor-thread-message is-you"><p><?= nl2br(e($selected['message'])) ?></p><small>You · <?= e(format_datetime($selected['created_at'])) ?></small></article>
            <?php foreach ($thread as $message): ?><article class="donor-thread-message <?= $message['sender_type'] === 'admin' ? 'is-admin' : 'is-you' ?>"><p><?= nl2br(e($message['body'])) ?></p><small><?= $message['sender_type'] === 'admin' ? e($message['sender_name'] ?: 'Reading Club 2000') : 'You' ?> · <?= e(format_datetime($message['created_at'])) ?></small></article><?php endforeach; ?>
          </div>
          <?php if ($selected['status'] !== 'closed'): ?><form method="post" class="donor-thread-reply"><?= csrf_field() ?><input type="hidden" name="action" value="reply"><input type="hidden" name="inquiry_id" value="<?= (int) $selected['id'] ?>"><label for="inquiry-reply">Your reply</label><textarea id="inquiry-reply" name="message" rows="4" maxlength="8000" required placeholder="Write a message to the team"></textarea><button class="donor-primary-link" type="submit">Send reply</button></form><?php else: ?><p class="donor-inbox-closed">This conversation is closed. Start a new inquiry if you need more help.</p><?php endif; ?>
        <?php else: ?><div class="donor-inbox-empty-state"><span aria-hidden="true">☏</span><h2>Select a conversation</h2><p>Choose an inquiry or start a new one to message the team.</p></div><?php endif; ?>
      </section>
    </div>
    <section class="donor-dashboard-card donor-new-inquiry" id="new-inquiry"><header class="donor-section-heading"><div><span>✉</span><h2>Start a new inquiry</h2></div></header><form method="post" class="donor-new-inquiry-form"><?= csrf_field() ?><input type="hidden" name="action" value="new"><label>Category<select name="category" required><option value="">Choose a category</option><?php foreach (inquiry_categories() as $key => $category): ?><option value="<?= e($key) ?>"><?= e($category) ?></option><?php endforeach; ?></select></label><label>Subject<input name="subject" maxlength="200" required></label><label>Message<textarea name="message" rows="5" maxlength="8000" required></textarea></label><button class="donor-primary-link" type="submit">Send inquiry</button></form></section>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
