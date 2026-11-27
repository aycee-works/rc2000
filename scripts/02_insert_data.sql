-- FICTIONAL demonstration data. Run ONLY on the new demo database after 01 and 03.
-- All emails use example.invalid and cannot receive mail.
BEGIN;
INSERT INTO rc2000_admin.campaigns (title, description, goal_amount, status, is_featured)
VALUES
 ('Demo School Supplies', 'Fictional school supply campaign for testing.', 15000, 'open', true),
 ('Demo Reading Sessions', 'Fictional reading activity campaign for testing.', 12000, 'open', false),
 ('Demo Library Materials', 'Fictional materials campaign for testing.', 18000, 'closed', false);

INSERT INTO rc2000_admin.monetary_donations
 (reference_no, donor_name, donor_email, reported_amount, payment_method, transaction_ref,
  status, verified_amount, campaign_id, created_at)
SELECT 'DEMO-MD-' || lpad(n::text, 3, '0'),
       'Demo Donor ' || n, 'donor' || n || '@example.invalid',
       (100 + n * 25)::numeric(12,2), 'Demo QR', 'DEMO-REF-' || n,
       CASE WHEN n % 3 = 0 THEN 'verified' ELSE 'pending' END,
       CASE WHEN n % 3 = 0 THEN (100 + n * 25)::numeric(12,2) ELSE NULL END,
       CASE WHEN n % 2 = 0 THEN (SELECT id FROM rc2000_admin.campaigns WHERE title = 'Demo School Supplies') ELSE NULL END,
       now() - (n || ' days')::interval
FROM generate_series(1, 30) AS n;

INSERT INTO rc2000_admin.inquiries (reference_no, name, email, subject, category, message, status, created_at)
SELECT 'DEMO-INQ-' || lpad(n::text, 3, '0'),
       'Demo Visitor ' || n, 'visitor' || n || '@example.invalid',
       'Demo inquiry ' || n, 'Inquiry',
       'Fictional question about reading activities number ' || n || '.',
       CASE WHEN n % 4 = 0 THEN 'resolved' ELSE 'open' END,
       now() - (n || ' days')::interval
FROM generate_series(1, 30) AS n;

INSERT INTO rc2000_admin.stories (type, title, body, author, status)
SELECT 'story', 'Demo story ' || n,
       'Fictional reading club story number ' || n || ' for database testing.',
       'Reading Club 2000 Demo', 'draft'
FROM generate_series(1, 30) AS n;
COMMIT;
