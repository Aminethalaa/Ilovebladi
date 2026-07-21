<?php
/**
 * Dependency-free Web Push for shared hosting (no Composer needed).
 * Implements VAPID (RFC 8292, ES256 JWT) and payload encryption
 * (RFC 8291 / RFC 8188, aes128gcm) using PHP's OpenSSL extension.
 */
require_once __DIR__ . '/db.php';

function push_supported(): bool
{
    return extension_loaded('openssl') && function_exists('curl_init') && function_exists('hash_hkdf');
}

/** Create the push tables when missing (safe to call repeatedly). */
function push_tables(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS settings (
        k VARCHAR(64) PRIMARY KEY,
        v TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        endpoint_hash CHAR(64) NOT NULL UNIQUE,
        p256dh VARCHAR(255) NOT NULL,
        auth VARCHAR(64) NOT NULL,
        created_at DATETIME NOT NULL,
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function b64url_encode(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64url_decode(string $s): string
{
    return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
}

/** Get (or generate once) the site VAPID keypair. */
function vapid_keys(): ?array
{
    static $keys = null;
    if ($keys !== null) {
        return $keys ?: null;
    }
    if (!push_supported()) {
        $keys = false;
        return null;
    }
    push_tables();
    $st = db()->query("SELECT k, v FROM settings WHERE k IN ('vapid_public','vapid_private')");
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $rows[$r['k']] = $r['v'];
    }
    if (isset($rows['vapid_public'], $rows['vapid_private'])) {
        return $keys = ['public' => $rows['vapid_public'], 'private_pem' => $rows['vapid_private']];
    }
    $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$res) {
        $keys = false;
        return null;
    }
    openssl_pkey_export($res, $privPem);
    $d = openssl_pkey_get_details($res);
    $public = b64url_encode("\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT)
        . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT));
    $ins = db()->prepare('INSERT INTO settings (k, v) VALUES (?,?)');
    $ins->execute(['vapid_public', $public]);
    $ins->execute(['vapid_private', $privPem]);
    return $keys = ['public' => $public, 'private_pem' => $privPem];
}

/** Raw uncompressed P-256 point -> PEM SubjectPublicKeyInfo. */
function p256_point_to_pem(string $point65): string
{
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point65;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** DER ECDSA signature -> raw r||s (JOSE format, 64 bytes). */
function ecdsa_der_to_raw(string $der): string
{
    // P-256 signatures are at most 72 bytes: length is short-form or a single 0x81 prefix.
    $pos = (ord($der[1]) === 0x81) ? 3 : 2;
    $out = '';
    for ($i = 0; $i < 2; $i++) {
        $pos++; // 0x02
        $len = ord($der[$pos++]);
        $int = substr($der, $pos, $len);
        $pos += $len;
        $int = ltrim($int, "\0");
        $out .= str_pad($int, 32, "\0", STR_PAD_LEFT);
    }
    return $out;
}

/** Signed VAPID JWT for a push service origin. */
function vapid_jwt(string $audience): ?string
{
    $keys = vapid_keys();
    if (!$keys) {
        return null;
    }
    $seg = b64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
        . '.' . b64url_encode(json_encode([
            'aud' => $audience,
            'exp' => time() + 12 * 3600,
            'sub' => 'mailto:' . MAIL_FROM,
        ]));
    if (!openssl_sign($seg, $sig, $keys['private_pem'], OPENSSL_ALGO_SHA256)) {
        return null;
    }
    return $seg . '.' . b64url_encode(ecdsa_der_to_raw($sig));
}

/**
 * RFC 8291 aes128gcm encryption of a payload for one subscription.
 * Returns the full binary body (header || ciphertext) or null.
 */
function webpush_encrypt(string $payload, string $p256dhB64, string $authB64): ?string
{
    $uaPublic = b64url_decode($p256dhB64);           // 65 bytes
    $authSecret = b64url_decode($authB64);           // 16 bytes
    if (strlen($uaPublic) !== 65 || strlen($authSecret) !== 16) {
        return null;
    }
    $as = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if (!$as) {
        return null;
    }
    $d = openssl_pkey_get_details($as);
    $asPublic = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT)
        . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
    $shared = openssl_pkey_derive(p256_point_to_pem($uaPublic), $as);
    if ($shared === false || strlen($shared) !== 32) {
        return null;
    }
    $ikm  = hash_hkdf('sha256', $shared, 32, 'WebPush: info' . "\0" . $uaPublic . $asPublic, $authSecret);
    $salt = random_bytes(16);
    $cek   = hash_hkdf('sha256', $ikm, 16, 'Content-Encoding: aes128gcm' . "\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, 'Content-Encoding: nonce' . "\0", $salt);
    $record = $payload . "\x02"; // final record delimiter
    $tag = '';
    $ct = openssl_encrypt($record, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($ct === false) {
        return null;
    }
    return $salt . pack('N', 4096) . chr(65) . $asPublic . $ct . $tag;
}

/**
 * Send one push message. Returns HTTP status (or 0 on transport failure).
 * 404/410 mean the subscription is dead and should be deleted by the caller.
 */
function webpush_send(array $sub, array $payload): int
{
    $endpoint = $sub['endpoint'];
    $parts = parse_url($endpoint);
    if (empty($parts['scheme']) || empty($parts['host'])) {
        return 0;
    }
    $jwt = vapid_jwt($parts['scheme'] . '://' . $parts['host']);
    if (!$jwt) {
        return 0;
    }
    $body = webpush_encrypt(json_encode($payload, JSON_UNESCAPED_UNICODE), $sub['p256dh'], $sub['auth']);
    if ($body === null) {
        return 0;
    }
    $keys = vapid_keys();
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: vapid t=' . $jwt . ', k=' . $keys['public'],
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'Content-Length: ' . strlen($body),
            'TTL: 86400',
            'Urgency: normal',
        ],
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $code;
}

/** Push a message to every device of one user (best effort). */
function push_user(int $userId, array $payload): void
{
    if (!push_supported()) {
        return;
    }
    try {
        push_tables();
        $st = db()->prepare('SELECT * FROM push_subscriptions WHERE user_id = ?');
        $st->execute([$userId]);
        foreach ($st->fetchAll() as $sub) {
            $code = webpush_send($sub, $payload);
            if ($code === 404 || $code === 410) {
                db()->prepare('DELETE FROM push_subscriptions WHERE id = ?')->execute([$sub['id']]);
            }
        }
    } catch (Throwable $e) {
        // never break the main flow because of push
    }
}
