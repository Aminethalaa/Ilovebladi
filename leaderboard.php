<?php
require_once __DIR__ . '/includes/auth.php';

$tab = $_GET['tab'] ?? 'communes';
if (!in_array($tab, ['communes', 'wilayas', 'citizens', 'associations'], true)) {
    $tab = 'communes';
}

$rows = [];
if ($tab === 'communes' || $tab === 'wilayas') {
    $group = $tab === 'communes'
        ? 'x.commune_id, x.wilaya_id, g.name_ar, g.name_fr, w.name_ar, w.name_fr'
        : 'x.wilaya_id, g.name_ar, g.name_fr';
    $nameJoin = $tab === 'communes'
        ? 'JOIN communes g ON g.id = x.commune_id JOIN wilayas w ON w.id = x.wilaya_id'
        : 'JOIN wilayas g ON g.id = x.wilaya_id';
    $extra = $tab === 'communes' ? ', w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr' : '';
    $rows = db()->query("SELECT g.name_ar, g.name_fr $extra,
            COUNT(*) AS published,
            SUM(x.status IN ('resolved','closed')) AS resolved,
            AVG(CASE WHEN x.resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, x.published_at, x.resolved_at)/24 END) AS avg_days
        FROM complaints x $nameJoin
        WHERE x.status NOT IN ('pending','rejected')
        GROUP BY $group
        ORDER BY (SUM(x.status IN ('resolved','closed'))/COUNT(*)) DESC,
                 SUM(x.status IN ('resolved','closed')) DESC
        LIMIT 50")->fetchAll();
    usort($rows, function ($a, $b) {
        $sa = commune_score((int) $a['published'], (int) $a['resolved'], $a['avg_days'] !== null ? (float) $a['avg_days'] : null);
        $sb = commune_score((int) $b['published'], (int) $b['resolved'], $b['avg_days'] !== null ? (float) $b['avg_days'] : null);
        return $sb <=> $sa;
    });
} elseif ($tab === 'citizens') {
    $rows = db()->query("SELECT u.name, u.points, cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
            (SELECT COUNT(*) FROM complaints WHERE user_id = u.id AND status NOT IN ('pending','rejected')) AS reports,
            (SELECT COUNT(*) FROM complaints WHERE user_id = u.id AND status IN ('resolved','closed')) AS fixed
        FROM users u LEFT JOIN communes cm ON cm.id = u.commune_id
        WHERE u.role = 'citizen' AND u.is_blocked = 0 AND u.points > 0
        ORDER BY u.points DESC LIMIT 50")->fetchAll();
} else {
    $rows = db()->query("SELECT u.id, u.name, u.points, cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
            (SELECT COUNT(*) FROM complaints WHERE handler_id = u.id AND status IN ('resolved','closed')) AS fixed
        FROM users u LEFT JOIN communes cm ON cm.id = u.commune_id
        WHERE u.role = 'association' AND u.is_verified = 1 AND u.is_blocked = 0
        ORDER BY u.points DESC LIMIT 50")->fetchAll();
}

$page_title = t('nav_leaderboard');
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container page">
  <div class="page-head">
    <h1>🏆 <?= e(t('lb_title')) ?></h1>
    <p class="muted"><?= e(t('lb_sub')) ?></p>
  </div>

  <nav class="tabs">
    <a class="<?= $tab === 'communes' ? 'on' : '' ?>" href="?tab=communes">🏛️ <?= e(t('lb_tab_communes')) ?></a>
    <a class="<?= $tab === 'wilayas' ? 'on' : '' ?>" href="?tab=wilayas">🗺️ <?= e(t('lb_tab_wilayas')) ?></a>
    <a class="<?= $tab === 'citizens' ? 'on' : '' ?>" href="?tab=citizens">🧑 <?= e(t('lb_tab_citizens')) ?></a>
    <a class="<?= $tab === 'associations' ? 'on' : '' ?>" href="?tab=associations">🤝 <?= e(t('lb_tab_assocs')) ?></a>
  </nav>

  <div class="table-wrap"><table class="table">
    <?php if ($tab === 'communes' || $tab === 'wilayas'): ?>
    <thead><tr><th>#</th><th><?= e($tab === 'communes' ? t('lb_commune') : t('lb_wilaya')) ?></th>
      <th><?= e(t('lb_reports')) ?></th><th><?= e(t('lb_resolved')) ?></th>
      <th><?= e(t('lb_avg_days')) ?></th><th><?= e(t('lb_score')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $i => $r):
          $score = commune_score((int) $r['published'], (int) $r['resolved'], $r['avg_days'] !== null ? (float) $r['avg_days'] : null); ?>
      <tr>
        <td class="rank"><?= ['🥇', '🥈', '🥉'][$i] ?? $i + 1 ?></td>
        <td><strong><?= e(lc($r, 'name')) ?></strong>
          <?php if ($tab === 'communes'): ?><span class="muted">— <?= e(lc($r, 'wilaya')) ?></span><?php endif; ?></td>
        <td><?= (int) $r['published'] ?></td>
        <td><?= (int) $r['resolved'] ?></td>
        <td><?= $r['avg_days'] !== null ? number_format((float) $r['avg_days'], 1) : '—' ?></td>
        <td><span class="score-pill"><?= $score ?>/100</span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <?php elseif ($tab === 'citizens'): ?>
    <thead><tr><th>#</th><th><?= e(t('lb_citizen')) ?></th><th><?= e(t('lb_level')) ?></th>
      <th><?= e(t('lb_reports')) ?></th><th><?= e(t('lb_fixed')) ?></th><th><?= e(t('lb_points')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $i => $r): $lvl = level_for((int) $r['points']); ?>
      <tr>
        <td class="rank"><?= ['🥇', '🥈', '🥉'][$i] ?? $i + 1 ?></td>
        <td><strong><?= e($r['name']) ?></strong> <span class="muted">— <?= e(lc($r, 'commune')) ?></span></td>
        <td><?= $lvl[2] ?> <?= e(t($lvl[1])) ?></td>
        <td><?= (int) $r['reports'] ?></td>
        <td><?= (int) $r['fixed'] ?></td>
        <td><span class="score-pill"><?= (int) $r['points'] ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <?php else: ?>
    <thead><tr><th>#</th><th><?= e(t('lb_assoc')) ?></th><th><?= e(t('lb_fixed')) ?></th>
      <th><?= e(t('lb_badges')) ?></th><th><?= e(t('lb_points')) ?></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $i => $r): $bs = user_badges((int) $r['id']); ?>
      <tr>
        <td class="rank"><?= ['🥇', '🥈', '🥉'][$i] ?? $i + 1 ?></td>
        <td><strong><?= e($r['name']) ?></strong> <span class="muted">— <?= e(lc($r, 'commune')) ?></span></td>
        <td><?= (int) $r['fixed'] ?></td>
        <td><?php foreach ($bs as $b): ?><span title="<?= e(lc($b, 'name')) ?>"><?= e($b['icon']) ?></span><?php endforeach; ?></td>
        <td><span class="score-pill"><?= (int) $r['points'] ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <?php endif; ?>
  </table></div>
  <?php if (!$rows): ?><p class="empty">📊 <?= e(t('no_results')) ?></p><?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
