<?php
declare(strict_types=1);

function list_my_inquiries(PDO $pdo, int $donorId): array
{
    $stmt = $pdo->prepare("SELECT i.id, i.reference_no, i.subject, i.category, i.status, i.created_at,
            GREATEST(i.created_at,
                COALESCE((SELECT MAX(r.created_at) FROM rc2000_admin.inquiry_responses r WHERE r.inquiry_id = i.id), i.created_at),
                COALESCE((SELECT MAX(m.created_at) FROM rc2000_admin.donor_inquiry_messages m WHERE m.inquiry_id = i.id), i.created_at)
            ) AS last_activity
        FROM rc2000_admin.inquiries i WHERE i.donor_user_id = ?
        ORDER BY last_activity DESC, i.id DESC LIMIT 50");
    $stmt->execute([$donorId]);
    return $stmt->fetchAll();
}

function get_my_inquiry(PDO $pdo, int $donorId, int $inquiryId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM rc2000_admin.inquiries WHERE id = ? AND donor_user_id = ?');
    $stmt->execute([$inquiryId, $donorId]);
    return $stmt->fetch() ?: null;
}

function get_my_inquiry_thread(PDO $pdo, int $inquiryId): array
{
    $stmt = $pdo->prepare("SELECT * FROM (
        SELECT r.id, r.body, r.created_at, 'admin'::text AS sender_type, a.name AS sender_name
        FROM rc2000_admin.inquiry_responses r LEFT JOIN rc2000_admin.admins a ON a.id = r.admin_id
        WHERE r.inquiry_id = ?
        UNION ALL
        SELECT m.id, m.body, m.created_at, 'donor'::text AS sender_type, 'You'::text AS sender_name
        FROM rc2000_admin.donor_inquiry_messages m WHERE m.inquiry_id = ?
    ) thread ORDER BY created_at ASC, id ASC");
    $stmt->execute([$inquiryId, $inquiryId]);
    return $stmt->fetchAll();
}

function create_my_inquiry(PDO $pdo, array $donor, string $category, string $subject, string $body): string
{
    $categories = inquiry_categories();
    if (!isset($categories[$category])) { throw new InvalidArgumentException('Choose a valid inquiry category.'); }
    $category = $categories[$category];
    $subject = trim($subject);
    $body = trim($body);
    if (!required_text($subject, 200)) { throw new InvalidArgumentException('Enter a subject (up to 200 characters).'); }
    if (!required_text($body, 8000)) { throw new InvalidArgumentException('Enter a message (up to 8,000 characters).'); }
    $stmt = $pdo->prepare("INSERT INTO rc2000_admin.inquiries
        (reference_no, name, email, subject, category, message, donor_user_id)
        VALUES ('INQ-' || lpad(nextval('rc2000_admin.inquiry_seq')::text, 4, '0'), ?, ?, ?, ?, ?, ?)
        RETURNING id");
    $stmt->execute([$donor['name'], $donor['email'], $subject, $category, $body, (int) $donor['id']]);
    $id = (int) $stmt->fetchColumn();
    $ref = $pdo->prepare('SELECT reference_no FROM rc2000_admin.inquiries WHERE id = ?');
    $ref->execute([$id]);
    return (string) $ref->fetchColumn();
}

function reply_to_my_inquiry(PDO $pdo, int $donorId, int $inquiryId, string $body): void
{
    $body = trim($body);
    if (!required_text($body, 8000)) { throw new InvalidArgumentException('Enter a reply (up to 8,000 characters).'); }
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT status FROM rc2000_admin.inquiries WHERE id = ? AND donor_user_id = ? FOR UPDATE');
        $stmt->execute([$inquiryId, $donorId]);
        $inquiry = $stmt->fetch();
        if (!$inquiry) { throw new RuntimeException('That inquiry was not found in your account.'); }
        if ($inquiry['status'] === 'closed') { throw new RuntimeException('This inquiry is closed. Please start a new inquiry instead.'); }
        $pdo->prepare('INSERT INTO rc2000_admin.donor_inquiry_messages (inquiry_id, donor_user_id, body) VALUES (?, ?, ?)')
            ->execute([$inquiryId, $donorId, $body]);
        $status = $inquiry['status'] === 'resolved' ? 'in_progress' : $inquiry['status'];
        $pdo->prepare("UPDATE rc2000_admin.inquiries SET status = ?, resolved_by = NULL, resolved_at = NULL, updated_at = NOW() WHERE id = ?")
            ->execute([$status, $inquiryId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
