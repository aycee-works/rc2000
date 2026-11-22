<?php
/**
 * includes/portal/services/archive-service.php
 * READ-ONLY queries for the Archive page (admin: Review, super admin: View).
 * Nothing here inserts, updates or deletes.
 */

require_once __DIR__ . '/inquiry-service.php';   // get_inquiry(), get_inquiry_responses(), inquiry_email_label()

const ARCHIVE_PER_PAGE = 10;

function archive_type_labels(): array
{
    return [
        'inquiry'      => 'Inquiry',
        'book_donation' => 'Book donation',
        'monetary_donation' => 'Monetary donation',
        'quiz'         => 'Quiz',
        'leaderboard'  => 'Quiz leaderboard',
        'challenge'    => 'Reading challenge',
        'campaign'     => 'Campaign',
        'story'        => 'Story',
        'article'      => 'Article',
        'announcement' => 'Announcement',
        'gallery'      => 'Gallery item',
        'trivia'       => 'Trivia',
    ];
}

/** Tab -> record types. */
function archive_groups(): array
{
    return [
        'inquiries'  => ['inquiry'],
        'donations'  => ['book_donation', 'monetary_donation'],
        'activities' => ['quiz', 'leaderboard', 'challenge'],
        'campaigns'  => ['campaign'],
        'content'    => ['story', 'article', 'announcement', 'gallery', 'trivia'],
    ];
}

/** One list of every archived record: type, id, record, status_key, status_label, archived_at. */
function archive_union_sql(): string
{
    return "
    SELECT 'inquiry'::text AS type, i.id AS id,
           (COALESCE(i.reference_no, 'INQ-' || i.id) || ' · ' || i.subject)::text AS record,
           i.status::text AS status_key,
           (CASE WHEN i.status = 'resolved' THEN 'Resolved' ELSE 'Closed' END)::text AS status_label,
           COALESCE(i.closed_at, i.resolved_at, i.updated_at) AS archived_at
    FROM rc2000_admin.inquiries i
    WHERE i.status IN ('resolved', 'closed')

    UNION ALL
    SELECT 'book_donation'::text, b.id,
           (COALESCE(b.reference_no, 'BD-' || b.id) || ' · ' || b.book_title || ' · ' || b.donor_name)::text,
           b.status::text,
           (CASE WHEN b.status = 'approved' THEN 'Approved' ELSE 'Rejected' END)::text,
           COALESCE(b.reviewed_at, b.updated_at, b.created_at)
    FROM rc2000_admin.book_donations b
    WHERE b.status IN ('approved', 'rejected')

    UNION ALL
    SELECT 'monetary_donation'::text, m.id,
           (COALESCE(m.reference_no, 'MD-' || m.id) || ' · ' || m.donor_name || ' · ₱'
             || to_char(m.reported_amount, 'FM999,999,999,990.00'))::text,
           m.status::text,
           (CASE WHEN m.status = 'verified' THEN 'Verified' ELSE 'Not verified' END)::text,
           COALESCE(m.verified_at, m.updated_at, m.created_at)
    FROM rc2000_admin.monetary_donations m
    WHERE m.status IN ('verified', 'not_verified')

    UNION ALL
    SELECT 'quiz'::text, q.id,
           ('Daily quiz · ' || to_char(q.quiz_date, 'Mon FMDD, YYYY')
             || ' · ' || (SELECT COUNT(*) FROM rc2000_admin.quiz_submissions s WHERE s.quiz_id = q.id) || ' entries · '
             || (CASE WHEN EXISTS (SELECT 1 FROM rc2000_admin.quiz_leaderboard_posts p
                                   WHERE p.quiz_id = q.id OR p.week_start = date_trunc('week', q.quiz_date::timestamp)::date)
                      THEN 'results published' ELSE 'results not published' END))::text,
           (CASE WHEN q.status = 'closed' THEN 'closed' ELSE 'ended' END)::text,
           (CASE WHEN q.status = 'closed' THEN 'Closed' ELSE 'Ended' END)::text,
           q.quiz_date::timestamptz
    FROM rc2000_admin.daily_quizzes q
    WHERE q.status = 'closed' OR (q.status = 'published' AND q.quiz_date < (CURRENT_TIMESTAMP AT TIME ZONE 'Asia/Manila')::date)

    UNION ALL
    SELECT 'leaderboard'::text, p.quiz_id,
           ((CASE WHEN p.week_start IS NOT NULL THEN 'Week of ' || to_char(p.week_start, 'Mon FMDD') ELSE st.title END)
             || ' · ' || (SELECT COUNT(DISTINCT s.username)
                          FROM rc2000_admin.quiz_submissions s
                          JOIN rc2000_admin.daily_quizzes q2 ON q2.id = s.quiz_id
                          WHERE p.week_start IS NOT NULL AND q2.quiz_date >= p.week_start AND q2.quiz_date < p.week_start + 7)
             || ' participants')::text,
           'published'::text, 'Published'::text, p.posted_at
    FROM rc2000_admin.quiz_leaderboard_posts p
    JOIN rc2000_admin.stories st ON st.id = p.story_id

    UNION ALL
    SELECT 'challenge'::text, c.id,
           (c.title || ' · ' || (SELECT COUNT(*) FROM rc2000_admin.challenge_votes v WHERE v.challenge_id = c.id AND v.choice = 'accept')
             || ' joining · ' || (SELECT COUNT(*) FROM rc2000_admin.challenge_votes v WHERE v.challenge_id = c.id AND v.choice = 'not_for_me')
             || ' not now')::text,
           (CASE WHEN c.status = 'closed' THEN 'closed' ELSE 'ended' END)::text,
           (CASE WHEN c.status = 'closed' THEN 'Closed' ELSE 'Ended' END)::text,
           c.ends_at
    FROM rc2000_admin.challenge_posts c
    WHERE c.status = 'closed' OR (c.status = 'published' AND c.ends_at <= now())

    UNION ALL
    SELECT 'campaign'::text, c.id,
           (c.title || ' · goal ₱' || to_char(c.goal_amount, 'FM999,999,999,990'))::text,
           'closed'::text, 'Closed'::text, c.updated_at
    FROM rc2000_admin.campaigns c
    WHERE c.status = 'closed'

    UNION ALL
    SELECT (CASE WHEN s.type = 'article' THEN 'article' ELSE 'story' END)::text, s.id,
           s.title::text, 'archived'::text, 'Archived'::text, s.updated_at
    FROM rc2000_admin.stories s
    WHERE s.status = 'archived'

    UNION ALL
    SELECT 'announcement'::text, a.id, a.title::text, 'archived'::text, 'Archived'::text, a.updated_at
    FROM rc2000_admin.announcements a
    WHERE a.status = 'archived'

    UNION ALL
    SELECT 'gallery'::text, g.id, g.title::text, 'archived'::text, 'Archived'::text, g.updated_at
    FROM rc2000_admin.gallery_images g
    WHERE g.status = 'archived'

    UNION ALL
    SELECT 'trivia'::text, t.id, t.question::text, 'archived'::text, 'Archived'::text, t.updated_at
    FROM rc2000_admin.trivia t
    WHERE t.status = 'archived'";
}

/** Status pill using the existing badge colours. */
function archive_badge(string $key, string $label): string
{
    $class = [
        'resolved'  => 'badge-resolved',
        'published' => 'badge-published',
        'ended'     => 'badge-resolved',
        'closed'    => 'badge-closed',
        'archived'  => 'badge-archived',
        'approved'  => 'badge-published',
        'rejected'  => 'badge-closed',
        'verified'  => 'badge-resolved',
        'not_verified' => 'badge-closed',
    ][$key] ?? 'badge-closed';
    return '<span class="badge ' . e($class) . '">' . e($label) . '</span>';
}

/** Link back to the archive page. Empty values are dropped. */
function archive_url(string $base, array $params): string
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== 'all');
    return url($base . ($params ? '?' . http_build_query($params) : ''));
}

/** Where an ADMIN can manage the original record (never shown to the super admin). */
function archive_manage_url(string $type, int $id): ?string
{
    return [
        'inquiry'      => url('admin/inquiries/index.php?id=' . $id),
        'monetary_donation' => url('admin/donations/index.php?id=' . $id),
        'quiz'         => url('admin/content/index.php?quiz=' . $id . '#daily-quiz'),
        'challenge'    => url('admin/content/index.php?challenge=' . $id . '#weekly-challenge'),
        'campaign'     => url('admin/community.php?campaign=' . $id . '#campaigns'),
        'story'        => url('admin/content/form.php?entity=story&id=' . $id),
        'article'      => url('admin/content/form.php?entity=story&id=' . $id),
        'announcement' => url('admin/content/form.php?entity=announcement&id=' . $id),
        'gallery'      => url('admin/content/index.php'),
        'trivia'       => url('admin/content/form.php?entity=trivia&id=' . $id),
    ][$type] ?? null;
}

function archive_filters_from_request(): array
{
    $tab = get_str('tab');
    if (!in_array($tab, ['inquiries', 'donations', 'activities', 'campaigns', 'content'], true)) {
    $tab = 'all';
    }
    return [
        'tab'  => $tab,
        'q'    => mb_substr(get_str('q'), 0, 100),
        'page' => max(1, (int) ($_GET['page'] ?? 1)),
    ];
}

/** Numbers for the five summary cards. */
function archive_counts(PDO $pdo): array
{
    $rows = $pdo->query('SELECT type, COUNT(*) AS n FROM (' . archive_union_sql() . ') a GROUP BY type')->fetchAll();
    $by = [];
    foreach ($rows as $r) {
        $by[$r['type']] = (int) $r['n'];
    }
    $sum = fn(array $types) => array_sum(array_map(fn($t) => $by[$t] ?? 0, $types));
    return [
        'inquiries'    => $sum(['inquiry']),
        'donations'    => $sum(archive_groups()['donations']),
        'activities'   => $sum(archive_groups()['activities']),
        'campaigns'    => $sum(['campaign']),
        'content'      => $sum(archive_groups()['content']),
        'all'          => array_sum($by),
    ];
}

/** One page of archived records. Returns [rows, total, pages, page]. */
function archive_list(PDO $pdo, array $filters, int $perPage = ARCHIVE_PER_PAGE): array
{
    $where = [];
    $params = [];
    if ($filters['tab'] !== 'all') {
        $types = archive_groups()[$filters['tab']];
        $where[] = 'type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
        array_push($params, ...$types);
    }
    if ($filters['q'] !== '') {
        $where[] = 'record ILIKE ?';
        $params[] = '%' . like_escape($filters['q']) . '%';
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $base = '(' . archive_union_sql() . ') a';

    $count = $pdo->prepare("SELECT COUNT(*) FROM $base $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($filters['page'], $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        "SELECT type, id, record, status_key, status_label, archived_at FROM $base $whereSql
         ORDER BY archived_at DESC, id DESC LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $labels = archive_type_labels();
    foreach ($rows as &$r) {
        $r['type_label'] = $labels[$r['type']] ?? $r['type'];
    }
    unset($r);

    return ['rows' => $rows, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/** Details for the Review / View pop-up, or null if the record is not archived. */
function archive_get_detail(PDO $pdo, string $type, int $id): ?array
{
    $labels = archive_type_labels();
    if (!isset($labels[$type])) {
        return null;
    }
    // Only records that really are in the archive can be opened
    $chk = $pdo->prepare('SELECT status_key, status_label FROM (' . archive_union_sql() . ') a WHERE type = ? AND id = ?');
    $chk->execute([$type, $id]);
    $meta = $chk->fetch();
    if (!$meta) {
        return null;
    }

    $d = [
        'type' => $type, 'type_label' => $labels[$type], 'title' => '',
        'status_key' => $meta['status_key'], 'status_label' => $meta['status_label'],
        'fields' => [],            // [label, value, wide?]
        'body_label' => '', 'body' => '', 'image' => null, 'responses' => [],
        'manage_url' => archive_manage_url($type, $id),
    ];

    if ($type === 'inquiry') {
        $r = get_inquiry($pdo, $id);
        if (!$r) { return null; }
        $d['title'] = ($r['reference_no'] ?: 'INQ-' . $r['id']) . ' · ' . $r['subject'];
        $d['fields'] = [
            ['Submitted by', $r['name']], ['Email', $r['email']],
            ['Category', $r['category']], ['Submitted', format_datetime($r['created_at'])],
        ];
        if ($r['resolved_at']) { $d['fields'][] = ['Resolved', format_datetime($r['resolved_at'])]; }
        if ($r['closed_at'])   { $d['fields'][] = ['Closed', format_datetime($r['closed_at'])]; }
        if (!empty($r['resolution_notes'])) { $d['fields'][] = ['Resolution notes', $r['resolution_notes'], true]; }
        $d['body_label'] = 'Message';
        $d['body'] = (string) $r['message'];
        $d['responses'] = get_inquiry_responses($pdo, $id);
    } elseif ($type === 'book_donation') {
        $q = $pdo->prepare("SELECT * FROM rc2000_admin.book_donations WHERE id = ? AND status IN ('approved', 'rejected')");
        $q->execute([$id]);
        $row = $q->fetch();
        if (!$row) { return null; }
        $d['title'] = 'Book donation · ' . (string) ($row['reference_no'] ?: 'BD-' . $row['id']);
        $d['fields'] = [
            ['Donor', (string) $row['donor_name']],
            ['Email', (string) $row['donor_email']],
            ['Contact number', (string) ($row['donor_contact'] ?? '')],
            ['Book', (string) $row['book_title']],
            ['Author', (string) ($row['author'] ?? '')],
            ['Genre', (string) ($row['genre'] ?? '')],
            ['Condition', (string) $row['book_condition']],
            ['Quantity offered', (string) (int) $row['qty_offered']],
            ['Quantity received', $row['qty_received'] === null ? 'Not recorded' : (string) (int) $row['qty_received']],
            ['Submitted', format_datetime($row['created_at'])],
            ['Reviewed', $row['reviewed_at'] ? format_datetime($row['reviewed_at']) : 'Not recorded'],
        ];
        if (!empty($row['notes'])) {
            $d['body_label'] = 'Donor notes';
            $d['body'] = (string) $row['notes'];
        }
    } elseif ($type === 'monetary_donation') {
        $q = $pdo->prepare("SELECT * FROM rc2000_admin.monetary_donations WHERE id = ? AND status IN ('verified', 'not_verified')");
        $q->execute([$id]);
        $row = $q->fetch();
        if (!$row) { return null; }
        $d['title'] = 'Monetary donation · ' . (string) ($row['reference_no'] ?: 'MD-' . $row['id']);
        $d['fields'] = [
            ['Donor', (string) $row['donor_name']],
            ['Email', (string) $row['donor_email']],
            ['Contact number', (string) ($row['donor_contact'] ?? '')],
            ['Reported amount', format_peso($row['reported_amount'])],
            ['Verified amount', $row['verified_amount'] === null ? 'Not verified' : format_peso($row['verified_amount'])],
            ['Payment method', (string) ($row['payment_method'] ?? '')],
            ['Transaction reference', (string) ($row['transaction_ref'] ?? '')],
            ['Proof of payment', empty($row['proof_path']) ? 'Not submitted' : 'Submitted'],
            ['Submitted', format_datetime($row['created_at'])],
            ['Reviewed', $row['verified_at'] ? format_datetime($row['verified_at']) : 'Not recorded'],
        ];
        if (!empty($row['verification_note'])) {
            $d['body_label'] = 'Verification note';
            $d['body'] = (string) $row['verification_note'];
        }
    } elseif ($type === 'quiz') {
        $q = $pdo->prepare('SELECT quiz_date, status FROM rc2000_admin.daily_quizzes WHERE id = ?');
        $q->execute([$id]);
        $quiz = $q->fetch();
        if (!$quiz) { return null; }
        $s = $pdo->prepare('SELECT COUNT(*) AS n, COALESCE(AVG(score), 0) AS avg_score FROM rc2000_admin.quiz_submissions WHERE quiz_id = ?');
        $s->execute([$id]);
        $stats = $s->fetch();
        $p = $pdo->prepare(
            "SELECT 1 FROM rc2000_admin.quiz_leaderboard_posts
             WHERE quiz_id = ? OR week_start = date_trunc('week', ?::timestamp)::date"
        );
        $p->execute([$id, $quiz['quiz_date']]);
        $d['title'] = 'Daily quiz · ' . format_date($quiz['quiz_date']);
        $d['fields'] = [
            ['Quiz date', format_date($quiz['quiz_date'])],
            ['Status', ucfirst((string) $quiz['status'])],
            ['Entries', (string) (int) $stats['n']],
            ['Average score', number_format((float) $stats['avg_score'], 1) . ' / 5'],
            ['Results published', $p->fetchColumn() ? 'Yes' : 'No'],
        ];
    } elseif ($type === 'trivia') {
        $q = $pdo->prepare('SELECT question, answer, explanation, updated_at FROM rc2000_admin.trivia WHERE id = ? AND status = \'archived\'');
        $q->execute([$id]);
        $row = $q->fetch();
        if (!$row) { return null; }
        $d['title'] = 'Trivia · ' . (string) $row['question'];
        $d['fields'] = [
            ['Answer', (string) $row['answer']],
            ['Archived', format_datetime($row['updated_at'])],
        ];
        if (!empty($row['explanation'])) {
            $d['body_label'] = 'Explanation';
            $d['body'] = (string) $row['explanation'];
        }
    } elseif ($type === 'leaderboard') {
        $l = $pdo->prepare(
            'SELECT p.week_start, p.posted_at, st.title, st.body,
                    (SELECT COUNT(DISTINCT s.username) FROM rc2000_admin.quiz_submissions s
                       JOIN rc2000_admin.daily_quizzes q ON q.id = s.quiz_id
                      WHERE p.week_start IS NOT NULL AND q.quiz_date >= p.week_start AND q.quiz_date < p.week_start + 7) AS participants
             FROM rc2000_admin.quiz_leaderboard_posts p
             JOIN rc2000_admin.stories st ON st.id = p.story_id
             WHERE p.quiz_id = ?'
        );
        $l->execute([$id]);
        $row = $l->fetch();
        if (!$row) { return null; }
        $d['title'] = (string) $row['title'];
        $week = $row['week_start'] ? format_date($row['week_start']) . ' – ' . format_date(date('Y-m-d', strtotime($row['week_start'] . ' +6 days'))) : 'Not recorded';
        $d['fields'] = [
            ['Week', $week],
            ['Participants', (string) (int) $row['participants']],
            ['Published', format_datetime($row['posted_at'])],
        ];
        $d['body_label'] = 'Published leaderboard';
        $d['body'] = (string) $row['body'];
    } elseif ($type === 'challenge') {
        $c = $pdo->prepare(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM rc2000_admin.challenge_votes v WHERE v.challenge_id = c.id AND v.choice = 'accept') AS accepts,
                    (SELECT COUNT(*) FROM rc2000_admin.challenge_votes v WHERE v.challenge_id = c.id AND v.choice = 'not_for_me') AS declines
             FROM rc2000_admin.challenge_posts c WHERE c.id = ?"
        );
        $c->execute([$id]);
        $row = $c->fetch();
        if (!$row) { return null; }
        $d['title'] = (string) $row['title'];
        $d['fields'] = [
            ['Starts', format_datetime($row['starts_at'])],
            ['Ended', format_datetime($row['ends_at'])],
            ['Joining', (string) (int) $row['accepts']],
            ['Not now', (string) (int) $row['declines']],
            ['Total responses', (string) ((int) $row['accepts'] + (int) $row['declines'])],
        ];
        $d['body_label'] = 'Challenge prompt';
        $d['body'] = (string) $row['prompt'];
    } elseif ($type === 'campaign') {
        $c = $pdo->prepare(
            "SELECT c.*, COALESCE((SELECT SUM(d.verified_amount) FROM rc2000_admin.monetary_donations d
                                    WHERE d.campaign_id = c.id AND d.status = 'verified'), 0) AS raised
             FROM rc2000_admin.campaigns c WHERE c.id = ?"
        );
        $c->execute([$id]);
        $row = $c->fetch();
        if (!$row) { return null; }
        $goal = (float) $row['goal_amount'];
        $raised = (float) $row['raised'];
        $d['title'] = (string) $row['title'];
        $d['fields'] = [
            ['Goal', format_peso($goal)],
            ['Raised (verified)', format_peso($raised)],
            ['Progress', $goal > 0 ? round($raised / $goal * 100) . '%' : '–'],
            ['Closed', format_datetime($row['updated_at'])],
        ];
        $d['body_label'] = 'Description and spending plan';
        $d['body'] = (string) $row['description'];
        if (!empty($row['image_path'])) { $d['image'] = portal_media_url($row['image_path']); }
    } else {
        // story / article / announcement / gallery item
        $map = [
            'story'        => ['stories', 'cover_path'],
            'article'      => ['stories', 'cover_path'],
            'announcement' => ['announcements', 'image_path'],
            'gallery'      => ['gallery_images', 'file_path'],
        ];
        [$table, $imgCol] = $map[$type];
        $c = $pdo->prepare("SELECT * FROM rc2000_admin.$table WHERE id = ?");
        $c->execute([$id]);
        $row = $c->fetch();
        if (!$row) { return null; }
        $d['title'] = (string) $row['title'];
        $d['fields'][] = ['Type', $labels[$type]];
        if (!empty($row['author']))       { $d['fields'][] = ['Author', $row['author']]; }
        if (!empty($row['published_at'])) { $d['fields'][] = ['Published', format_date($row['published_at'])]; }
        $d['fields'][] = ['Archived', format_datetime($row['updated_at'])];
        if ($type === 'gallery') {
            $d['body_label'] = 'Caption';
            $d['body'] = (string) ($row['caption'] ?? '');
        } else {
            $d['body_label'] = 'Content preview';
            $text = (string) $row['body'];
            $d['body'] = mb_strlen($text) > 1500 ? mb_substr($text, 0, 1500) . '…' : $text;
        }
        if (!empty($row[$imgCol])) { $d['image'] = portal_media_url($row[$imgCol]); }
    }
    return $d;
}

/** Everything a page needs. Never throws; a problem is returned as loadError. */
function archive_page_data(array $filters): array
{
    $out = [
        'counts'    => ['inquiries' => 0, 'donations' => 0, 'activities' => 0, 'campaigns' => 0, 'content' => 0, 'all' => 0],
        'list'      => ['rows' => [], 'total' => 0, 'pages' => 1, 'page' => 1],
        'selected'  => null,
        'loadError' => null,
    ];
    try {
        $pdo = db();
        $out['counts'] = archive_counts($pdo);
        $out['list']   = archive_list($pdo, $filters);
        $type = get_str('type');
        $id = (int) ($_GET['id'] ?? 0);
        if ($type !== '' && $id > 0) {
            $out['selected'] = archive_get_detail($pdo, $type, $id);
            if (!$out['selected']) {
                flash('error', 'That archived record could not be found.');
            }
        }
    } catch (Throwable $e) {
        error_log('Archive load failed: ' . $e->getMessage());
        $out['loadError'] = 'The archive could not be loaded. Check that database/community_features.sql has been applied and the database is running.' . dev_detail($e);
    }
    return $out;
}
