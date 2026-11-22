# Admin portal integration

The attached admin and superadmin screens are installed in the existing `public/` tree:

- Admin: `http://localhost/rc2000/admin/`
- Superadmin: `http://localhost/rc2000/superadmin/`
- Direct public-tree URLs also work under `/rc2000/public/admin/`.
- The project root `http://localhost/rc2000/` loads the public home page.

The portals use shared code in `includes/portal/`, settings in `config/portal/`, and assets in `public/assets/`. Their database tables are installed into the separate PostgreSQL schema `rc2000_admin` inside the existing `rc2000_db` database. The public site's existing `public` schema and `database/schema.sql` are preserved.

## One-time setup

1. Start Apache and PostgreSQL in XAMPP.
2. In pgAdmin, select the existing `rc2000_db` database and run `database/admin_portal_schema.sql`.
3. Run `database/super-admin.sql`, then `database/integration.sql` in the same database.
4. Run `database/community_features.sql` in the same database to add public campaigns, campaign donation tracking, weekly challenges, and daily quizzes.
5. In XAMPP Shell, create the initial superadmin:

   ```powershell
   cd C:\xampp\htdocs\rc2000
   C:\xampp\php\php.exe database\create-super-admin.php
   ```

   Follow the prompts for the superadmin email and password. Then open `/rc2000/superadmin/` and use the admin management page to create regular admin accounts. Regular staff sign in at `/rc2000/admin/`.

The portal connection reuses `config/db.php` and its `RC2000_DB_*` environment variables. Keep that file's local database credentials configured for XAMPP.

The campaign and quiz experience requires the one-time `database/community_features.sql` migration. It adds the campaign, challenge, vote, quiz, and quiz score tables to `rc2000_admin` and extends monetary donation records with campaign and email status fields. Campaigns and donation reports are managed at `/rc2000/public/admin/community.php`; weekly challenges, daily quizzes, and leaderboard publishing are in Manage content at `/rc2000/public/admin/content/index.php`. Existing featured stories and articles remain in their current content system.


## Super Admin sign-in

Super Admin passwords must contain at least 16 characters and fit within 72 bytes. To update an existing password, run `php database/reset-super-admin-password.php` from the server.

Super Admin sign-in is throttled after three failures for the email or client IP within 15 minutes. Sessions expire after 15 minutes of inactivity.

To restrict Super Admin access, set `RC2000_SUPERADMIN_ALLOWED_IPS` in the web server environment to a comma-separated list of IPv4 or IPv6 addresses. The check uses the address Apache exposes as `REMOTE_ADDR`. Behind a reverse proxy, configure Apache to resolve the client address from trusted proxies before enabling this setting. An unset or blank value leaves the allowlist disabled.
