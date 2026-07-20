<?php
/**
 * Baladiyati — configuration.
 * Edit the 4 database constants below to match your cPanel MySQL database,
 * then import database.sql via phpMyAdmin and open /install.php once.
 */

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
