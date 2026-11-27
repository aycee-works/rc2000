<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';

if (current_donor_user()) {
    redirect('/public/dashboard.php');
}

$authError = null;
$authEmail = '';
$authName = '';
$authNotice = flash_get('donor_auth_notice');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $authEmail = post_string('email');
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $authError = 'Your session expired. Refresh the page and try again.';
    } else {
        try {
            $authError = sign_in_donor($authEmail, $password);
            if ($authError === null) {
                $returnTo = $_SESSION['donor_return_to'] ?? '/public/home.php';
                unset($_SESSION['donor_return_to']);
                if (!in_array($returnTo, ['/public/donate.php?tab=money', '/public/donate.php?tab=campaign', '/public/dashboard.php', '/public/my-inquiries.php'], true)) {
                    $returnTo = '/public/dashboard.php';
                }
                redirect($returnTo);
            }
        } catch (Throwable $e) {
            error_log('Donor login failed: ' . $e->getMessage());
            $authError = 'We could not sign you in right now. Please try again later.';
        }
    }
}

$authMode = 'login';
require __DIR__ . '/../includes/donor/auth-page.php';
