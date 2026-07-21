<?php
require_once __DIR__ . '/includes/auth.php';
if (!module_on('complaints')) {
    redirect('index.php');
}

$fWilaya  = (int) ($_GET['wilaya'] ?? 0);
$fCommune = (int) ($_GET['commune'] ?? 0);
$fCat     = (int) ($_GET['category'] ?? 0);
$fStatus  = (string) ($_GET['status'] ?? '');
$allowedStatuses = ['published', 'in_progress', 'resolved', 'closed'];
if (!in_array($fStatus, $allowedStatuses, true)) {
    $fStatus = '';
}

$where = ["x.status IN ('published','in_progress','resolved','closed')"];
$bind = [];
if ($fWilaya)  { $where[] = 'x.wilaya_id = ?';   $bind[] = $fWilaya; }
if ($fCommune) { $where[] = 'x.commune_id = ?';  $bind[] = $fCommune; }
if ($fCat)     { $where[] = 'x.category_id = ?'; $bind[] = $fCat; }
if ($fStatus)  { $where[] = 'x.status = ?';      $bind[] = $fStatus; }

$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 12;
$sql = 'FROM complaints x
        JOIN categories cat ON cat.id = x.category_id
        JOIN communes cm ON cm.id = x.commune_id
        JOIN wilayas w ON w.id = x.wilaya_id
        WHERE ' . implode(' AND ', $where);
$st = db()->prepare("SELECT COUNT(*) c $sql");
$st->execute($bind);
$total = (int) $st->fetch()['c'];

$st = db()->prepare("SELECT x.*, cat.icon, cat.name_ar AS cat_ar, cat.name_fr AS cat_fr,
        cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
        w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr
        $sql ORDER BY x.upvotes DESC, x.id DESC LIMIT $per OFFSET " . ($page - 1) * $per);
$st->execute($bind);
$rows = $st->fetchAll();

$wilayas = all_wilayas();
$communes = $fWilaya ? communes_of($fWilaya) : [];
$cats = active_categories();

$use_leaflet = true;
$page_title = t('nav_complaints');
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container page">
  <div class="page-head">
    <h1><?= e(t('browse_title')) ?></h1>
    <p class="muted"><?= e(t('browse_count', $total)) ?></p>
  </div>

  <form class="filters card" method="get">
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
    <select name="category">
      <option value=""><?= e(t('all_categories')) ?></option>
      <?php foreach ($cats as $c): ?>
      <option value="<?= (int) $c['id'] ?>" <?= $fCat === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['icon']) ?> <?= e(lc($c, 'name')) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status">
      <option value=""><?= e(t('all_statuses')) ?></option>
      <?php foreach ($allowedStatuses as $s): ?>
      <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= e(t(status_meta($s)['key'])) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary"><?= e(t('filter')) ?></button>
  </form>

  <div id="map" class="browse-map"
       data-geojson="<?= e(url('api/geojson.php') . '?' . http_build_query(array_filter(['wilaya' => $fWilaya, 'commune' => $fCommune, 'category' => $fCat, 'status' => $fStatus]))) ?>"
       data-base="<?= e(url('complaint.php')) ?>"></div>

  <div class="grid-3">
    <?php foreach ($rows as $r): ?>
    <a class="card c-card" href="<?= e(url('complaint.php?id=' . (int) $r['id'])) ?>">
      <div class="c-card-img">
        <img loading="lazy" src="<?= e(upload_url($r['photo'])) ?>" alt="">
        <?= status_badge($r['status']) ?>
      </div>
      <div class="card-body">
        <span class="chip"><?= e($r['icon']) ?> <?= e(lc($r, 'cat')) ?></span>
        <h3><?= e($r['title']) ?></h3>
        <p class="muted">📍 <?= e(lc($r, 'commune')) ?> — <?= e(lc($r, 'wilaya')) ?></p>
        <p class="c-meta"><span>👍 <?= (int) $r['upvotes'] ?></span><span><?= e(time_ago($r['created_at'])) ?></span></p>
      </div>
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
<script>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('map');
  if (!el || typeof L === 'undefined') return;
  var map = L.map(el).setView([32.5, 2.9], 5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}).addTo(map);
  var colors = {published: '#d97706', in_progress: '#2563eb', resolved: '#16a34a', closed: '#15803d'};
  fetch(el.dataset.geojson).then(r => r.json()).then(function (fc) {
    var layer = L.geoJSON(fc, {
      pointToLayer: function (f, latlng) {
        return L.circleMarker(latlng, {radius: 9, weight: 2, color: '#fff', fillColor: colors[f.properties.status] || '#888', fillOpacity: 0.95});
      },
      onEachFeature: function (f, l) {
        l.bindPopup('<strong>' + f.properties.title + '</strong><br>' +
          '<a href="' + el.dataset.base + '?id=' + f.properties.id + '">' + f.properties.link_label + '</a>');
      }
    }).addTo(map);
    if (fc.features.length) map.fitBounds(layer.getBounds().pad(0.2));
  });
});
</script>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
