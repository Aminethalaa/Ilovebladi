<?php
/**
 * Discussion threads on complaints.
 * Table auto-creates on first use — existing databases need no migration.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function comment_tables(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS comments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        complaint_id INT NOT NULL,
        user_id INT NOT NULL,
        body TEXT NOT NULL,
        is_official TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        KEY idx_complaint (complaint_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** All comments of a complaint with their author. */
function comments_of(int $complaintId): array
{
    try {
        comment_tables();
        $st = db()->prepare('SELECT c.*, u.name AS author, u.role AS author_role, u.points AS author_points
            FROM comments c JOIN users u ON u.id = c.user_id
            WHERE c.complaint_id = ? ORDER BY c.id ASC LIMIT 200');
        $st->execute([$complaintId]);
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function comments_count(int $complaintId): int
{
    try {
        comment_tables();
        $st = db()->prepare('SELECT COUNT(*) c FROM comments WHERE complaint_id = ?');
        $st->execute([$complaintId]);
        return (int) $st->fetch()['c'];
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Post a comment. Comments by the commune admin of that complaint, or by the
 * association/citizen handling it, are flagged as official (verified answer).
 * Notifies the reporter and everyone else in the thread.
 */
function comment_post(array $complaint, array $author, string $body): bool
{
    $body = trim($body);
    if (mb_strlen($body) < 2 || mb_strlen($body) > 1500) {
        return false;
    }
    comment_tables();
    $official = ($author['role'] === 'admin' && (int) $author['commune_id'] === (int) $complaint['commune_id'])
        || $author['role'] === 'superadmin'
        || (int) ($complaint['handler_id'] ?? 0) === (int) $author['id'];

    db()->prepare('INSERT INTO comments (complaint_id, user_id, body, is_official, created_at)
                   VALUES (?,?,?,?,NOW())')
        ->execute([(int) $complaint['id'], (int) $author['id'], $body, $official ? 1 : 0]);

    // notify the reporter and previous participants (never the author)
    $targets = [(int) $complaint['user_id']];
    if (!empty($complaint['handler_id'])) {
        $targets[] = (int) $complaint['handler_id'];
    }
    $st = db()->prepare('SELECT DISTINCT user_id FROM comments WHERE complaint_id = ?');
    $st->execute([(int) $complaint['id']]);
    foreach ($st->fetchAll() as $r) {
        $targets[] = (int) $r['user_id'];
    }
    foreach (array_unique($targets) as $uid) {
        if ($uid !== (int) $author['id']) {
            notify($uid, $official ? 'official_reply' : 'new_comment', (int) $complaint['id']);
        }
    }
    return true;
}

/** A comment can be removed by its author, the zone admin, or the super admin. */
function comment_can_delete(array $comment, array $complaint, ?array $me): bool
{
    if (!$me) {
        return false;
    }
    return (int) $comment['user_id'] === (int) $me['id']
        || $me['role'] === 'superadmin'
        || ($me['role'] === 'admin' && (int) $me['commune_id'] === (int) $complaint['commune_id']);
}
