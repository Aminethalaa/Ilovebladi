<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/associations.php';
$me = require_role('citizen', 'association');
$isAssoc = $me['role'] === 'association';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $communeId = (int) ($_POST['commune_id'] ?? 0);
    $pass = (string) ($_POST['password'] ?? '');

    if (mb_strlen($name) < 3) {
        $errors[] = t('err_name');
    }
    $st = db()->prepare('SELECT wilaya_id FROM communes WHERE id = ?');
    $st->execute([$communeId]);
    $cw = $st->fetch();
    if (!$cw) {
        $errors[] = t('err_commune');
    }
    if ($pass !== '' && strlen($pass) < 8) {
        $errors[] = t('err_password');
    }

    // association logo (optional)
    $newLogo = null;
    if ($isAssoc && !empty($_FILES['logo']['name'])) {
        $newLogo = upload_photo($_FILES['logo'], $lerr, 512);
        if (!$newLogo) {
            $errors[] = t($lerr ?? 'err_photo_upload');
        }
    }

    if (!$errors) {
        db()->prepare('UPDATE users SET name = ?, commune_id = ?, wilaya_id = ? WHERE id = ?')
            ->execute([$name, $communeId, (int) $cw['wilaya_id'], (int) $me['id']]);
        if ($pass !== '') {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), (int) $me['id']]);
        }

        if ($isAssoc) {
            assoc_tables();
            db()->prepare('UPDATE users SET about = ? WHERE id = ?')
                ->execute([mb_substr(trim($_POST['about'] ?? ''), 0, 600), (int) $me['id']]);

            $year = (int) ($_POST['founded_year'] ?? 0);
            $year = ($year >= 1900 && $year <= (int) date('Y')) ? $year : null;
            $existing = assoc_profile((int) $me['id']);
            $logo = $newLogo ?: ($existing['logo'] ?? null);
            db()->prepare('INSERT INTO assoc_profiles (user_id, logo, phone, website, facebook, address, founded_year, updated_at)
                    VALUES (?,?,?,?,?,?,?,NOW())
                    ON DUPLICATE KEY UPDATE logo = VALUES(logo), phone = VALUES(phone),
                        website = VALUES(website), facebook = VALUES(facebook),
                        address = VALUES(address), founded_year = VALUES(founded_year), updated_at = NOW()')
                ->execute([
                    (int) $me['id'], $logo,
                    mb_substr(trim($_POST['phone'] ?? ''), 0, 40) ?: null,
                    mb_substr(trim($_POST['website'] ?? ''), 0, 190) ?: null,
                    mb_substr(trim($_POST['facebook'] ?? ''), 0, 190) ?: null,
                    mb_substr(trim($_POST['address'] ?? ''), 0, 190) ?: null,
                    $year,
                ]);

            // categories (many-to-many)
            $picked = array_slice(array_map('intval', (array) ($_POST['cats'] ?? [])), 0, 20);
            db()->prepare('DELETE FROM user_assoc_categories WHERE user_id = ?')->execute([(int) $me['id']]);
            if ($picked) {
                $valid = array_column(assoc_all_categories(), 'id');
                $ins = db()->prepare('INSERT IGNORE INTO user_assoc_categories (user_id, category_id) VALUES (?,?)');
                foreach ($picked as $cid) {
                    if (in_array($cid, array_map('intval', $valid), true)) {
                        $ins->execute([(int) $me['id'], $cid]);
                    }
                }
            }
        }

        flash_set('success', t('msg_saved'));
        redirect('dashboard/profile.php');
    }
}

$assocProfile = $isAssoc ? assoc_profile((int) $me['id']) : [];
$assocCats = $isAssoc ? assoc_all_categories() : [];
$assocMyCats = $isAssoc ? assoc_category_ids((int) $me['id']) : [];

$log = db()->prepare('SELECT p.*, c.title FROM points_log p
    LEFT JOIN complaints c ON c.id = p.complaint_id
    WHERE p.user_id = ? ORDER BY p.id DESC LIMIT 25');
$log->execute([(int) $me['id']]);
$pointsLog = $log->fetchAll();
$badges = user_badges((int) $me['id']);
$lvl = level_for((int) $me['points']);

$wilayas = all_wilayas();
$communes = communes_of((int) $me['wilaya_id']);
$page_title = t('nav_profile');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page page-narrow">
  <div class="page-head"><h1>⚙️ <?= e(t('nav_profile')) ?></h1></div>
  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

  <div class="card card-pad profile-hero">
    <span class="profile-lvl"><?= $lvl[2] ?></span>
    <div>
      <h2><?= e($me['name']) ?></h2>
      <p class="muted"><?= e(t($lvl[1])) ?> · <?= (int) $me['points'] ?> <?= e(t('lb_points')) ?></p>
    </div>
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

  <?php if ($isAssoc): ?>
  <div class="card card-pad">
    <p class="muted">🔗 <a href="<?= e(url('association.php?id=' . (int) $me['id'])) ?>"><?= e(t('assoc_view_public')) ?></a>
      <?php if ((int) $me['is_verified'] === 0): ?> · <span class="badge st-pending"><?= e(t('sa_awaiting_verification')) ?></span><?php endif; ?>
    </p>
  </div>
  <?php endif; ?>

  <form method="post" class="form card card-pad" <?= $isAssoc ? 'enctype="multipart/form-data"' : '' ?>>
    <?= csrf_field() ?>
    <h3><?= e(t('profile_edit')) ?></h3>
    <label class="field"><span><?= e($isAssoc ? t('reg_assoc_name') : t('reg_name')) ?></span>
      <input required name="name" value="<?= e($me['name']) ?>" maxlength="120">
    </label>
    <div class="field-row">
      <label class="field"><span><?= e(t('wilaya')) ?></span>
        <select id="wilayaSel" data-communes-url="<?= e(url('api/communes.php')) ?>">
          <?php foreach ($wilayas as $w): ?>
          <option value="<?= (int) $w['id'] ?>" <?= (int) $me['wilaya_id'] === (int) $w['id'] ? 'selected' : '' ?>><?= (int) $w['id'] ?> — <?= e(lc($w, 'name')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span><?= e(t('commune')) ?></span>
        <select name="commune_id" id="communeSel" required>
          <?php foreach ($communes as $c): ?>
          <option value="<?= (int) $c['id'] ?>" <?= (int) $me['commune_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(lc($c, 'name')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <?php if ($isAssoc): ?>
    <label class="field"><span><?= e(t('reg_about')) ?></span>
      <textarea name="about" rows="4" maxlength="600"><?= e($me['about'] ?? '') ?></textarea>
    </label>
    <label class="field"><span><?= e(t('assoc_logo')) ?></span>
      <?php if (!empty($assocProfile['logo'])): ?>
      <img src="<?= e(upload_url($assocProfile['logo'])) ?>" alt="" style="max-height:64px;border-radius:12px;margin-bottom:6px;">
      <?php endif; ?>
      <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
      <span class="hint"><?= e(t('assoc_logo_hint')) ?></span>
    </label>
    <div class="field-row">
      <label class="field"><span>📞 <?= e(t('ss_contact_phone')) ?></span>
        <input name="phone" dir="ltr" value="<?= e($assocProfile['phone'] ?? '') ?>" maxlength="40"></label>
      <label class="field"><span>📅 <?= e(t('assoc_founded')) ?></span>
        <input type="number" name="founded_year" min="1900" max="<?= date('Y') ?>" value="<?= e($assocProfile['founded_year'] ?? '') ?>"></label>
    </div>
    <div class="field-row">
      <label class="field"><span>🌐 <?= e(t('assoc_website')) ?></span>
        <input name="website" dir="ltr" value="<?= e($assocProfile['website'] ?? '') ?>" maxlength="190" placeholder="https://…"></label>
      <label class="field"><span>📘 <?= e(t('ss_facebook')) ?></span>
        <input name="facebook" dir="ltr" value="<?= e($assocProfile['facebook'] ?? '') ?>" maxlength="190" placeholder="https://facebook.com/…"></label>
    </div>
    <label class="field"><span>📍 <?= e(t('assoc_address')) ?></span>
      <input name="address" value="<?= e($assocProfile['address'] ?? '') ?>" maxlength="190"></label>

    <?php if ($assocCats): ?>
    <label class="field"><span><?= e(t('assoc_categories_label')) ?></span></label>
    <div class="cat-picker">
      <?php foreach ($assocCats as $c): $on = in_array((int) $c['id'], $assocMyCats, true); ?>
      <label class="cat-opt <?= $on ? 'on' : '' ?>">
        <input type="checkbox" name="cats[]" value="<?= (int) $c['id'] ?>" <?= $on ? 'checked' : '' ?>>
        <span class="cat-ico"><?= e($c['icon']) ?></span><?= e(lc($c, 'name')) ?>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <label class="field"><span><?= e(t('profile_new_password')) ?></span>
      <input type="password" name="password" minlength="8" placeholder="<?= e(t('profile_pass_ph')) ?>">
    </label>
    <button class="btn btn-primary"><?= e(t('save')) ?></button>
  </form>

  <div class="card card-pad">
    <h3>🧮 <?= e(t('points_history')) ?></h3>
    <?php if ($pointsLog): ?>
    <ul class="points-log">
      <?php foreach ($pointsLog as $p): ?>
      <li>
        <span class="pl-pts">+<?= (int) $p['points'] ?></span>
        <span><?= e(t('pts_' . $p['reason'])) ?><?php if ($p['title']): ?> — <span class="muted"><?= e($p['title']) ?></span><?php endif; ?></span>
        <span class="muted"><?= e(time_ago($p['created_at'])) ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php else: ?><p class="empty"><?= e(t('no_results')) ?></p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
