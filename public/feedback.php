<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

function feedback_reply(bool $success, string $message, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    feedback_reply(false, 'Method not allowed.', 405);
}
if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    feedback_reply(false, 'Your session expired. Please refresh the page and try again.', 419);
}

$name = post_string('name');
$email = post_string('email');
$message = post_string('message');

if (!required_text($name, 120)) {
    feedback_reply(false, 'Please enter your name.', 422);
}
if (!valid_email($email) || mb_strlen($email) > 150) {
    feedback_reply(false, 'Please enter a valid email address.', 422);
}
if (mb_strlen(trim($message)) < 10 || !required_text($message, 5000)) {
    feedback_reply(false, 'Please write at least 10 characters of feedback.', 422);
}

try {
    $stmt = db()->prepare(
        'INSERT INTO rc2000_admin.website_feedback (name, email, message)
         VALUES (:name, :email, :message)'
    );
    $stmt->execute(['name' => $name, 'email' => $email, 'message' => $message]);
    feedback_reply(true, 'Thank you for your feedback! Our admins will read it.');
} catch (Throwable $error) {
    error_log('Feedback save failed: ' . $error->getMessage());
    feedback_reply(false, 'We could not save your feedback yet. Please try again later.', 500);
}