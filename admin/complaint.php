<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('admin', 'superadmin');

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT x.*, cat.icon, cat.name_ar AS cat_ar, cat.name_fr AS cat_fr,
        cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
        w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr,
        u.name AS reporter_name, u.email AS reporter_email,
        h.name AS handler_name, h.role AS handler_role
    FROM complaints x
    JOIN categories cat ON cat.id = x.category_id
    JOIN communes cm ON cm.id = x.commune_id
    JOIN wilayas w ON w.id = x.wilaya_id
    JOIN users u ON u.id = x.user_id
    LEFT JOIN users h ON h.id = x.handler_id
    WHERE x.id = ?');
$st->execute([$id]);
$c = $st->fetch();

if (!$c || ($me['role'] === 'admin' && (int) $me['commune_id'] !== (int) $c['commune_id'])) {
    flash_set('error', t('not_found'));
    redirect(role_home($me));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $note = trim($_POST['note'] ?? '');

    if ($action === 'approve' && $c['status'] === 'pending') {
        db()->prepare("UPDATE complaints SET status = 'published', published_at = NOW() WHERE id = ?")->execute([$id]);
        log_event($id, (int) $me['id'], 'approved', $note ?: null);
        award_points((int) $c['user_id'], 'complaint_approved', $id);
        notify((int) $c['user_id'], 'complaint_approved', $id);
        flash_set('success', t('msg_approved'));

    } elseif ($action === 'reject' && $c['status'] === 'pending') {
        if (mb_strlen($note) < 5) {
            flash_set('error', t('err_reject_reason'));
        } else {
            db()->prepare("UPDATE complaints SET status = 'rejected', reject_reason = ? WHERE id = ?")->execute([$note, $id]);
            log_event($id, (int) $me['id'], 'rejected', $note);
            notify((int) $c['user_id'], 'complaint_rejected', $id);
            flash_set('success', t('msg_rejected'));
        }

    } elseif ($action === 'start' && $c['status'] === 'published') {
        db()->prepare("UPDATE complaints SET status = 'in_progress', handler_id = ? WHERE id = ?")
            ->execute([(int) $me['id'], $id]);
        log_event($id, (int) $me['id'], 'in_progress', $note ?: null);
        notify((int) $c['user_id'], 'complaint_in_progress', $id);
        flash_set('success', t('msg_started'));

    } elseif ($action === 'resolve' && in_array($c['status'], ['published', 'in_progress'], true)) {
        $photo = upload_photo($_FILES['after_photo'] ?? [], $err);
        if (!$photo) {
            flash_set('error', t($err ?? 'err_photo_upload'));
        } else {
            db()->prepare("UPDATE complaints SET status = 'resolved', after_photo = ?, resolved_at = NOW(), handler_id = ? WHERE id = ?")
                ->execute([$photo, (int) $me['id'], $id]);
            log_event($id, (int) $me['id'], 'resolved', $note ?: null);
            award_points((int) $c['user_id'], 'complaint_resolved', $id);
            notify((int) $c['user_id'], 'complaint_resolved', $id);
            check_badges((int) $me['id']);
            flash_set('success', t('msg_resolved'));
        }

    } elseif ($action === 'validate_fix' && $c['status'] === 'in_progress' && $c['after_photo'] && $c['handler_role'] === 'citizen') {
        db()->prepare("UPDATE complaints SET status = 'resolved', resolved_at = NOW() WHERE id = ?")->execute([$id]);
        log_event($id, (int) $me['id'], 'resolved', trim($_POST['note'] ?? '') ?: null);
        award_points((int) $c['user_id'], 'complaint_resolved', $id);
        notify((int) $c['user_id'], 'complaint_resolved', $id);
        notify((int) $c['handler_id'], 'fix_validated', $id);
        check_badges((int) $c['handler_id']);
        flash_set('success', t('msg_fix_validated'));

    } elseif ($action === 'reject_fix' && $c['status'] === 'in_progress' && $c['after_photo'] && $c['handler_role'] === 'citizen') {
        if (mb_strlen($note) < 5) {
            flash_set('error', t('err_reject_reason'));
        } else {
            db()->prepare('UPDATE complaints SET after_photo = NULL WHERE id = ?')->execute([$id]);
            log_event($id, (int) $me['id'], 'fix_rejected', $note);
            notify((int) $c['handler_id'], 'fix_rejected', $id);
            flash_set('success', t('msg_fix_rejected'));
        }

    } elseif ($action === 'close' && $c['status'] === 'resolved') {
        db()->prepare("UPDATE complaints SET status = 'closed', closed_at = NOW() WHERE id = ?")->execute([$id]);
        log_event($id, (int) $me['id'], 'closed', $note ?: null);
        notify((int) $c['user_id'], 'complaint_closed', $id);
        if ($c['handler_id']) {
            if ($c['handler_role'] === 'association') {
                award_points((int) $c['handler_id'], 'assoc_fix_confirmed', $id);
            } else {
                check_badges((int) $c['handler_id']);
            }
        }
        flash_set('success', t('msg_closed'));
    }
    redirect('admin/complaint.php?id=' . $id);
}

$ev = db()->prepare('SELECT ev.*, u.name AS actor FROM complaint_events ev
    LEFT JOIN users u ON u.id = ev.user_id WHERE ev.complaint_id = ? ORDER BY ev.id');
$ev->execute([$id]);
$events = $ev->fetchAll();

$use_leaflet = $c['lat'] !== null;
$page_title = t('admin_manage') . ' #' . $c['ref'];
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page detail">
  <p><a class="muted" href="<?= e(url(role_home($me))) ?>">← <?= e(t('back')) ?></a></p>
  <div class="detail-head">
    <div>
      <span class="ref">#<?= e($c['ref']) ?></span> <?= status_badge($c['status']) ?>
      <?php if ((int) $c['reopened'] === 1): ?><span class="badge st-rejected"><?= e(t('reopened_flag')) ?></span><?php endif; ?>
      <h1><?= e($c['title']) ?></h1>
      <p class="muted">
        <span class="chip"><?= e($c['icon']) ?> <?= e(lc($c, 'cat')) ?></span>
        📍 <?= e(lc($c, 'commune')) ?> — <?= e(lc($c, 'wilaya')) ?> · 👍 <?= (int) $c['upvotes'] ?> · <?= e(time_ago($c['created_at'])) ?>
      </p>
      <p class="muted"><?= e(t('reported_by')) ?> <strong><?= e($c['reporter_name']) ?></strong> (<?= e($c['reporter_email']) ?>)</p>
      <?php if ($c['handler_name']): ?>
      <p class="muted"><?= e(t('handled_by')) ?> <strong><?= e($c['handler_name']) ?></strong>
        <span class="chip chip-sm"><?= e(t('role_' . ($c['handler_role'] === 'admin' ? 'admin' : ($c['handler_role'] === 'association' ? 'association' : 'citizen')))) ?></span></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="detail-grid">
    <div>
      <?php if ($c['after_photo']): ?>
      <div class="before-after before-after-lg">
        <figure><img src="<?= e(upload_url($c['photo'])) ?>" alt=""><figcaption><?= e(t('before')) ?></figcaption></figure>
        <figure><img src="<?= e(upload_url($c['after_photo'])) ?>" alt=""><figcaption class="cap-after"><?= e(t('after')) ?></figcaption></figure>
      </div>
      <?php else: ?>
      <img class="detail-photo" src="<?= e(upload_url($c['photo'])) ?>" alt="">
      <?php endif; ?>
      <div class="card card-pad">
        <h2><?= e(t('description')) ?></h2>
        <p class="pre"><?= nl2br(e($c['description'])) ?></p>
      </div>
      <?php if ($c['lat'] !== null): ?>
      <div id="miniMap" class="mini-map" data-lat="<?= e($c['lat']) ?>" data-lng="<?= e($c['lng']) ?>"></div>
      <?php endif; ?>
    </div>

    <aside>
      <?php if ($c['status'] === 'pending'): ?>
      <div class="card card-pad action-card">
        <h3>⏳ <?= e(t('adm_review_title')) ?></h3>
        <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="approve">
          <button class="btn btn-primary btn-block">✅ <?= e(t('adm_approve')) ?></button>
        </form>
        <details class="reopen-box">
          <summary><?= e(t('adm_reject')) ?></summary>
          <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="reject">
            <textarea name="note" rows="3" required minlength="5" placeholder="<?= e(t('adm_reject_ph')) ?>"></textarea>
            <button class="btn btn-danger btn-block"><?= e(t('adm_reject')) ?></button>
          </form>
        </details>
      </div>
      <?php endif; ?>

      <?php if ($c['status'] === 'published'): ?>
      <div class="card card-pad action-card">
        <h3>🚧 <?= e(t('adm_start_title')) ?></h3>
        <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="start">
          <textarea name="note" rows="2" maxlength="500" placeholder="<?= e(t('note_optional')) ?>"></textarea>
          <button class="btn btn-primary btn-block"><?= e(t('adm_start')) ?></button>
        </form>
      </div>
      <?php endif; ?>

      <?php $citizenFixPending = $c['status'] === 'in_progress' && $c['after_photo'] && $c['handler_role'] === 'citizen'; ?>
      <?php if ($citizenFixPending): ?>
      <div class="card card-pad action-card">
        <h3>🕓 <?= e(t('adm_validate_title')) ?></h3>
        <p class="muted"><?= e(t('adm_validate_sub', $c['handler_name'])) ?></p>
        <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="validate_fix">
          <button class="btn btn-primary btn-block">✅ <?= e(t('adm_validate_btn')) ?></button>
        </form>
        <details class="reopen-box">
          <summary><?= e(t('adm_reject_fix')) ?></summary>
          <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="reject_fix">
            <textarea name="note" rows="3" required minlength="5" placeholder="<?= e(t('adm_reject_fix_ph')) ?>"></textarea>
            <button class="btn btn-danger btn-block"><?= e(t('adm_reject_fix')) ?></button>
          </form>
        </details>
      </div>
      <?php endif; ?>

      <?php if (in_array($c['status'], ['published', 'in_progress'], true) && !$citizenFixPending): ?>
      <div class="card card-pad action-card">
        <h3>✅ <?= e(t('adm_resolve_title')) ?></h3>
        <form method="post" enctype="multipart/form-data" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="resolve">
          <label class="field"><span><?= e(t('after_photo')) ?></span>
            <input type="file" name="after_photo" accept="image/jpeg,image/png,image/webp" required>
          </label>
          <textarea name="note" rows="2" maxlength="500" placeholder="<?= e(t('note_optional')) ?>"></textarea>
          <button class="btn btn-primary btn-block"><?= e(t('mark_resolved')) ?></button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($c['status'] === 'resolved'): ?>
      <div class="card card-pad action-card">
        <h3>🔒 <?= e(t('adm_close_title')) ?></h3>
        <p class="muted"><?= e(t('adm_close_sub')) ?></p>
        <form method="post" class="form"><?= csrf_field() ?><input type="hidden" name="action" value="close">
          <button class="btn btn-primary btn-block"><?= e(t('adm_close')) ?></button>
        </form>
      </div>
      <?php endif; ?>

      <div class="card card-pad">
        <h3>🕓 <?= e(t('timeline')) ?></h3>
        <ol class="timeline">
          <li><span class="tl-dot"></span><div><strong><?= e(t('ev_created')) ?></strong><span class="muted"><?= e(time_ago($c['created_at'])) ?></span></div></li>
          <?php foreach ($events as $evt): ?>
          <li><span class="tl-dot tl-<?= e($evt['event']) ?>"></span>
            <div>
              <strong><?= e(t('ev_' . $evt['event'])) ?></strong>
              <?php if ($evt['actor']): ?><span class="muted"> — <?= e($evt['actor']) ?></span><?php endif; ?>
              <?php if ($evt['note']): ?><p class="tl-note"><?= e($evt['note']) ?></p><?php endif; ?>
              <span class="muted"><?= e(time_ago($evt['created_at'])) ?></span>
            </div>
          </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </aside>
  </div>
</div>
<?php if ($c['lat'] !== null): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('miniMap');
  if (!el || typeof L === 'undefined') return;
  var lat = parseFloat(el.dataset.lat), lng = parseFloat(el.dataset.lng);
  var map = L.map(el, {scrollWheelZoom: false}).setView([lat, lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '© OpenStreetMap'}).addTo(map);
  L.marker([lat, lng]).addTo(map);
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
