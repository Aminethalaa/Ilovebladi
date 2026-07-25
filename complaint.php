<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/comments.php';
if (!module_on('complaints')) {
    redirect('index.php');
}

$id = (int) ($_GET['id'] ?? 0);
$st = db()->prepare('SELECT x.*, cat.icon, cat.name_ar AS cat_ar, cat.name_fr AS cat_fr,
        cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
        w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr,
        u.name AS reporter_name, u.points AS reporter_points,
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

$me = current_user();
$isOwner = $c && $me && (int) $me['id'] === (int) $c['user_id'];
$isZoneAdmin = $c && $me && $me['role'] === 'admin' && (int) $me['commune_id'] === (int) $c['commune_id'];
$isSuper = $me && $me['role'] === 'superadmin';

if (!$c || (in_array($c['status'], ['pending', 'rejected'], true) && !$isOwner && !$isZoneAdmin && !$isSuper)) {
    http_response_code(404);
    $page_title = t('not_found');
    require __DIR__ . '/includes/layout/header.php';
    echo '<div class="container page"><p class="empty">😕 ' . e(t('not_found')) . '</p></div>';
    require __DIR__ . '/includes/layout/footer.php';
    exit;
}

$canUpvote = $me && !$isOwner && in_array($me['role'], ['citizen', 'association'], true)
    && in_array($c['status'], ['published', 'in_progress'], true);
$hasUpvoted = false;
if ($me) {
    $q = db()->prepare('SELECT 1 FROM upvotes WHERE complaint_id = ? AND user_id = ?');
    $q->execute([$id, (int) $me['id']]);
    $hasUpvoted = (bool) $q->fetch();
}
$isVerifiedAssoc = $me && $me['role'] === 'association' && (int) $me['is_verified'] === 1;
$isVolunteerCitizen = $me && $me['role'] === 'citizen';
$canTakeCharge = ($isVerifiedAssoc || $isVolunteerCitizen) && $c['status'] === 'published'
    && (int) $me['wilaya_id'] === (int) $c['wilaya_id'] && !$isOwner;
$isHandler = $me && (int) ($c['handler_id'] ?? 0) === (int) $me['id'];
$canAssocResolve = $isVerifiedAssoc && $isHandler && $c['status'] === 'in_progress';
$canCitizenSubmitFix = $isVolunteerCitizen && $isHandler && $c['status'] === 'in_progress' && !$c['after_photo'];
$fixAwaitingValidation = $c['status'] === 'in_progress' && $c['after_photo'] && $c['handler_role'] === 'citizen';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $me) {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'comment') {
        if (comment_post($c, $me, (string) ($_POST['body'] ?? ''))) {
            flash_set('success', t('cm_posted'));
        } else {
            flash_set('error', t('cm_too_short'));
        }

    } elseif ($action === 'delete_comment') {
        $cid = (int) ($_POST['comment_id'] ?? 0);
        $q = db()->prepare('SELECT * FROM comments WHERE id = ? AND complaint_id = ?');
        $q->execute([$cid, $id]);
        $cm = $q->fetch();
        if ($cm && comment_can_delete($cm, $c, $me)) {
            db()->prepare('DELETE FROM comments WHERE id = ?')->execute([$cid]);
            flash_set('success', t('cm_deleted'));
        }

    } elseif ($action === 'citizen_submit_fix' && $canCitizenSubmitFix) {
        $photo = upload_photo($_FILES['after_photo'] ?? [], $err);
        if (!$photo) {
            flash_set('error', t($err ?? 'err_photo_upload'));
        } else {
            db()->prepare('UPDATE complaints SET after_photo = ? WHERE id = ?')->execute([$photo, $id]);
            log_event($id, (int) $me['id'], 'fix_submitted', trim($_POST['note'] ?? '') ?: null);
            award_points((int) $me['id'], 'citizen_fix', $id);
            $admins = db()->prepare("SELECT id FROM users WHERE role = 'admin' AND commune_id = ?");
            $admins->execute([(int) $c['commune_id']]);
            foreach ($admins->fetchAll() as $a) {
                notify((int) $a['id'], 'fix_submitted', $id);
            }
            flash_set('success', t('msg_fix_submitted', POINTS['citizen_fix']));
        }

    } elseif ($action === 'upvote' && $canUpvote && !$hasUpvoted) {
        $ins = db()->prepare('INSERT IGNORE INTO upvotes (complaint_id, user_id, created_at) VALUES (?,?,NOW())');
        $ins->execute([$id, (int) $me['id']]);
        if ($ins->rowCount() > 0) {
            db()->prepare('UPDATE complaints SET upvotes = upvotes + 1 WHERE id = ?')->execute([$id]);
            award_points((int) $me['id'], 'upvote_given', $id);
            award_points((int) $c['user_id'], 'upvote_received', $id);
            notify((int) $c['user_id'], 'new_upvote', $id);
        }
        flash_set('success', t('msg_upvoted'));

    } elseif ($action === 'confirm_fix' && $isOwner && $c['status'] === 'resolved') {
        db()->prepare("UPDATE complaints SET status = 'closed', closed_at = NOW() WHERE id = ?")->execute([$id]);
        log_event($id, (int) $me['id'], 'closed', null);
        award_points((int) $me['id'], 'fix_confirmed', $id);
        if ($c['handler_id']) {
            if ($c['handler_role'] === 'association') {
                award_points((int) $c['handler_id'], 'assoc_fix_confirmed', $id);
            } elseif ($c['handler_role'] === 'citizen') {
                award_points((int) $c['handler_id'], 'citizen_fix_confirmed', $id);
            } else {
                check_badges((int) $c['handler_id']);
            }
            notify((int) $c['handler_id'], 'fix_confirmed', $id);
        }
        flash_set('celebrate', t('msg_fix_confirmed_celebrate', POINTS['fix_confirmed']));

    } elseif ($action === 'reopen' && $isOwner && $c['status'] === 'resolved') {
        $note = trim($_POST['note'] ?? '');
        if (mb_strlen($note) < 5) {
            flash_set('error', t('err_reopen_note'));
        } else {
            db()->prepare("UPDATE complaints SET status = 'in_progress', reopened = 1 WHERE id = ?")->execute([$id]);
            log_event($id, (int) $me['id'], 'reopened', $note);
            if ($c['handler_id']) {
                notify((int) $c['handler_id'], 'complaint_reopened', $id);
            }
            flash_set('success', t('msg_reopened'));
        }

    } elseif ($action === 'take_charge' && $canTakeCharge) {
        $upd = db()->prepare("UPDATE complaints SET status = 'in_progress', handler_id = ? WHERE id = ? AND status = 'published'");
        $upd->execute([(int) $me['id'], $id]);
        if ($upd->rowCount() > 0) {
            log_event($id, (int) $me['id'], 'taken', null);
            award_points((int) $me['id'], $me['role'] === 'association' ? 'assoc_take_charge' : 'take_charge', $id);
            notify((int) $c['user_id'], 'complaint_in_progress', $id);
            flash_set('success', t('msg_taken'));
        }

    } elseif ($action === 'assoc_resolve' && $canAssocResolve) {
        $photo = upload_photo($_FILES['after_photo'] ?? [], $err);
        if (!$photo) {
            flash_set('error', t($err ?? 'err_photo_upload'));
        } else {
            db()->prepare("UPDATE complaints SET status = 'resolved', after_photo = ?, resolved_at = NOW() WHERE id = ?")
                ->execute([$photo, $id]);
            log_event($id, (int) $me['id'], 'resolved', trim($_POST['note'] ?? '') ?: null);
            award_points((int) $me['id'], 'assoc_fix', $id);
            award_points((int) $c['user_id'], 'complaint_resolved', $id);
            notify((int) $c['user_id'], 'complaint_resolved', $id);
            flash_set('success', t('msg_resolved'));
        }
    }
    redirect('complaint.php?id=' . $id);
}

$ev = db()->prepare('SELECT ev.*, u.name AS actor, u.role AS actor_role
    FROM complaint_events ev LEFT JOIN users u ON u.id = ev.user_id
    WHERE ev.complaint_id = ? ORDER BY ev.id');
$ev->execute([$id]);
$events = $ev->fetchAll();

$comments = comments_of($id);

$use_leaflet = $c['lat'] !== null;
$page_title = $c['title'];
// Open Graph: rich preview when shared on Facebook / WhatsApp
$og = [
    'title' => $c['title'],
    'description' => mb_strimwidth($c['description'], 0, 180, '…'),
    'image' => abs_url('uploads/' . rawurlencode($c['after_photo'] ?: $c['photo'])),
    'url' => abs_url('complaint.php?id=' . $id),
];
$shareUrl = $og['url'];
$shareText = $c['title'];
require __DIR__ . '/includes/layout/header.php';
$lvl = level_for((int) $c['reporter_points']);
?>
<div class="container page detail">
  <div class="detail-head">
    <div>
      <span class="ref">#<?= e($c['ref']) ?></span> <?= status_badge($c['status']) ?>
      <?php if ((int) $c['reopened'] === 1 && $c['status'] === 'in_progress'): ?><span class="badge st-rejected"><?= e(t('reopened_flag')) ?></span><?php endif; ?>
      <h1><?= e($c['title']) ?></h1>
      <p class="muted">
        <span class="chip"><?= e($c['icon']) ?> <?= e(lc($c, 'cat')) ?></span>
        📍 <?= e(lc($c, 'commune')) ?> — <?= e(lc($c, 'wilaya')) ?> · <?= e(time_ago($c['created_at'])) ?>
      </p>
      <p class="muted"><?= e(t('reported_by')) ?> <strong><?= e($c['reporter_name']) ?></strong> <span title="<?= e(t($lvl[1])) ?>"><?= $lvl[2] ?></span></p>
      <?php if ($c['handler_name']): ?>
      <p class="muted"><?= e(t('handled_by')) ?> <strong><?= e($c['handler_name']) ?></strong>
        <span class="chip chip-sm"><?= e(t('role_' . ($c['handler_role'] === 'admin' ? 'admin' : ($c['handler_role'] === 'association' ? 'association' : 'citizen')))) ?></span></p>
      <?php endif; ?>
      <?php if ($fixAwaitingValidation): ?>
      <p><span class="badge st-pending">🕓 <?= e(t('fix_awaiting_validation')) ?></span></p>
      <?php endif; ?>
    </div>
    <div class="detail-actions">
      <?php if ($canUpvote || $hasUpvoted): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="upvote">
        <button class="btn <?= $hasUpvoted ? 'btn-ghost' : 'btn-primary' ?>" <?= $hasUpvoted ? 'disabled' : '' ?>>
          👍 <?= e($hasUpvoted ? t('upvoted') : t('upvote_btn')) ?> (<?= (int) $c['upvotes'] ?>)
        </button>
      </form>
      <?php else: ?>
      <span class="upvote-pill">👍 <?= (int) $c['upvotes'] ?> <?= e(t('upvotes')) ?></span>
      <?php endif; ?>
      <?php if ($isZoneAdmin || $isSuper): ?>
      <a class="btn btn-ghost" href="<?= e(url('admin/complaint.php?id=' . $id)) ?>">🛠️ <?= e(t('admin_manage')) ?></a>
      <?php endif; ?>
      <?php require __DIR__ . '/includes/layout/share.php'; ?>
    </div>
  </div>

  <?php if ($c['status'] === 'rejected' && $c['reject_reason']): ?>
  <div class="flash flash-error"><strong><?= e(t('reject_reason')) ?>:</strong> <?= e($c['reject_reason']) ?></div>
  <?php endif; ?>

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

      <div class="card card-pad" id="comments">
        <h2>💬 <?= e(t('cm_title')) ?> (<?= count($comments) ?>)</h2>
        <?php if (!$comments): ?>
        <p class="empty"><?= e(t('cm_empty')) ?></p>
        <?php endif; ?>
        <ul class="comment-list">
          <?php foreach ($comments as $cm): ?>
          <li class="comment <?= (int) $cm['is_official'] === 1 ? 'comment-official' : '' ?>">
            <div class="comment-head">
              <strong><?= e($cm['author']) ?></strong>
              <?php if ((int) $cm['is_official'] === 1): ?>
              <span class="badge st-closed">✔ <?= e(t('cm_official')) ?></span>
              <?php elseif ($cm['author_role'] === 'association'): ?>
              <span class="chip chip-sm">🤝</span>
              <?php endif; ?>
              <span class="muted"><?= e(time_ago($cm['created_at'])) ?></span>
              <?php if (comment_can_delete($cm, $c, $me)): ?>
              <form method="post" class="comment-del" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>');">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete_comment">
                <input type="hidden" name="comment_id" value="<?= (int) $cm['id'] ?>">
                <button class="link-btn" title="<?= e(t('cm_delete')) ?>">🗑️</button>
              </form>
              <?php endif; ?>
            </div>
            <p class="comment-body"><?= nl2br(e($cm['body'])) ?></p>
          </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($me): ?>
        <form method="post" class="form comment-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="comment">
          <label class="field"><span><?= e(t('cm_add')) ?></span>
            <textarea name="body" rows="3" required minlength="2" maxlength="1500"
                      placeholder="<?= e(t('cm_placeholder')) ?>"></textarea>
          </label>
          <button class="btn btn-primary"><?= e(t('cm_send')) ?></button>
        </form>
        <?php else: ?>
        <p class="muted center"><a href="<?= e(url('login.php')) ?>"><?= e(t('cm_login_hint')) ?></a></p>
        <?php endif; ?>
      </div>
      <?php if ($c['lat'] !== null): ?>
      <div id="miniMap" class="mini-map" data-lat="<?= e($c['lat']) ?>" data-lng="<?= e($c['lng']) ?>"></div>
      <?php endif; ?>
    </div>

    <aside>
      <?php if ($isOwner && $c['status'] === 'resolved'): ?>
      <div class="card card-pad action-card">
        <h3>✅ <?= e(t('confirm_title')) ?></h3>
        <p class="muted"><?= e(t('confirm_sub')) ?></p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="confirm_fix">
          <button class="btn btn-primary btn-block"><?= e(t('confirm_btn')) ?></button>
        </form>
        <details class="reopen-box">
          <summary><?= e(t('reopen_link')) ?></summary>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reopen">
            <textarea name="note" rows="3" required minlength="5" placeholder="<?= e(t('reopen_ph')) ?>"></textarea>
            <button class="btn btn-danger btn-block"><?= e(t('reopen_btn')) ?></button>
          </form>
        </details>
      </div>
      <?php endif; ?>

      <?php if ($canTakeCharge): ?>
      <div class="card card-pad action-card">
        <h3>🤝 <?= e(t('take_title')) ?></h3>
        <p class="muted"><?= e($isVolunteerCitizen ? t('take_sub_citizen') : t('take_sub')) ?></p>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="take_charge">
          <button class="btn btn-primary btn-block"><?= e(t('take_btn')) ?></button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($canCitizenSubmitFix): ?>
      <div class="card card-pad action-card">
        <h3>🛠️ <?= e(t('citizen_fix_title')) ?></h3>
        <p class="muted"><?= e(t('citizen_fix_sub')) ?></p>
        <form method="post" enctype="multipart/form-data" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="citizen_submit_fix">
          <label class="field"><span><?= e(t('after_photo')) ?></span>
            <input type="file" name="after_photo" accept="image/jpeg,image/png,image/webp" capture="environment" required>
          </label>
          <label class="field"><span><?= e(t('note_optional')) ?></span>
            <textarea name="note" rows="2" maxlength="500"></textarea>
          </label>
          <button class="btn btn-primary btn-block"><?= e(t('citizen_fix_btn')) ?></button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($canAssocResolve): ?>
      <div class="card card-pad action-card">
        <h3>🛠️ <?= e(t('assoc_resolve_title')) ?></h3>
        <form method="post" enctype="multipart/form-data" class="form">
          <?= csrf_field() ?><input type="hidden" name="action" value="assoc_resolve">
          <label class="field"><span><?= e(t('after_photo')) ?></span>
            <input type="file" name="after_photo" accept="image/jpeg,image/png,image/webp" required>
          </label>
          <label class="field"><span><?= e(t('note_optional')) ?></span>
            <textarea name="note" rows="2" maxlength="500"></textarea>
          </label>
          <button class="btn btn-primary btn-block"><?= e(t('mark_resolved')) ?></button>
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
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
