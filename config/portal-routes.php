<?php
declare(strict_types=1);

// Keep these public path segments in sync with the matching rules in .htaccess.
const ADMIN_PORTAL_PATH = 'entry-6e91b4c8';
const SUPERADMIN_PORTAL_PATH = 'entry-a73d02f5';

/** Translate logical portal paths while preserving query strings and fragments. */
function portal_path(string $path): string
{
    $path = ltrim($path, '/');
    $prefixes = [
        'public/admin/super_admin' => SUPERADMIN_PORTAL_PATH,
        'admin/super_admin' => SUPERADMIN_PORTAL_PATH,
        'superadmin' => SUPERADMIN_PORTAL_PATH,
        'staff-console' => SUPERADMIN_PORTAL_PATH,
        'public/admin' => ADMIN_PORTAL_PATH,
        'admin' => ADMIN_PORTAL_PATH,
    ];

    foreach ($prefixes as $prefix => $replacement) {
        if (preg_match('#^' . preg_quote($prefix, '#') . '(?=/|\?|\#|$)#', $path)) {
            return $replacement . substr($path, strlen($prefix));
        }
    }

    return $path;
}
