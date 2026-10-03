<?php
/**
 * Authentication: register, login (with rate limiting), logout,
 * email verification, password reset, and access guards.
 */

/* ---------------- Current user ---------------- */
function current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([(int) $_SESSION['user_id']]);
    $user = $st->fetch() ?: null;
    // If the account was banned/deleted mid-session, force logout.
    if ($user && (int) $user['is_banned'] === 1) {
        logout_user();
        return null;
    }
    return $user;
}

function is_admin(): bool
{
    $u = current_user();
    return $u && $u['role'] === 'admin';
}

/** Redirect guests to the login page (remembering where they came from). */
function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? u('index.php');
        flash('info', 'Please log in first.');
        redirect('login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('<h1>403 — Admins only</h1>');
    }
    return $user;
}

/* ---------------- Email domain restriction ---------------- */
function email_domain_allowed(string $email): bool
{
    if (site_setting('allowed_domains_only', config('allowed_domains_only') ? '1' : '0') !== '1') {
        return true; // feature switched off
    }
    $domain = strtolower(substr(strrchr($email, '@'), 1) ?: '');
    $st = db()->prepare('SELECT COUNT(*) FROM allowed_domains WHERE domain = ?');
    $st->execute([$domain]);
    return (int) $st->fetchColumn() > 0;
}

/* ---------------- Registration ---------------- */
function register_user(string $name, string $email, string $password): array
{
    $errors = [];
    $name  = trim($name);
    $email = strtolower(trim($email));

    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Please enter your full name.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'That email address does not look valid.';
    } elseif (!email_domain_allowed($email)) {
        $errors['email'] = 'Registration is limited to approved university email domains.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }

    $db = db();
    if (!$errors) {
        $st = $db->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $errors['email'] = 'An account with this email already exists. Try logging in.';
        }
    }
    if ($errors) {
        return ['ok' => false, 'errors' => $errors];
    }

    $needsVerify = site_setting('email_verification',
        config('email_verification') ? '1' : '0') === '1';
    $token = $needsVerify ? bin2hex(random_bytes(32)) : null;

    $st = $db->prepare(
        'INSERT INTO users (name, email, password_hash, is_verified, verify_token)
         VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([
        $name, $email, password_hash($password, PASSWORD_DEFAULT),
        $needsVerify ? 0 : 1, $token,
    ]);
    $userId = (int) $db->lastInsertId();

    if ($needsVerify && $token) {
        $link = u('verify.php?token=' . $token);
        send_email($email, 'Verify your CampusMart account',
            "<p>Hi " . e($name) . ",</p><p>Click the link to verify your account:</p>"
            . "<p><a href=\"$link\">Verify my account</a></p>");
    }

    return ['ok' => true, 'user_id' => $userId, 'needs_verify' => $needsVerify];
}

/* ---------------- Login (with rate limiting) ---------------- */
function login_rate_limited(string $email): bool
{
    // Portable: the cutoff timestamp is computed in PHP, so the same SQL
    // works on both SQLite and MySQL.
    $cutoff = date('Y-m-d H:i:s', strtotime('-15 minutes'));
    $st = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip = ? AND email = ? AND attempted_at > ?'
    );
    $st->execute([client_ip(), strtolower(trim($email)), $cutoff]);
    return (int) $st->fetchColumn() >= 5;
}

function record_login_attempt(string $email): void
{
    db()->prepare('INSERT INTO login_attempts (ip, email) VALUES (?, ?)')
      ->execute([client_ip(), strtolower(trim($email))]);
}

function clear_login_attempts(string $email): void
{
    db()->prepare('DELETE FROM login_attempts WHERE ip = ? AND email = ?')
      ->execute([client_ip(), strtolower(trim($email))]);
}

function login_user(string $email, string $password): array
{
    $email = strtolower(trim($email));

    if (login_rate_limited($email)) {
        return ['ok' => false,
                'error' => 'Too many failed attempts. Please wait 15 minutes and try again.'];
    }

    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $user = $st->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_login_attempt($email);
        // Generic message on purpose: never reveal whether the email exists.
        return ['ok' => false, 'error' => 'Incorrect email or password.'];
    }
    if ((int) $user['is_banned'] === 1) {
        return ['ok' => false, 'error' => 'This account has been banned. Contact support.'];
    }
    if ((int) $user['is_verified'] === 0) {
        return ['ok' => false, 'error' => 'Please verify your email first (check your inbox).'];
    }

    clear_login_attempts($email);
    session_regenerate_id(true); // prevent session fixation
    $_SESSION['user_id'] = (int) $user['id'];
    return ['ok' => true, 'user' => $user];
}

function logout_user(): void
{
    unset($_SESSION['user_id']);
    session_regenerate_id(true);
}

/* ---------------- Email verification ---------------- */
function verify_email_token(string $token): bool
{
    $db = db();
    $st = $db->prepare('SELECT id FROM users WHERE verify_token = ?');
    $st->execute([$token]);
    $user = $st->fetch();
    if (!$user) {
        return false;
    }
    $db->prepare('UPDATE users SET is_verified = 1, verify_token = NULL WHERE id = ?')
       ->execute([$user['id']]);
    return true;
}

/* ---------------- Password reset ---------------- */
function request_password_reset(string $email): void
{
    $email = strtolower(trim($email));
    $db = db();
    $st = $db->prepare('SELECT id, name FROM users WHERE email = ?');
    $st->execute([$email]);
    $user = $st->fetch();
    // Always "succeed" so attackers cannot enumerate registered emails.
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $db->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?')
           ->execute([$token, $expires, $user['id']]);
        $link = u('reset.php?token=' . $token);
        send_email($email, 'Reset your CampusMart password',
            "<p>Hi " . e($user['name']) . ",</p>"
            . "<p>Click the link below to choose a new password (valid for 1 hour):</p>"
            . "<p><a href=\"$link\">Reset my password</a></p>");
    }
}

function reset_password_with_token(string $token, string $password): array
{
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Password must be at least 8 characters.'];
    }
    $db = db();
    $st = $db->prepare(
        'SELECT id FROM users WHERE reset_token = ? AND reset_expires > ?'
    );
    $st->execute([$token, date('Y-m-d H:i:s')]);
    $user = $st->fetch();
    if (!$user) {
        return ['ok' => false, 'error' => 'This reset link is invalid or has expired.'];
    }
    $db->prepare(
        'UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?'
    )->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    return ['ok' => true];
}
