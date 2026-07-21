<?php
/**
 * Tree-planting campaigns: admins/associations set goals, citizens plant
 * and log trees, the community confirms them (2 confirmations, or one from
 * the campaign creator / an admin, approve a planting).
 */
require_once __DIR__ . '/db.php';

const TREE_CONFIRMS_NEEDED = 2;

/** Create the tree tables when missing (safe for existing databases). */
function tree_tables(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS tree_campaigns (
        id INT AUTO_INCREMENT PRIMARY KEY,
        creator_id INT NOT NULL,
        wilaya_id INT NOT NULL,
        commune_id INT NOT NULL,
        title VARCHAR(180) NOT NULL,
        description TEXT NOT NULL,
        goal INT NOT NULL,
        ends_at DATE NULL,
        status VARCHAR(10) NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL,
        KEY idx_status (status),
        KEY idx_commune (commune_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS tree_plantings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        campaign_id INT NOT NULL,
        user_id INT NOT NULL,
        trees INT NOT NULL DEFAULT 1,
        photo VARCHAR(120) NOT NULL,
        lat DECIMAL(10,7) NULL,
        lng DECIMAL(10,7) NULL,
        note VARCHAR(300) NULL,
        confirms INT NOT NULL DEFAULT 0,
        status VARCHAR(12) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL,
        KEY idx_campaign (campaign_id),
        KEY idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    db()->exec("CREATE TABLE IF NOT EXISTS tree_confirms (
        planting_id INT NOT NULL,
        user_id INT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (planting_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

/** Approved trees planted so far for a campaign. */
function campaign_planted(int $campaignId): int
{
    $st = db()->prepare("SELECT COALESCE(SUM(trees),0) t FROM tree_plantings
        WHERE campaign_id = ? AND status = 'approved'");
    $st->execute([$campaignId]);
    return (int) $st->fetch()['t'];
}

/** Total approved trees across the whole platform. */
function trees_total(): int
{
    try {
        tree_tables();
        return (int) db()->query("SELECT COALESCE(SUM(trees),0) t FROM tree_plantings WHERE status='approved'")
            ->fetch()['t'];
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Register one confirmation. Approves the planting once it reaches
 * TREE_CONFIRMS_NEEDED confirmations, or immediately when the confirmer is
 * the campaign creator, a zone admin or the super admin.
 * Returns true when the planting became approved.
 */
function tree_confirm(array $planting, array $confirmer): bool
{
    $ins = db()->prepare('INSERT IGNORE INTO tree_confirms (planting_id, user_id, created_at) VALUES (?,?,NOW())');
    $ins->execute([(int) $planting['id'], (int) $confirmer['id']]);
    if ($ins->rowCount() === 0 || $planting['status'] === 'approved') {
        return false;
    }
    db()->prepare('UPDATE tree_plantings SET confirms = confirms + 1 WHERE id = ?')->execute([(int) $planting['id']]);
    award_points_custom((int) $confirmer['id'], POINTS['tree_confirm_given'], 'tree_confirm_given', null);

    $st = db()->prepare('SELECT creator_id FROM tree_campaigns WHERE id = ?');
    $st->execute([(int) $planting['campaign_id']]);
    $creatorId = (int) $st->fetch()['creator_id'];
    $authoritative = (int) $confirmer['id'] === $creatorId
        || in_array($confirmer['role'], ['admin', 'superadmin'], true);

    $st = db()->prepare('SELECT confirms FROM tree_plantings WHERE id = ?');
    $st->execute([(int) $planting['id']]);
    $confirms = (int) $st->fetch()['confirms'];

    if ($confirms >= TREE_CONFIRMS_NEEDED || $authoritative) {
        db()->prepare("UPDATE tree_plantings SET status = 'approved' WHERE id = ? AND status = 'pending'")
            ->execute([(int) $planting['id']]);
        $pts = min(3 * (int) $planting['trees'], 30);
        award_points_custom((int) $planting['user_id'], $pts, 'tree_planted', null);
        notify((int) $planting['user_id'], 'tree_approved', null);
        return true;
    }
    return false;
}
