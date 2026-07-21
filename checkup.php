<?php
/**
 * Baladiyati server checkup — self-diagnostic for cPanel deployments.
 * Written with old-PHP-compatible syntax on purpose so it runs (and reports
 * problems clearly) even on hosts where the app itself cannot start.
 * Delete this file once your platform is up and running.
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: text/html; charset=utf-8');

$checks = array();
function add_check($label, $ok, $detail)
{
    global $checks;
    $checks[] = array($label, $ok, $detail);
}

// 1. PHP version
$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');
add_check('PHP version', $phpOk, 'PHP ' . PHP_VERSION . ($phpOk ? ' (OK, 8.1+ recommended)'
    : ' — TOO OLD. In cPanel open "MultiPHP Manager" or "Select PHP Version" and pick PHP 8.1 or newer.'));

// 2. Extensions
$exts = array('pdo_mysql' => 'database driver (required)', 'mbstring' => 'Arabic text handling (required)',
              'gd' => 'images (recommended)', 'session' => 'logins (required)');
foreach ($exts as $ext => $why) {
    add_check('Extension: ' . $ext, extension_loaded($ext), $why);
}

// 3. Config file + DB connection + tables
$dbOk = false;
if (!file_exists(__DIR__ . '/includes/config.php')) {
    add_check('includes/config.php', false, 'File is missing — upload the full project.');
} elseif ($phpOk && extension_loaded('pdo_mysql') && extension_loaded('mbstring')) {
    require __DIR__ . '/includes/config.php';
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        add_check('Database connection', true, 'Connected to "' . DB_NAME . '" as "' . DB_USER . '".');
        $dbOk = true;
        try {
            $n = $pdo->query('SELECT COUNT(*) FROM wilayas')->fetchColumn();
            add_check('Database tables', true, 'Schema imported (' . $n . ' wilayas found).');
            $sa = $pdo->query("SELECT COUNT(*) FROM users WHERE role='superadmin'")->fetchColumn();
            add_check('Super admin account', $sa > 0, $sa > 0 ? 'Created — you can delete install.php and checkup.php.'
                : 'Not created yet — open install.php after all checks above pass.');
        } catch (Exception $e) {
            add_check('Database tables', false, 'Tables missing — import database.sql via phpMyAdmin into "' . DB_NAME . '".');
        }
    } catch (Exception $e) {
        add_check('Database connection', false,
            'Failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            . ' — check DB_HOST/DB_NAME/DB_USER/DB_PASS in includes/config.php.'
            . ' On cPanel, names are usually prefixed (e.g. cpuser_baladiyati) and the user must be'
            . ' added to the database with ALL PRIVILEGES.');
    }
} else {
    add_check('Database connection', false, 'Skipped — fix the PHP version/extension checks above first.');
}

// 4. Uploads folder writable
$up = __DIR__ . '/uploads';
$w = is_dir($up) && is_writable($up);
add_check('uploads/ writable', $w, $w ? 'OK' : 'Create the folder and set permissions to 755 in File Manager.');

// 5. Sessions
$sessOk = @session_start();
add_check('PHP sessions', (bool) $sessOk, $sessOk ? 'OK' : 'session.save_path may be invalid on this host.');

$allOk = true;
foreach ($checks as $c) {
    if (!$c[1]) { $allOk = false; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Baladiyati — Server checkup</title>
<style>
body{font-family:system-ui,Tahoma,sans-serif;background:#f6f8f7;color:#1c2422;margin:0;padding:24px}
.wrap{max-width:760px;margin:0 auto}
h1{color:#006233}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 10px rgba(0,40,20,.08)}
td{padding:12px 16px;border-bottom:1px solid #e3e9e6;vertical-align:top}
.ok{color:#15803d;font-weight:bold;white-space:nowrap}
.ko{color:#d21034;font-weight:bold;white-space:nowrap}
.verdict{margin:18px 0;padding:14px 18px;border-radius:12px;font-weight:bold}
.v-ok{background:#dcfce7;color:#14532d}.v-ko{background:#fdebee;color:#d21034}
small{color:#64716c}
</style>
</head>
<body><div class="wrap">
<h1>🇩🇿 Baladiyati — Server checkup</h1>
<div class="verdict <?php echo $allOk ? 'v-ok' : 'v-ko'; ?>">
<?php echo $allOk
    ? '✅ Everything looks good. Open install.php to create the super admin (if not done), then delete install.php and checkup.php.'
    : '⚠️ Fix the failed checks below, then reload this page.'; ?>
</div>
<table>
<?php foreach ($checks as $c) { ?>
<tr>
  <td class="<?php echo $c[1] ? 'ok' : 'ko'; ?>"><?php echo $c[1] ? '✔ OK' : '✘ FAIL'; ?></td>
  <td><strong><?php echo htmlspecialchars($c[0], ENT_QUOTES, 'UTF-8'); ?></strong><br>
      <small><?php echo $c[2]; ?></small></td>
</tr>
<?php } ?>
</table>
<p><small>Delete this file (checkup.php) once the platform is running.</small></p>
</div></body>
</html>
