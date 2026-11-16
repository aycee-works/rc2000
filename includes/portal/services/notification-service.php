<?php
/**
 * Notifications shown in the Admin and Super Admin portals.
 * Admins see new public submissions; Super Admins see recorded admin activity.
 */

function portal_notification_source_sql(string $role): string
{
    if ($role === 'admin') {
        return <<<'SQL'
SELECT 'inquiry'::text AS kind, id AS source_id, reference_no, subject AS summary, created_at
FROM inquiries
UNION ALL
SELECT 'book_donation'::text, id, reference_no, book_title, created_at
FROM book_donations
UNION ALL
SELECT 'monetary_donation'::text, id, reference_no, payment_method, created_at
FROM monetary_donations
SQL;
    }

    return <<<'SQL'
SELECT action AS kind, id AS source_id, entity_type, entity_id, details AS summary, created_at
FROM activity_logs
WHERE action NOT IN ('login', 'logout')
SQL;
}

function portal_notification_item(array $row, string $role): array
{
    $id = (int) $row['source_id'];
    if ($role === 'admin') {
        $kind = $row['kind'];
        $title = [
            'inquiry' => 'New inquiry received',
            'book_donation' => 'New book donation',
            'monetary_donation' => 'New monetary donation',
        ][$kind] ?? 'New submission';
        $summary = trim((string) ($row['summary'] ?? ''));
        $message = trim((string) $row['reference_no']);
        if ($summary !== '') {
            $message .= ' · ' . $summary;
        }
        $href = match ($kind) {
            'inquiry' => url('admin/inquiries/index.php?id=' . $id),
            'book_donation' => url('admin/donations/index.php?tab=book&id=' . $id),
            default => url('admin/donations/index.php?tab=monetary&id=' . $id),
        };
    } else {
        $kind = $row['kind'];
        $title = match ($kind) {
            'admin_created' => 'New admin account created',
            'admin_updated' => 'Admin account changed',
            'admin_deactivated' => 'Admin account deactivated',
            'admin_reactivated' => 'Admin account reactivated',
            'admin_removed' => 'Admin account removed',
            'admin_password_reset', 'admin_password_set' => 'Admin password changed',
            default => 'Administrative activity recorded',
        };
        $message = trim((string) ($row['summary'] ?? ''));
        if ($message === '') {
            $message = 'Review the recent activity log.';
        }
        $href = url('superadmin/audit-logs.php');
    }

    return [
        'id' => (string) $row['kind'] . '-' . $id,
        'title' => $title,
        'message' => $message,
        'url' => $href,
        'created_at' => (new DateTimeImmutable((string) $row['created_at']))->format(DATE_ATOM),
    ];
}

function portal_notifications(PDO $pdo, string $role, string $since): array
{
    if (!in_array($role, ['admin', 'super_admin'], true)) {
        throw new InvalidArgumentException('Unknown portal role.');
    }

    $source = portal_notification_source_sql($role);
    $query = $pdo->query('SELECT * FROM (' . $source . ') AS notification_events ORDER BY created_at DESC, source_id DESC LIMIT 10');
    $rows = $query->fetchAll();
    $items = array_map(fn(array $row) => portal_notification_item($row, $role), $rows);

    $unreadCount = 0;
    if ($since !== '' && strlen($since) <= 50) {
        try {
            $sinceTime = (new DateTimeImmutable($since))->format('Y-m-d H:i:sP');
            $count = $pdo->prepare('SELECT COUNT(*) FROM (' . $source . ') AS notification_events WHERE created_at > ?');
            $count->execute([$sinceTime]);
            $unreadCount = (int) $count->fetchColumn();
        } catch (Throwable $e) {
            $unreadCount = 0;
        }
    }

    return [
        'items' => $items,
        'unread_count' => $unreadCount,
        'server_time' => (new DateTimeImmutable())->format(DATE_ATOM),
    ];
}
