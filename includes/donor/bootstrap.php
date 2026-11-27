<?php
declare(strict_types=1);

// Public pages already start the visitor session through includes/functions.php.
// Admin and superadmin use their own session names and remain separate.
require_once dirname(__DIR__) . '/functions.php';
require_once __DIR__ . '/auth.php';

/** Public donor portal formatters; portal-only helpers are not loaded here. */
function donor_format_peso($amount): string
{
    $amount = (float) $amount;
    $decimals = floor($amount) === $amount ? 0 : 2;
    return '₱' . number_format($amount, $decimals);
}

function donor_format_date_short(?string $datetime): string
{
    return $datetime ? date('M j', strtotime($datetime)) : '';
}

function donor_format_datetime(?string $datetime): string
{
    return $datetime ? date('M j, Y g:i A', strtotime($datetime)) : '';
}
