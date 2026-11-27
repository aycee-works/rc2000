<?php
declare(strict_types=1);

// Public pages already start the visitor session through includes/functions.php.
// Admin and superadmin use their own session names and remain separate.
require_once dirname(__DIR__) . '/functions.php';
require_once __DIR__ . '/auth.php';
