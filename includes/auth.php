<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 14,
        'path'     => (WEB_BASE === '' ? '/' : WEB_BASE . '/'),
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_name('baladiyati_sid');
    session_start();
}

// Language switch (?lang=ar|fr) — persisted in session and on the account.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'fr'], true)) {
    $_SESSION['lang'] = $_GET['lang'];
    if (!empty($_SESSION['uid'])) {
        db()->prepare('UPDATE users SET lang = ? WHERE id = ?')
            ->execute([$_GET['lang'], (int) $_SESSION['uid']]);
    }
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['uid'])) {
            $st = db()->prepare(
                'SELECT u.*, c.name_ar AS commune_ar, c.name_fr AS commune_fr,
                        w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr
                 FROM users u
                 LEFT JOIN communes c ON c.id = u.commune_id
                 LEFT JOIN wilayas  w ON w.id = u.wilaya_id
                 WHERE u.id = ?'
            );
            $st->execute([(int) $_SESSION['uid']]);
            $user = $st->fetch() ?: null;
            if ($user && (int) $user['is_blocked'] === 1) {
                unset($_SESSION['uid']);
                $user = null;
            }
            if ($user && empty($_SESSION['lang'])) {
                $_SESSION['lang'] = $user['lang'];
            }
        }
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['uid']  = (int) $user['id'];
    $_SESSION['lang'] = $user['lang'] ?? 'ar';
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        redirect('dashboard/index.php');
    }
    return $u;
}

/** Landing page of the account's role. */
function role_home(array $u): string
{
    switch ($u['role']) {
        case 'superadmin': return 'superadmin/index.php';
        case 'admin':      return 'admin/index.php';
        default:           return 'dashboard/index.php';
    }
}

// ----- CSRF -----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (($_POST['csrf'] ?? '') === '' || !hash_equals(csrf_token(), (string) $_POST['csrf'])) {
        http_response_code(403);
        die('Invalid security token — رمز الأمان غير صالح. أعد تحميل الصفحة.');
    }
}
