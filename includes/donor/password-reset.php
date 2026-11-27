<?php
declare(strict_types=1);

if (!defined('MAIL_ENABLED')) {
    require_once dirname(__DIR__, 2) . '/config/portal/app.php';
}

/** Send a donor password reset email. Does not reveal whether an address exists. */
function send_donor_password_reset(PDO $pdo, int $donorId): bool
{
    if (!MAIL_ENABLED) { return false; }
    $stmt = $pdo->prepare("SELECT id, name, email FROM rc2000_admin.donor_users WHERE id = ? AND status = 'active'");
    $stmt->execute([$donorId]);
    $donor = $stmt->fetch();
    if (!$donor) { return false; }
    $recent = $pdo->prepare("SELECT 1 FROM rc2000_admin.donor_auth_tokens
        WHERE donor_user_id = ? AND purpose = 'reset_password' AND created_at > NOW() - INTERVAL '60 seconds'
        LIMIT 1");
    $recent->execute([$donorId]);
    if ($recent->fetchColumn()) { return false; }

    $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
    if (!is_file($autoload)) { throw new RuntimeException('PHPMailer is not installed.'); }
    require_once $autoload;
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) { throw new RuntimeException('PHPMailer is not installed.'); }

    $token = bin2hex(random_bytes(32));
    $pdo->prepare("DELETE FROM rc2000_admin.donor_auth_tokens
        WHERE donor_user_id = ? AND purpose = 'reset_password' AND used_at IS NULL")->execute([$donorId]);
    $pdo->prepare("INSERT INTO rc2000_admin.donor_auth_tokens (donor_user_id, purpose, token_hash, expires_at)
        VALUES (?, 'reset_password', ?, NOW() + INTERVAL '30 minutes')")
        ->execute([$donorId, hash('sha256', $token)]);

    $config = $GLOBALS['APP_CONFIG'] ?? [];
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = (string) ($config['mail_host'] ?? 'smtp.gmail.com');
    $mail->SMTPAuth = true;
    $mail->Username = (string) ($config['mail_username'] ?? '');
    $mail->Password = (string) ($config['mail_password'] ?? '');
    $mail->Port = (int) ($config['mail_port'] ?? 587);
    $mail->SMTPSecure = ($config['mail_encryption'] ?? 'tls') === 'ssl'
        ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom((string) ($config['mail_from_email'] ?? $mail->Username), (string) ($config['mail_from_name'] ?? 'Reading Club 2000'));
    $mail->addAddress((string) $donor['email'], (string) $donor['name']);
    $resetPath = 'public/reset-password.php?token=' . rawurlencode($token);
    $relativeResetUrl = function_exists('app_url') ? app_url($resetPath) : url($resetPath);

    // Email links must be absolute. Prefer an explicitly configured canonical
    // origin, then recognize Render's public hostname, then use the current
    // request origin for local development.
    $appOrigin = rtrim(trim((string) ($config['app_url'] ?? '')), '/');
    if ($appOrigin === '') {
        $requestHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if ($requestHost === 'rc2000.onrender.com') {
            $appOrigin = 'https://rc2000.onrender.com';
        } elseif ($requestHost !== '') {
            $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
                || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
            $appOrigin = ($isHttps ? 'https://' : 'http://') . $requestHost;
        } else {
            // Safe fallback for reset requests not initiated by an HTTP request.
            $appOrigin = 'https://rc2000.onrender.com';
        }
    }
    $resetUrl = $appOrigin . '/' . ltrim($relativeResetUrl, '/');
    $mail->Subject = 'Reset your Reading Club 2000 donor password';
    $mail->isHTML(true);
    $safeName = htmlspecialchars((string) $donor['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeResetUrl = htmlspecialchars($resetUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $mail->Body = '<p>Hello ' . $safeName . ',</p>'
        . '<p>Use the link below to reset your donor account password. This link expires in 30 minutes and can only be used once.</p>'
        . '<p><a href="' . $safeResetUrl . '">Reset your password</a></p>'
        . '<p>If the button does not work, copy and paste this full URL into your browser:<br>' . $safeResetUrl . '</p>'
        . '<p>If you did not request this, you can ignore this email.</p>';
    $mail->AltBody = "Hello {$donor['name']},\n\nUse the link below to reset your donor account password. This link expires in 30 minutes and can only be used once.\n\n{$resetUrl}\n\nIf you did not request this, you can ignore this email.";
    try {
        $mail->send();
    } catch (Throwable $e) {
        $pdo->prepare("UPDATE rc2000_admin.donor_auth_tokens SET used_at = NOW()
            WHERE donor_user_id = ? AND purpose = 'reset_password' AND token_hash = ?")
            ->execute([$donorId, hash('sha256', $token)]);
        throw $e;
    }
    return true;
}

function consume_donor_password_reset(PDO $pdo, string $token, string $password): bool
{
    if (strlen($password) < 12 || strlen($password) > 72) {
        throw new InvalidArgumentException('Choose a password between 12 and 72 bytes long.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) { return false; }
    $hash = hash('sha256', $token);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT id, donor_user_id FROM rc2000_admin.donor_auth_tokens
            WHERE token_hash = ? AND purpose = 'reset_password' AND used_at IS NULL AND expires_at > NOW() FOR UPDATE");
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) { $pdo->rollBack(); return false; }
        $pdo->prepare('UPDATE rc2000_admin.donor_users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['donor_user_id']]);
        $pdo->prepare('UPDATE rc2000_admin.donor_auth_tokens SET used_at = NOW() WHERE donor_user_id = ? AND purpose = ? AND used_at IS NULL')
            ->execute([(int) $row['donor_user_id'], 'reset_password']);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        throw $e;
    }
}
