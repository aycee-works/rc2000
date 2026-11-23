# Run Reading Club 2000 on localhost with PostgreSQL

## 1. Put the folder in Apache's web root

For a typical XAMPP installation, copy this `rc2000` folder to:

```text
C:\xampp\htdocs\rc2000
```

Start Apache and PostgreSQL. The page URLs will be:

- `http://localhost/rc2000/public/home.php`
- `http://localhost/rc2000/public/explore.php`
- `http://localhost/rc2000/public/gallery.php`
- `http://localhost/rc2000/public/donate.php`
- `http://localhost/rc2000/public/inquiry.php`
- `http://localhost/rc2000/public/about.php`
- `http://localhost/rc2000/public/contact.php`

The public pages, assets, and uploaded files live under `public/`.

## 2. Create and load the PostgreSQL database

Create a database named `rc2000_db` in pgAdmin or with `CREATE DATABASE rc2000_db;`. Then select `rc2000_db` and run `database/schema.sql` followed by `database/seed.sql`.

If your SQL client reports that `CREATE DATABASE` cannot run inside a transaction, run that one statement by itself first. Run the rest of `schema.sql` while connected to `rc2000_db`.

## 3. Set the PHP database connection

Copy `config/db.local.example.php` to `config/db.local.php` and set `pass` to the password for your PostgreSQL user. The example uses `postgres` and the local database `rc2000_db`. Adjust the other values if your installation differs. Git ignores `config/db.local.php` so your credentials stay local.

Alternatively, set `RC2000_DB_PASSWORD` and any other database settings Apache needs through `RC2000_DB_HOST`, `RC2000_DB_PORT`, `RC2000_DB_NAME`, and `RC2000_DB_USER`. Environment values take precedence over the local file when set to nonempty values other than `0`.

The PHP installation used by Apache must have the `pdo_pgsql` extension enabled. If it is missing, enable the PostgreSQL PDO extension in the `php.ini` used by Apache, then restart Apache.

## 4. Check the connection

Open `http://localhost/rc2000/public/test.php`. It reports whether `pdo_pgsql` is loaded and whether PHP can query the `content` table. The public pages show sample fallback cards even when the database is not connected; the test page checks the actual connection.

Set `RC2000_BASE_PATH` only if the folder is mounted at a URL other than `/rc2000`.

## Show Facebook Page posts on the homepage

Set the `RC2000_FB_PAGE_URL` environment variable to the public Facebook Page URL (for example, `https://www.facebook.com/YourPageName`). The homepage then displays the Page timeline, so posts published to Facebook appear there automatically. The existing footer link will also point to that Page. The Facebook share link currently in the footer is not a Page URL and cannot be used for the timeline embed. Facebook may require the Page to be public and its embedding settings to allow the Page Plugin.
