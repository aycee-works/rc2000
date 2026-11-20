<?php
declare(strict_types=1);

/**
 * PostgreSQL (Supabase) connection.
 * Credentials come from config/db.local.php (not committed to Git),
 * or from RC2000_DB_* environment variables.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $local = [];
    $localFile = __DIR__ . '/db.local.php';
    if (is_file($localFile)) {
        $local = require $localFile;
    }

    $host = getenv('RC2000_DB_HOST') ?: ($local['host'] ?? '127.0.0.1');
    $port = getenv('RC2000_DB_PORT') ?: ($local['port'] ?? '5432');
    $name = getenv('RC2000_DB_NAME') ?: ($local['name'] ?? 'rc2000_db');
    $user = getenv('RC2000_DB_USER') ?: ($local['user'] ?? 'postgres');
    $pass = getenv('RC2000_DB_PASSWORD') ?: ($local['pass'] ?? '12345Ikang02!');
    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode=require";
   // Local PostgreSQL installations often have SSL disabled. Keep SSL enabled
    // for remote hosts, while allowing an explicit override for either setup.
    $isLocalHost = in_array(strtolower((string) $host), ['localhost', '127.0.0.1', '::1'], true);
    $sslMode = getenv('RC2000_DB_SSLMODE') ?: ($local['sslmode'] ?? ($isLocalHost ? 'disable' : 'require'));
    $dsn = "pgsql:host={$host};port={$port};dbname={$name};sslmode={$sslMode}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
