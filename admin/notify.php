<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/webpush.php';
$me = require_role('admin', 'superadmin');
$isSuper = $me['role'] === 'superadmin';

$supported = push_supported() && vapid_keys();
push_tables();

/** Device subscriptions for the selected audience. */
function audience_subs(array $me, string $aud, int $wilayaId): array
{
    if ($me['role'] === 'admin') {
        $st = db()->prepare('SELECT ps.* FROM push_subscriptions ps JOIN users u ON u.id = ps.user_id
            WHERE u.commune_id = ? AND u.is_blocked = 0 LIMIT 1000');
        $st->execute([(int) $me['commune_id']]);
        return $st->fetchAll();
    }
    if ($aud === 'wilaya' && $wilayaId > 0) {
        $st = db()->prepare('SELECT ps.* FROM push_subscriptions ps JOIN users u ON u.id = ps.user_id
            WHERE u.wilaya_id = ? AND u.is_blocked = 0 LIMIT 1000');
        $st->execute([$wilayaId]);
        return $st->fetchAll();
    }
    return db()->query('SELECT ps.* FROM push_subscriptions ps JOIN users u ON u.id = ps.user_id
        WHERE u.is_blocked = 0 LIMIT 1000')->fetchAll();
}

$aud = ($isSuper && ($_REQUEST['audience'] ?? '') === 'wilaya') ? 'wilaya' : 'all';
$audWilaya = (int) ($_REQUEST['wilaya_id'] ?? 0);
$deviceCount = $supported ? count(audience_subs($me, $aud, $audWilaya)) : 0;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $supported) {
    csrf_check();
    $title = trim($_POST['title'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $errors = [];
    if (mb_strlen($title) < 3 || mb_strlen($title) > 80) {
        $errors[] = t('err_notify_title');
    }
    if (mb_strlen($body) < 5 || mb_strlen($body) > 300) {
        $errors[] = t('err_notify_body');
    }
    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $image = upload_photo($_FILES['image'], $ierr);
        if (!$image) {
            $errors[] = t($ierr ?? 'err_photo_upload');
        }
    }
    if ($link !== '' && !preg_match('#^(https?://|/)#', $link)) {
        $link = '/' . ltrim($link, '/');
    }
    if ($errors) {
        flash_set('error', implode(' ', $errors));
    } else {
        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => url('assets/img/icon-192.png'),
            'url' => $link !== '' ? $link : url('index.php'),
        ];
        if ($image) {
            $payload['image'] = upload_url($image);
        }
        $subs = audience_subs($me, $aud, $audWilaya);
        @set_time_limit(300);
        $sent = 0;
        $dead = 0;
        $fail = 0;
        foreach ($subs as $sub) {
            $code = webpush_send($sub, $payload);
            if ($code >= 200 && $code < 300) {
                $sent++;
            } elseif ($code === 404 || $code === 410) {
                db()->prepare('DELETE FROM push_subscriptions WHERE id = ?')->execute([$sub['id']]);
                $dead++;
            } else {
                $fail++;
            }
        }
        flash_set($sent > 0 ? 'success' : 'error', t('notify_result', $sent, $dead, $fail));
        redirect('admin/notify.php' . ($isSuper ? '?audience=' . $aud . '&wilaya_id=' . $audWilaya : ''));
    }
}

$wilayas = $isSuper ? all_wilayas() : [];
$page_title = t('notify_page_title');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page page-narrow">
  <p><a class="muted" href="<?= e(url(role_home($me))) ?>">← <?= e(t('back')) ?></a></p>
  <div class="page-head">
    <div>
      <h1>📣 <?= e(t('notify_page_title')) ?></h1>
      <p class="muted"><?= $isSuper ? e(t('notify_sub_super')) : e(t('notify_sub_admin', lc($me, 'commune'))) ?></p>
    </div>
  </div>

  <?php if (!$supported): ?>
  <div class="flash flash-error"><?= e(t('notify_unsupported')) ?></div>
  <?php else: ?>

  <div class="stat-row">
    <div class="stat-card"><span class="stat-num"><?= $deviceCount ?></span><span class="stat-label"><?= e(t('notify_devices')) ?></span></div>
  </div>
  <?php if ($deviceCount === 0): ?><div class="flash flash-warn">ℹ️ <?= e(t('notify_none')) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="form card card-pad">
    <?= csrf_field() ?>
    <?php if ($isSuper): ?>
    <div class="field-row">
      <label class="field"><span><?= e(t('notify_audience')) ?></span>
        <select name="audience" onchange="location.search = '?audience=' + this.value;">
          <option value="all" <?= $aud === 'all' ? 'selected' : '' ?>><?= e(t('notify_audience_all')) ?></option>
          <option value="wilaya" <?= $aud === 'wilaya' ? 'selected' : '' ?>><?= e(t('notify_audience_wilaya')) ?></option>
        </select>
      </label>
      <label class="field" id="wilayaWrap" <?= $aud !== 'wilaya' ? 'hidden' : '' ?>><span><?= e(t('wilaya')) ?></span>
        <select name="wilaya_id" onchange="location.search = '?audience=wilaya&wilaya_id=' + this.value;">
          <?php foreach ($wilayas as $w): ?>
          <option value="<?= (int) $w['id'] ?>" <?= $audWilaya === (int) $w['id'] ? 'selected' : '' ?>><?= (int) $w['id'] ?> — <?= e(lc($w, 'name')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <?php endif; ?>
    <label class="field"><span><?= e(t('notify_title_field')) ?></span>
      <input required name="title" maxlength="80" placeholder="<?= e(t('notify_title_ph')) ?>">
    </label>
    <label class="field"><span><?= e(t('notify_body_field')) ?></span>
      <textarea required name="body" rows="3" maxlength="300" placeholder="<?= e(t('notify_body_ph')) ?>"></textarea>
    </label>
    <label class="field"><span><?= e(t('notify_image_field')) ?></span>
      <input type="file" name="image" accept="image/jpeg,image/png,image/webp">
    </label>
    <label class="field"><span><?= e(t('notify_link_field')) ?></span>
      <input name="link" maxlength="300" placeholder="<?= e(url('complaints.php')) ?>">
    </label>
    <p class="hint">ℹ️ <?= e(t('notify_hint')) ?></p>
    <button class="btn btn-primary btn-lg btn-block" <?= $deviceCount === 0 ? 'disabled' : '' ?>>📣 <?= e(t('notify_send_btn')) ?></button>
  </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
