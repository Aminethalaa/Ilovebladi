<?php
// Dynamic PWA manifest: reflects the site name, colors and logo set by the
// super admin (falls back to the built-in Baladiyati identity).
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/manifest+json; charset=utf-8');

$nameAr = site_setting('site_name_ar', APP_NAME_AR);
$nameFr = site_setting('site_name_fr', APP_NAME_FR);

echo json_encode([
    'name' => $nameAr . ' — ' . $nameFr,
    'short_name' => $nameAr,
    'description' => site_setting('site_tagline_ar', 'بلّغ عن مشاكل حيّك وتابع إصلاحها'),
    'id' => 'baladiyati',
    'dir' => 'rtl',
    'lang' => 'ar',
    'start_url' => './index.php?src=pwa',
    'scope' => './',
    'display' => 'standalone',
    'orientation' => 'portrait-primary',
    'background_color' => '#f6f8f7',
    'theme_color' => site_primary_color(),
    'icons' => array_values(array_filter([
        ['src' => app_icon_url(192), 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => app_icon_url(512), 'sizes' => '512x512', 'type' => 'image/png'],
        site_setting('site_icon_512') === ''
            ? ['src' => url('assets/img/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable']
            : ['src' => app_icon_url(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ])),
    'shortcuts' => [
        ['name' => 'بلاغ جديد — Nouveau signalement', 'url' => './dashboard/new-complaint.php',
         'icons' => [['src' => app_icon_url(192), 'sizes' => '192x192']]],
        ['name' => 'لوحتي — Mon espace', 'url' => './dashboard/index.php',
         'icons' => [['src' => app_icon_url(192), 'sizes' => '192x192']]],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
