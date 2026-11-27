<?php
require_once dirname(__DIR__, 3) . '/includes/portal/bootstrap.php';
require_once dirname(__DIR__, 3) . '/includes/portal/services/donor-account-service.php';
require_once dirname(__DIR__, 3) . '/includes/donor/password-reset.php';

try {
    require_admin();
} catch (RuntimeException $e) {
    http_response_code(503);
    exit(e($e->getMessage()));
}

$currentAdminId = (int) (current_admin()['id'] ?? 0);
if (isset($_GET['export'])) {
    $exportId = max(0, (int) $_GET['export']);
    $export = donor_export_rows(db(), $exportId);
    if (!$export) { http_response_code(404); exit('Donor account not found.'); }
    log_activity('donor_account_exported', 'donor_user', $exportId, 'Individual donor CSV exported.', $currentAdminId);
    $safeCell = static function ($value): string {
        $value = (string) $value;
        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
    };
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="donor-' . $exportId . '-export.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fputcsv($out, ['Record type', 'Account ID', 'Name', 'Email', 'Account status', 'Joined', 'Donation reference', 'Date', 'Donation type', 'Campaign', 'Reported amount', 'Verified amount', 'Donation status', 'Payment method', 'Inquiry reference', 'Subject', 'Category', 'Inquiry status', 'Message', 'Sender']);
    $a = $export['account'];
    if (!$export['donations']) {
        fputcsv($out, array_map($safeCell, ['Account', $a['id'], $a['name'], $a['email'], $a['status'], $a['created_at'], '', '', '', '', '', '', '', '', '', '', '', '', '', '']));
    }
    foreach ($export['donations'] as $d) {
        fputcsv($out, array_map($safeCell, ['Donation', $a['id'], $a['name'], $a['email'], $a['status'], $a['created_at'], $d['reference_no'], $d['created_at'], $d['campaign_id'] ? 'Campaign' : 'General', $d['campaign_title'] ?? '', $d['reported_amount'], $d['verified_amount'] ?? '', $d['status'], $d['payment_method'], '', '', '', '', '', '']));
    }
    foreach ($export['inquiries'] as $i) {
        fputcsv($out, array_map($safeCell, ['Inquiry', $a['id'], $a['name'], $a['email'], $a['status'], $a['created_at'], '', '', '', '', '', '', '', '', $i['reference_no'], $i['subject'], $i['category'], $i['status'], $i['message'], '']));
    }
    foreach ($export['messages'] as $m) {
        fputcsv($out, array_map($safeCell, ['Inquiry message', $a['id'], $a['name'], $a['email'], $a['status'], $a['created_at'], '', '', '', '', '', '', '', '', $m['reference_no'], '', '', '', $m['body'], $m['sender']]));
    }
    fclose($out);
    exit;
}

if (is_post()) {
    csrf_require();
    $donorId = max(0, (int) post_str('donor_id'));
    $action = post_str('action');
    $goBackToAccount = true;
    try {
        if ($action === 'set_status') {
            $status = post_str('status');
            if ($donorId < 1 || !set_donor_account_status(db(), $donorId, $status, $currentAdminId, post_str('reason'))) {
                flash('error', 'That donor account could not be found.');
            } else { flash('success', $status === 'disabled' ? 'Donor account disabled.' : 'Donor account reactivated.'); }
        } elseif ($action === 'update_profile') {
            update_donor_account_profile(db(), $donorId, post_str('name'), post_str('email'), $currentAdminId);
            flash('success', 'Donor profile updated. A changed email is now marked unverified.');
        } elseif ($action === 'send_reset') {
            if (!send_donor_password_reset(db(), $donorId)) {
                flash('error', 'A reset link could not be sent. Check that mail is configured and the donor account is active.');
            } else {
                log_activity('donor_password_reset_sent', 'donor_user', $donorId, 'Admin sent a one-time password reset link.', $currentAdminId);
                flash('success', 'A one-time password reset link was emailed to the donor.');
            }
        } elseif ($action === 'review_request') {
            $requestId = max(0, (int) post_str('request_id'));
            $decision = post_str('decision');
            if (!review_donor_data_request(db(), $requestId, $currentAdminId, $decision, post_str('admin_note'))) {
                flash('error', 'That request is no longer pending. Refresh the page.');
            } else {
                if ($decision === 'complete' && post_str('request_type') === 'removal') { $goBackToAccount = false; }
                flash('success', $decision === 'complete' ? 'Data request processed.' : 'Data request declined.');
            }
        } else { flash('error', 'Unknown donor account action.'); }
    } catch (InvalidArgumentException $e) {
        flash('error', $e->getMessage());
    } catch (PDOException $e) {
        error_log('Donor account database action failed: ' . $e->getMessage());
        flash('error', $e->getCode() === '23505' ? 'That email address is already used by another account.' : 'The account action could not be completed. Please try again.');
    } catch (Throwable $e) {
        error_log('Donor account update failed: ' . $e->getMessage());
        flash('error', 'The account action could not be completed. Please try again.');
    }
    if (!$goBackToAccount) {
        redirect('admin/donors/index.php');
    }
    redirect('admin/donors/index.php' . ($donorId ? '?id=' . $donorId : ''));
}

$filters = donor_account_filters();
$list = ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
$summary = ['total' => 0, 'active' => 0, 'disabled' => 0, 'verified' => 0];
$selected = null;
$donations = [];
$linkedInquiries = [];
$dataRequests = [];
$pendingRequests = [];
$loadError = null;
try {
    $pdo = db();
    $summary = donor_account_summary($pdo);
    $pendingRequests = list_pending_donor_data_requests($pdo);
    $list = list_donor_accounts($pdo, $filters);
    $filters['page'] = $list['page'];
    $selectedId = max(0, (int) ($_GET['id'] ?? 0));
    if ($selectedId > 0) {
        $selected = get_donor_account($pdo, $selectedId);
        if ($selected) {
            $donations = list_donor_account_donations($pdo, $selectedId);
            $linkedInquiries = get_donor_account_inquiries($pdo, $selectedId);
            $dataRequests = get_donor_data_requests($pdo, $selectedId);
        } else { flash('error', 'That donor account could not be found.'); }
    }
} catch (Throwable $e) {
    error_log('Donor accounts load failed: ' . $e->getMessage());
    $loadError = 'Donor accounts could not be loaded. Confirm the donor tables and donation account link are installed.';
}

function donor_accounts_url(array $params): string
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0);
    return url('admin/donors/index.php' . ($params ? '?' . http_build_query($params) : ''));
}

$baseParams = ['q' => $filters['q'], 'status' => $filters['status'], 'page' => $filters['page'] > 1 ? $filters['page'] : ''];
$hasFilters = $filters['q'] !== '' || $filters['status'] !== '';
$pageTitle = 'Donor accounts';
$activeNav = 'donors';
$extraCss = ['donor-accounts.css?v=' . filemtime(dirname(__DIR__, 3) . '/public/assets/css/donor-accounts.css')];
require dirname(__DIR__, 3) . '/includes/portal/admin-header.php';
?>
<header class="page-header donor-page-header">
    <div><h1 class="page-title">Donor Accounts</h1><p class="page-subtitle">Review donor profiles, account status and linked donation history.</p></div>
</header>

<?php if ($loadError): ?><div class="flash flash-error" role="alert"><span><?= e($loadError) ?></span></div><?php endif; ?>

<section class="donor-stats" aria-label="Donor account summary">
    <article class="panel donor-stat"><span>All accounts</span><strong><?= number_format($summary['total']) ?></strong></article>
    <article class="panel donor-stat"><span>Active accounts</span><strong><?= number_format($summary['active']) ?></strong></article>
    <article class="panel donor-stat"><span>Email verified</span><strong><?= number_format($summary['verified']) ?></strong></article>
    <article class="panel donor-stat"><span>Disabled</span><strong><?= number_format($summary['disabled']) ?></strong></article>
</section>

<?php if ($pendingRequests): ?><section class="panel donor-pending-requests"><header><h2>Pending account data requests</h2><span>Showing <?= count($pendingRequests) ?> oldest</span></header><div class="donor-pending-request-list">
    <?php foreach ($pendingRequests as $request): ?><a href="<?= e(donor_accounts_url(['id' => (int) ($request['donor_user_id'] ?? 0)])) ?>"><strong><?= e(ucfirst($request['request_type'])) ?> request</strong><span><?= e($request['donor_name_snapshot']) ?> · <?= e($request['donor_email_snapshot']) ?></span><small><?= e(format_date_short($request['created_at'])) ?></small></a><?php endforeach; ?>
</div></section><?php endif; ?>

<div class="donor-layout">
    <section class="panel donor-list-panel" aria-label="Donor account list">
        <form class="filter-bar donor-filter" method="get" action="<?= e(url('admin/donors/index.php')) ?>">
            <input class="input filter-search" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search donor name or email" maxlength="100" aria-label="Search donor name or email">
            <select class="select filter-select" name="status" aria-label="Filter donor accounts">
                <option value="">All statuses</option>
                <option value="active"<?= $filters['status'] === 'active' ? ' selected' : '' ?>>Active</option>
                <option value="disabled"<?= $filters['status'] === 'disabled' ? ' selected' : '' ?>>Disabled</option>
                <option value="unverified"<?= $filters['status'] === 'unverified' ? ' selected' : '' ?>>Email not verified</option>
            </select>
            <button type="submit" class="btn btn-dark">Search</button>
            <?php if ($hasFilters): ?><a class="filter-reset" href="<?= e(donor_accounts_url([])) ?>">Clear</a><?php endif; ?>
        </form>
        <div class="table-wrap">
            <table class="data-table donor-table">
                <thead><tr><th>Donor</th><th>Joined</th><th>Donations</th><th>Verified total</th><th>Account</th><th></th></tr></thead>
                <tbody>
                <?php if (!$list['rows']): ?><tr><td colspan="6" class="table-empty"><?= $hasFilters ? 'No donor accounts match these filters.' : 'No donor accounts have registered yet.' ?></td></tr><?php endif; ?>
                <?php foreach ($list['rows'] as $row):
                    $isSelected = $selected && (int) $selected['id'] === (int) $row['id'];
                    $rowUrl = donor_accounts_url($baseParams + ['id' => (int) $row['id']]);
                ?>
                    <tr<?= $isSelected ? ' class="is-selected"' : '' ?>><td><a class="donor-name" href="<?= e($rowUrl) ?>"><?= e($row['name']) ?></a><small><?= e($row['email']) ?></small></td>
                        <td><?= e(format_date_short($row['created_at'])) ?></td><td><?= (int) $row['donation_count'] ?></td>
                        <td><?= e(format_peso($row['verified_total'])) ?></td><td><span class="donor-badge <?= $row['status'] === 'active' ? 'is-active' : 'is-disabled' ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                        <td><a href="<?= e($rowUrl) ?>">View</a></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($list['pages'] > 1): ?><nav class="pagination" aria-label="Donor account pages">
            <?php if ($list['page'] > 1): ?><a href="<?= e(donor_accounts_url(array_merge($baseParams, ['page' => $list['page'] - 1]))) ?>">Previous</a><?php endif; ?>
            <span>Page <?= (int) $list['page'] ?> of <?= (int) $list['pages'] ?></span>
            <?php if ($list['page'] < $list['pages']): ?><a href="<?= e(donor_accounts_url(array_merge($baseParams, ['page' => $list['page'] + 1]))) ?>">Next</a><?php endif; ?>
        </nav><?php endif; ?>
    </section>

    <aside class="panel donor-detail" aria-label="Selected donor details">
        <?php if (!$selected): ?><div class="donor-detail-empty"><span class="donor-avatar" aria-hidden="true">◎</span><h2>Select a donor</h2><p>Choose an account from the list to review its details and linked donation reports.</p></div>
        <?php else: ?>
            <div class="donor-profile-head"><span class="donor-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($selected['name'], 0, 1))) ?></span><div><h2><?= e($selected['name']) ?></h2><p><?= e($selected['email']) ?></p></div></div>
            <span class="donor-badge <?= $selected['status'] === 'active' ? 'is-active' : 'is-disabled' ?>"><?= e(ucfirst($selected['status'])) ?></span>
            <dl class="donor-meta"><div><dt>Email verification</dt><dd><?= $selected['email_verified_at'] ? 'Verified' : 'Not verified' ?></dd></div><div><dt>Joined</dt><dd><?= e(format_date_short($selected['created_at'])) ?></dd></div><div><dt>Last sign in</dt><dd><?= $selected['last_login_at'] ? e(format_date_short($selected['last_login_at'])) : 'No sign in recorded' ?></dd></div><div><dt>Linked reports</dt><dd><?= (int) $selected['donation_count'] ?></dd></div><div><dt>Verified donations</dt><dd><?= e(format_peso($selected['verified_total'])) ?></dd></div></dl>
            <h3 class="donor-history-title">Recent linked donations</h3>
            <?php if (!$donations): ?><p class="donor-muted">No donation reports are linked to this account yet.</p><?php else: ?><div class="donor-history-list">
                <?php foreach ($donations as $donation): ?><div class="donor-history-row"><div><strong><?= e($donation['reference_no']) ?></strong><small><?= e($donation['campaign_title'] ?: 'General monetary donation') ?> · <?= e(format_date_short($donation['created_at'])) ?></small></div><div><strong><?= e(format_peso($donation['reported_amount'])) ?></strong><small><?= e(ucwords(str_replace('_', ' ', $donation['status']))) ?></small></div></div><?php endforeach; ?>
            </div><?php endif; ?>
            <div class="donor-admin-actions">
                <a class="btn btn-light" href="<?= e(donor_accounts_url(['export' => (int) $selected['id']])) ?>">Export donor CSV</a>
                <form method="post" onsubmit="return confirm('Send a one-time password reset link to this donor email?');">
                    <?= csrf_field() ?><input type="hidden" name="action" value="send_reset"><input type="hidden" name="donor_id" value="<?= (int) $selected['id'] ?>">
                    <button class="btn btn-dark" type="submit">Send password reset link</button>
                </form>
                <form method="post" class="donor-status-form" onsubmit="return confirm('<?= $selected['status'] === 'active' ? 'Disable' : 'Reactivate' ?> this donor account?');">
                    <?= csrf_field() ?><input type="hidden" name="action" value="set_status"><input type="hidden" name="donor_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="status" value="<?= $selected['status'] === 'active' ? 'disabled' : 'active' ?>">
                    <?php if ($selected['status'] === 'active'): ?><label>Reason for disabling<textarea name="reason" rows="2" maxlength="500" required placeholder="Explain why this account is being disabled"></textarea></label><?php endif; ?>
                    <button class="btn <?= $selected['status'] === 'active' ? 'btn-red' : 'btn-maroon' ?>" type="submit"><?= $selected['status'] === 'active' ? 'Disable account' : 'Reactivate account' ?></button>
                </form>
            </div>
            <details class="donor-admin-section"><summary>Correct account details</summary>
                <form method="post" class="donor-admin-form">
                    <?= csrf_field() ?><input type="hidden" name="action" value="update_profile"><input type="hidden" name="donor_id" value="<?= (int) $selected['id'] ?>">
                    <label>Name<input name="name" maxlength="200" required value="<?= e($selected['name']) ?>"></label>
                    <label>Email<input type="email" name="email" maxlength="250" required value="<?= e($selected['email']) ?>"></label>
                    <p class="donor-muted">Changing the email clears its current verified marker.</p>
                    <button class="btn btn-dark" type="submit">Save account details</button>
                </form>
            </details>
            <details class="donor-admin-section"><summary>Linked inquiries <span><?= count($linkedInquiries) ?></span></summary>
                <?php if (!$linkedInquiries): ?><p class="donor-muted">No account-linked inquiries yet.</p><?php else: ?><ul class="donor-admin-link-list">
                    <?php foreach ($linkedInquiries as $inquiry): ?><li><a href="<?= e(url('admin/inquiries/index.php?id=' . (int) $inquiry['id'])) ?>"><strong><?= e($inquiry['subject']) ?></strong><small><?= e($inquiry['reference_no']) ?> · <?= e(ucwords(str_replace('_', ' ', $inquiry['status']))) ?></small></a></li><?php endforeach; ?>
                </ul><?php endif; ?>
            </details>
            <details class="donor-admin-section"<?= array_filter($dataRequests, fn($r) => $r['status'] === 'pending') ? ' open' : '' ?>><summary>Account data requests <span><?= count($dataRequests) ?></span></summary>
                <?php if (!$dataRequests): ?><p class="donor-muted">No correction or removal requests.</p><?php endif; ?>
                <?php foreach ($dataRequests as $request): ?><article class="donor-request-card"><div><strong><?= e(ucfirst($request['request_type'])) ?> request</strong><span class="donor-request-state"><?= e(ucfirst($request['status'])) ?></span></div>
                    <small><?= e(format_datetime($request['created_at'])) ?></small>
                    <?php if ($request['request_type'] === 'correction'): ?><p><?= e($request['requested_name'] ?? '') ?><br><?= e($request['requested_email'] ?? '') ?></p><?php endif; ?>
                    <?php if ($request['details']): ?><p><?= nl2br(e($request['details'])) ?></p><?php endif; ?>
                    <?php if ($request['admin_note']): ?><p class="donor-muted">Admin note: <?= e($request['admin_note']) ?></p><?php endif; ?>
                    <?php if ($request['status'] === 'pending'): ?><form method="post" class="donor-request-review" onsubmit="return confirm('<?= $request['request_type'] === 'removal' ? 'This permanently deletes the donor login, redacts account details and inquiry conversation text, and keeps donation amounts, verification history, and uploaded payment proof. Process this removal?' : 'Apply this correction request?' ?>');">
                        <?= csrf_field() ?><input type="hidden" name="action" value="review_request"><input type="hidden" name="donor_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="request_id" value="<?= (int) $request['id'] ?>"><input type="hidden" name="request_type" value="<?= e($request['request_type']) ?>">
                        <label>Admin note<textarea name="admin_note" rows="2" maxlength="1000"></textarea></label>
                        <div><button class="btn btn-green" name="decision" value="complete" type="submit">Process request</button><button class="btn btn-light" name="decision" value="reject" type="submit" onclick="this.form.onsubmit=null; return confirm('Decline this request?')">Decline</button></div>
                    </form><?php endif; ?>
                </article><?php endforeach; ?>
            </details>
        <?php endif; ?>
    </aside>
</div>
<?php require dirname(__DIR__, 3) . '/includes/portal/admin-footer.php'; ?>
