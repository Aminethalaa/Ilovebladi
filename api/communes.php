<?php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

$wilayaId = (int) ($_GET['wilaya'] ?? 0);
$out = [];
if ($wilayaId > 0) {
    foreach (communes_of($wilayaId) as $c) {
        $out[] = ['id' => (int) $c['id'], 'name' => lc($c, 'name')];
    }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
