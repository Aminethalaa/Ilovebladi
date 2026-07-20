<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('citizen', 'association');

$errors = [];
$old = [
    'category_id' => 0,
    'commune_id'  => (int) $me['commune_id'],
    'wilaya_id'   => (int) $me['wilaya_id'],
    'title'       => '',
    'description' => '',
    'lat'         => '',
    'lng'         => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['category_id'] = (int) ($_POST['category_id'] ?? 0);
    $old['commune_id']  = (int) ($_POST['commune_id'] ?? 0);
    $old['title']       = trim($_POST['title'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $old['lat']         = trim($_POST['lat'] ?? '');
    $old['lng']         = trim($_POST['lng'] ?? '');

    if (mb_strlen($old['title']) < 5)        $errors[] = t('err_title');
    if (mb_strlen($old['description']) < 20) $errors[] = t('err_description');
    if ($old['category_id'] <= 0)            $errors[] = t('err_category');

    $st = db()->prepare('SELECT wilaya_id FROM communes WHERE id = ?');
    $st->execute([$old['commune_id']]);
    $cw = $st->fetch();
    if (!$cw) {
        $errors[] = t('err_commune');
    } else {
        $old['wilaya_id'] = (int) $cw['wilaya_id'];
    }

    $lat = is_numeric($old['lat']) ? (float) $old['lat'] : null;
    $lng = is_numeric($old['lng']) ? (float) $old['lng'] : null;
    if ($lat === null || $lng === null || $lat < 18 || $lat > 38 || $lng < -9 || $lng > 12) {
        $errors[] = t('err_location');
    }

    $photo = null;
    if (!$errors) {
        $photo = upload_photo($_FILES['photo'] ?? [], $perr);
        if (!$photo) {
            $errors[] = t($perr ?? 'err_photo_upload');
        }
    }

    if (!$errors) {
        db()->prepare("INSERT INTO complaints
                (ref, user_id, category_id, wilaya_id, commune_id, title, description, lat, lng, photo, status, created_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,'pending',NOW())")
            ->execute([
                new_ref(), (int) $me['id'], $old['category_id'], $old['wilaya_id'], $old['commune_id'],
                $old['title'], $old['description'], $lat, $lng, $photo,
            ]);
        flash_set('success', t('msg_complaint_sent'));
        redirect('dashboard/index.php');
    }
}

$cats = active_categories();
$wilayas = all_wilayas();
$communes = $old['wilaya_id'] ? communes_of((int) $old['wilaya_id']) : [];

$use_leaflet = true;
$page_title = t('new_complaint');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page page-narrow">
  <div class="page-head"><h1>📣 <?= e(t('new_complaint')) ?></h1>
    <p class="muted"><?= e(t('new_complaint_sub')) ?></p></div>
  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" class="form card card-pad">
    <?= csrf_field() ?>
    <label class="field"><span><?= e(t('nc_category')) ?></span></label>
    <div class="cat-picker">
      <?php foreach ($cats as $c): ?>
      <label class="cat-opt <?= $old['category_id'] === (int) $c['id'] ? 'on' : '' ?>">
        <input type="radio" name="category_id" value="<?= (int) $c['id'] ?>" <?= $old['category_id'] === (int) $c['id'] ? 'checked' : '' ?> required>
        <span class="cat-ico"><?= e($c['icon']) ?></span><?= e(lc($c, 'name')) ?>
      </label>
      <?php endforeach; ?>
    </div>

    <label class="field"><span><?= e(t('nc_title')) ?></span>
      <input required name="title" minlength="5" maxlength="180" value="<?= e($old['title']) ?>" placeholder="<?= e(t('nc_title_ph')) ?>">
    </label>
    <label class="field"><span><?= e(t('description')) ?></span>
      <textarea required name="description" rows="5" minlength="20" maxlength="4000" placeholder="<?= e(t('nc_desc_ph')) ?>"><?= e($old['description']) ?></textarea>
    </label>

    <div class="field-row">
      <label class="field"><span><?= e(t('wilaya')) ?></span>
        <select name="wilaya_id" id="wilayaSel" required data-communes-url="<?= e(url('api/communes.php')) ?>">
          <?php foreach ($wilayas as $w): ?>
          <option value="<?= (int) $w['id'] ?>" <?= (int) $old['wilaya_id'] === (int) $w['id'] ? 'selected' : '' ?>><?= (int) $w['id'] ?> — <?= e(lc($w, 'name')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span><?= e(t('commune')) ?></span>
        <select name="commune_id" id="communeSel" required>
          <?php foreach ($communes as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $old['commune_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(lc($c, 'name')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <label class="field"><span><?= e(t('nc_location')) ?></span></label>
    <div class="loc-bar">
      <button type="button" class="btn btn-ghost" id="gpsBtn">📡 <?= e(t('nc_use_gps')) ?></button>
      <span class="muted" id="gpsStatus"><?= e(t('nc_tap_map')) ?></span>
    </div>
    <div id="pickMap" class="pick-map"></div>
    <input type="hidden" name="lat" id="latInput" value="<?= e($old['lat']) ?>">
    <input type="hidden" name="lng" id="lngInput" value="<?= e($old['lng']) ?>">

    <label class="field"><span><?= e(t('nc_photo')) ?></span>
      <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" required>
      <span class="hint"><?= e(t('nc_photo_hint')) ?></span>
    </label>

    <p class="hint">ℹ️ <?= e(t('nc_review_note')) ?></p>
    <button class="btn btn-primary btn-lg btn-block">📨 <?= e(t('nc_submit')) ?></button>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof L === 'undefined') return;
  var latI = document.getElementById('latInput'), lngI = document.getElementById('lngInput');
  var status = document.getElementById('gpsStatus');
  var start = [36.75, 3.06], zoom = 6;
  if (latI.value && lngI.value) { start = [parseFloat(latI.value), parseFloat(lngI.value)]; zoom = 15; }
  var map = L.map('pickMap').setView(start, zoom);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}).addTo(map);
  var marker = null;
  function setPoint(lat, lng, pan) {
    latI.value = lat.toFixed(7); lngI.value = lng.toFixed(7);
    if (marker) marker.setLatLng([lat, lng]); else marker = L.marker([lat, lng], {draggable: true}).addTo(map);
    marker.off('dragend').on('dragend', function () { var p = marker.getLatLng(); setPoint(p.lat, p.lng, false); });
    status.textContent = '📍 ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
    if (pan) map.setView([lat, lng], Math.max(map.getZoom(), 16));
  }
  if (latI.value && lngI.value) setPoint(parseFloat(latI.value), parseFloat(lngI.value), false);
  map.on('click', function (ev) { setPoint(ev.latlng.lat, ev.latlng.lng, false); });
  document.getElementById('gpsBtn').addEventListener('click', function () {
    if (!navigator.geolocation) return;
    status.textContent = '…';
    navigator.geolocation.getCurrentPosition(
      function (pos) { setPoint(pos.coords.latitude, pos.coords.longitude, true); },
      function () { status.textContent = '⚠️'; },
      {enableHighAccuracy: true, timeout: 10000}
    );
  });
});
</script>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
