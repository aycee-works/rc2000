# Admin portal integration

The attached admin and superadmin screens are installed in the existing `public/` tree:

- Admin: `http://localhost/rc2000/entry-6e91b4c8/`
- Superadmin: `http://localhost/rc2000/entry-a73d02f5/`
- The former portal URLs and direct requests under `/rc2000/public/admin/` return 404.
- The project root `http://localhost/rc2000/` loads the public home page.

Hosted login URLs are `/entry-6e91b4c8/login.php` and `/entry-a73d02f5/login.php`. The public paths are defined in `config/portal-routes.php` and mirrored in `.htaccess`. PHP callers can continue passing logical paths such as `admin/dashboard.php` to `url()`; the helper translates them. The physical folders remain under `public/admin/`.

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

   Follow the prompts for the superadmin email and password. Then open `/rc2000/entry-a73d02f5/` and use the admin management page to create regular admin accounts. Regular staff sign in at `/rc2000/entry-6e91b4c8/`.

The portal uses the same PostgreSQL connection as the public site. Follow the [localhost database setup](README-localhost.md#3-set-the-php-database-connection) to configure `config/db.local.php` or the `RC2000_DB_*` environment variables.

The campaign and quiz experience requires the one-time `database/community_features.sql` migration. It adds the campaign, challenge, vote, quiz, and quiz score tables to `rc2000_admin` and extends monetary donation records with campaign and email status fields. Campaigns and donation reports are managed at `/rc2000/entry-6e91b4c8/community.php`; weekly challenges, daily quizzes, and leaderboard publishing are in Manage content at `/rc2000/entry-6e91b4c8/content/index.php`. Existing featured stories and articles remain in their current content system.


## Super Admin sign-in

Super Admin passwords must contain at least 16 characters and fit within 72 bytes. To update an existing password, run `php database/reset-super-admin-password.php` from the server.

Super Admin sign-in is throttled after three failures for the email or client IP within 15 minutes. Sessions expire after 15 minutes of inactivity.

To restrict Super Admin access, set `RC2000_SUPERADMIN_ALLOWED_IPS` in the web server environment to a comma-separated list of IPv4 or IPv6 addresses. The check uses the address Apache exposes as `REMOTE_ADDR`. Behind a reverse proxy, configure Apache to resolve the client address from trusted proxies before enabling this setting. An unset or blank value leaves the allowlist disabled.
