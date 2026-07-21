<?php
/**
 * Association directory: extended profiles + activity categories.
 * Tables auto-create (and category list auto-seeds) on first use, so the
 * feature works on any existing database with no manual migration.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/** Default activity domains, seeded when the category table is empty. */
function assoc_default_categories(): array
{
    return [
        ['🌿', 'البيئة والنظافة', 'Environnement et propreté'],
        ['🤝', 'التضامن والعمل الاجتماعي', 'Solidarité et action sociale'],
        ['🎭', 'الثقافة والفنون', 'Culture et arts'],
        ['⚽', 'الرياضة', 'Sport'],
        ['🏥', 'الصحة', 'Santé'],
        ['📚', 'التربية والتعليم', 'Éducation'],
        ['🧒', 'الطفولة والشباب', 'Enfance et jeunesse'],
        ['♿', 'ذوو الاحتياجات الخاصة', 'Personnes à besoins spécifiques'],
        ['🏘️', 'التنمية المحلية', 'Développement local'],
        ['🕌', 'الأعمال الخيرية', 'Œuvres caritatives'],
        ['👵', 'كبار السن', 'Personnes âgées'],
        ['🐾', 'الرفق بالحيوان', 'Protection animale'],
    ];
}

function assoc_tables(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS assoc_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        icon VARCHAR(16) NOT NULL DEFAULT '🏷️',
        name_ar VARCHAR(80) NOT NULL,
        name_fr VARCHAR(80) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS assoc_profiles (
        user_id INT PRIMARY KEY,
        logo VARCHAR(120) NULL,
        phone VARCHAR(40) NULL,
        website VARCHAR(190) NULL,
        facebook VARCHAR(190) NULL,
        address VARCHAR(190) NULL,
        founded_year SMALLINT NULL,
        updated_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    db()->exec("CREATE TABLE IF NOT EXISTS user_assoc_categories (
        user_id INT NOT NULL,
        category_id INT NOT NULL,
        PRIMARY KEY (user_id, category_id),
        KEY idx_cat (category_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $n = (int) db()->query('SELECT COUNT(*) c FROM assoc_categories')->fetch()['c'];
    if ($n === 0) {
        $ins = db()->prepare('INSERT INTO assoc_categories (icon, name_ar, name_fr, sort) VALUES (?,?,?,?)');
        foreach (assoc_default_categories() as $i => $cat) {
            $ins->execute([$cat[0], $cat[1], $cat[2], $i + 1]);
        }
    }
}

function assoc_all_categories(bool $activeOnly = true): array
{
    try {
        assoc_tables();
    } catch (Throwable $e) {
        return [];
    }
    $sql = 'SELECT * FROM assoc_categories';
    if ($activeOnly) {
        $sql .= ' WHERE is_active = 1';
    }
    return db()->query($sql . ' ORDER BY sort, id')->fetchAll();
}

function assoc_profile(int $userId): array
{
    try {
        assoc_tables();
        $st = db()->prepare('SELECT * FROM assoc_profiles WHERE user_id = ?');
        $st->execute([$userId]);
        return $st->fetch() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** Category ids (array of int) an association belongs to. */
function assoc_category_ids(int $userId): array
{
    try {
        assoc_tables();
        $st = db()->prepare('SELECT category_id FROM user_assoc_categories WHERE user_id = ?');
        $st->execute([$userId]);
        return array_map('intval', array_column($st->fetchAll(), 'category_id'));
    } catch (Throwable $e) {
        return [];
    }
}

/** Full category rows (icon + names) for one association. */
function assoc_categories_of(int $userId): array
{
    $ids = assoc_category_ids($userId);
    if (!$ids) {
        return [];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT * FROM assoc_categories WHERE id IN ($in) ORDER BY sort, id");
    $st->execute($ids);
    return $st->fetchAll();
}

/** Public activity stats for an association. */
function assoc_stats(int $userId): array
{
    $fix = db()->prepare("SELECT COUNT(*) c FROM complaints WHERE handler_id = ? AND status IN ('resolved','closed')");
    $fix->execute([$userId]);
    $stats = ['fixes' => (int) $fix->fetch()['c'], 'campaigns' => 0, 'trees' => 0];
    try {
        $cp = db()->prepare('SELECT COUNT(*) c FROM tree_campaigns WHERE creator_id = ?');
        $cp->execute([$userId]);
        $stats['campaigns'] = (int) $cp->fetch()['c'];
        $tr = db()->prepare("SELECT COALESCE(SUM(tp.trees),0) t FROM tree_plantings tp
            JOIN tree_campaigns tc ON tc.id = tp.campaign_id
            WHERE tc.creator_id = ? AND tp.status = 'approved'");
        $tr->execute([$userId]);
        $stats['trees'] = (int) $tr->fetch()['t'];
    } catch (Throwable $e) {
        // tree tables absent
    }
    return $stats;
}
