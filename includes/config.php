<?php
/**
 * Baladiyati — configuration.
 * Edit the 4 database constants below to match your cPanel MySQL database,
 * then import database.sql via phpMyAdmin and open /install.php once.
 */

// Requires PHP 7.4+ (8.1+ recommended). On cPanel: MultiPHP Manager / Select PHP Version.
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    http_response_code(500);
    die('This application requires PHP 7.4 or newer — your server runs PHP ' . PHP_VERSION
        . '. In cPanel, open "MultiPHP Manager" or "Select PHP Version" and choose PHP 8.1+.'
        . ' — التطبيق يتطلب PHP 7.4 على الأقل. غيّر إصدار PHP من لوحة cPanel.');
}
if (!extension_loaded('pdo_mysql') || !extension_loaded('mbstring')) {
    http_response_code(500);
    die('Missing PHP extensions: pdo_mysql and mbstring are required. Enable them in cPanel'
        . ' ("Select PHP Version" → Extensions). — إضافات PHP الناقصة: pdo_mysql و mbstring، فعّلها من cPanel.');
}

// ----- DATABASE (edit these) -----
define('DB_HOST', 'localhost');
define('DB_NAME', 'baladiyati');
define('DB_USER', 'root');
define('DB_PASS', '');

// ----- APPLICATION -----
define('APP_NAME_AR', 'بلديتي');
define('APP_NAME_FR', 'Baladiyati');
define('MAIL_FROM',   'noreply@' . (preg_replace('/^www\./', '', $_SERVER['HTTP_HOST'] ?? 'localhost')));
define('MAX_PHOTO_BYTES', 6 * 1024 * 1024); // 6 MB

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');

// Web base path: auto-detected. If your host uses a symlinked document root
// and links break, hard-code it instead, e.g. define('WEB_BASE', '/app');
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$appDir  = str_replace('\\', '/', BASE_PATH);
if ($docRoot !== '' && strpos($appDir, $docRoot) === 0) {
    $webBase = trim(substr($appDir, strlen($docRoot)), '/');
    define('WEB_BASE', $webBase === '' ? '' : '/' . $webBase);
} else {
    define('WEB_BASE', '');
}

date_default_timezone_set('Africa/Algiers');
mb_internal_encoding('UTF-8');
