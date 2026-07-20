<?php
require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------- output
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    return WEB_BASE . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function upload_url(?string $file): string
{
    return $file ? url('uploads/' . rawurlencode($file)) : '';
}

// ---------------------------------------------------------------- i18n
function lang(): string
{
    return (isset($_SESSION['lang']) && $_SESSION['lang'] === 'fr') ? 'fr' : 'ar';
}

function is_rtl(): bool
{
    return lang() === 'ar';
}

function t(string $key, ...$args): string
{
    static $strings = null;
    if ($strings === null) {
        $strings = require BASE_PATH . '/includes/lang/' . lang() . '.php';
    }
    $s = $strings[$key] ?? $key;
    return $args ? vsprintf($s, $args) : $s;
}

/** Localized column helper: lc($row, 'name') => name_ar or name_fr. */
function lc(array $row, string $col): string
{
    return (string) ($row[$col . '_' . lang()] ?? $row[$col . '_ar'] ?? '');
}

function app_name(): string
{
    return lang() === 'ar' ? APP_NAME_AR : APP_NAME_FR;
}

// ---------------------------------------------------------------- flash
function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_render(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="flash flash-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
}

// ---------------------------------------------------------------- uploads
/**
 * Validate + store an uploaded image. Returns stored filename, or null with
 * $error set to a translation key.
 */
function upload_photo(array $file, ?string &$error): ?string
{
    $error = null;
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $error = 'err_photo_required';
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        $error = 'err_photo_upload';
        return null;
    }
    if ($file['size'] > MAX_PHOTO_BYTES) {
        $error = 'err_photo_size';
        return null;
    }
    $info = @getimagesize($file['tmp_name']);
    $mimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!$info || !isset($mimes[$info['mime']])) {
        $error = 'err_photo_type';
        return null;
    }
    $name = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $mimes[$info['mime']];
    if (!is_dir(UPLOAD_PATH)) {
        @mkdir(UPLOAD_PATH, 0755, true);
    }
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $name)) {
        $error = 'err_photo_upload';
        return null;
    }
    return $name;
}

// ---------------------------------------------------------------- statuses
function status_meta(string $status): array
{
    return [
        'pending'     => ['key' => 'st_pending',     'class' => 'st-pending'],
        'published'   => ['key' => 'st_published',   'class' => 'st-published'],
        'in_progress' => ['key' => 'st_in_progress', 'class' => 'st-progress'],
        'resolved'    => ['key' => 'st_resolved',    'class' => 'st-resolved'],
        'closed'      => ['key' => 'st_closed',      'class' => 'st-closed'],
        'rejected'    => ['key' => 'st_rejected',    'class' => 'st-rejected'],
    ][$status] ?? ['key' => $status, 'class' => 'st-pending'];
}

function status_badge(string $status): string
{
    $m = status_meta($status);
    return '<span class="badge ' . $m['class'] . '">' . e(t($m['key'])) . '</span>';
}

function new_ref(): string
{
    return 'DZ-' . date('y') . strtoupper(bin2hex(random_bytes(3)));
}

function time_ago(string $dt): string
{
    $diff = time() - strtotime($dt);
    if ($diff < 60)          return t('ago_now');
    if ($diff < 3600)        return t('ago_min', (int) floor($diff / 60));
    if ($diff < 86400)       return t('ago_hour', (int) floor($diff / 3600));
    if ($diff < 86400 * 30)  return t('ago_day', (int) floor($diff / 86400));
    return date('Y/m/d', strtotime($dt));
}

// ---------------------------------------------------------------- gamification
const POINTS = [
    'complaint_approved' => 10, // reporter, complaint published
    'complaint_resolved' => 25, // reporter, complaint resolved
    'fix_confirmed'      => 5,  // reporter confirms the fix
    'upvote_given'       => 2,  // voter
    'upvote_received'    => 1,  // reporter
    'assoc_take_charge'  => 5,  // association takes a complaint
    'assoc_fix'          => 10, // association submits a fix
    'assoc_fix_confirmed'=> 30, // association's fix confirmed/closed
];

/** Citizen levels: [min_points, translation key, icon]. */
function levels(): array
{
    return [
        [1000, 'lvl_hero',    '🏆'],
        [400,  'lvl_guard',   '🛡️'],
        [150,  'lvl_eye',     '👁️'],
        [50,   'lvl_active',  '⭐'],
        [0,    'lvl_new',     '🌱'],
    ];
}

function level_for(int $points): array
{
    foreach (levels() as $l) {
        if ($points >= $l[0]) {
            return $l;
        }
    }
    return [0, 'lvl_new', '🌱'];
}

/** Next level threshold or null when max level reached. */
function next_level(int $points): ?array
{
    $next = null;
    foreach (levels() as $l) {
        if ($points < $l[0]) {
            $next = $l;
        }
    }
    return $next;
}

function award_points(int $userId, string $reason, ?int $complaintId = null): void
{
    $pts = POINTS[$reason] ?? 0;
    if ($pts === 0) {
        return;
    }
    db()->prepare('INSERT INTO points_log (user_id, points, reason, complaint_id, created_at)
                   VALUES (?,?,?,?,NOW())')->execute([$userId, $pts, $reason, $complaintId]);
    db()->prepare('UPDATE users SET points = points + ? WHERE id = ?')->execute([$pts, $userId]);
    check_badges($userId);
}

/**
 * Award any count-based badges the user newly qualifies for.
 * Badge codes and thresholds must match the seed data in database.sql.
 */
function check_badges(int $userId): void
{
    $u = db()->prepare('SELECT id, role, points FROM users WHERE id = ?');
    $u->execute([$userId]);
    $user = $u->fetch();
    if (!$user) {
        return;
    }

    $counts = [];
    if ($user['role'] === 'citizen') {
        $q = db()->prepare("SELECT
            (SELECT COUNT(*) FROM complaints WHERE user_id = ? AND status <> 'pending' AND status <> 'rejected') AS approved,
            (SELECT COUNT(*) FROM complaints WHERE user_id = ? AND status IN ('resolved','closed')) AS fixed,
            (SELECT COUNT(*) FROM upvotes WHERE user_id = ?) AS votes");
        $q->execute([$userId, $userId, $userId]);
        $c = $q->fetch();
        $counts = [
            'first_report' => $c['approved'] >= 1,
            'reporter_5'   => $c['approved'] >= 5,
            'reporter_20'  => $c['approved'] >= 20,
            'first_fixed'  => $c['fixed'] >= 1,
            'fixed_10'     => $c['fixed'] >= 10,
            'supporter_10' => $c['votes'] >= 10,
        ];
    } elseif ($user['role'] === 'association') {
        $q = db()->prepare("SELECT COUNT(*) AS fixed FROM complaints
                            WHERE handler_id = ? AND status IN ('resolved','closed')");
        $q->execute([$userId]);
        $c = $q->fetch();
        $counts = [
            'assoc_first_fix' => $c['fixed'] >= 1,
            'assoc_fix_5'     => $c['fixed'] >= 5,
            'assoc_fix_20'    => $c['fixed'] >= 20,
        ];
    } elseif ($user['role'] === 'admin') {
        $q = db()->prepare("SELECT COUNT(*) AS fixed FROM complaints
                            WHERE handler_id = ? AND status IN ('resolved','closed')");
        $q->execute([$userId]);
        $c = $q->fetch();
        $counts = [
            'admin_fix_10' => $c['fixed'] >= 10,
            'admin_fix_50' => $c['fixed'] >= 50,
        ];
    }

    $earned = array_keys(array_filter($counts));
    if (!$earned) {
        return;
    }
    $in = implode(',', array_fill(0, count($earned), '?'));
    $st = db()->prepare("SELECT id, code FROM badges WHERE code IN ($in)");
    $st->execute($earned);
    foreach ($st->fetchAll() as $b) {
        $ins = db()->prepare('INSERT IGNORE INTO user_badges (user_id, badge_id, created_at) VALUES (?,?,NOW())');
        $ins->execute([$userId, $b['id']]);
        if ($ins->rowCount() > 0) {
            notify($userId, 'badge_earned', null);
        }
    }
}

function user_badges(int $userId): array
{
    $st = db()->prepare('SELECT b.* FROM user_badges ub JOIN badges b ON b.id = ub.badge_id
                         WHERE ub.user_id = ? ORDER BY b.sort');
    $st->execute([$userId]);
    return $st->fetchAll();
}

/**
 * Commune performance score /100: 70% resolution rate + 30% speed
 * (full speed marks at <=3 days average, fading out by 30 days).
 */
function commune_score(int $published, int $resolved, ?float $avgDays): int
{
    if ($published === 0) {
        return 0;
    }
    $rate = $resolved / $published;
    $speed = 0.0;
    if ($resolved > 0) {
        $d = max(0.0, (float) $avgDays);
        $speed = $d <= 3 ? 1.0 : max(0.0, 1 - ($d - 3) / 27);
    }
    return (int) round($rate * 70 + $speed * 30);
}

// ---------------------------------------------------------------- events + notifications
function log_event(int $complaintId, ?int $userId, string $event, ?string $note = null): void
{
    db()->prepare('INSERT INTO complaint_events (complaint_id, user_id, event, note, created_at)
                   VALUES (?,?,?,?,NOW())')->execute([$complaintId, $userId, $event, $note]);
}

/** In-app notification + best-effort email. */
function notify(int $userId, string $type, ?int $complaintId): void
{
    db()->prepare('INSERT INTO notifications (user_id, type, complaint_id, is_read, created_at)
                   VALUES (?,?,?,0,NOW())')->execute([$userId, $type, $complaintId]);

    $st = db()->prepare('SELECT email, lang FROM users WHERE id = ?');
    $st->execute([$userId]);
    if (!$to = $st->fetch()) {
        return;
    }
    $L = require BASE_PATH . '/includes/lang/' . (in_array($to['lang'], ['ar', 'fr'], true) ? $to['lang'] : 'ar') . '.php';
    $subject = $L['notif_' . $type] ?? $type;
    $ref = '';
    if ($complaintId) {
        $c = db()->prepare('SELECT ref, title FROM complaints WHERE id = ?');
        $c->execute([$complaintId]);
        if ($row = $c->fetch()) {
            $ref = ' [' . $row['ref'] . '] ' . $row['title'];
        }
    }
    $appName = ($to['lang'] === 'fr') ? APP_NAME_FR : APP_NAME_AR;
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n"
             . 'From: ' . $appName . ' <' . MAIL_FROM . ">\r\n";
    @mail(
        $to['email'],
        '=?UTF-8?B?' . base64_encode($appName . ' — ' . $subject) . '?=',
        $subject . $ref . "\n\n" . ($L['email_footer'] ?? ''),
        $headers
    );
}

function unread_notifications(int $userId): int
{
    $st = db()->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int) $st->fetch()['c'];
}

// ---------------------------------------------------------------- lookups
function all_wilayas(): array
{
    return db()->query('SELECT * FROM wilayas ORDER BY id')->fetchAll();
}

function communes_of(int $wilayaId): array
{
    $st = db()->prepare('SELECT * FROM communes WHERE wilaya_id = ? ORDER BY name_fr');
    $st->execute([$wilayaId]);
    return $st->fetchAll();
}

function active_categories(): array
{
    return db()->query('SELECT * FROM categories WHERE is_active = 1 ORDER BY id')->fetchAll();
}
