-- RC2000 Query Portfolio
-- Run against the RC2000 PostgreSQL database after applying:
--   scripts/01_create_tables.sql
--   scripts/03_views_functions_triggers.sql
--   scripts/05_donor_accounts.sql
-- This file contains read-only reporting queries. It does not change data.

-- Query 1: Which campaigns have the most verified funding?
-- Requires v_campaign_progress from scripts/03_views_functions_triggers.sql.
SELECT title,
       verified_raised,
       goal_amount,
       ROUND(100 * verified_raised / NULLIF(goal_amount, 0), 2) AS percent_funded
FROM rc2000_admin.v_campaign_progress
ORDER BY verified_raised DESC, title;

-- Query 2: How many pending donation reports are general or campaign donations?
SELECT CASE
           WHEN campaign_id IS NULL THEN 'General monetary'
           ELSE 'Campaign'
       END AS donation_category,
       COUNT(*) AS pending_count
FROM rc2000_admin.monetary_donations
WHERE status = 'pending'
GROUP BY 1
ORDER BY 1;

-- Query 3: What are the verified report count and verified total for each campaign?
SELECT c.title,
       COUNT(d.id) FILTER (WHERE d.status = 'verified') AS verified_reports,
       COALESCE(SUM(d.verified_amount) FILTER (WHERE d.status = 'verified'), 0) AS total_raised
FROM rc2000_admin.campaigns AS c
LEFT JOIN rc2000_admin.monetary_donations AS d ON d.campaign_id = c.id
GROUP BY c.id, c.title
ORDER BY total_raised DESC, c.title;

-- Query 4: Which pending donation reports have been waiting longest for review?
SELECT reference_no,
       donor_name,
       reported_amount,
       campaign_id,
       created_at
FROM rc2000_admin.monetary_donations
WHERE status = 'pending'
ORDER BY created_at ASC
LIMIT 20;

-- Query 5: What is each donor account's status and linked donation history?
-- Includes accounts with no linked reports. The total includes verified donations only.
SELECT u.id AS donor_user_id,
       u.name,
       u.email,
       u.status AS account_status,
       u.email_verified_at,
       COUNT(d.id) AS linked_reports,
       COALESCE(SUM(d.verified_amount) FILTER (WHERE d.status = 'verified'), 0) AS verified_total
FROM rc2000_admin.donor_users AS u
LEFT JOIN rc2000_admin.monetary_donations AS d ON d.donor_user_id = u.id
GROUP BY u.id, u.name, u.email, u.status, u.email_verified_at
ORDER BY u.created_at DESC, u.id DESC;

-- Query 6: What is the inquiry workload by category and status?
-- Requires v_inquiry_workload from scripts/03_views_functions_triggers.sql.
SELECT category, status, inquiry_count, newest_inquiry
FROM rc2000_admin.v_inquiry_workload
ORDER BY inquiry_count DESC, category, status;

-- Query 7: Which inquiries do not yet have a saved admin response?
SELECT i.reference_no,
       i.subject,
       i.category,
       i.status,
       i.created_at
FROM rc2000_admin.inquiries AS i
WHERE NOT EXISTS (
    SELECT 1
    FROM rc2000_admin.inquiry_responses AS r
    WHERE r.inquiry_id = i.id
)
ORDER BY i.created_at ASC;

-- Query 8: How many account-linked inquiries and conversation messages does each donor have?
SELECT u.id AS donor_user_id,
       u.name,
       u.email,
       COUNT(DISTINCT i.id) AS linked_inquiries,
       COUNT(m.id) AS donor_messages
FROM rc2000_admin.donor_users AS u
LEFT JOIN rc2000_admin.inquiries AS i ON i.donor_user_id = u.id
LEFT JOIN rc2000_admin.donor_inquiry_messages AS m ON m.inquiry_id = i.id
GROUP BY u.id, u.name, u.email
ORDER BY linked_inquiries DESC, donor_messages DESC, u.name;

-- Query 9: Which donor account correction/removal requests are awaiting review?
SELECT id AS request_id,
       donor_name_snapshot,
       donor_email_snapshot,
       request_type,
       details,
       created_at
FROM rc2000_admin.donor_data_requests
WHERE status = 'pending'
ORDER BY created_at ASC;

-- Query 10: How many public stories are published by type?
SELECT type,
       COUNT(*) AS published_count
FROM rc2000_admin.stories
WHERE status = 'published'
GROUP BY type
ORDER BY published_count DESC, type;

-- Query 11: What are the top quiz scores for each quiz?
SELECT q.quiz_date,
       s.username,
       s.score,
       ROW_NUMBER() OVER (
           PARTITION BY q.id
           ORDER BY s.score DESC, s.created_at ASC
       ) AS rank_in_quiz
FROM rc2000_admin.quiz_submissions AS s
JOIN rc2000_admin.daily_quizzes AS q ON q.id = s.quiz_id
ORDER BY q.quiz_date DESC, rank_in_quiz;

-- Query 12: Which administrators made the most recent recorded changes?
SELECT a.name,
       l.action,
       l.entity_type,
       l.entity_id,
       l.created_at
FROM rc2000_admin.activity_logs AS l
LEFT JOIN rc2000_admin.admins AS a ON a.id = l.admin_id
ORDER BY l.created_at DESC
LIMIT 30;
