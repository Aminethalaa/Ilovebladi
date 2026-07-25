<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/associations.php';
if (!module_on('associations')) {
    redirect('index.php');
}

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare("SELECT u.*, cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
        w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr
    FROM users u
    JOIN communes cm ON cm.id = u.commune_id
    JOIN wilayas w ON w.id = u.wilaya_id
    WHERE u.id = ? AND u.role = 'association' AND u.is_verified = 1 AND u.is_blocked = 0");
$st->execute([$id]);
$a = $st->fetch();

if (!$a) {
    http_response_code(404);
    $page_title = t('not_found');
    require __DIR__ . '/includes/layout/header.php';
    echo '<div class="container page"><p class="empty">😕 ' . e(t('not_found'))
        . '</p><p class="center"><a class="btn btn-ghost" href="' . e(url('associations.php')) . '">← ' . e(t('dir_title')) . '</a></p></div>';
    require __DIR__ . '/includes/layout/footer.php';
    exit;
}

$profile = assoc_profile($id);
$cats = assoc_categories_of($id);
$stats = assoc_stats($id);
$badges = user_badges($id);

$fixes = db()->prepare("SELECT x.id, x.title, x.status, x.photo, x.after_photo, x.resolved_at,
        cat.icon, cm.name_ar AS commune_ar, cm.name_fr AS commune_fr
    FROM complaints x
    JOIN categories cat ON cat.id = x.category_id
    JOIN communes cm ON cm.id = x.commune_id
    WHERE x.handler_id = ? AND x.status IN ('resolved','closed')
    ORDER BY x.resolved_at DESC LIMIT 6");
$fixes->execute([$id]);
$fixList = $fixes->fetchAll();

$campaigns = [];
try {
    $cp = db()->prepare("SELECT tc.id, tc.title, tc.goal, tc.status,
            (SELECT COALESCE(SUM(tp.trees),0) FROM tree_plantings tp
             WHERE tp.campaign_id = tc.id AND tp.status='approved') AS planted
        FROM tree_campaigns tc WHERE tc.creator_id = ? ORDER BY tc.id DESC LIMIT 6");
    $cp->execute([$id]);
    $campaigns = $cp->fetchAll();
} catch (Throwable $e) {
    // tree tables absent
}

$page_title = $a['name'];
$og = [
    'title' => '🤝 ' . $a['name'],
    'description' => mb_strimwidth((string) $a['about'], 0, 180, '…'),
    'url' => abs_url('association.php?id=' . $id),
];
if (!empty($profile['logo'])) {
    $og['image'] = abs_url('uploads/' . rawurlencode($profile['logo']));
}
$shareUrl = $og['url'];
$shareText = $a['name'];
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container page">
  <p><a class="muted" href="<?= e(url('associations.php')) ?>">← <?= e(t('dir_title')) ?></a></p>

  <div class="card card-pad assoc-hero">
    <?php if (!empty($profile['logo'])): ?>
    <img class="assoc-hero-logo" src="<?= e(upload_url($profile['logo'])) ?>" alt="">
    <?php else: ?>
    <span class="assoc-hero-logo assoc-logo-ph">🤝</span>
    <?php endif; ?>
    <div class="assoc-hero-main">
      <h1><?= e($a['name']) ?></h1>
      <p class="muted">📍 <?= e(lc($a, 'commune')) ?> — <?= e(lc($a, 'wilaya')) ?>
        <?php if (!empty($profile['founded_year'])): ?> · <?= e(t('assoc_since')) ?> <?= (int) $profile['founded_year'] ?><?php endif; ?>
      </p>
      <?php if ($cats): ?>
      <div class="assoc-cats">
        <?php foreach ($cats as $c): ?>
        <span class="chip"><?= e($c['icon']) ?> <?= e(lc($c, 'name')) ?></span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php require __DIR__ . '/includes/layout/share.php'; ?>
    </div>
  </div>

  <div class="stat-row">
    <div class="stat-card stat-green"><span class="stat-num"><?= (int) $stats['fixes'] ?></span><span class="stat-label"><?= e(t('lb_fixed')) ?></span></div>
    <?php if (module_on('trees')): ?>
    <div class="stat-card"><span class="stat-num"><?= (int) $stats['trees'] ?></span><span class="stat-label"><?= e(t('trees_planted')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= (int) $stats['campaigns'] ?></span><span class="stat-label"><?= e(t('dir_campaigns')) ?></span></div>
    <?php endif; ?>
    <div class="stat-card stat-gold"><span class="stat-num"><?= (int) $a['points'] ?></span><span class="stat-label"><?= e(t('lb_points')) ?></span></div>
  </div>

  <div class="detail-grid">
    <div>
      <?php if (!empty($a['about'])): ?>
      <div class="card card-pad">
        <h2><?= e(t('dir_about')) ?></h2>
        <p class="pre"><?= nl2br(e($a['about'])) ?></p>
      </div>
      <?php endif; ?>

      <?php if ($fixList): ?>
      <div class="card card-pad">
        <h2>🛠️ <?= e(t('dir_recent_fixes')) ?></h2>
        <div class="grid-3">
          <?php foreach ($fixList as $f): ?>
          <a class="card c-card" href="<?= e(url('complaint.php?id=' . (int) $f['id'])) ?>">
            <div class="c-card-img"><img loading="lazy" src="<?= e(upload_url($f['after_photo'] ?: $f['photo'])) ?>" alt=""><?= status_badge($f['status']) ?></div>
            <div class="card-body"><h3><?= e($f['icon']) ?> <?= e($f['title']) ?></h3>
              <p class="muted">📍 <?= e(lc($f, 'commune')) ?></p></div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($campaigns): ?>
      <div class="card card-pad">
        <h2>🌳 <?= e(t('dir_campaigns')) ?></h2>
        <div class="list">
          <?php foreach ($campaigns as $c): $pct = min(100, (int) round($c['planted'] * 100 / max(1, (int) $c['goal']))); ?>
          <a class="list-item" href="<?= e(url('tree-campaign.php?id=' . (int) $c['id'])) ?>">
            <div class="list-main">
              <strong><?= e($c['title']) ?></strong>
              <div class="tree-progress"><div class="tree-progress-fill" style="width:<?= $pct ?>%"></div></div>
              <span class="muted"><?= (int) $c['planted'] ?> / <?= (int) $c['goal'] ?> 🌳</span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <aside>
      <div class="card card-pad">
        <h3>📞 <?= e(t('dir_contact')) ?></h3>
        <ul class="contact-list">
          <li>✉️ <a href="mailto:<?= e($a['email']) ?>"><?= e($a['email']) ?></a></li>
          <?php if (!empty($profile['phone'])): ?><li>📞 <a href="tel:<?= e($profile['phone']) ?>"><?= e($profile['phone']) ?></a></li><?php endif; ?>
          <?php if (!empty($profile['website'])): ?><li>🌐 <a href="<?= e($profile['website']) ?>" rel="noopener" target="_blank"><?= e($profile['website']) ?></a></li><?php endif; ?>
          <?php if (!empty($profile['facebook'])): ?><li>📘 <a href="<?= e($profile['facebook']) ?>" rel="noopener" target="_blank">Facebook</a></li><?php endif; ?>
          <?php if (!empty($profile['address'])): ?><li>📍 <?= e($profile['address']) ?></li><?php endif; ?>
        </ul>
      </div>

      <?php if ($badges): ?>
      <div class="card card-pad">
        <h3>🎖️ <?= e(t('my_badges')) ?></h3>
        <div class="badge-row">
          <?php foreach ($badges as $b): ?>
          <span class="badge-item" title="<?= e(lc($b, 'desc')) ?>"><span class="badge-ico"><?= e($b['icon']) ?></span><?= e(lc($b, 'name')) ?></span>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </aside>
  </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
