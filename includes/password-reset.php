<?php
/**
 * Password reset via emailed single-use token.
 * The table auto-creates on first use, so existing databases need no migration.
 * Only a SHA-256 hash of the token is stored: a database leak cannot be used
 * to hijack accounts.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

const RESET_TTL_MINUTES = 60;

function reset_tables(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL,
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/**
 * Create a reset token for an email and send it. Always behaves the same way
 * from the caller's perspective so the form cannot be used to discover which
 * emails are registered.
 */
function reset_request(string $email): void
{
    reset_tables();
    $st = db()->prepare('SELECT id, name, lang FROM users WHERE email = ? AND is_blocked = 0');
    $st->execute([$email]);
    $user = $st->fetch();
    if (!$user) {
        return;
    }
    // one live token per user
    db()->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')
        ->execute([(int) $user['id']]);

    $token = bin2hex(random_bytes(32));
    db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
                   VALUES (?,?,DATE_ADD(NOW(), INTERVAL ? MINUTE),NOW())')
        ->execute([(int) $user['id'], hash('sha256', $token), RESET_TTL_MINUTES]);

    $lang = in_array($user['lang'], ['ar', 'fr'], true) ? $user['lang'] : 'ar';
    $L = require BASE_PATH . '/includes/lang/' . $lang . '.php';
    $appName = ($lang === 'fr') ? APP_NAME_FR : APP_NAME_AR;
    if ($custom = site_setting('site_name_' . $lang)) {
        $appName = $custom;
    }
    $link = abs_url('reset-password.php?token=' . $token);
    $body = ($L['pr_email_body'] ?? '') . "\n\n" . $link . "\n\n"
        . sprintf($L['pr_email_expiry'] ?? '', RESET_TTL_MINUTES) . "\n"
        . ($L['pr_email_ignore'] ?? '');
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n"
        . 'From: ' . $appName . ' <' . MAIL_FROM . ">\r\n";
    @mail(
        $email,
        '=?UTF-8?B?' . base64_encode($appName . ' — ' . ($L['pr_email_subject'] ?? 'Reset')) . '?=',
        $body,
        $headers
    );
}

/** Return the user row for a valid, unused, unexpired token, or null. */
function reset_lookup(string $token): ?array
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }
    reset_tables();
    $st = db()->prepare('SELECT pr.id AS reset_id, u.*
        FROM password_resets pr JOIN users u ON u.id = pr.user_id
        WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() AND u.is_blocked = 0');
    $st->execute([hash('sha256', $token)]);
    return $st->fetch() ?: null;
}

/** Set the new password and burn the token. */
function reset_complete(int $resetId, int $userId, string $password): void
{
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$resetId]);
    // invalidate any other outstanding tokens for this account
    db()->prepare('DELETE FROM password_resets WHERE user_id = ? AND used_at IS NULL')->execute([$userId]);
}
