<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

$where = ["status IN ('published','in_progress','resolved','closed')", 'lat IS NOT NULL'];
$bind = [];
if ($v = (int) ($_GET['wilaya'] ?? 0))   { $where[] = 'wilaya_id = ?';   $bind[] = $v; }
if ($v = (int) ($_GET['commune'] ?? 0))  { $where[] = 'commune_id = ?';  $bind[] = $v; }
if ($v = (int) ($_GET['category'] ?? 0)) { $where[] = 'category_id = ?'; $bind[] = $v; }
$s = (string) ($_GET['status'] ?? '');
if (in_array($s, ['published', 'in_progress', 'resolved', 'closed'], true)) {
    $where[] = 'status = ?';
    $bind[] = $s;
}

$st = db()->prepare('SELECT id, title, status, lat, lng FROM complaints WHERE '
    . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 500');
$st->execute($bind);

$features = [];
foreach ($st->fetchAll() as $r) {
    $features[] = [
        'type' => 'Feature',
        'geometry' => ['type' => 'Point', 'coordinates' => [(float) $r['lng'], (float) $r['lat']]],
        'properties' => [
            'id' => (int) $r['id'],
            'title' => $r['title'],
            'status' => $r['status'],
            'link_label' => t('view_details'),
        ],
    ];
}
echo json_encode(['type' => 'FeatureCollection', 'features' => $features], JSON_UNESCAPED_UNICODE);
