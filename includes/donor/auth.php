<?php
declare(strict_types=1);

/** Load the signed-in donor, checking the account is still active. */
function current_donor_user(): ?array
{
    $id = filter_var($_SESSION['donor_user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$id || $id < 1) {
        return null;
    }

    $stmt = db()->prepare(
        "SELECT id, name, email, email_verified_at
         FROM rc2000_admin.donor_users
         WHERE id = :id AND status = 'active'"
    );
    $stmt->execute(['id' => $id]);
    $donor = $stmt->fetch();
    if (!$donor) {
        unset($_SESSION['donor_user_id']);
        return null;
    }

    return $donor;
}

/** Create a donor account and return its ID. */
function create_donor_account(string $name, string $email, string $password): int
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    $email = mb_strtolower(trim($email));

    if (!required_text($name, 200)) {
        throw new InvalidArgumentException('Enter your name (up to 200 characters).');
    }
    if (!valid_email($email) || mb_strlen($email) > 250) {
        throw new InvalidArgumentException('Enter a valid email address.');
    }
    if (strlen($password) < 12 || strlen($password) > 72) {
        throw new InvalidArgumentException('Choose a password between 12 and 72 bytes long.');
    }

    $stmt = db()->prepare(
        'INSERT INTO rc2000_admin.donor_users (name, email, password_hash)
         VALUES (:name, :email, :password_hash)
         RETURNING id'
    );
    $stmt->execute([
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    return (int) $stmt->fetchColumn();
}

/** Sign in and return a generic error when credentials are invalid. */
function sign_in_donor(string $email, string $password): ?string
{
    $email = mb_strtolower(trim($email));
    if ($email === '' || $password === '') {
        return 'Enter your email and password.';
    }

    $stmt = db()->prepare(
        "SELECT id, password_hash
         FROM rc2000_admin.donor_users
         WHERE LOWER(BTRIM(email)) = :email AND status = 'active'"
    );
    $stmt->execute(['email' => $email]);
    $donor = $stmt->fetch();

    if (!$donor || !password_verify($password, (string) $donor['password_hash'])) {
        return 'The email or password is incorrect.';
    }

    session_regenerate_id(true);
    $_SESSION['donor_user_id'] = (int) $donor['id'];
    unset($_SESSION['csrf_token']);
    csrf_token();

    $update = db()->prepare(
        'UPDATE rc2000_admin.donor_users SET last_login_at = NOW() WHERE id = :id'
    );
    $update->execute(['id' => (int) $donor['id']]);

    return null;
}

/** End the public donor session. */
function sign_out_donor(): void
{
    unset($_SESSION['donor_user_id']);
    session_regenerate_id(true);
}
