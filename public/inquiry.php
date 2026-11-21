<?php
require_once __DIR__ . '/../includes/functions.php';

$view = $_GET['view'] ?? 'submit';
if (!in_array($view, ['submit', 'status', 'receipt'], true)) {
    $view = 'submit';
}
$inquiryReceipt = $_SESSION['inquiry_receipt'] ?? null;

$errors = [];
$statusResult = null;
$old = [
    'category' => '',
    'sender_name' => '',
    'sender_email' => '',
    'phone' => '',
    'subject' => '',
    'message' => '',
    'ticket_number' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }
    $action = (string) ($_POST['action'] ?? '');
    foreach ($old as $key => $_) {
        $old[$key] = post_string($key);
    }

    if ($action === 'submit') {
        $view = 'submit';
        $categories = inquiry_categories();
        if (!isset($categories[$old['category']])) {
            $errors[] = 'Please select a category.';
        }
        if (!required_text($old['sender_name'], 150)) {
            $errors[] = 'Please enter your name.';
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
            $errors[] = 'Please describe your issue.';
        }

        if (!$errors) {
            $category = $categories[$old['category']];
            $message = $old['message'];
            if ($old['phone'] !== '') {
                $message .= "\n\nPhone: " . $old['phone'];
            }
            try {
                $stmt = db()->prepare(
                    "INSERT INTO rc2000_admin.inquiries (reference_no, name, email, subject, category, message)
                     VALUES ('INQ-' || lpad(nextval('rc2000_admin.inquiry_seq')::text, 4, '0'),
                             :name, :email, :subject, :category, :message)
                     RETURNING reference_no"
                );
                $stmt->execute([
                    'name' => $old['sender_name'],
                    'email' => $old['sender_email'],
                    'subject' => $old['subject'],
                    'category' => $category,
                    'message' => $message,
                ]);
                $ticket = (string) $stmt->fetchColumn();
                $_SESSION['inquiry_receipt'] = [
                    'reference' => $ticket,
                    'name' => $old['sender_name'],
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

    $referenceNo = $ticketRef;

    if (!required_text($old['sender_name'], 150)) {
        $errors[] = 'Please enter the name used when you submitted.';
    }

    if (!valid_email($old['sender_email'])) {
        $errors[] = 'Please enter the email used when you submitted.';
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'SELECT id, reference_no, name, email, subject, status, created_at, updated_at
             FROM rc2000_admin.inquiries
             WHERE LOWER(reference_no) = LOWER(:reference_no)
               AND LOWER(email) = LOWER(:email)
               AND LOWER(name) = LOWER(:name)
             LIMIT 1'
        );

        $stmt->execute([
            'reference_no' => $referenceNo,
            'email' => $old['sender_email'],
            'name' => $old['sender_name'],
        ]);

        $row = fetch_one(
            'SELECT reference_no, name, email, subject, status, created_at
            FROM rc2000_admin.inquiries
            WHERE reference_no = :ref
              AND LOWER(email) = LOWER(:email)
              AND LOWER(name) = LOWER(:name)
            LIMIT 1',
            [
                'ref' => $ticketRef,
                'email' => $old['sender_email'],
                'name' => $old['sender_name'],
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

page_start('Inquiry');
?>
<main class="section inquiry-page">
  <div class="section-heading">
    <div>
      <h1>Send Us an Inquiry!</h1>
      <p>Send us an inquiry, request, report, or message. You’ll get a ticket number to track it.</p>
    </div>
  </div>

  <div class="subnav">
    <a href="<?= e(app_url('public/inquiry.php?view=submit')) ?>" class="<?= $view === 'submit' ? 'is-active' : '' ?>">Submit an Inquiry</a>
    <a href="<?= e(app_url('public/inquiry.php?view=status')) ?>" class="<?= $view === 'status' ? 'is-active' : '' ?>">Check Inquiry Status</a>
  </div>

  <?php render_alerts($errors); ?>

  <?php if ($view === 'receipt'): ?>
    <section class="form-card framed submission-receipt">
      <?php if (is_array($inquiryReceipt)): ?>
        <p class="eyebrow">Inquiry confirmation</p>
        <h2>Your inquiry was submitted</h2>
        <p>Keep this receipt. Use the ticket number, your name, and email to check its status later.</p>
        <dl class="receipt-details">
          <div><dt>Ticket number</dt><dd><?= e((string) $inquiryReceipt['reference']) ?></dd></div>
          <div><dt>Name</dt><dd><?= e((string) $inquiryReceipt['name']) ?></dd></div>
          <div><dt>Email</dt><dd><?= e((string) $inquiryReceipt['email']) ?></dd></div>
          <div><dt>Category</dt><dd><?= e((string) $inquiryReceipt['category']) ?></dd></div>
          <div><dt>Subject</dt><dd><?= e((string) $inquiryReceipt['subject']) ?></dd></div>
          <div><dt>Submitted</dt><dd><?= e(date('M j, Y g:i A', strtotime((string) $inquiryReceipt['submitted_at']))) ?></dd></div>
          <div class="receipt-message"><dt>Message</dt><dd><?= nl2br(e((string) $inquiryReceipt['message'])) ?></dd></div>
        </dl>
        <div class="receipt-actions"><button class="button" type="button" onclick="window.print()">Print confirmation</button><a class="button button-secondary" href="<?= e(app_url('public/inquiry.php?view=status')) ?>">Check inquiry status</a></div>
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
        <div class="form-grid">
          <label>Name
            <input name="sender_name" required maxlength="150" value="<?= e($old['sender_name']) ?>">
          </label>
          <label>Email
            <input type="email" name="sender_email" required maxlength="255" value="<?= e($old['sender_email']) ?>">
          </label>
        </div>
        <label>Phone (optional)
          <input name="phone" maxlength="30" value="<?= e($old['phone']) ?>">
        </label>
        <label>Subject
          <input name="subject" required maxlength="200" value="<?= e($old['subject']) ?>">
        </label>
        <label>Describe your issue
          <textarea name="message" rows="7" required><?= e($old['message']) ?></textarea>
        </label>
        <button class="button wide" type="submit">Send Message</button>
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
      <form method="post">
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
