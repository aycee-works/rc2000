<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Method not allowed.');
}

if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Your session expired. Refresh the page and try again.');
}

sign_out_donor();
redirect('/public/home.php');
