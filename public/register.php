<?php
require_once __DIR__ . '/../includes/donor/bootstrap.php';

if (current_donor_user()) {
    redirect('/public/dashboard.php');
}

$authError = null;
$authName = '';
$authEmail = '';
$authNotice = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $authName = post_string('name');
    $authEmail = post_string('email');
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');

    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $authError = 'Your session expired. Refresh the page and try again.';
    } elseif ($password !== $confirmation) {
        $authError = 'The passwords do not match.';
    } else {
        try {
            create_donor_account($authName, $authEmail, $password);
            flash_set('donor_auth_notice', 'Your account is ready. Sign in to continue.');
            redirect('/public/login.php');
        } catch (InvalidArgumentException $e) {
            $authError = $e->getMessage();
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                $authError = 'An account with that email already exists.';
            } else {
                error_log('Donor registration failed: ' . $e->getMessage());
                $authError = 'We could not create your account right now. Please try again later.';
            }
        } catch (Throwable $e) {
            error_log('Donor registration failed: ' . $e->getMessage());
            $authError = 'We could not create your account right now. Please try again later.';
        }
    }
}

$authMode = 'register';
require __DIR__ . '/../includes/donor/auth-page.php';
