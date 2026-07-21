<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('admin');

$communeId = (int) $me['commune_id'];
$st = db()->prepare("SELECT
    SUM(status = 'pending') AS pending,
    SUM(status = 'published') AS published,
    SUM(status = 'in_progress') AS in_progress,
    SUM(status = 'resolved') AS resolved,
    SUM(status = 'closed') AS closed,
    SUM(status NOT IN ('pending','rejected')) AS total_pub,
    SUM(status IN ('resolved','closed')) AS total_fixed,
    AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, published_at, resolved_at)/24 END) AS avg_days
    FROM complaints WHERE commune_id = ?");
$st->execute([$communeId]);
$S = $st->fetch();
$score = commune_score((int) $S['total_pub'], (int) $S['total_fixed'], $S['avg_days'] !== null ? (float) $S['avg_days'] : null);

$tab = $_GET['tab'] ?? 'pending';
if (!in_array($tab, ['pending', 'published', 'in_progress', 'resolved'], true)) {
    $tab = 'pending';
}
$st = db()->prepare("SELECT x.*, cat.icon, cat.name_ar AS cat_ar, cat.name_fr AS cat_fr, u.name AS reporter
    FROM complaints x
    JOIN categories cat ON cat.id = x.category_id
    JOIN users u ON u.id = x.user_id
    WHERE x.commune_id = ? AND x.status = ?
    ORDER BY x.upvotes DESC, x.id ASC LIMIT 100");
$st->execute([$communeId, $tab]);
$rows = $st->fetchAll();
$badges = user_badges((int) $me['id']);

$page_title = t('admin_title');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page">
  <div class="page-head">
    <div>
      <h1>🏛️ <?= e(t('admin_title')) ?></h1>
      <p class="muted">📍 <?= e(lc($me, 'commune')) ?> — <?= e(lc($me, 'wilaya')) ?></p>
    </div>
    <div class="admin-head-side">
      <div class="score-big" title="<?= e(t('lb_score')) ?>"><?= $score ?><span>/100</span></div>
      <a class="btn btn-primary" href="<?= e(url('admin/notify.php')) ?>">📣 <?= e(t('notify_page_title')) ?></a>
    </div>
  </div>

  <?php require BASE_PATH . '/includes/layout/push-card.php'; ?>

  <div class="stat-row">
    <div class="stat-card stat-warn"><span class="stat-num"><?= (int) $S['pending'] ?></span><span class="stat-label"><?= e(t('st_pending')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= (int) $S['published'] ?></span><span class="stat-label"><?= e(t('st_published')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= (int) $S['in_progress'] ?></span><span class="stat-label"><?= e(t('st_in_progress')) ?></span></div>
    <div class="stat-card stat-green"><span class="stat-num"><?= (int) $S['total_fixed'] ?></span><span class="stat-label"><?= e(t('stat_resolved')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= $S['avg_days'] !== null ? number_format((float) $S['avg_days'], 1) : '—' ?></span><span class="stat-label"><?= e(t('lb_avg_days')) ?></span></div>
  </div>

  <?php if ($badges): ?>
  <div class="card card-pad badge-strip">
    <h3>🎖️ <?= e(t('my_badges')) ?></h3>
    <div class="badge-row">
      <?php foreach ($badges as $b): ?>
      <span class="badge-item" title="<?= e(lc($b, 'desc')) ?>"><span class="badge-ico"><?= e($b['icon']) ?></span><?= e(lc($b, 'name')) ?></span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <nav class="tabs">
    <a class="<?= $tab === 'pending' ? 'on' : '' ?>" href="?tab=pending">⏳ <?= e(t('st_pending')) ?> (<?= (int) $S['pending'] ?>)</a>
    <a class="<?= $tab === 'published' ? 'on' : '' ?>" href="?tab=published">📢 <?= e(t('st_published')) ?> (<?= (int) $S['published'] ?>)</a>
    <a class="<?= $tab === 'in_progress' ? 'on' : '' ?>" href="?tab=in_progress">🚧 <?= e(t('st_in_progress')) ?> (<?= (int) $S['in_progress'] ?>)</a>
    <a class="<?= $tab === 'resolved' ? 'on' : '' ?>" href="?tab=resolved">✅ <?= e(t('st_resolved')) ?> (<?= (int) $S['resolved'] ?>)</a>
  </nav>

  <?php if ($rows): ?>
  <div class="list">
    <?php foreach ($rows as $r): ?>
    <a class="list-item" href="<?= e(url('admin/complaint.php?id=' . (int) $r['id'])) ?>">
      <img class="thumb" src="<?= e(upload_url($r['photo'])) ?>" alt="">
      <div class="list-main">
        <strong><?= e($r['title']) ?></strong>
        <span class="muted">#<?= e($r['ref']) ?> · <?= e($r['icon']) ?> <?= e(lc($r, 'cat')) ?> · <?= e($r['reporter']) ?> · <?= e(time_ago($r['created_at'])) ?></span>
      </div>
      <span class="list-side">👍 <?= (int) $r['upvotes'] ?><?php if ((int) $r['reopened'] === 1): ?> <span class="badge st-rejected"><?= e(t('reopened_flag')) ?></span><?php endif; ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="empty">🎉 <?= e(t('admin_queue_empty')) ?></p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
