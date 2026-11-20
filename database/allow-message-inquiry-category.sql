-- Align the live inquiry category constraint with the public form options.
-- Safe to rerun: it replaces only the named category check constraint.
BEGIN;

ALTER TABLE rc2000_admin.inquiries
    DROP CONSTRAINT IF EXISTS chk_inq_category;

ALTER TABLE rc2000_admin.inquiries
    ADD CONSTRAINT chk_inq_category
    CHECK (category IN ('Request', 'Technical', 'Report', 'Inquiry', 'Message'));

COMMIT;
