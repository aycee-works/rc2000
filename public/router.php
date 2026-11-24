<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/portal-routes.php';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$uri = preg_replace('#/+#', '/', $uri);

// Match Apache's portal routing when using PHP's local development server.
if (preg_match('#^/(?:public/)?(?:admin|superadmin|super_admin|staff-console)(?:/|$)#i', $uri)
    || preg_match('#^/' . ADMIN_PORTAL_PATH . '/+super_admin(?:/|$)#i', $uri)) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

foreach ([ADMIN_PORTAL_PATH => '/admin', SUPERADMIN_PORTAL_PATH => '/admin/super_admin'] as $prefix => $folder) {
    if (!preg_match('#^/' . $prefix . '(?:/(.*))?$#', $uri, $match)) {
        continue;
    }
    $relative = $match[1] ?? '';
    if ($relative === '' || is_dir(__DIR__ . $folder . '/' . $relative)) {
        $relative = rtrim($relative, '/') . '/index.php';
        $relative = ltrim($relative, '/');
    }
    $root = realpath(__DIR__ . $folder);
    $target = realpath(__DIR__ . $folder . '/' . $relative);
    if (!$root || !$target || !str_starts_with($target, $root . DIRECTORY_SEPARATOR)
        || !is_file($target) || pathinfo($target, PATHINFO_EXTENSION) !== 'php') {
        http_response_code(404);
        echo 'Not found';
        return true;
    }
    if ($prefix === ADMIN_PORTAL_PATH
        && str_starts_with($target, $root . DIRECTORY_SEPARATOR . 'super_admin' . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        echo 'Not found';
        return true;
    }
    $_SERVER['SCRIPT_FILENAME'] = $target;
    $_SERVER['SCRIPT_NAME'] = '/' . $prefix . '/' . $relative;
    $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'];
    require $target;
    return true;
}

$file = __DIR__ . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

if ($uri === '/') {
    require __DIR__ . '/home.php';
    return true;
}

http_response_code(404);
echo 'Not found';
