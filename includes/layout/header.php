<?php
// Expects: $page_title (string), optional $use_leaflet (bool).
$me = current_user();
$unread = $me ? unread_notifications((int) $me['id']) : 0;
run_reminders();

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
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($page_title ?? '') ?> — <?= e(app_name()) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<?php if (!empty($use_leaflet)): ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<?php
$cPrimary = site_setting('color_primary');
$cAccent = site_setting('color_accent');
$cGold = site_setting('color_gold');
$hexOk = function (string $c): bool { return (bool) preg_match('/^#[0-9a-fA-F]{6}$/', $c); };
if ($hexOk($cPrimary) || $hexOk($cAccent) || $hexOk($cGold)): ?>
<style>:root{
<?php if ($hexOk($cPrimary)): ?>
--green:<?= e($cPrimary) ?>;--green-dark:<?= e(color_shade($cPrimary, -0.30)) ?>;--green-bright:<?= e(color_shade($cPrimary, 0.14)) ?>;--green-light:<?= e(color_shade($cPrimary, 0.90)) ?>;
<?php endif; ?>
<?php if ($hexOk($cAccent)): ?>--red:<?= e($cAccent) ?>;--red-dark:<?= e(color_shade($cAccent, -0.25)) ?>;<?php endif; ?>
<?php if ($hexOk($cGold)): ?>--gold:<?= e($cGold) ?>;<?php endif; ?>
}</style>
<?php endif; ?>
<?php if (site_logo_url()): ?>
<link rel="icon" href="<?= e(app_icon_url(192)) ?>">
<?php else: ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🇩🇿</text></svg>">
<?php endif; ?>
<link rel="manifest" href="<?= e(url('manifest.php')) ?>">
<meta name="theme-color" content="<?= e(site_primary_color()) ?>">
<link rel="apple-touch-icon" href="<?= e(app_icon_url(192)) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e(app_name()) ?>">
</head>
<body data-base="<?= e(url('')) ?>">
<header class="topbar">
  <div class="container topbar-inner">
    <a class="brand" href="<?= e(url('index.php')) ?>">
      <?php if (site_logo_url()): ?>
      <img class="brand-logo" src="<?= e(site_logo_url()) ?>" alt="">
      <?php else: ?>
      <span class="brand-mark">★</span>
      <?php endif; ?>
      <span class="brand-name"><?= e(app_name()) ?></span>
    </a>
    <nav class="mainnav" id="mainnav">
      <a href="<?= e(url('index.php')) ?>"><?= e(t('nav_home')) ?></a>
      <?php if (module_on('complaints')): ?>
      <a href="<?= e(url('complaints.php')) ?>"><?= e(t('nav_complaints')) ?></a>
      <?php endif; ?>
      <?php if (module_on('trees')): ?>
      <a href="<?= e(url('trees.php')) ?>">🌳 <?= e(t('nav_trees')) ?></a>
      <?php endif; ?>
      <?php if (module_on('leaderboard')): ?>
      <a href="<?= e(url('leaderboard.php')) ?>"><?= e(t('nav_leaderboard')) ?></a>
      <?php endif; ?>
      <?php if ($me): ?>
        <a href="<?= e(url(role_home($me))) ?>" class="nav-strong"><?= e(t('nav_dashboard')) ?></a>
        <a href="<?= e(url('logout.php')) ?>" class="nav-muted"><?= e(t('nav_logout')) ?></a>
      <?php else: ?>
        <a href="<?= e(url('login.php')) ?>"><?= e(t('nav_login')) ?></a>
        <a href="<?= e(url('register.php')) ?>" class="btn btn-sm btn-primary"><?= e(t('nav_register')) ?></a>
      <?php endif; ?>
    </nav>
    <div class="topbar-side">
      <?php if ($me): ?>
      <a class="nav-bell" href="<?= e(url('dashboard/notifications.php')) ?>" title="<?= e(t('nav_notifications')) ?>">
        🔔<?php if ($unread): ?><span class="bell-count"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
      </a>
      <?php endif; ?>
      <span class="lang-switch">
        <?php if (lang() === 'ar'): ?>
          <a href="<?= $langSwitch('fr') ?>">FR</a>
        <?php else: ?>
          <a href="<?= $langSwitch('ar') ?>">عربية</a>
        <?php endif; ?>
      </span>
      <button class="nav-toggle" id="navToggle" aria-label="menu">☰</button>
    </div>
  </div>
</header>
<main>
<div class="container"><?= flash_render() ?></div>
