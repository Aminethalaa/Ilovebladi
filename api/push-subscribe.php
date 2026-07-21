<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/webpush.php';
header('Content-Type: application/json; charset=utf-8');

$me = current_user();
if (!$me) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit;
}
if (!push_supported()) {
    echo json_encode(['ok' => false, 'error' => 'unsupported']);
    exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || empty($in['csrf']) || !hash_equals(csrf_token(), (string) $in['csrf'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'csrf']);
    exit;
}

push_tables();
$endpoint = (string) ($in['endpoint'] ?? '');
$hash = hash('sha256', $endpoint);

if (($in['action'] ?? '') === 'unsubscribe') {
    db()->prepare('DELETE FROM push_subscriptions WHERE endpoint_hash = ?')->execute([$hash]);
    echo json_encode(['ok' => true]);
    exit;
}

$p256dh = (string) ($in['keys']['p256dh'] ?? '');
$auth = (string) ($in['keys']['auth'] ?? '');
if (!preg_match('#^https://#', $endpoint) || strlen($endpoint) > 1000 || $p256dh === '' || $auth === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit;
}
db()->prepare('INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth, created_at)
    VALUES (?,?,?,?,?,NOW())
    ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth)')
    ->execute([(int) $me['id'], $endpoint, $hash, $p256dh, $auth]);
echo json_encode(['ok' => true]);
