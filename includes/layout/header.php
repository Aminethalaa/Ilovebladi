<?php
// Expects: $page_title (string), optional $use_leaflet (bool).
$me = current_user();
$unread = $me ? unread_notifications((int) $me['id']) : 0;

$langSwitch = function (string $to): string {
    $qs = $_GET;
    $qs['lang'] = $to;
    return e(strtok($_SERVER['REQUEST_URI'] ?? '', '?') . '?' . http_build_query($qs));
};
?>
<!DOCTYPE html>
<html lang="<?= lang() ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? '') ?> — <?= e(app_name()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<?php if (!empty($use_leaflet)): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🇩🇿</text></svg>">
</head>
<body>
<header class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <span class="brand-mark">★</span>
      <span class="brand-name"><?= e(app_name()) ?></span>
    </a>
    <nav class="mainnav" id="mainnav">
      <a href="<?= e(url('index.php')) ?>"><?= e(t('nav_home')) ?></a>
      <a href="<?= e(url('complaints.php')) ?>"><?= e(t('nav_complaints')) ?></a>
      <a href="<?= e(url('leaderboard.php')) ?>"><?= e(t('nav_leaderboard')) ?></a>
      <?php if ($me): ?>
        <a href="<?= e(url(role_home($me))) ?>" class="nav-strong"><?= e(t('nav_dashboard')) ?></a>
        <?php if (in_array($me['role'], ['citizen', 'association'], true)): ?>
          <a class="nav-bell" href="<?= e(url('dashboard/notifications.php')) ?>" title="<?= e(t('nav_notifications')) ?>">
            🔔<?php if ($unread): ?><span class="bell-count"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
          </a>
        <?php endif; ?>
        <a href="<?= e(url('logout.php')) ?>" class="nav-muted"><?= e(t('nav_logout')) ?></a>
      <?php else: ?>
        <a href="<?= e(url('login.php')) ?>"><?= e(t('nav_login')) ?></a>
        <a href="<?= e(url('register.php')) ?>" class="btn btn-sm btn-primary"><?= e(t('nav_register')) ?></a>
      <?php endif; ?>
      <span class="lang-switch">
        <?php if (lang() === 'ar'): ?>
          <a href="<?= $langSwitch('fr') ?>">FR</a>
        <?php else: ?>
          <a href="<?= $langSwitch('ar') ?>">عربية</a>
        <?php endif; ?>
      </span>
    </nav>
    <button class="nav-toggle" id="navToggle" aria-label="menu">☰</button>
  </div>
</header>
<main>
<div class="container"><?= flash_render() ?></div>
