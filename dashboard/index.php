<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('citizen', 'association');

$st = db()->prepare("SELECT
    COUNT(*) AS total,
    SUM(status = 'pending') AS pending,
    SUM(status IN ('published','in_progress')) AS open_,
    SUM(status IN ('resolved','closed')) AS fixed
    FROM complaints WHERE user_id = ?");
$st->execute([(int) $me['id']]);
$my = $st->fetch();

$st = db()->prepare("SELECT x.*, cat.icon, cat.name_ar AS cat_ar, cat.name_fr AS cat_fr
    FROM complaints x JOIN categories cat ON cat.id = x.category_id
    WHERE x.user_id = ? ORDER BY x.id DESC LIMIT 30");
$st->execute([(int) $me['id']]);
$complaints = $st->fetchAll();

$isAssoc = $me['role'] === 'association';
$taken = [];
if ($isAssoc) {
    $st = db()->prepare("SELECT x.*, cat.icon, cat.name_ar AS cat_ar, cat.name_fr AS cat_fr,
            cm.name_ar AS commune_ar, cm.name_fr AS commune_fr
        FROM complaints x
        JOIN categories cat ON cat.id = x.category_id
        JOIN communes cm ON cm.id = x.commune_id
        WHERE x.handler_id = ? ORDER BY FIELD(x.status,'in_progress','resolved','closed'), x.id DESC LIMIT 30");
    $st->execute([(int) $me['id']]);
    $taken = $st->fetchAll();
}

$lvl = level_for((int) $me['points']);
$next = next_level((int) $me['points']);
$badges = user_badges((int) $me['id']);

$page_title = t('nav_dashboard');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page">
  <div class="page-head">
    <div>
      <h1><?= e(t('dash_hello', $me['name'])) ?> 👋</h1>
      <p class="muted">📍 <?= e(lc($me, 'commune')) ?> — <?= e(lc($me, 'wilaya')) ?>
        <?php if ($isAssoc): ?><span class="chip chip-sm">🤝 <?= e(t('role_association')) ?></span><?php endif; ?>
      </p>
    </div>
    <a class="btn btn-primary btn-lg" href="<?= e(url('dashboard/new-complaint.php')) ?>">📣 <?= e(t('new_complaint')) ?></a>
  </div>

  <?php if ($isAssoc && (int) $me['is_verified'] === 0): ?>
  <div class="flash flash-warn">⏳ <?= e(t('assoc_pending_banner')) ?></div>
  <?php endif; ?>

  <?php require BASE_PATH . '/includes/layout/push-card.php'; ?>

  <div class="stat-row">
    <div class="stat-card"><span class="stat-num"><?= (int) $my['total'] ?></span><span class="stat-label"><?= e(t('dash_my_total')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= (int) $my['open_'] ?></span><span class="stat-label"><?= e(t('dash_my_open')) ?></span></div>
    <div class="stat-card stat-green"><span class="stat-num"><?= (int) $my['fixed'] ?></span><span class="stat-label"><?= e(t('dash_my_fixed')) ?></span></div>
    <div class="stat-card stat-gold">
      <span class="stat-num"><?= (int) $me['points'] ?></span>
      <span class="stat-label"><?= e(t('lb_points')) ?> · <?= $lvl[2] ?> <?= e(t($lvl[1])) ?></span>
      <?php if ($next): ?>
      <div class="lvl-track"><div class="lvl-fill" style="width:<?= min(100, (int) round($me['points'] * 100 / $next[0])) ?>%"></div></div>
      <span class="lvl-hint"><?= e(t('dash_next_level', $next[0] - (int) $me['points'], t($next[1]))) ?></span>
      <?php endif; ?>
    </div>
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

  <?php if ($isAssoc && (int) $me['is_verified'] === 1): ?>
  <section class="dash-section">
    <div class="section-head">
      <h2>🛠️ <?= e(t('assoc_taken_title')) ?></h2>
      <a class="btn btn-ghost" href="<?= e(url('complaints.php?wilaya=' . (int) $me['wilaya_id'] . '&status=published')) ?>"><?= e(t('assoc_browse_open')) ?></a>
    </div>
    <?php if ($taken): ?>
    <div class="list">
      <?php foreach ($taken as $r): ?>
      <a class="list-item" href="<?= e(url('complaint.php?id=' . (int) $r['id'])) ?>">
        <img class="thumb" src="<?= e(upload_url($r['photo'])) ?>" alt="">
        <div class="list-main">
          <strong><?= e($r['title']) ?></strong>
          <span class="muted"><?= e($r['icon']) ?> <?= e(lc($r, 'cat')) ?> · 📍 <?= e(lc($r, 'commune')) ?></span>
        </div>
        <?= status_badge($r['status']) ?>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="empty">🤝 <?= e(t('assoc_none_taken')) ?></p>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <section class="dash-section">
    <h2>📋 <?= e(t('dash_my_complaints')) ?></h2>
    <?php if ($complaints): ?>
    <div class="list">
      <?php foreach ($complaints as $r): ?>
      <a class="list-item" href="<?= e(url('complaint.php?id=' . (int) $r['id'])) ?>">
        <img class="thumb" src="<?= e(upload_url($r['photo'])) ?>" alt="">
        <div class="list-main">
          <strong><?= e($r['title']) ?></strong>
          <span class="muted">#<?= e($r['ref']) ?> · <?= e($r['icon']) ?> <?= e(lc($r, 'cat')) ?> · <?= e(time_ago($r['created_at'])) ?></span>
        </div>
        <span class="list-side">👍 <?= (int) $r['upvotes'] ?> <?= status_badge($r['status']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="empty">📣 <?= e(t('dash_no_complaints')) ?></p>
    <?php endif; ?>
  </section>

  <p><a class="muted" href="<?= e(url('dashboard/profile.php')) ?>">⚙️ <?= e(t('nav_profile')) ?></a></p>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
