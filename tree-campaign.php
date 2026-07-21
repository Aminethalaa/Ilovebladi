<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/trees.php';
if (!module_on('trees')) {
    redirect('index.php');
}
tree_tables();

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT tc.*, u.name AS creator_name, u.role AS creator_role,
        cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
        w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr
    FROM tree_campaigns tc
    JOIN users u ON u.id = tc.creator_id
    JOIN communes cm ON cm.id = tc.commune_id
    JOIN wilayas w ON w.id = tc.wilaya_id
    WHERE tc.id = ?');
$st->execute([$id]);
$tc = $st->fetch();

$me = current_user();
if (!$tc) {
    http_response_code(404);
    $page_title = t('not_found');
    require __DIR__ . '/includes/layout/header.php';
    echo '<div class="container page"><p class="empty">😕 ' . e(t('not_found')) . '</p></div>';
    require __DIR__ . '/includes/layout/footer.php';
    exit;
}

$isCreator = $me && (int) $me['id'] === (int) $tc['creator_id'];
$isSuper = $me && $me['role'] === 'superadmin';
$canParticipate = $me && $tc['status'] === 'active'
    && in_array($me['role'], ['citizen', 'association'], true)
    && ($me['role'] !== 'association' || (int) $me['is_verified'] === 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $me) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'plant' && $canParticipate) {
        $trees = max(1, min(50, (int) ($_POST['trees'] ?? 1)));
        $lat = is_numeric($_POST['lat'] ?? '') ? (float) $_POST['lat'] : null;
        $lng = is_numeric($_POST['lng'] ?? '') ? (float) $_POST['lng'] : null;
        $photo = upload_photo($_FILES['photo'] ?? [], $err);
        if (!$photo) {
            flash_set('error', t($err ?? 'err_photo_upload'));
        } else {
            db()->prepare('INSERT INTO tree_plantings
                    (campaign_id, user_id, trees, photo, lat, lng, note, created_at)
                    VALUES (?,?,?,?,?,?,?,NOW())')
                ->execute([$id, (int) $me['id'], $trees, $photo, $lat, $lng,
                           mb_substr(trim($_POST['note'] ?? ''), 0, 300) ?: null]);
            notify((int) $tc['creator_id'], 'tree_new_planting', null);
            flash_set('success', t('msg_planting_sent', TREE_CONFIRMS_NEEDED));
        }

    } elseif ($action === 'confirm') {
        $pid = (int) ($_POST['planting_id'] ?? 0);
        $ps = db()->prepare('SELECT * FROM tree_plantings WHERE id = ? AND campaign_id = ?');
        $ps->execute([$pid, $id]);
        $planting = $ps->fetch();
        if ($planting && (int) $planting['user_id'] !== (int) $me['id'] && $planting['status'] === 'pending') {
            $approved = tree_confirm($planting, $me);
            flash_set('success', $approved ? t('msg_planting_approved') : t('msg_planting_confirmed'));
        }

    } elseif ($action === 'close_campaign' && ($isCreator || $isSuper) && $tc['status'] === 'active') {
        db()->prepare("UPDATE tree_campaigns SET status = 'closed' WHERE id = ?")->execute([$id]);
        flash_set('success', t('msg_campaign_closed'));
    }
    redirect('tree-campaign.php?id=' . $id);
}

$planted = campaign_planted($id);
$pct = min(100, (int) round($planted * 100 / max(1, (int) $tc['goal'])));

$pl = db()->prepare('SELECT tp.*, u.name AS planter_name FROM tree_plantings tp
    JOIN users u ON u.id = tp.user_id
    WHERE tp.campaign_id = ? ORDER BY (tp.status = ?) DESC, tp.id DESC LIMIT 100');
$pl->execute([$id, 'pending']);
$plantings = $pl->fetchAll();

$myConfirms = [];
if ($me) {
    $mc = db()->prepare('SELECT planting_id FROM tree_confirms tcf
        JOIN tree_plantings tp ON tp.id = tcf.planting_id
        WHERE tp.campaign_id = ? AND tcf.user_id = ?');
    $mc->execute([$id, (int) $me['id']]);
    foreach ($mc->fetchAll() as $r) {
        $myConfirms[(int) $r['planting_id']] = true;
    }
}

$use_leaflet = true;
$page_title = $tc['title'];
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container page">
  <p><a class="muted" href="<?= e(url('trees.php')) ?>">← <?= e(t('back')) ?></a></p>
  <div class="detail-head">
    <div>
      <?php if ($tc['status'] === 'active'): ?><span class="badge st-resolved"><?= e(t('trees_status_active')) ?></span>
      <?php else: ?><span class="badge st-closed"><?= e(t('trees_status_done')) ?></span><?php endif; ?>
      <h1>🌳 <?= e($tc['title']) ?></h1>
      <p class="muted">📍 <?= e(lc($tc, 'commune')) ?> — <?= e(lc($tc, 'wilaya')) ?>
        · <?= e(t('trees_by')) ?> <strong><?= e($tc['creator_name']) ?></strong>
        <?php if ($tc['ends_at']): ?> · ⏳ <?= e($tc['ends_at']) ?><?php endif; ?></p>
    </div>
    <?php if (($isCreator || $isSuper) && $tc['status'] === 'active'): ?>
    <form method="post" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>');">
      <?= csrf_field() ?><input type="hidden" name="action" value="close_campaign">
      <button class="btn btn-ghost"><?= e(t('trees_close_btn')) ?></button>
    </form>
    <?php endif; ?>
  </div>

  <div class="card card-pad">
    <div class="tree-progress tree-progress-lg">
      <div class="tree-progress-fill" style="width:<?= $pct ?>%"></div>
    </div>
    <p class="tree-progress-nums center">
      <strong class="tree-big"><?= $planted ?></strong> / <?= (int) $tc['goal'] ?> 🌳 (<?= $pct ?>%)
    </p>
    <p class="pre"><?= nl2br(e($tc['description'])) ?></p>
  </div>

  <?php if ($canParticipate): ?>
  <div class="card card-pad action-card">
    <h3>🌱 <?= e(t('trees_participate_title')) ?></h3>
    <p class="muted"><?= e(t('trees_participate_sub', TREE_CONFIRMS_NEEDED)) ?></p>
    <form method="post" enctype="multipart/form-data" class="form wizard"
          data-back="<?= e(t('wz_back')) ?>" data-next="<?= e(t('wz_next')) ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="plant">
      <div class="wizard-step" data-title="<?= e(t('trees_step_count')) ?>">
        <div class="field-row">
          <label class="field"><span><?= e(t('trees_count')) ?></span>
            <input required type="number" name="trees" min="1" max="50" value="1">
          </label>
          <label class="field"><span><?= e(t('trees_photo')) ?></span>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" required>
          </label>
        </div>
      </div>
      <div class="wizard-step" data-title="<?= e(t('nc_step_location')) ?>">
        <label class="field"><span><?= e(t('nc_location')) ?> — <?= e(t('optional')) ?></span></label>
        <div class="loc-bar">
          <button type="button" class="btn btn-ghost" id="gpsBtn">📡 <?= e(t('nc_use_gps')) ?></button>
          <span class="muted" id="gpsStatus"><?= e(t('nc_tap_map')) ?></span>
        </div>
        <div id="pickMap" class="pick-map"></div>
        <input type="hidden" name="lat" id="latInput"><input type="hidden" name="lng" id="lngInput">
        <label class="field"><span><?= e(t('note_optional')) ?></span>
          <input name="note" maxlength="300">
        </label>
        <button class="btn btn-primary btn-lg btn-block wizard-submit">🌳 <?= e(t('trees_submit_btn')) ?></button>
      </div>
    </form>
  </div>
  <?php elseif (!$me): ?>
  <div class="flash flash-warn">🌱 <?= e(t('trees_login_hint')) ?> <a href="<?= e(url('register.php')) ?>"><?= e(t('nav_register')) ?></a></div>
  <?php endif; ?>

  <section class="dash-section">
    <h2>📸 <?= e(t('trees_plantings')) ?> (<?= count($plantings) ?>)</h2>
    <?php if (!$plantings): ?><p class="empty">🌱 <?= e(t('trees_none_yet')) ?></p><?php endif; ?>
    <div class="grid-3">
      <?php foreach ($plantings as $p): ?>
      <div class="card">
        <div class="c-card-img">
          <img loading="lazy" src="<?= e(upload_url($p['photo'])) ?>" alt="">
          <?php if ($p['status'] === 'approved'): ?>
          <span class="badge st-closed">✔ <?= e(t('trees_approved')) ?></span>
          <?php else: ?>
          <span class="badge st-pending"><?= e(t('trees_pending', (int) $p['confirms'], TREE_CONFIRMS_NEEDED)) ?></span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <p><strong>🌳 ×<?= (int) $p['trees'] ?></strong> — <?= e($p['planter_name']) ?></p>
          <?php if ($p['note']): ?><p class="muted"><?= e($p['note']) ?></p><?php endif; ?>
          <p class="muted"><?= e(time_ago($p['created_at'])) ?></p>
          <?php if ($me && $p['status'] === 'pending' && (int) $p['user_id'] !== (int) $me['id'] && empty($myConfirms[(int) $p['id']])): ?>
          <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="confirm">
            <input type="hidden" name="planting_id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-sm btn-primary">👍 <?= e(t('trees_confirm_btn')) ?></button>
          </form>
          <?php elseif ($me && !empty($myConfirms[(int) $p['id']])): ?>
          <span class="muted">✔ <?= e(t('trees_you_confirmed')) ?></span>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('pickMap');
  if (!el || typeof L === 'undefined') return;
  var latI = document.getElementById('latInput'), lngI = document.getElementById('lngInput');
  var status = document.getElementById('gpsStatus');
  var map = L.map(el).setView([36.75, 3.06], 6);
  (window.Baladiyati = window.Baladiyati || {}).maps = window.Baladiyati.maps || [];
  window.Baladiyati.maps.push(map);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}).addTo(map);
  var marker = null;
  function setPoint(lat, lng, pan) {
    latI.value = lat.toFixed(7); lngI.value = lng.toFixed(7);
    if (marker) marker.setLatLng([lat, lng]); else marker = L.marker([lat, lng], {draggable: true}).addTo(map);
    marker.off('dragend').on('dragend', function () { var p = marker.getLatLng(); setPoint(p.lat, p.lng, false); });
    status.textContent = '📍 ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
    if (pan) map.setView([lat, lng], Math.max(map.getZoom(), 16));
  }
  map.on('click', function (ev) { setPoint(ev.latlng.lat, ev.latlng.lng, false); });
  var gpsBtn = document.getElementById('gpsBtn');
  if (gpsBtn) gpsBtn.addEventListener('click', function () {
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
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
