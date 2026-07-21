<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/associations.php';
if (!module_on('associations')) {
    redirect('index.php');
}
assoc_tables();

$fWilaya = (int) ($_GET['wilaya'] ?? 0);
$fCommune = (int) ($_GET['commune'] ?? 0);
$fCat = (int) ($_GET['category'] ?? 0);
$q = trim($_GET['q'] ?? '');

$where = ["u.role = 'association'", 'u.is_verified = 1', 'u.is_blocked = 0'];
$bind = [];
if ($fWilaya)  { $where[] = 'u.wilaya_id = ?';  $bind[] = $fWilaya; }
if ($fCommune) { $where[] = 'u.commune_id = ?'; $bind[] = $fCommune; }
if ($q !== '') { $where[] = 'u.name LIKE ?';    $bind[] = '%' . $q . '%'; }
if ($fCat) {
    $where[] = 'EXISTS (SELECT 1 FROM user_assoc_categories uac WHERE uac.user_id = u.id AND uac.category_id = ?)';
    $bind[] = $fCat;
}

$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 12;
$base = "FROM users u
    JOIN communes cm ON cm.id = u.commune_id
    JOIN wilayas w ON w.id = u.wilaya_id
    LEFT JOIN assoc_profiles ap ON ap.user_id = u.id
    WHERE " . implode(' AND ', $where);
$cs = db()->prepare("SELECT COUNT(*) c $base");
$cs->execute($bind);
$total = (int) $cs->fetch()['c'];

$st = db()->prepare("SELECT u.id, u.name, u.about, u.points,
        cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
        w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr,
        ap.logo
    $base
    ORDER BY u.points DESC, u.name ASC
    LIMIT $per OFFSET " . ($page - 1) * $per);
$st->execute($bind);
$rows = $st->fetchAll();

$wilayas = all_wilayas();
$communes = $fWilaya ? communes_of($fWilaya) : [];
$cats = assoc_all_categories();

$page_title = t('dir_title');
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container page">
  <div class="page-head">
    <div>
      <h1>🤝 <?= e(t('dir_title')) ?></h1>
      <p class="muted"><?= e(t('dir_count', $total)) ?></p>
    </div>
  </div>

  <?php if ($cats): ?>
  <div class="cat-chips">
    <a class="cat-chip <?= $fCat === 0 ? 'on' : '' ?>" href="<?= e(url('associations.php')) ?>"><?= e(t('dir_all_cats')) ?></a>
    <?php foreach ($cats as $c): ?>
    <a class="cat-chip <?= $fCat === (int) $c['id'] ? 'on' : '' ?>" href="?category=<?= (int) $c['id'] ?>"><?= e($c['icon']) ?> <?= e(lc($c, 'name')) ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form class="filters card" method="get">
    <?php if ($fCat): ?><input type="hidden" name="category" value="<?= $fCat ?>"><?php endif; ?>
    <input name="q" value="<?= e($q) ?>" placeholder="🔍 <?= e(t('dir_search')) ?>">
    <select name="wilaya" id="wilayaSel" data-communes-url="<?= e(url('api/communes.php')) ?>" onchange="this.form.commune.value='';">
      <option value=""><?= e(t('all_wilayas')) ?></option>
      <?php foreach ($wilayas as $w): ?>
      <option value="<?= (int) $w['id'] ?>" <?= $fWilaya === (int) $w['id'] ? 'selected' : '' ?>><?= (int) $w['id'] ?> — <?= e(lc($w, 'name')) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="commune" id="communeSel">
      <option value=""><?= e(t('all_communes')) ?></option>
      <?php foreach ($communes as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= $fCommune === (int) $c['id'] ? 'selected' : '' ?>><?= e(lc($c, 'name')) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary"><?= e(t('filter')) ?></button>
  </form>

  <div class="grid-3">
    <?php foreach ($rows as $r): $rcats = assoc_categories_of((int) $r['id']); ?>
    <a class="card assoc-card" href="<?= e(url('association.php?id=' . (int) $r['id'])) ?>">
      <div class="assoc-card-head">
        <?php if (!empty($r['logo'])): ?>
        <img class="assoc-logo" src="<?= e(upload_url($r['logo'])) ?>" alt="">
        <?php else: ?>
        <span class="assoc-logo assoc-logo-ph">🤝</span>
        <?php endif; ?>
        <div>
          <h3><?= e($r['name']) ?></h3>
          <p class="muted">📍 <?= e(lc($r, 'commune')) ?> — <?= e(lc($r, 'wilaya')) ?></p>
        </div>
      </div>
      <?php if ($rcats): ?>
      <div class="assoc-cats">
        <?php foreach (array_slice($rcats, 0, 3) as $c): ?>
        <span class="chip chip-sm"><?= e($c['icon']) ?> <?= e(lc($c, 'name')) ?></span>
        <?php endforeach; ?>
        <?php if (count($rcats) > 3): ?><span class="chip chip-sm">+<?= count($rcats) - 3 ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($r['about'])): ?>
      <p class="assoc-about"><?= e(mb_strimwidth($r['about'], 0, 120, '…')) ?></p>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="empty">🔍 <?= e(t('no_results')) ?></p><?php endif; ?>
  </div>

  <?php $pages = (int) ceil($total / $per); if ($pages > 1): ?>
  <nav class="pager">
    <?php for ($i = 1; $i <= $pages; $i++):
        $qs = http_build_query(array_merge($_GET, ['p' => $i])); ?>
      <a class="<?= $i === $page ? 'on' : '' ?>" href="?<?= e($qs) ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
