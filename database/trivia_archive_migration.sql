-- Enable the Archived status for existing trivia records in rc2000_admin.
-- Run once in the Render database Query Tool before using the trivia archive UI.
BEGIN;

ALTER TABLE rc2000_admin.trivia
    DROP CONSTRAINT IF EXISTS chk_trivia_status;

ALTER TABLE rc2000_admin.trivia
    ADD CONSTRAINT chk_trivia_status
    CHECK (status IN ('draft', 'published', 'archived'));

COMMIT;
