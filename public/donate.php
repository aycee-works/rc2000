<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/community.php';

$tab = in_array($_GET['tab'] ?? '', ['money', 'campaign', 'status'], true) ? (string) $_GET['tab'] : 'money';

$methods = active_donation_methods();
$errors = [];
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
    'anonymous' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    }

    $action = (string) ($_POST['action'] ?? '');
    foreach ($old as $key => $_) {
        if ($key !== 'anonymous') {
            $old[$key] = post_string($key);
        }
    }
    $old['anonymous'] = isset($_POST['anonymous']) ? '1' : '';
    $tab = in_array($_POST['action'] ?? '', ['campaign'], true) ? 'campaign' : 'money';

    if (!$errors && $action === 'campaign') {
        $campaignId = (int) post_string('campaign_id');
        $name = $old['anonymous'] === '1' ? 'Anonymous' : trim($old['donor_name']);
        if ($name === '' || mb_strlen($name) > 120) $errors[] = 'Please enter your name or select anonymous giving.';
        if (!valid_email($old['donor_email']) || mb_strlen($old['donor_email']) > 150) $errors[] = 'Please enter a valid email address.';
        if (!valid_amount($old['amount_reported'])) $errors[] = 'Please enter a valid amount.';
        if (!valid_date_time($old['payment_date'])) $errors[] = 'Please enter the payment date.';
        if (!required_text($old['transaction_reference'], 100)) $errors[] = 'Please enter a transaction reference or check number (100 characters maximum).';
        $contact = trim($old['contact_info']);
        if ($contact !== '' && !preg_match('/^[0-9+() \-]{7,30}$/', $contact)) $errors[] = 'Contact number may only contain digits, spaces, + ( ) and - (up to 30 characters).';
        $campaign = fetch_one("SELECT id FROM rc2000_admin.campaigns WHERE id = :id AND status = 'open'", ['id' => $campaignId]);
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
                $stmt->execute(['name' => $name, 'email' => strtolower(trim($old['donor_email'])), 'contact' => $contact ?: null, 'amount' => number_format((float) $old['amount_reported'], 2, '.', ''), 'method' => $methodName, 'reference' => $old['transaction_reference'], 'proof' => $proof, 'campaign' => $campaignId]);
                $trackingId = (string) $stmt->fetchColumn();
                flash_set('success', 'Donation report received. Tracking ID: ' . $trackingId . '. It will appear as verified only after admin review.');
                redirect('/public/donate.php?tab=status&reference=' . rawurlencode($trackingId));
            } catch (Throwable $error) {
                error_log('Campaign donation report failed: ' . $error->getMessage());
                $errors[] = 'We could not save your campaign donation report yet. Please try again.';
            }
        }
    }

    if (!$errors && $action === 'monetary') {
        $donorName = $old['anonymous'] === '1' ? 'Anonymous' : $old['donor_name'];
        if ($old['anonymous'] !== '1' && !required_text($donorName, 150)) {
            $errors[] = 'Please enter your name, or donate anonymously.';
        }
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
            try {
                $pdo = db();
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO public.monetary_donations
                        (donor_name, contact_info, donation_method_id, amount_reported, transaction_reference, payment_date, proof_of_payment_path)
                     VALUES
                        (:donor_name, :contact_info, :donation_method_id, :amount_reported, :transaction_reference, :payment_date, :proof_of_payment_path)'
                );
                $stmt->execute([
                    'donor_name' => $donorName,
                    'contact_info' => $old['contact_info'] !== '' ? $old['contact_info'] : null,
                    'donation_method_id' => $methodId,
                    'amount_reported' => number_format((float) $old['amount_reported'], 2, '.', ''),
                    'transaction_reference' => $old['transaction_reference'],
                    'payment_date' => date('c', strtotime($old['payment_date'])),
                    'proof_of_payment_path' => $proof,
                ]);
                $method = null;
                foreach ($methods as $availableMethod) {
                    if ((int) $availableMethod['donation_method_id'] === $methodId) {
                        $method = trim($availableMethod['method_name'] . ' · ' . $availableMethod['payment_provider']);
                        break;
                    }
                }
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
                    'payment_method' => mb_substr((string) $method, 0, 40),
                    'transaction_ref' => $old['transaction_reference'],
                    'proof_path' => $proof,
                ]);
                $trackingId = (string) $portal->fetchColumn();
                $pdo->commit();
                flash_set('success', 'Donation report received. Tracking ID: ' . $trackingId . '. It will appear as verified only after admin review.');
                redirect('/public/donate.php?tab=status&reference=' . rawurlencode($trackingId));
            } catch (PDOException $error) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Monetary donation save failed: ' . $error->getMessage());
                if (str_contains($error->getMessage(), 'uq_monetary_method_reference')) {
                    $errors[] = 'That transaction reference was already recorded for this method.';
                } else {
                    $errors[] = 'We could not save your report yet. Please try again when the database is available.';
                }
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
if ($tab === 'status' && isset($_GET['reference'], $_GET['email'])) {
    $statusRecord = fetch_one('SELECT d.reference_no, d.donor_email, d.reported_amount, d.verified_amount, d.status, d.verification_note, d.verified_at, d.created_at, c.title AS campaign_title FROM rc2000_admin.monetary_donations d LEFT JOIN rc2000_admin.campaigns c ON c.id = d.campaign_id WHERE upper(d.reference_no) = upper(:ref) AND lower(d.donor_email) = lower(:email)', ['ref' => trim((string) $_GET['reference']), 'email' => trim((string) $_GET['email'])]);
    if (!$statusRecord) $statusMessage = 'We could not find a donation with that tracking ID and email address.';
}

page_start('Donate');
?>
<main class="donate-layout">
  <aside class="donate-side">
    <p class="eyebrow">Donate by</p>
    <a class="<?= $tab === 'money' ? 'is-active' : '' ?>" href="<?= e(app_url('public/donate.php?tab=money')) ?>">Monetary Donation</a>
    <a class="<?= $tab === 'campaign' ? 'is-active' : '' ?>" href="<?= e(app_url('public/donate.php?tab=campaign')) ?>">Campaign Donation</a>
    <a class="<?= $tab === 'status' ? 'is-active' : '' ?>" href="<?= e(app_url('public/donate.php?tab=status')) ?>">Donation Status</a>
  </aside>

  <div class="donate-main">
    <?php render_alerts($errors); ?>

    <section class="howto">
      <h3>How to donate</h3>
      <ol>
        <li>Use QR, bank transfer, or check, then fill out the donation form so we can thank you.</li>
        <li>You’ll get a confirmation after you submit.</li>
      </ol>
    </section>

    <?php if ($tab === 'money'): ?>
      <section class="form-card">
        <h1>Monetary donation</h1>
        <p class="lede">After donating, please fill out this form so we can thank you. The amount is self-reported and recorded for transparency; it is not independently tracked through this form.</p>

        <div class="method-tabs" data-tabs>
            <button type="button" class="is-active" data-tab-target="qr">QR Codes</button>
            <button type="button" data-tab-target="bank">Bank Transfer</button>
            <button type="button" data-tab-target="check">Check</button>
          </div>

        <div class="method-panels">
          <div class="is-active" data-tab-panel="qr">
            <?php $qrMethods = array_values(array_filter($methods, static fn ($m) => stripos($m['payment_provider'] . $m['method_name'], 'qr') !== false || stripos($m['payment_provider'], 'gcash') !== false || stripos($m['payment_provider'], 'maya') !== false)); ?>
            <?php if ($qrMethods): foreach ($qrMethods as $method): ?>
              <figure class="qr-card">
                <?php if (media_file_exists($method['qr_image_path'])): ?>
                  <img src="<?= e(media_url($method['qr_image_path'])) ?>" alt="<?= e($method['method_name']) ?>">
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
          <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="monetary">
            <label class="check-line">
              <input type="checkbox" name="anonymous" value="1" <?= $old['anonymous'] ? 'checked' : '' ?>>
              Donate anonymously
            </label>
            <div class="form-grid">
              <label>Name 
                <input name="donor_name" maxlength="150" value="<?= e($old['donor_name']) ?>">
              </label>
              <label>Email
                <input type="email" name="donor_email" required maxlength="150" value="<?= e($old['donor_email']) ?>">
              </label>
              <label>Contact info
                <input name="contact_info" maxlength="255" value="<?= e($old['contact_info']) ?>">
              </label>
              <label>Amount
                <input type="number" name="amount_reported" min="1" step="0.01" required value="<?= e($old['amount_reported']) ?>">
              </label>
              <label class="payment-date-label"><span><img src="<?= e(app_url('public/assets/images/icons/donationdate_icon.png')) ?>" alt="" width="18" height="18">Payment date</span>
                <input type="datetime-local" name="payment_date" required value="<?= e($old['payment_date']) ?>">
              </label>
              <label>Donation method
                <select name="donation_method_id" required>
                  <option value="">Select a method</option>
                  <?php foreach ($methods as $method): ?>
                    <option value="<?= (int) $method['donation_method_id'] ?>" <?= (string) $method['donation_method_id'] === $old['donation_method_id'] ? 'selected' : '' ?>>
                      <?= e($method['method_name'] . ' · ' . $method['payment_provider']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>Transaction / check number
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
        <span class="eyebrow">Support reading in our community</span><h1>Make a campaign donation report</h1>
        <?php if ($featured): $campaignGoal=(float)$featured['goal_amount']; $campaignRaised=(float)$featured['raised']; $campaignPct=$campaignGoal ? min(100,(int)round($campaignRaised/$campaignGoal*100)) : 0; ?>
          <article class="campaign-donation-summary"><h2><?= e($featured['title']) ?></h2><p><?= nl2br(e($featured['description'])) ?></p><div class="campaign-stats"><strong>₱<?= e(number_format($campaignRaised,2)) ?> raised</strong><span>of ₱<?= e(number_format($campaignGoal,2)) ?> goal</span></div><div class="campaign-progress"><span style="width:<?= $campaignPct ?>%"></span></div><strong><?= $campaignPct ?>% funded</strong></article>
        <?php endif; ?>
        <?php if (!$campaigns): ?><p>No open campaigns are accepting reports right now.</p><?php elseif (!$methods): ?><p>Donation methods are not configured yet.</p><?php else: ?>
        <p class="lede">This form records a payment you have already sent. It does not process payments. Your report stays pending until an admin verifies it.</p>
        <div class="campaign-payment-methods"><h2>Choose a payment method</h2><div><?php foreach ($methods as $method): ?><article><strong><?=e($method['method_name'])?></strong><span><?=e($method['payment_provider'])?></span><?php if(!empty($method['qr_image_path'])&&media_file_exists($method['qr_image_path'])):?><img src="<?=e(media_url($method['qr_image_path']))?>" alt="<?=e($method['method_name'])?> payment QR" loading="lazy"><?php endif; ?><?php if(!empty($method['instructions'])):?><p><?=nl2br(e($method['instructions']))?></p><?php endif; ?></article><?php endforeach; ?></div></div>
        <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="campaign"><label>Campaign<select name="campaign_id" required><?php foreach ($campaigns as $item): ?><option value="<?= (int)$item['id'] ?>" <?= (int)$item['id']===(int)($featured['id']??0)?'selected':'' ?>><?= e($item['title']) ?> · goal ₱<?= e(number_format((float)$item['goal_amount'],2)) ?></option><?php endforeach; ?></select></label><label class="check-line"><input type="checkbox" name="anonymous" value="1" <?= $old['anonymous']?'checked':'' ?>> Donate anonymously</label><div class="form-grid"><label>Name<input name="donor_name" maxlength="120" value="<?= e($old['donor_name']) ?>"></label><label>Email<input type="email" name="donor_email" required maxlength="150" value="<?= e($old['donor_email']) ?>"></label><label>Contact info<input name="contact_info" maxlength="30" value="<?= e($old['contact_info']) ?>"></label><label>Amount (PHP)<input type="number" name="amount_reported" min="1" step="0.01" required value="<?= e($old['amount_reported']) ?>"></label><label>Payment date<input type="datetime-local" name="payment_date" required value="<?= e($old['payment_date']) ?>"></label><label>Donation method<select name="donation_method_id" required><option value="">Select a method</option><?php foreach($methods as $method): ?><option value="<?= (int)$method['donation_method_id'] ?>"><?= e($method['method_name'].' · '.$method['payment_provider']) ?></option><?php endforeach; ?></select></label><label>Transaction reference<input name="transaction_reference" maxlength="100" value="<?= e($old['transaction_reference']) ?>"></label></div><label class="upload-box">Payment proof (optional)<input type="file" name="proof_of_payment" accept="image/jpeg,image/png,image/webp,image/gif"></label><label class="check-line"><input type="checkbox" required> I have already sent this donation</label><button class="button wide" type="submit">Submit donation report</button></form>
        <?php endif; ?>
      </section>
    <?php else: ?>
      <section class="form-card donation-status-page"><span class="eyebrow">Donation transparency</span><h1>Check your donation report</h1><p>Enter the tracking ID from your receipt and the email used in your report.</p><form method="get"><input type="hidden" name="tab" value="status"><div class="form-grid"><label>Tracking ID<input name="reference" required value="<?= e((string)($_GET['reference']??'')) ?>"></label><label>Email<input type="email" name="email" required value="<?= e((string)($_GET['email']??'')) ?>"></label></div><button class="button" type="submit">Check status</button></form><?php if ($statusMessage): ?><p class="alert alert-error" role="alert"><?= e($statusMessage) ?></p><?php endif; ?><?php if ($statusRecord): ?><div class="donation-status-result"><span class="eyebrow"><?= e($statusRecord['reference_no']) ?></span><h2><?= $statusRecord['status']==='verified'?'Donation verified':($statusRecord['status']==='not_verified'?'Donation not verified':'Donation report received') ?></h2><p>Reported amount: ₱<?= e(number_format((float)$statusRecord['reported_amount'],2)) ?></p><?php if ($statusRecord['verified_amount']!==null): ?><p>Verified amount: ₱<?= e(number_format((float)$statusRecord['verified_amount'],2)) ?></p><?php endif; ?><?php if ($statusRecord['campaign_title']): ?><p>Campaign: <?= e($statusRecord['campaign_title']) ?></p><?php endif; ?><?php if ($statusRecord['verification_note']): ?><p><?= nl2br(e($statusRecord['verification_note'])) ?></p><?php endif; ?><p class="field-note"><?= $statusRecord['status']==='pending'?'Pending admin verification.':($statusRecord['verified_at']?'Reviewed '.e(date('F j, Y',strtotime($statusRecord['verified_at']))):'') ?></p></div><?php endif; ?></section>
    <?php endif; ?>
  </div>
</main>
<?php page_end(); ?>
