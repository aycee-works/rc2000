<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/community.php';

$tab = in_array($_GET['tab'] ?? '', ['money', 'campaign', 'howto', 'status', 'receipt'], true) ? (string) $_GET['tab'] : 'money';
$donationReceipt = $_SESSION['donation_receipt'] ?? null;

$methods = active_donation_methods();
$errors = [];
$donationPrivacyNote = 'Your email is required to check your donation status and receive updates. Admins use your payment details to verify your donation. Any payment proof you upload may show your name.';
$old = [
    'donor_name' => '',
    'donor_email' => '',
    'contact_info' => '',
    'quantity' => '1',
    'donation_description' => '',
    'title' => '',
    'author' => '',
    'genre' => '',
    'condition' => '',
    'amount_reported' => '',
    'donation_method_id' => '',
    'transaction_reference' => '',
    'payment_date' => date('Y-m-d\TH:i'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($tab === 'receipt' && $action === 'status_from_receipt' && !$errors && is_array($donationReceipt)) {
        flash_set('donation_status_reference', $donationReceipt['reference']);
        redirect('/public/donate.php?tab=status');
    }
    foreach ($old as $key => $_) {
        $old[$key] = post_string($key);
    }
    // Honor the checkbox if someone submits an older copy of the form.
    if (($_POST['anonymous'] ?? null) === '1') {
        $old['donor_name'] = '';
    }
    $donorName = $old['donor_name'];
    if ($action !== 'status_from_receipt' || $tab !== 'receipt') {
        $tab = $action === 'campaign' ? 'campaign' : 'money';
    }

    if (!$errors && in_array($action, ['campaign', 'monetary'], true) && !optional_text($donorName, 120)) {
        $errors[] = 'Please enter a name of 120 characters or fewer, or leave it blank.';
    }

    if (!$errors && $action === 'campaign') {
        $campaignId = (int) post_string('campaign_id');
        if (!valid_email($old['donor_email']) || mb_strlen($old['donor_email']) > 150) $errors[] = 'Please enter a valid email address.';
        if (!valid_amount($old['amount_reported'])) $errors[] = 'Please enter a valid amount.';
        if (!valid_date_time($old['payment_date'])) $errors[] = 'Please enter the payment date.';
        if (!required_text($old['transaction_reference'], 100)) $errors[] = 'Please enter a transaction reference or check number (100 characters maximum).';
        $contact = trim($old['contact_info']);
        if ($contact !== '' && !preg_match('/^[0-9+() \-]{7,30}$/', $contact)) $errors[] = 'Contact number may only contain digits, spaces, + ( ) and - (up to 30 characters).';
        $campaign = fetch_one("SELECT id, title FROM rc2000_admin.campaigns WHERE id = :id AND status = 'open'", ['id' => $campaignId]);
        if (!$campaign) $errors[] = 'Please choose an open campaign.';
        $methodId = (int) $old['donation_method_id'];
        $selectedMethod = null;
        foreach ($methods as $method) if ((int) $method['donation_method_id'] === $methodId) $selectedMethod = $method;
        if (!$selectedMethod) $errors[] = 'Please choose a donation method.';
        $proof = store_upload('proof_of_payment', 'proofs', $errors, false);
        if (!$errors) {
            try {
                $methodName = mb_substr(trim($selectedMethod['method_name'] . ' · ' . $selectedMethod['payment_provider']), 0, 40);
                $stmt = db()->prepare("INSERT INTO rc2000_admin.monetary_donations (reference_no, donor_name, donor_email, donor_contact, reported_amount, payment_method, transaction_ref, proof_path, campaign_id) VALUES ('MD-' || lpad(nextval('rc2000_admin.monetary_donation_seq')::text, 4, '0'), :name, :email, :contact, :amount, :method, :reference, :proof, :campaign) RETURNING reference_no");
                $stmt->execute(['name' => $donorName, 'email' => strtolower(trim($old['donor_email'])), 'contact' => $contact ?: null, 'amount' => number_format((float) $old['amount_reported'], 2, '.', ''), 'method' => $methodName, 'reference' => $old['transaction_reference'], 'proof' => $proof, 'campaign' => $campaignId]);
                $trackingId = (string) $stmt->fetchColumn();
                $_SESSION['donation_receipt'] = [
                    'reference' => $trackingId,
                    'name' => $donorName,
                    'email' => strtolower(trim($old['donor_email'])),
                    'amount' => number_format((float) $old['amount_reported'], 2, '.', ''),
                    'method' => $methodName,
                    'transaction_reference' => $old['transaction_reference'],
                    'payment_date' => $old['payment_date'],
                    'campaign' => (string) ($campaign['title'] ?? ''),
                    'submitted_at' => date('c'),
                ];
                redirect('/public/donate.php?tab=receipt');
            } catch (Throwable $error) {
                error_log('Campaign donation report failed: ' . $error->getMessage());
                $errors[] = 'We could not save your campaign donation report yet. Please try again.';
            }
        }
    }

    if (!$errors && $action === 'monetary') {
        if (!optional_text($old['contact_info'], 255)) {
            $errors[] = 'Contact information is too long.';
        }
        if (!valid_email($old['donor_email']) || mb_strlen($old['donor_email']) > 150) {
            $errors[] = 'Please enter a valid email address (up to 150 characters).';
        }
        if (!valid_amount($old['amount_reported'])) {
            $errors[] = 'Please enter a valid amount.';
        }
        if (!required_text($old['transaction_reference'], 150)) {
            $errors[] = 'Please enter a transaction reference or check number.';
        }
        if (mb_strlen($old['transaction_reference']) > 100) {
            $errors[] = 'Transaction reference must be 100 characters or fewer.';
        }
        if (!valid_date_time($old['payment_date'])) {
            $errors[] = 'Please enter the payment date.';
        }
        $methodId = (int) $old['donation_method_id'];
        $validIds = array_map(static fn ($row) => (int) $row['donation_method_id'], $methods);
        if (!in_array($methodId, $validIds, true)) {
            $errors[] = 'Please choose a donation method.';
        }

        $proof = store_upload('proof_of_payment', 'proofs', $errors, false);

        if (!$errors) {
            $method = null;
            foreach ($methods as $availableMethod) {
                if ((int) $availableMethod['donation_method_id'] === $methodId) {
                    $method = mb_substr(trim($availableMethod['method_name'] . ' · ' . $availableMethod['payment_provider']), 0, 40);
                    break;
                }
            }
            $pdo = db();
            try {
                $duplicate = $pdo->prepare(
                    'SELECT 1 FROM rc2000_admin.monetary_donations
                     WHERE payment_method = :payment_method AND transaction_ref = :transaction_ref
                     LIMIT 1'
                );
                $duplicate->execute(['payment_method' => $method, 'transaction_ref' => $old['transaction_reference']]);
                if ($duplicate->fetchColumn()) {
                    $errors[] = 'That transaction reference was already recorded for this method.';
                } else {
                    $pdo->beginTransaction();
                    $portal = $pdo->prepare(
                        "INSERT INTO rc2000_admin.monetary_donations
                            (reference_no, donor_name, donor_email, donor_contact, reported_amount, payment_method, transaction_ref, proof_path)
                         VALUES
                            ('MD-' || lpad(nextval('rc2000_admin.monetary_donation_seq')::text, 4, '0'), :donor_name, :donor_email, :donor_contact, :reported_amount, :payment_method, :transaction_ref, :proof_path)
                         RETURNING reference_no"
                    );
                    $contact = trim($old['contact_info']);
                    $portal->execute([
                        'donor_name' => $donorName,
                        'donor_email' => strtolower(trim($old['donor_email'])),
                        'donor_contact' => preg_match('/^[0-9+() \\-]{7,30}$/', $contact) ? $contact : null,
                        'reported_amount' => number_format((float) $old['amount_reported'], 2, '.', ''),
                        'payment_method' => $method,
                        'transaction_ref' => $old['transaction_reference'],
                        'proof_path' => $proof,
                    ]);
                    $trackingId = (string) $portal->fetchColumn();
                    $pdo->commit();
                    $_SESSION['donation_receipt'] = [
                        'reference' => $trackingId,
                        'name' => $donorName,
                        'email' => strtolower(trim($old['donor_email'])),
                        'amount' => number_format((float) $old['amount_reported'], 2, '.', ''),
                        'method' => (string) $method,
                        'transaction_reference' => $old['transaction_reference'],
                        'payment_date' => $old['payment_date'],
                        'campaign' => '',
                        'submitted_at' => date('c'),
                    ];
                    redirect('/public/donate.php?tab=receipt');
                }
            } catch (PDOException $error) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Monetary donation save failed: ' . $error->getMessage());
                $errors[] = 'We could not save your report yet. Please try again when the database is available.';
            } catch (Throwable $error) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Monetary donation save failed: ' . $error->getMessage());
                $errors[] = 'We could not save your report yet. Please try again when the database is available.';
            }
        }
    }
}

$campaigns = fetch_all("SELECT id, title, description, goal_amount FROM rc2000_admin.campaigns WHERE status = 'open' ORDER BY is_featured DESC, created_at DESC");
$statusRecord = null;
$statusMessage = '';
$statusReference = '';
$statusEmail = '';
if ($tab === 'status') {
    $prefillReference = flash_get('donation_status_reference');
    if (is_array($donationReceipt) && $prefillReference === $donationReceipt['reference']) {
        $statusReference = $donationReceipt['reference'];
        $statusEmail = $donationReceipt['email'];
    }
}
if ($tab === 'status' && isset($_GET['reference'], $_GET['email'])) {
    $statusReference = trim((string) $_GET['reference']);
    $statusEmail = trim((string) $_GET['email']);
    $statusRecord = fetch_one('SELECT d.reference_no, d.donor_email, d.reported_amount, d.verified_amount, d.status, d.verification_note, d.verified_at, d.created_at, c.title AS campaign_title FROM rc2000_admin.monetary_donations d LEFT JOIN rc2000_admin.campaigns c ON c.id = d.campaign_id WHERE upper(d.reference_no) = upper(:ref) AND lower(d.donor_email) = lower(:email)', ['ref' => $statusReference, 'email' => $statusEmail]);
    if (!$statusRecord) $statusMessage = 'We could not find a donation with that tracking ID and email address.';
}

$donationTab = empty($donationReceipt['campaign']) ? 'money' : 'campaign';
$backTab = $tab === 'status' && is_array($donationReceipt) ? 'receipt' : $donationTab;
page_start('Donate');
?>
<main class="donate-layout">
  <aside class="donate-side">
    <p class="eyebrow">Donate by</p>
    <a class="<?= $tab === 'money' ? 'is-active' : '' ?>" href="<?= e(app_url('public/donate.php?tab=money')) ?>">Monetary Donation</a>
    <a class="<?= $tab === 'campaign' ? 'is-active' : '' ?>" href="<?= e(app_url('public/donate.php?tab=campaign')) ?>">Campaign Donation</a>
    <a class="<?= $tab === 'howto' ? 'is-active' : '' ?>" href="<?= e(app_url('public/donate.php?tab=howto')) ?>">Other Donations</a>
    <a class="<?= $tab === 'status' ? 'is-active' : '' ?>" href="<?= e(app_url('public/donate.php?tab=status')) ?>">Donation Status</a>
  </aside>

  <div class="donate-main">
    <?php if ($tab === 'receipt' || $tab === 'status'): ?>
      <a class="inquiry-back" href="<?= e(app_url('public/donate.php?tab=' . $backTab)) ?>"><span aria-hidden="true">←</span> Back</a>
    <?php endif; ?>
    <?php render_alerts($errors); ?>

    <?php if ($tab === 'receipt'): ?>
      <section class="form-card submission-receipt">
        <?php if (is_array($donationReceipt)): ?>
          <header class="receipt-header">
            <img src="<?= e(app_url('public/assets/images/icons/logo.png')) ?>" alt="" width="56" height="56">
            <div>
              <p>Reading Club 2000</p>
              <h1>Donation Confirmation</h1>
            </div>
          </header>
          <p class="receipt-intro">Thank you for your donation report. Keep this receipt while your report is pending review.</p>
          <dl class="receipt-details">
            <div class="receipt-reference">
              <dt>Tracking ID</dt>
              <dd>
                <div class="receipt-ticket">
                  <strong id="receipt-ticket-number" data-copy-label="Tracking ID"><?= e((string) $donationReceipt['reference']) ?></strong>
                  <button class="button button-secondary receipt-copy" type="button" id="receipt-copy" aria-describedby="receipt-copy-help" hidden>Copy</button>
                </div>
                <p id="receipt-copy-help" class="receipt-copy-help" role="status">Copy this number for later.</p>
              </dd>
            </div>
            <div><dt>Donor</dt><dd><?= e($donationReceipt['name'] === '' ? 'Name not provided' : (string) $donationReceipt['name']) ?></dd></div>
            <div><dt>Email</dt><dd><?= e((string) $donationReceipt['email']) ?></dd></div>
            <div><dt>Reported amount</dt><dd>₱<?= e(number_format((float) $donationReceipt['amount'], 2)) ?></dd></div>
            <div><dt>Payment method</dt><dd><?= e((string) $donationReceipt['method']) ?></dd></div>
            <div><dt>Transaction reference</dt><dd><?= e((string) $donationReceipt['transaction_reference']) ?></dd></div>
            <div><dt>Payment date</dt><dd><?= e(date('M j, Y g:i A', strtotime((string) $donationReceipt['payment_date']))) ?></dd></div>
            <?php if (!empty($donationReceipt['campaign'])): ?><div><dt>Campaign</dt><dd><?= e((string) $donationReceipt['campaign']) ?></dd></div><?php endif; ?>
            <div><dt>Submitted</dt><dd><?= e(date('M j, Y g:i A', strtotime((string) $donationReceipt['submitted_at']))) ?></dd></div>
          </dl>
          <p>To check your donation status later, use the tracking ID above and the email you submitted.</p>
          <div class="receipt-actions">
            <button class="button" type="button" onclick="window.print()">Print Receipt</button>
            <form method="post" action="<?= e(app_url('public/donate.php?tab=receipt')) ?>">
              <?= csrf_field() ?>
              <button class="button button-secondary" type="submit" name="action" value="status_from_receipt">Track Donation</button>
            </form>
          </div>
          <script src="<?= e(app_url('public/assets/js/inquiry-receipt.js?v=2')) ?>" defer></script>
        <?php else: ?>
          <h1>No recent donation receipt</h1><p>Submit a donation report to get a printable receipt and tracking ID.</p>
          <a class="button" href="<?= e(app_url('public/donate.php?tab=money')) ?>">Make a donation report</a>
        <?php endif; ?>
      </section>
    <?php elseif ($tab === 'money'): ?>
      <section class="form-card">
        <h1>Monetary Donation</h1>
        <p class="lede">After donating, please fill out this form so we can thank you. The amount is self-reported and recorded for transparency; it is not independently tracked through this form.</p>

        <div class="method-tabs" data-tabs>
            <button type="button" class="is-active" data-tab-target="qr">QR Codes</button>
            <button type="button" data-tab-target="bank">Bank Transfer</button>
            <button type="button" data-tab-target="check">Check</button>
          </div>

        <div class="method-panels">
          <div class="is-active" data-tab-panel="qr">
            <?php $qrMethods = array_values(array_filter($methods, static fn ($m) => stripos($m['payment_provider'] . $m['method_name'], 'qr') !== false || stripos($m['payment_provider'], 'gcash') !== false || stripos($m['payment_provider'], 'maya') !== false)); ?>

        <?php if ($methods): ?>
          <label class="qr-method-select">Donation method
            <select form="monetary-donation-form" data-donation-method name="donation_method_id" required>
              <option value="">Select a method</option>
              <?php foreach ($qrMethods as $method): ?>
                <option value="<?= (int) $method['donation_method_id'] ?>" <?= (string) $method['donation_method_id'] === $old['donation_method_id'] ? 'selected' : '' ?>>
                  <?= e($method['method_name'] . ' · ' . $method['payment_provider']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        <?php endif; ?>


            <?php if ($qrMethods): foreach ($qrMethods as $method): ?>
              <figure class="qr-card" data-donation-method-details="<?= (int) $method['donation_method_id'] ?>" <?= (string) $method['donation_method_id'] === $old['donation_method_id'] ? '' : 'hidden' ?>>
                <?php if (media_file_exists($method['qr_image_path'])): ?>
                  <button class="qr-expand" type="button" data-qr-expand aria-label="Expand <?= e($method['method_name']) ?> QR code">
                    <img src="<?= e(media_url($method['qr_image_path'])) ?>" alt="<?= e($method['method_name']) ?> QR code">
                    <span>Click to enlarge for scanning</span>
                  </button>
                <?php else: ?>
                  <div class="qr-placeholder">Add the <?= e($method['method_name']) ?> image in the donation method settings.</div>
                <?php endif; ?>
                <figcaption>
                  <strong><?= e($method['method_name']) ?></strong>
                  <p><?= e($method['instructions'] ?: $method['payment_provider']) ?></p>
                </figcaption>
              </figure>
            <?php endforeach; else: ?>
              <div class="qr-placeholder">QR images appear here once donation methods are added in the database.</div>
              <p>Scan with GCash, Maya, or any QR PH app.</p>
            <?php endif; ?>
          </div>
          <div hidden data-tab-panel="bank">
            <?php $bankMethods = array_values(array_filter($methods, static fn ($m) => stripos($m['payment_provider'] . $m['method_name'], 'bank') !== false)); ?>
            <?php if ($bankMethods): foreach ($bankMethods as $method): ?>
              <p><strong><?= e($method['method_name']) ?></strong><br><?= nl2br(e($method['instructions'] ?: $method['payment_provider'])) ?></p>
            <?php endforeach; else: ?>
              <p><strong>Bank / Account name</strong><br>BDO · Reading Club 2000</p>
              <p><strong>Account number</strong><br>Add the live account in donation_methods.</p>
            <?php endif; ?>
          </div>
          <div hidden data-tab-panel="check">
            <p>Make checks payable to <strong>Reading Club 2000</strong>.</p>
            <p>Mail or drop off at 1454 Balagtas St., Barangay La Paz, 1204 Makati.</p>
          </div>
        </div>

        <?php if (!$methods): ?>
          <p class="alert alert-error">Monetary reports need at least one active row in <code>donation_methods</code>.</p>
        <?php else: ?>
          <form id="monetary-donation-form" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="monetary">
            <p class="field-note" id="donation-report-privacy"><?= e($donationPrivacyNote) ?></p>
            <div class="form-grid">
              <label>Name (Optional)
                <input name="donor_name" maxlength="120" value="<?= e($old['donor_name']) ?>">
              </label>
              <label>Email
                <input type="email" name="donor_email" required maxlength="150" aria-describedby="donation-report-privacy" value="<?= e($old['donor_email']) ?>">
              </label>
              <label>Contact Info (Optional)
                <input name="contact_info" maxlength="255" value="<?= e($old['contact_info']) ?>">
              </label>
              <label>Amount
                <input type="number" name="amount_reported" min="1" step="0.01" required value="<?= e($old['amount_reported']) ?>">
              </label>
              <label>Payment date
                <input type="datetime-local" name="payment_date" required value="<?= e($old['payment_date']) ?>">
              </label>
              <label>Transaction / Check Number
                <input name="transaction_reference" required maxlength="150" value="<?= e($old['transaction_reference']) ?>">
              </label>
            </div>
            <label class="upload-box">
              <span>Proof of payment (optional)</span>
              <input type="file" name="proof_of_payment" accept="image/jpeg,image/png,image/webp,image/gif">
            </label>
            <label class="check-line">
              <input type="checkbox" name="sent_confirm" value="1" required>
              I’ve sent my donation
            </label>
            <button class="button wide" type="submit">Submit donation</button>
          </form>
        <?php endif; ?>
      </section>
    <?php elseif ($tab === 'campaign'): ?>
      <?php $featured = featured_campaign(); ?>
      <section class="form-card campaign-donation-page">
        <span class="eyebrow"></span><h1>Make a Campaign Donation Report</h1>
        <?php if ($featured): $campaignGoal=(float)$featured['goal_amount']; $campaignRaised=(float)$featured['raised']; $campaignPct=$campaignGoal ? min(100,(int)round($campaignRaised/$campaignGoal*100)) : 0; ?>
          <article class="campaign-donation-summary">
            <h2><?= e($featured['title']) ?></h2>
            <p><?= nl2br(e($featured['description'])) ?></p>
            <div class="campaign-stats"><strong>₱<?= e(number_format($campaignRaised, 2)) ?> raised</strong> of ₱<?= e(number_format($campaignGoal, 2)) ?> goal</div>
            <div class="campaign-progress" aria-hidden="true"><span style="width:<?= $campaignPct ?>%"></span></div>
            <strong><?= $campaignPct ?>% funded</strong>
          </article>
        <?php endif; ?>
        <?php if (!$campaigns): ?><p>No open campaigns are accepting reports right now.</p><?php elseif (!$methods): ?><p>Donation methods are not configured yet.</p><?php else: ?>
        <p class="lede">This form records a payment you have already sent. It does not process payments. Your report stays pending until an admin verifies it.</p>
        <form method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="campaign">
          <label>Donation method
            <select data-donation-method name="donation_method_id" required>
              <option value="">Select a method</option>
              <?php foreach ($methods as $method): ?>
                <option value="<?= (int) $method['donation_method_id'] ?>" <?= (string) $method['donation_method_id'] === $old['donation_method_id'] ? 'selected' : '' ?>>
                  <?= e($method['method_name'] . ' · ' . $method['payment_provider']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        <div class="campaign-payment-methods"><div><?php foreach ($methods as $method): ?><article class="qr-card" data-donation-method-details="<?= (int) $method['donation_method_id'] ?>" <?= (string) $method['donation_method_id'] === $old['donation_method_id'] ? '' : 'hidden' ?>><strong><?=e($method['method_name'])?></strong><span><?=e($method['payment_provider'])?></span><?php if(!empty($method['qr_image_path'])&&media_file_exists($method['qr_image_path'])):?><button class="qr-expand" type="button" data-qr-expand aria-label="Expand <?=e($method['method_name'])?> QR code"><img src="<?=e(media_url($method['qr_image_path']))?>" alt="<?=e($method['method_name'])?> payment QR" loading="lazy"><span>Click to enlarge for scanning</span></button><?php endif; ?><?php if(!empty($method['instructions'])):?><p><?=nl2br(e($method['instructions']))?></p><?php endif; ?></article><?php endforeach; ?></div></div>
          <label>Campaign
            <select name="campaign_id" required>
              <?php foreach ($campaigns as $item): ?>
                <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === (int) ($featured['id'] ?? 0) ? 'selected' : '' ?>>
                  <?= e($item['title']) ?> · goal ₱<?= e(number_format((float) $item['goal_amount'], 2)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <p class="field-note" id="donation-report-privacy"><?= e($donationPrivacyNote) ?></p>
          <div class="form-grid">
            <label>Name (optional)
              <input name="donor_name" maxlength="120" value="<?= e($old['donor_name']) ?>">
            </label>
            <label>Email
              <input type="email" name="donor_email" required maxlength="150" aria-describedby="donation-report-privacy" value="<?= e($old['donor_email']) ?>">
            </label>
            <label>Contact info
              <input name="contact_info" maxlength="30" value="<?= e($old['contact_info']) ?>">
            </label>
            <label>Amount (PHP)
              <input type="number" name="amount_reported" min="1" step="0.01" required value="<?= e($old['amount_reported']) ?>">
            </label>
            <label>Payment date
              <input type="datetime-local" name="payment_date" required value="<?= e($old['payment_date']) ?>">
            </label>
            <label>Transaction reference
              <input name="transaction_reference" maxlength="100" value="<?= e($old['transaction_reference']) ?>">
            </label>
          </div>
          <label class="upload-box">Payment proof (optional)
            <input type="file" name="proof_of_payment" accept="image/jpeg,image/png,image/webp,image/gif">
          </label>
          <label class="check-line">
            <input type="checkbox" required> I have already sent this donation
          </label>
          <button class="button wide" type="submit">Submit donation report</button>
        </form>
        <?php endif; ?>
      </section>
    <?php elseif ($tab === 'howto'): ?>
      <section class="form-card donation-howto-page">
        <h1>How to Donate</h1>
        <p class="lede">You can support Reading Club 2000 with useful items or your time, as well as through monetary donations.</p>

        <div class="donation-howto-grid">
          <article>
            <h2>In-kind Donations</h2>
            <p>We may be able to accept items that help readers and library activities, such as:</p>
            <ul>
              <li>Books and reading materials in clean, usable condition</li>
              <li>School, art, and craft supplies for learning activities</li>
              <li>Other library or program supplies that are in good condition</li>
            </ul>
            <p>Accepted items can depend on current needs, available space, and condition.</p>
          </article>
          <article>
            <h2>Volunteer Your Time</h2>
            <p>You can also ask about helping with reading sessions, storytelling, or library activities. Let us know your interests and availability so we can coordinate.</p>
          </article>
        </div>

        <div class="donation-howto-next-step">
          <h2>Coordinate before bringing items</h2>
          <p>Please contact us first so we can confirm whether we can receive the donation and arrange a suitable time. Include the item type, quantity, condition, and your preferred drop-off or delivery schedule.</p>
          <a class="button" href="<?= e(app_url('public/inquiry.php')) ?>">Contact us about a donation</a>
        </div>
      </section>
    <?php else: ?>
      <section class="form-card donation-status-page"><span class="eyebrow">Donation transparency</span><h1>Check your donation report</h1><p>Enter the tracking ID from your receipt and the email used in your report.</p><form method="get" autocomplete="off"><input type="hidden" name="tab" value="status"><div class="form-grid"><label>Tracking ID<input name="reference" required value="<?= e($statusReference) ?>"></label><label>Email<input type="email" name="email" required value="<?= e($statusEmail) ?>"></label></div><button class="button" type="submit">Check status</button></form><?php if ($statusMessage): ?><p class="alert alert-error" role="alert"><?= e($statusMessage) ?></p><?php endif; ?><?php if ($statusRecord): ?><div class="donation-status-result"><span class="eyebrow"><?= e($statusRecord['reference_no']) ?></span><h2><?= $statusRecord['status']==='verified'?'Donation verified':($statusRecord['status']==='not_verified'?'Donation not verified':'Donation report received') ?></h2><p>Reported amount: ₱<?= e(number_format((float)$statusRecord['reported_amount'],2)) ?></p><?php if ($statusRecord['verified_amount']!==null): ?><p>Verified amount: ₱<?= e(number_format((float)$statusRecord['verified_amount'],2)) ?></p><?php endif; ?><?php if ($statusRecord['campaign_title']): ?><p>Campaign: <?= e($statusRecord['campaign_title']) ?></p><?php endif; ?><?php if ($statusRecord['verification_note']): ?><p><?= nl2br(e($statusRecord['verification_note'])) ?></p><?php endif; ?><p class="field-note"><?= $statusRecord['status']==='pending'?'Pending admin verification.':($statusRecord['verified_at']?'Reviewed '.e(date('F j, Y',strtotime($statusRecord['verified_at']))):'') ?></p></div><?php endif; ?></section>
    <?php endif; ?>
  </div>
</main>
<dialog class="qr-viewer" data-qr-viewer aria-label="Expanded donation QR code">
  <button class="qr-viewer-close" type="button" data-qr-viewer-close aria-label="Close expanded QR code">×</button>
  <img data-qr-viewer-image alt="Expanded donation QR code">
  <p>Scan this QR code with your payment app.</p>
</dialog>
<?php page_end(); ?>
