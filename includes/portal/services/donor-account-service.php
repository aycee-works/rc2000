<?php
/** Donor account queries and account status actions for the admin portal. */

const DONOR_ACCOUNTS_PER_PAGE = 12;

function donor_account_filters(): array
{
    $status = get_str('status');
    if (!in_array($status, ['active', 'disabled', 'unverified'], true)) {
        $status = '';
    }
    return [
        'q' => mb_substr(get_str('q'), 0, 100),
        'status' => $status,
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
    ];
}

function donor_account_summary(PDO $pdo): array
{
    $row = $pdo->query("SELECT COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status = 'active') AS active,
        COUNT(*) FILTER (WHERE status = 'disabled') AS disabled,
        COUNT(*) FILTER (WHERE email_verified_at IS NOT NULL) AS verified
        FROM rc2000_admin.donor_users")->fetch();
    return array_map('intval', $row ?: ['total' => 0, 'active' => 0, 'disabled' => 0, 'verified' => 0]);
}

function list_donor_accounts(PDO $pdo, array $filters): array
{
    $where = [];
    $params = [];
    if ($filters['q'] !== '') {
        $like = '%' . like_escape($filters['q']) . '%';
        $where[] = '(u.name ILIKE ? OR u.email ILIKE ?)';
        array_push($params, $like, $like);
    }
    if ($filters['status'] === 'unverified') {
        $where[] = 'u.email_verified_at IS NULL';
    } elseif ($filters['status'] !== '') {
        $where[] = 'u.status = ?';
        $params[] = $filters['status'];
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $count = $pdo->prepare("SELECT COUNT(*) FROM rc2000_admin.donor_users u $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / DONOR_ACCOUNTS_PER_PAGE));
    $page = min($filters['page'], $pages);
    $offset = ($page - 1) * DONOR_ACCOUNTS_PER_PAGE;
    $stmt = $pdo->prepare("SELECT u.id, u.name, u.email, u.email_verified_at, u.status,
        u.created_at, u.last_login_at,
        COALESCE(d.donation_count, 0) AS donation_count,
        COALESCE(d.verified_total, 0) AS verified_total
        FROM rc2000_admin.donor_users u
        LEFT JOIN (
            SELECT donor_user_id, COUNT(*) AS donation_count,
                SUM(verified_amount) FILTER (WHERE status = 'verified') AS verified_total
            FROM rc2000_admin.monetary_donations
            WHERE donor_user_id IS NOT NULL
            GROUP BY donor_user_id
        ) d ON d.donor_user_id = u.id
        $whereSql ORDER BY u.created_at DESC, u.id DESC
        LIMIT " . DONOR_ACCOUNTS_PER_PAGE . " OFFSET " . (int) $offset);
    $stmt->execute($params);
    return ['rows' => $stmt->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
}

function get_donor_account(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT u.*,
        COUNT(d.id) AS donation_count,
        COALESCE(SUM(d.verified_amount) FILTER (WHERE d.status = 'verified'), 0) AS verified_total
        FROM rc2000_admin.donor_users u
        LEFT JOIN rc2000_admin.monetary_donations d ON d.donor_user_id = u.id
        WHERE u.id = ? GROUP BY u.id");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function get_donor_account_inquiries(PDO $pdo, int $donorId): array
{
    $stmt = $pdo->prepare("SELECT i.id, i.reference_no, i.subject, i.status, i.created_at,
            GREATEST(i.created_at,
                COALESCE((SELECT MAX(r.created_at) FROM rc2000_admin.inquiry_responses r WHERE r.inquiry_id = i.id), i.created_at),
                COALESCE((SELECT MAX(m.created_at) FROM rc2000_admin.donor_inquiry_messages m WHERE m.inquiry_id = i.id), i.created_at)
            ) AS last_activity
        FROM rc2000_admin.inquiries i
        WHERE i.donor_user_id = ? ORDER BY i.created_at DESC, i.id DESC LIMIT 8");
    $stmt->execute([$donorId]);
    return $stmt->fetchAll();
}

function get_donor_data_requests(PDO $pdo, int $donorId): array
{
    $stmt = $pdo->prepare("SELECT * FROM rc2000_admin.donor_data_requests
        WHERE donor_user_id = ? ORDER BY created_at DESC, id DESC LIMIT 10");
    $stmt->execute([$donorId]);
    return $stmt->fetchAll();
}

function list_pending_donor_data_requests(PDO $pdo): array
{
    return $pdo->query("SELECT r.id, r.donor_user_id, r.donor_name_snapshot, r.donor_email_snapshot,
            r.request_type, r.status, r.created_at, u.status AS account_status
        FROM rc2000_admin.donor_data_requests r
        LEFT JOIN rc2000_admin.donor_users u ON u.id = r.donor_user_id
        WHERE r.status = 'pending' ORDER BY r.created_at ASC, r.id ASC LIMIT 20")->fetchAll();
}

function list_donor_account_donations(PDO $pdo, int $donorId): array
{
    $stmt = $pdo->prepare("SELECT id, reference_no, reported_amount, verified_amount, status, created_at, campaign_id,
        (SELECT title FROM rc2000_admin.campaigns c WHERE c.id = d.campaign_id) AS campaign_title
        FROM rc2000_admin.monetary_donations d
        WHERE donor_user_id = ? ORDER BY created_at DESC, id DESC LIMIT 8");
    $stmt->execute([$donorId]);
    return $stmt->fetchAll();
}

function set_donor_account_status(PDO $pdo, int $id, string $status, int $adminId, string $reason): bool
{
    if (!in_array($status, ['active', 'disabled'], true)) {
        throw new InvalidArgumentException('Invalid donor account status.');
    }
    $reason = trim($reason);
    if ($status === 'disabled' && ($reason === '' || mb_strlen($reason) > 500)) {
        throw new InvalidArgumentException('Enter a reason (up to 500 characters) before disabling this account.');
    }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, name, status FROM rc2000_admin.donor_users WHERE id = ? FOR UPDATE');
        $stmt->execute([$id]);
        $donor = $stmt->fetch();
        if (!$donor) {
            $pdo->rollBack();
            return false;
        }
        if ($donor['status'] === $status) {
            $pdo->rollBack();
            return true;
        }
        $pdo->prepare('UPDATE rc2000_admin.donor_users SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $id]);
        log_activity($status === 'disabled' ? 'donor_account_deactivated' : 'donor_account_reactivated', 'donor_user', $id,
            $donor['name'] . ': ' . $donor['status'] . ' -> ' . $status . ($reason !== '' ? '; reason: ' . $reason : ''), $adminId);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function update_donor_account_profile(PDO $pdo, int $id, string $name, string $email, int $adminId): void
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    $email = mb_strtolower(trim($email));
    if (!required_text($name, 200)) { throw new InvalidArgumentException('Enter a name of up to 200 characters.'); }
    if (!valid_email($email) || mb_strlen($email) > 250) { throw new InvalidArgumentException('Enter a valid email address of up to 250 characters.'); }
    $stmt = $pdo->prepare('SELECT name, email FROM rc2000_admin.donor_users WHERE id = ?');
    $stmt->execute([$id]);
    $old = $stmt->fetch();
    if (!$old) { throw new RuntimeException('That donor account could not be found.'); }
    $up = $pdo->prepare("UPDATE rc2000_admin.donor_users
        SET name = ?, email = ?,
            email_verified_at = CASE WHEN LOWER(BTRIM(email)) <> ? THEN NULL ELSE email_verified_at END,
            updated_at = NOW() WHERE id = ?");
    $up->execute([$name, $email, $email, $id]);
    log_activity('donor_account_profile_updated', 'donor_user', $id, $old['name'] . ' <' . $old['email'] . '> -> ' . $name . ' <' . $email . '>', $adminId);
}

function review_donor_data_request(PDO $pdo, int $requestId, int $adminId, string $decision, string $note): bool
{
    if (!in_array($decision, ['complete', 'reject'], true)) { throw new InvalidArgumentException('Choose a valid request action.'); }
    $note = trim($note);
    if (mb_strlen($note) > 1000) { throw new InvalidArgumentException('The admin note must be 1,000 characters or fewer.'); }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM rc2000_admin.donor_data_requests WHERE id = ? FOR UPDATE');
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();
        if (!$request || $request['status'] !== 'pending') { $pdo->rollBack(); return false; }
        $donorId = (int) ($request['donor_user_id'] ?? 0);
        if ($request['request_type'] === 'correction' && $decision === 'complete') {
            if ($donorId < 1) { throw new RuntimeException('The donor account no longer exists.'); }
            $name = trim((string) ($request['requested_name'] ?? ''));
            $email = mb_strtolower(trim((string) ($request['requested_email'] ?? '')));
            if (!required_text($name, 200) || !valid_email($email) || mb_strlen($email) > 250) {
                throw new RuntimeException('This correction request does not contain valid replacement account details.');
            }
            $pdo->prepare("UPDATE rc2000_admin.donor_users
                SET name = ?, email = ?,
                    email_verified_at = CASE WHEN LOWER(BTRIM(email)) <> ? THEN NULL ELSE email_verified_at END,
                    updated_at = NOW() WHERE id = ?")->execute([$name, $email, $email, $donorId]);
            log_activity('donor_account_profile_updated', 'donor_user', $donorId, 'Profile correction request #' . $requestId . ' completed.', $adminId);
        } elseif ($request['request_type'] === 'removal' && $decision === 'complete') {
            if ($donorId < 1) { throw new RuntimeException('The donor account no longer exists.'); }
            $donorStmt = $pdo->prepare('SELECT name FROM rc2000_admin.donor_users WHERE id = ? FOR UPDATE');
            $donorStmt->execute([$donorId]);
            $donor = $donorStmt->fetch();
            if (!$donor) { throw new RuntimeException('The donor account no longer exists.'); }
            $pdo->prepare("UPDATE rc2000_admin.monetary_donations
                SET donor_name = 'Former donor', donor_email = 'removed-' || id::text || '@invalid.example', donor_contact = NULL
                WHERE donor_user_id = ?")->execute([$donorId]);
            $pdo->prepare("UPDATE rc2000_admin.inquiry_responses
                SET body = '[Conversation text removed after account data removal request.]'
                WHERE inquiry_id IN (SELECT id FROM rc2000_admin.inquiries WHERE donor_user_id = ?)")->execute([$donorId]);
            $pdo->prepare("UPDATE rc2000_admin.donor_inquiry_messages
                SET donor_user_id = NULL, body = '[Conversation text removed after account data removal request.]'
                WHERE inquiry_id IN (SELECT id FROM rc2000_admin.inquiries WHERE donor_user_id = ?)")->execute([$donorId]);
            $pdo->prepare("UPDATE rc2000_admin.inquiries
                SET donor_user_id = NULL, name = 'Former donor', email = 'removed-' || id::text || '@invalid.example',
                    message = '[Inquiry text removed after account data removal request.]'
                WHERE donor_user_id = ?")->execute([$donorId]);
            $pdo->prepare("UPDATE rc2000_admin.donor_data_requests
                SET donor_name_snapshot = 'Former donor', donor_email_snapshot = 'removed-' || id::text || '@invalid.example',
                    requested_name = NULL, requested_email = NULL, details = 'Account removal completed.', admin_note = NULL
                WHERE donor_user_id = ?")->execute([$donorId]);
            log_activity('donor_account_removed', 'donor_user', $donorId, 'Account removed after data request #' . $requestId, $adminId);
            $pdo->prepare('DELETE FROM rc2000_admin.donor_users WHERE id = ?')->execute([$donorId]);
        }
        $newStatus = $decision === 'complete' ? 'completed' : 'rejected';
        $storedNote = $request['request_type'] === 'removal' && $decision === 'complete'
            ? 'Account removal completed.'
            : ($note === '' ? null : $note);
        $pdo->prepare('UPDATE rc2000_admin.donor_data_requests SET status = ?, reviewed_by = ?, admin_note = ?, reviewed_at = NOW() WHERE id = ?')
            ->execute([$newStatus, $adminId, $storedNote, $requestId]);
        $requestDetails = $request['request_type'] === 'removal' && $decision === 'complete'
            ? 'Account removal request #' . $requestId
            : $request['request_type'] . ' request for ' . $request['donor_name_snapshot'];
        log_activity('donor_data_request_' . $newStatus, 'donor_data_request', $requestId,
            $requestDetails . ($note !== '' && $request['request_type'] !== 'removal' ? '; note: ' . $note : ''), $adminId);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}

function donor_export_rows(PDO $pdo, int $donorId): array
{
    $account = get_donor_account($pdo, $donorId);
    if (!$account) { return []; }
    $stmt = $pdo->prepare("SELECT d.reference_no, d.created_at, d.campaign_id, c.title AS campaign_title,
            d.reported_amount, d.verified_amount, d.status, d.payment_method
        FROM rc2000_admin.monetary_donations d
        LEFT JOIN rc2000_admin.campaigns c ON c.id = d.campaign_id
        WHERE d.donor_user_id = ? ORDER BY d.created_at, d.id");
    $stmt->execute([$donorId]);
    $donations = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT reference_no, subject, category, status, message, created_at
        FROM rc2000_admin.inquiries WHERE donor_user_id = ? ORDER BY created_at");
    $stmt->execute([$donorId]);
    $inquiries = $stmt->fetchAll();
    $stmt = $pdo->prepare("SELECT i.reference_no, 'Donor'::text AS sender, m.body, m.created_at
        FROM rc2000_admin.donor_inquiry_messages m
        JOIN rc2000_admin.inquiries i ON i.id = m.inquiry_id
        WHERE i.donor_user_id = ?
        UNION ALL
        SELECT i.reference_no, COALESCE(a.name, 'Reading Club 2000')::text AS sender, r.body, r.created_at
        FROM rc2000_admin.inquiry_responses r
        JOIN rc2000_admin.inquiries i ON i.id = r.inquiry_id
        LEFT JOIN rc2000_admin.admins a ON a.id = r.admin_id
        WHERE i.donor_user_id = ?
        ORDER BY created_at");
    $stmt->execute([$donorId, $donorId]);
    return ['account' => $account, 'donations' => $donations, 'inquiries' => $inquiries, 'messages' => $stmt->fetchAll()];
}
