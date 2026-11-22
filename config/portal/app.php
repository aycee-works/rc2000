<?php
/**
 * config/app.php
 * Central application settings. Environment-specific values (database login,
 * base URL, debug mode) can be overridden in config/local.php, which is NOT
 * committed to Git. Copy config/local.example.php to config/local.php.
 */

define('ROOT_PATH', dirname(__DIR__, 2));

// Defaults suitable for XAMPP on localhost
$mailEnabledEnv = getenv('RC2000_MAIL_ENABLED');
$config = [
    'app_env'    => 'development',          // 'development' or 'production'
    'base_url'   => '/rc2000',              // URL path of this project (no trailing slash)
    'timezone'   => 'Asia/Manila',
    'db_host'    => '127.0.0.1',
    'db_port'    => 5432,
    'db_name'    => getenv('RC2000_DB_NAME') ?: 'rc2000_db',
    'db_user'    => getenv('RC2000_DB_USER') ?: 'postgres',
    'db_pass'    => getenv('RC2000_DB_PASSWORD') ?: '',
    'db_tz'      => 'Asia/Manila',          // PostgreSQL time zone name; must match 'timezone'
    // Email: leave false until SMTP is configured. Inquiry responses are
    // saved either way; the UI says so when email is not configured.
   'mail_enabled' => $mailEnabledEnv === false
    ? false
    : filter_var($mailEnabledEnv, FILTER_VALIDATE_BOOLEAN),
'mail_host' => getenv('RC2000_MAIL_HOST') ?: 'smtp.gmail.com',
'mail_port' => (int) (getenv('RC2000_MAIL_PORT') ?: 587),
'mail_encryption' => getenv('RC2000_MAIL_ENCRYPTION') ?: 'tls',
'mail_username' => getenv('RC2000_MAIL_USERNAME') ?: '',
'mail_password' => getenv('RC2000_MAIL_PASSWORD') ?: '',
'mail_from_email' => getenv('RC2000_MAIL_FROM_EMAIL') ?: '',
'mail_from_name' => getenv('RC2000_MAIL_FROM_NAME') ?: 'Reading Club 2000',
'mail_admin_email' => getenv('RC2000_MAIL_ADMIN_EMAIL') ?: '',
'cloudinary_url' => getenv('CLOUDINARY_URL') ?: '',
];

$localFile = __DIR__ . '/local.php';
if (is_file($localFile)) {
    $local = require $localFile;
    if (is_array($local)) {
        $config = array_merge($config, $local);
    }
}

// The deployed site is served from the domain root, while local XAMPP uses /rc2000.
$basePath = getenv('RC2000_BASE_PATH');
if ($basePath !== false) {
    $config['base_url'] = rtrim((string) $basePath, '/');
}

define('APP_ENV', $config['app_env']);
define('BASE_URL', rtrim($config['base_url'], '/'));
define('MAIL_ENABLED', (bool) $config['mail_enabled']);

date_default_timezone_set($config['timezone']);

// Never show raw PHP errors to visitors in production; always log them.
ini_set('log_errors', '1');
ini_set('display_errors', APP_ENV === 'development' ? '1' : '0');
error_reporting(E_ALL);

// Make the config available to config/database.php
$GLOBALS['APP_CONFIG'] = $config;
