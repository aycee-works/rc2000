<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';
$donorUser = current_donor_user();

$view = $_GET['view'] ?? 'submit';
if (!in_array($view, ['submit', 'status', 'receipt'], true)) {
    $view = 'submit';
}
$inquiryReceipt = $_SESSION['inquiry_receipt'] ?? null;
$replyTimeNote = 'We typically respond within 3 to 5 days. Thank you for your patience.';

$errors = [];
$statusResult = null;
$old = [
    'category' => '',
    'first_name' => '',
    'last_name' => '',
    'sender_name' => '',
    'sender_email' => '',
    'phone' => '',
    'subject' => '',
    'message' => '',
    'ticket_number' => '',
];
if ($donorUser) {
    $nameParts = preg_split('/\s+/u', trim($donorUser['name']), 2) ?: [];
    $old['first_name'] = $nameParts[0] ?? $donorUser['name'];
    $old['last_name'] = $nameParts[1] ?? '';
    $old['sender_name'] = $donorUser['name'];
    $old['sender_email'] = $donorUser['email'];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $view === 'status') {
    $prefillReference = flash_get('inquiry_status_reference');
    if (is_array($inquiryReceipt) && $prefillReference === $inquiryReceipt['reference']) {
        $old['ticket_number'] = $inquiryReceipt['reference'];
        $old['sender_name'] = $inquiryReceipt['name'];
        $old['sender_email'] = $inquiryReceipt['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'status_from_receipt') {
        $view = 'receipt';
        if (!$errors && is_array($inquiryReceipt)) {
            flash_set('inquiry_status_reference', $inquiryReceipt['reference']);
            redirect('/public/inquiry.php?view=status');
        }
    }
    foreach ($old as $key => $_) {
        $old[$key] = post_string($key);
    }
    if ($donorUser) {
        $nameParts = preg_split('/\s+/u', trim($donorUser['name']), 2) ?: [];
        $old['first_name'] = $nameParts[0] ?? $donorUser['name'];
        $old['last_name'] = $nameParts[1] ?? '';
        $old['sender_name'] = $donorUser['name'];
        $old['sender_email'] = $donorUser['email'];
    }

    if ($action === 'submit') {
        $view = 'submit';
        $fullName = $donorUser
            ? mb_substr($donorUser['name'], 0, 150)
            : (preg_replace('/\s+/u', ' ', trim($old['first_name'] . ' ' . $old['last_name'])) ?? '');
        $categories = inquiry_categories();
        if (!isset($categories[$old['category']])) {
            $errors[] = 'Please select a category.';
        }
        if (!$donorUser && (!required_text($old['first_name'], 150) || !required_text($old['last_name'], 150))) {
            $errors[] = 'Please enter your first and last names.';
        } elseif (!required_text($fullName, 150)) {
            $errors[] = 'Your first and last names must total 150 characters or fewer.';
        }
        if (!valid_email($old['sender_email'])) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (!valid_phone($old['phone'])) {
            $errors[] = 'Please enter a valid phone number, or leave it blank.';
        }
        if (!required_text($old['subject'], 200)) {
            $errors[] = 'Please enter a subject.';
        }
        if (!required_text($old['message'], 8000)) {
            $errors[] = 'Please enter your message.';
        }

        if (!$errors) {
            $category = $categories[$old['category']];
            $message = $old['message'];
            if ($old['phone'] !== '') {
                $message .= "\n\nPhone: " . $old['phone'];
            }
            try {
                $stmt = db()->prepare(
                    "INSERT INTO rc2000_admin.inquiries (reference_no, name, email, subject, category, message, donor_user_id)
                     VALUES ('INQ-' || lpad(nextval('rc2000_admin.inquiry_seq')::text, 4, '0'),
                             :name, :email, :subject, :category, :message, :donor_user_id)
                     RETURNING reference_no"
                );
                $stmt->execute([
                    'name' => $fullName,
                    'email' => $old['sender_email'],
                    'subject' => $old['subject'],
                    'category' => $category,
                    'message' => $message,
                    'donor_user_id' => $donorUser ? (int) $donorUser['id'] : null,
                ]);
                $ticket = (string) $stmt->fetchColumn();
                $_SESSION['inquiry_receipt'] = [
                    'reference' => $ticket,
                    'name' => $fullName,
                    'email' => $old['sender_email'],
                    'category' => $category,
                    'subject' => $old['subject'],
                    'message' => $message,
                    'submitted_at' => date('c'),
                ];
                redirect('/public/inquiry.php?view=receipt');
            } catch (Throwable $error) {
                $errors[] = 'We could not save your ticket yet. Please try again when the database is available.';
            }
        }
    }

if ($action === 'status') {
    $view = 'status';

    $ticketRef = strtoupper(trim($old['ticket_number']));
    if (!preg_match('/^INQ-\d{4,}$/', $ticketRef)) {
        $errors[] = 'Enter a ticket number such as INQ-0001.';
    }

    $fullName = preg_replace('/\s+/u', ' ', $old['sender_name']) ?? '';
    if (!required_text($fullName, 150)) {
        $errors[] = 'Please enter the name used when you submitted.';
    }

    if (!valid_email($old['sender_email'])) {
        $errors[] = 'Please enter the email used when you submitted.';
    }

    if (!$errors) {
        $row = fetch_one(
            'SELECT reference_no, name, email, subject, status, created_at
            FROM rc2000_admin.inquiries
            WHERE reference_no = :ref
              AND LOWER(email) = LOWER(:email)
              AND LOWER(regexp_replace(name, \'[[:space:]]+\', \' \', \'g\')) = LOWER(:name)
            LIMIT 1',
            [
                'ref' => $ticketRef,
                'email' => $old['sender_email'],
                'name' => $fullName,
            ]
        );

        if (!$row) {
            $errors[] = 'No ticket matched that number, name, and email.';
        } else {
            $statusResult = $row;
        }
    }
}
}

$backPath = 'public/inquiry.php?view=submit';
if ($view === 'submit') {
    $backPath = 'public/home.php';
} elseif ($view === 'status' && is_array($inquiryReceipt)) {
    $backPath = 'public/inquiry.php?view=receipt';
}

page_start('Inquiry');
?>
<main class="section inquiry-page">
  <a class="inquiry-back" href="<?= e(app_url($backPath)) ?>"><span aria-hidden="true">←</span> Back</a>
  <div class="section-heading">
    <div>
      <h1>How can we help?</h1>
      <p>Ask a question, make a request, or report a concern. You’ll get a ticket number to check for updates.</p>
    </div>
  </div>

  <div class="subnav">
    <a href="<?= e(app_url('public/inquiry.php?view=submit')) ?>" class="<?= $view === 'submit' ? 'is-active' : '' ?>">Submit an Inquiry</a>
    <a href="<?= e(app_url('public/inquiry.php?view=status')) ?>" class="<?= $view === 'status' ? 'is-active' : '' ?>">Check Status</a>
    <?php if ($donorUser): ?><a href="<?= e(app_url('public/my-inquiries.php')) ?>">My conversations</a><?php endif; ?>
  </div>

  <?php render_alerts($errors); ?>

  <?php if ($view === 'receipt'): ?>
    <section class="form-card framed submission-receipt">
      <?php if (is_array($inquiryReceipt)): ?>
        <header class="receipt-header">
          <img src="<?= e(app_url('public/assets/images/icons/logo.png')) ?>" alt="" width="56" height="56">
          <div>
            <p>Reading Club 2000</p>
            <h2>Inquiry Confirmation</h2>
          </div>
        </header>
        <p class="receipt-intro">Your inquiry was submitted. Use your ticket number, name, and email to check its status.</p>
        <p class="inquiry-reply-note"><?= e($replyTimeNote) ?></p>
        <dl class="receipt-details">
          <div class="receipt-reference">
            <dt>Ticket number</dt>
            <dd>
              <div class="receipt-ticket">
                <strong id="receipt-ticket-number"><?= e((string) $inquiryReceipt['reference']) ?></strong>
                <button class="button button-secondary receipt-copy" type="button" id="receipt-copy" aria-describedby="receipt-copy-help" hidden>Copy</button>
              </div>
              <p id="receipt-copy-help" class="receipt-copy-help" role="status">Copy this number for later.</p>
            </dd>
          </div>
          <div><dt>Full name</dt><dd><?= e((string) $inquiryReceipt['name']) ?></dd></div>
          <div><dt>Email</dt><dd><?= e((string) $inquiryReceipt['email']) ?></dd></div>
          <div><dt>Category</dt><dd><?= e((string) $inquiryReceipt['category']) ?></dd></div>
          <div><dt>Subject</dt><dd><?= e((string) $inquiryReceipt['subject']) ?></dd></div>
          <div><dt>Submitted</dt><dd><?= e(date('M j, Y g:i A', strtotime((string) $inquiryReceipt['submitted_at']))) ?></dd></div>
          <div class="receipt-message"><dt>Message</dt><dd><?= nl2br(e((string) $inquiryReceipt['message'])) ?></dd></div>
        </dl>
        <div class="receipt-actions">
          <button class="button" type="button" onclick="window.print()">Print Confirmation</button>
          <form method="post" action="<?= e(app_url('public/inquiry.php?view=receipt')) ?>">
            <?= csrf_field() ?>
            <button class="button button-secondary" type="submit" name="action" value="status_from_receipt">Check Status</button>
          </form>
        </div>
        <script src="<?= e(app_url('public/assets/js/inquiry-receipt.js')) ?>" defer></script>
      <?php else: ?>
        <h2>No recent inquiry receipt</h2><p>Submit an inquiry to receive a printable confirmation and ticket number.</p>
        <a class="button" href="<?= e(app_url('public/inquiry.php?view=submit')) ?>">Submit an inquiry</a>
      <?php endif; ?>
    </section>
  <?php elseif ($view === 'submit'): ?>
    <section class="form-card framed" data-reveal>
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit">
        <label>Category
          <select name="category" required>
            <option value="">Select a category</option>
            <?php foreach (inquiry_categories() as $value => $label): ?>
              <option value="<?= e($value) ?>" <?= $old['category'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <?php if (!$donorUser): ?><div class="form-grid">
          <label>First name
            <input name="first_name" autocomplete="given-name" required maxlength="150" value="<?= e($old['first_name']) ?>">
          </label>
          <label>Last name
            <input name="last_name" autocomplete="family-name" required maxlength="150" value="<?= e($old['last_name']) ?>">
          </label>
        </div>
        <label>Email
          <input type="email" name="sender_email" required maxlength="255" value="<?= e($old['sender_email']) ?>">
        </label><?php else: ?><p class="inquiry-account-identity">Sending as <strong><?= e($donorUser['name']) ?></strong> · <?= e($donorUser['email']) ?></p><?php endif; ?>
        <label>Phone (optional)
          <input name="phone" maxlength="30" value="<?= e($old['phone']) ?>">
        </label>
        <label>Subject
          <input name="subject" required maxlength="200" value="<?= e($old['subject']) ?>">
        </label>
        <label>Your message
          <textarea name="message" rows="7" maxlength="8000" required><?= e($old['message']) ?></textarea>
        </label>
        <p class="inquiry-reply-note"><?= e($replyTimeNote) ?></p>
        <button class="button wide" type="submit">Submit an Inquiry</button>
        <p class="inquiry-privacy">Read our <a href="<?= e(app_url('public/privacy-policy.php')) ?>" target="_blank" rel="noopener">Privacy Policy</a>.</p>
      </form>
    </section>
  <?php else: ?>
    <section class="form-card framed" data-reveal>
      <?php if ($statusResult): ?>
        <?php $statusLabels = ['open' => 'Open', 'in_progress' => 'In progress', 'resolved' => 'Resolved', 'closed' => 'Closed']; ?>
        <div class="status-result">
          <p class="eyebrow">Ticket</p>
          <h2><?= e((string) $statusResult['reference_no']) ?></h2>
          <p><strong>Status:</strong> <?= e($statusLabels[$statusResult['status']] ?? (string) $statusResult['status']) ?></p>
          <p><strong>Subject:</strong> <?= e((string) $statusResult['subject']) ?></p>
          <p><strong>Submitted:</strong> <?= e(date('M j, Y g:i A', strtotime((string) $statusResult['created_at']))) ?></p>
        </div>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="status">
        <label>Reference number
          <input name="ticket_number" required placeholder="INQ-0001" value="<?= e($old['ticket_number']) ?>">
        </label>
        <div class="form-grid">
          <label>Name
            <input name="sender_name" required maxlength="150" value="<?= e($old['sender_name']) ?>">
          </label>
          <label>Email used when you submitted
            <input type="email" name="sender_email" required maxlength="255" value="<?= e($old['sender_email']) ?>">
          </label>
        </div>
        <button class="button wide" type="submit">Check Status</button>
      </form>
    </section>
  <?php endif; ?>
</main>
<?php page_end(); ?>
