<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/trees.php';
if (!module_on('trees')) {
    redirect('index.php');
}
tree_tables();

$me = current_user();
$canCreate = $me && ($me['role'] === 'admin' || $me['role'] === 'superadmin'
    || ($me['role'] === 'association' && (int) $me['is_verified'] === 1));

$totals = db()->query("SELECT
    (SELECT COALESCE(SUM(trees),0) FROM tree_plantings WHERE status='approved') AS trees,
    (SELECT COUNT(*) FROM tree_campaigns WHERE status='active') AS active_campaigns,
    (SELECT COUNT(DISTINCT user_id) FROM tree_plantings WHERE status='approved') AS planters")->fetch();

$campaigns = db()->query("SELECT tc.*, u.name AS creator_name, u.role AS creator_role,
        cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
        w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr,
        (SELECT COALESCE(SUM(tp.trees),0) FROM tree_plantings tp
         WHERE tp.campaign_id = tc.id AND tp.status='approved') AS planted,
        (SELECT COUNT(DISTINCT tp.user_id) FROM tree_plantings tp WHERE tp.campaign_id = tc.id) AS participants
    FROM tree_campaigns tc
    JOIN users u ON u.id = tc.creator_id
    JOIN communes cm ON cm.id = tc.commune_id
    JOIN wilayas w ON w.id = tc.wilaya_id
    ORDER BY (tc.status = 'active') DESC, tc.id DESC LIMIT 60")->fetchAll();

$page_title = t('trees_title');
require __DIR__ . '/includes/layout/header.php';
?>
<section class="hero hero-trees">
  <div class="container hero-inner">
    <div class="hero-text">
      <h1>🌳 <?= e(t('trees_hero_h1')) ?></h1>
      <p class="hero-sub"><?= e(t('trees_hero_sub')) ?></p>
      <?php if ($canCreate): ?>
      <a class="btn btn-light btn-lg" href="<?= e(url('trees-new.php')) ?>">➕ <?= e(t('trees_new_campaign')) ?></a>
      <?php endif; ?>
    </div>
    <div class="hero-stats">
      <div class="stat-card"><span class="stat-num" data-count="<?= (int) $totals['trees'] ?>">0</span><span class="stat-label"><?= e(t('trees_planted')) ?></span></div>
      <div class="stat-card"><span class="stat-num" data-count="<?= (int) $totals['active_campaigns'] ?>">0</span><span class="stat-label"><?= e(t('trees_active_campaigns')) ?></span></div>
      <div class="stat-card"><span class="stat-num" data-count="<?= (int) $totals['planters'] ?>">0</span><span class="stat-label"><?= e(t('trees_planters')) ?></span></div>
    </div>
  </div>
</section>

<div class="container page">
  <?php if (!$campaigns): ?>
  <p class="empty">🌱 <?= e(t('trees_no_campaigns')) ?></p>
  <?php endif; ?>
  <div class="grid-3">
    <?php foreach ($campaigns as $tc):
        $pct = min(100, (int) round($tc['planted'] * 100 / max(1, (int) $tc['goal']))); ?>
    <a class="card c-card tree-card" href="<?= e(url('tree-campaign.php?id=' . (int) $tc['id'])) ?>">
      <div class="card-body">
        <p>
          <?php if ($tc['status'] === 'active'): ?><span class="badge st-resolved"><?= e(t('trees_status_active')) ?></span>
          <?php else: ?><span class="badge st-closed"><?= e(t('trees_status_done')) ?></span><?php endif; ?>
          <?php if ($tc['ends_at']): ?><span class="muted"> · ⏳ <?= e($tc['ends_at']) ?></span><?php endif; ?>
        </p>
        <h3><?= e($tc['title']) ?></h3>
        <p class="muted">📍 <?= e(lc($tc, 'commune')) ?> — <?= e(lc($tc, 'wilaya')) ?></p>
        <p class="muted"><?= e(t('trees_by')) ?> <strong><?= e($tc['creator_name']) ?></strong>
          <span class="chip chip-sm"><?= e(t('role_' . ($tc['creator_role'] === 'association' ? 'association' : 'admin'))) ?></span></p>
        <div class="tree-progress">
          <div class="tree-progress-fill" style="width:<?= $pct ?>%"></div>
        </div>
        <p class="tree-progress-nums"><strong><?= (int) $tc['planted'] ?></strong> / <?= (int) $tc['goal'] ?> 🌳
          <span class="muted">· 👥 <?= (int) $tc['participants'] ?></span></p>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
