-- OPTIONAL role demonstration. DBA only, on a dedicated DEMO database.
-- Do not run against the production Render database or change its owner.
-- These are NOLOGIN roles. Assign a separate demo login to one role as needed.
CREATE ROLE rc2000_demo_app NOLOGIN;
CREATE ROLE rc2000_demo_readonly NOLOGIN;
GRANT USAGE ON SCHEMA public, rc2000_admin TO rc2000_demo_app, rc2000_demo_readonly;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public, rc2000_admin TO rc2000_demo_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public, rc2000_admin TO rc2000_demo_app;
GRANT SELECT ON ALL TABLES IN SCHEMA public, rc2000_admin TO rc2000_demo_readonly;
