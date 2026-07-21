<?php
require_once __DIR__ . '/includes/auth.php';

$db = db();
$S = $db->query("SELECT
    (SELECT COUNT(*) FROM complaints WHERE status NOT IN ('pending','rejected')) AS total,
    (SELECT COUNT(*) FROM complaints WHERE status IN ('resolved','closed')) AS resolved,
    (SELECT COUNT(*) FROM complaints WHERE status = 'in_progress') AS in_progress,
    (SELECT COUNT(DISTINCT commune_id) FROM complaints WHERE status NOT IN ('pending','rejected')) AS communes,
    (SELECT COUNT(*) FROM users WHERE role = 'citizen') AS citizens,
    (SELECT COUNT(*) FROM users WHERE role = 'association' AND is_verified = 1) AS assocs")->fetch();
$rate = $S['total'] > 0 ? (int) round($S['resolved'] * 100 / $S['total']) : 0;

$cats = $db->query("SELECT c.id, c.name_ar, c.name_fr, c.icon, COUNT(x.id) AS n
    FROM categories c
    LEFT JOIN complaints x ON x.category_id = c.id AND x.status NOT IN ('pending','rejected')
    WHERE c.is_active = 1
    GROUP BY c.id, c.name_ar, c.name_fr, c.icon ORDER BY n DESC")->fetchAll();
$maxCat = 1;
foreach ($cats as $c) {
    $maxCat = max($maxCat, (int) $c['n']);
}

$topCommunes = $db->query("SELECT cm.name_ar, cm.name_fr, w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr,
        COUNT(*) AS published,
        SUM(x.status IN ('resolved','closed')) AS resolved,
        AVG(CASE WHEN x.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, x.published_at, x.resolved_at)/24 END) AS avg_days
    FROM complaints x
    JOIN communes cm ON cm.id = x.commune_id
    JOIN wilayas w ON w.id = x.wilaya_id
    WHERE x.status NOT IN ('pending','rejected')
    GROUP BY x.commune_id, x.wilaya_id, cm.name_ar, cm.name_fr, w.name_ar, w.name_fr
    HAVING SUM(x.status IN ('resolved','closed')) > 0
    ORDER BY (SUM(x.status IN ('resolved','closed'))/COUNT(*)) DESC,
             SUM(x.status IN ('resolved','closed')) DESC
    LIMIT 5")->fetchAll();

$recentFixed = $db->query("SELECT x.*, cat.icon, cat.name_ar AS cat_ar, cat.name_fr AS cat_fr,
        cm.name_ar AS commune_ar, cm.name_fr AS commune_fr
    FROM complaints x
    JOIN categories cat ON cat.id = x.category_id
    JOIN communes cm ON cm.id = x.commune_id
    WHERE x.status IN ('resolved','closed') AND x.after_photo IS NOT NULL
    ORDER BY x.resolved_at DESC LIMIT 3")->fetchAll();

$page_title = t('home_title');
require __DIR__ . '/includes/layout/header.php';
?>
<section class="hero">
  <div class="container hero-inner">
    <div class="hero-text">
      <h1><?= e(t('hero_h1')) ?></h1>
      <p class="hero-sub"><?= e(t('hero_sub')) ?></p>
      <div class="hero-cta">
        <a class="btn btn-primary btn-lg" href="<?= e(url($me ? 'dashboard/new-complaint.php' : 'register.php')) ?>">📣 <?= e(t('hero_cta_report')) ?></a>
        <a class="btn btn-ghost btn-lg" href="<?= e(url('complaints.php')) ?>">🗺️ <?= e(t('hero_cta_browse')) ?></a>
      </div>
    </div>
    <div class="hero-stats">
      <div class="stat-card"><span class="stat-num" data-count="<?= (int) $S['total'] ?>">0</span><span class="stat-label"><?= e(t('stat_total')) ?></span></div>
      <div class="stat-card stat-green"><span class="stat-num" data-count="<?= (int) $S['resolved'] ?>">0</span><span class="stat-label"><?= e(t('stat_resolved')) ?></span></div>
      <div class="stat-card"><span class="stat-num" data-count="<?= $rate ?>">0</span><span class="stat-suffix">%</span><span class="stat-label"><?= e(t('stat_rate')) ?></span></div>
      <div class="stat-card"><span class="stat-num" data-count="<?= (int) $S['in_progress'] ?>">0</span><span class="stat-label"><?= e(t('stat_in_progress')) ?></span></div>
      <div class="stat-card"><span class="stat-num" data-count="<?= (int) $S['communes'] ?>">0</span><span class="stat-label"><?= e(t('stat_communes')) ?></span></div>
      <div class="stat-card"><span class="stat-num" data-count="<?= (int) $S['citizens'] + (int) $S['assocs'] ?>">0</span><span class="stat-label"><?= e(t('stat_members')) ?></span></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <h2 class="section-title"><?= e(t('home_by_category')) ?></h2>
    <div class="cat-bars">
      <?php foreach ($cats as $c): ?>
      <div class="cat-bar">
        <span class="cat-bar-label"><?= e($c['icon']) ?> <?= e(lc($c, 'name')) ?></span>
        <div class="cat-bar-track"><div class="cat-bar-fill" style="width:<?= (int) round($c['n'] * 100 / $maxCat) ?>%"></div></div>
        <span class="cat-bar-num"><?= (int) $c['n'] ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-alt">
  <div class="container">
    <h2 class="section-title"><?= e(t('home_how_title')) ?></h2>
    <div class="steps">
      <div class="step"><span class="step-icon">📱</span><h3><?= e(t('how_1_t')) ?></h3><p><?= e(t('how_1_d')) ?></p></div>
      <div class="step"><span class="step-icon">📍</span><h3><?= e(t('how_2_t')) ?></h3><p><?= e(t('how_2_d')) ?></p></div>
      <div class="step"><span class="step-icon">🛠️</span><h3><?= e(t('how_3_t')) ?></h3><p><?= e(t('how_3_d')) ?></p></div>
      <div class="step"><span class="step-icon">🏆</span><h3><?= e(t('how_4_t')) ?></h3><p><?= e(t('how_4_d')) ?></p></div>
    </div>
  </div>
</section>

<?php if ($topCommunes): ?>
<section class="section">
  <div class="container">
    <h2 class="section-title">🏅 <?= e(t('home_top_communes')) ?></h2>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th><?= e(t('lb_commune')) ?></th><th><?= e(t('lb_resolved')) ?></th><th><?= e(t('lb_score')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($topCommunes as $i => $tc):
          $score = commune_score((int) $tc['published'], (int) $tc['resolved'], $tc['avg_days'] !== null ? (float) $tc['avg_days'] : null); ?>
        <tr>
          <td class="rank"><?= ['🥇', '🥈', '🥉'][$i] ?? $i + 1 ?></td>
          <td><strong><?= e(lc($tc, 'name')) ?></strong> <span class="muted">— <?= e(lc($tc, 'wilaya')) ?></span></td>
          <td><?= (int) $tc['resolved'] ?>/<?= (int) $tc['published'] ?></td>
          <td><span class="score-pill"><?= $score ?>/100</span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="center"><a class="btn btn-ghost" href="<?= e(url('leaderboard.php')) ?>"><?= e(t('home_full_leaderboard')) ?></a></p>
  </div>
</section>
<?php endif; ?>

<?php if ($recentFixed): ?>
<section class="section section-alt">
  <div class="container">
    <h2 class="section-title">✅ <?= e(t('home_recent_fixed')) ?></h2>
    <div class="grid-3">
      <?php foreach ($recentFixed as $r): ?>
      <a class="card fix-card" href="<?= e(url('complaint.php?id=' . (int) $r['id'])) ?>">
        <div class="before-after">
          <figure><img loading="lazy" src="<?= e(upload_url($r['photo'])) ?>" alt=""><figcaption><?= e(t('before')) ?></figcaption></figure>
          <figure><img loading="lazy" src="<?= e(upload_url($r['after_photo'])) ?>" alt=""><figcaption class="cap-after"><?= e(t('after')) ?></figcaption></figure>
        </div>
        <div class="card-body">
          <span class="chip"><?= e($r['icon']) ?> <?= e(lc($r, 'cat')) ?></span>
          <h3><?= e($r['title']) ?></h3>
          <p class="muted">📍 <?= e(lc($r, 'commune')) ?> · <?= e(time_ago($r['resolved_at'])) ?></p>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="cta-band">
  <div class="container center">
    <h2><?= e(t('home_cta_title')) ?></h2>
    <p><?= e(t('home_cta_sub')) ?></p>
    <a class="btn btn-light btn-lg" href="<?= e(url('register.php')) ?>"><?= e(t('nav_register')) ?></a>
  </div>
</section>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
