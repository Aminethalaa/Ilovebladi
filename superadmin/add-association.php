<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/associations.php';
$me = require_role('superadmin');
assoc_tables();

$errors = [];
$old = ['name' => '', 'email' => '', 'wilaya_id' => (int) $me['wilaya_id'], 'commune_id' => 0,
        'about' => '', 'phone' => '', 'website' => '', 'facebook' => '', 'address' => '', 'founded_year' => ''];
$oldCats = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['name', 'email', 'about', 'phone', 'website', 'facebook', 'address'] as $k) {
        $old[$k] = trim($_POST[$k] ?? '');
    }
    $old['email'] = mb_strtolower($old['email']);
    $old['commune_id'] = (int) ($_POST['commune_id'] ?? 0);
    $old['founded_year'] = trim($_POST['founded_year'] ?? '');
    $oldCats = array_map('intval', (array) ($_POST['cats'] ?? []));
    $pass = (string) ($_POST['password'] ?? '');

    if (mb_strlen($old['name']) < 3)                        $errors[] = t('err_name');
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))  $errors[] = t('err_email');
    if (strlen($pass) < 8)                                  $errors[] = t('err_password');
    if (mb_strlen($old['about']) < 10)                      $errors[] = t('err_about');
    $st = db()->prepare('SELECT wilaya_id FROM communes WHERE id = ?');
    $st->execute([$old['commune_id']]);
    $cw = $st->fetch();
    if (!$cw) {
        $errors[] = t('err_commune');
    } else {
        $old['wilaya_id'] = (int) $cw['wilaya_id'];
    }
    if (!$errors) {
        $st = db()->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$old['email']]);
        if ($st->fetch()) {
            $errors[] = t('err_email_taken');
        }
    }

    $logo = null;
    if (!$errors && !empty($_FILES['logo']['name'])) {
        $logo = upload_photo($_FILES['logo'], $lerr, 512);
        if (!$logo) {
            $errors[] = t($lerr ?? 'err_photo_upload');
        }
    }

    if (!$errors) {
        // admin-created associations are verified immediately
        db()->prepare("INSERT INTO users (role, name, email, password_hash, wilaya_id, commune_id, about, lang, is_verified, created_at)
                       VALUES ('association',?,?,?,?,?,?,?,1,NOW())")
            ->execute([$old['name'], $old['email'], password_hash($pass, PASSWORD_DEFAULT),
                       $old['wilaya_id'], $old['commune_id'], $old['about'], lang()]);
        $uid = (int) db()->lastInsertId();

        $year = (int) $old['founded_year'];
        $year = ($year >= 1900 && $year <= (int) date('Y')) ? $year : null;
        db()->prepare('INSERT INTO assoc_profiles (user_id, logo, phone, website, facebook, address, founded_year, updated_at)
                       VALUES (?,?,?,?,?,?,?,NOW())')
            ->execute([$uid, $logo,
                       $old['phone'] ?: null, $old['website'] ?: null, $old['facebook'] ?: null,
                       $old['address'] ?: null, $year]);

        if ($oldCats) {
            $valid = array_map('intval', array_column(assoc_all_categories(), 'id'));
            $ins = db()->prepare('INSERT IGNORE INTO user_assoc_categories (user_id, category_id) VALUES (?,?)');
            foreach ($oldCats as $cid) {
                if (in_array($cid, $valid, true)) {
                    $ins->execute([$uid, $cid]);
                }
            }
        }
        flash_set('success', t('msg_assoc_added'));
        redirect('association.php?id=' . $uid);
    }
}

$wilayas = all_wilayas();
$communes = $old['wilaya_id'] ? communes_of((int) $old['wilaya_id']) : [];
$cats = assoc_all_categories();
$page_title = t('sa_add_assoc');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page page-narrow">
  <p><a class="muted" href="<?= e(url('superadmin/users.php?role=association')) ?>">← <?= e(t('back')) ?></a></p>
  <div class="page-head"><h1>🤝 <?= e(t('sa_add_assoc')) ?></h1>
    <p class="muted"><?= e(t('sa_add_assoc_sub')) ?></p></div>
  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" class="form card card-pad wizard"
        data-back="<?= e(t('wz_back')) ?>" data-next="<?= e(t('wz_next')) ?>">
    <?= csrf_field() ?>

    <div class="wizard-step" data-title="<?= e(t('reg_step_identity')) ?>">
      <label class="field"><span><?= e(t('reg_assoc_name')) ?></span>
        <input required name="name" value="<?= e($old['name']) ?>" maxlength="120"></label>
      <label class="field"><span><?= e(t('reg_email')) ?></span>
        <input required type="email" name="email" value="<?= e($old['email']) ?>" maxlength="190"></label>
      <label class="field"><span><?= e(t('reg_password')) ?></span>
        <input required type="password" name="password" minlength="8"></label>
    </div>

    <div class="wizard-step" data-title="<?= e(t('reg_step_location')) ?>">
      <div class="field-row">
        <label class="field"><span><?= e(t('wilaya')) ?></span>
          <select id="wilayaSel" required data-communes-url="<?= e(url('api/communes.php')) ?>">
            <option value=""><?= e(t('choose')) ?></option>
            <?php foreach ($wilayas as $w): ?>
            <option value="<?= (int) $w['id'] ?>" <?= (int) $old['wilaya_id'] === (int) $w['id'] ? 'selected' : '' ?>><?= (int) $w['id'] ?> — <?= e(lc($w, 'name')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field"><span><?= e(t('commune')) ?></span>
          <select name="commune_id" id="communeSel" required>
            <option value=""><?= e(t('choose')) ?></option>
            <?php foreach ($communes as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $old['commune_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(lc($c, 'name')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>
      <label class="field"><span><?= e(t('reg_about')) ?></span>
        <textarea required name="about" rows="3" maxlength="600"><?= e($old['about']) ?></textarea></label>
    </div>

    <div class="wizard-step" data-title="<?= e(t('dir_contact')) ?>">
      <label class="field"><span><?= e(t('assoc_logo')) ?></span>
        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label>
      <div class="field-row">
        <label class="field"><span>📞 <?= e(t('ss_contact_phone')) ?></span>
          <input name="phone" dir="ltr" value="<?= e($old['phone']) ?>" maxlength="40"></label>
        <label class="field"><span>📅 <?= e(t('assoc_founded')) ?></span>
          <input type="number" name="founded_year" min="1900" max="<?= date('Y') ?>" value="<?= e($old['founded_year']) ?>"></label>
      </div>
      <label class="field"><span>🌐 <?= e(t('assoc_website')) ?></span>
        <input name="website" dir="ltr" value="<?= e($old['website']) ?>" maxlength="190" placeholder="https://…"></label>
      <label class="field"><span>📘 <?= e(t('ss_facebook')) ?></span>
        <input name="facebook" dir="ltr" value="<?= e($old['facebook']) ?>" maxlength="190"></label>
      <label class="field"><span>📍 <?= e(t('assoc_address')) ?></span>
        <input name="address" value="<?= e($old['address']) ?>" maxlength="190"></label>
    </div>

    <div class="wizard-step" data-title="<?= e(t('sa_manage_assoc_cats')) ?>">
      <label class="field"><span><?= e(t('assoc_categories_label')) ?></span></label>
      <div class="cat-picker">
        <?php foreach ($cats as $c): $on = in_array((int) $c['id'], $oldCats, true); ?>
        <label class="cat-opt <?= $on ? 'on' : '' ?>">
          <input type="checkbox" name="cats[]" value="<?= (int) $c['id'] ?>" <?= $on ? 'checked' : '' ?>>
          <span class="cat-ico"><?= e($c['icon']) ?></span><?= e(lc($c, 'name')) ?>
        </label>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-primary btn-lg btn-block wizard-submit">✅ <?= e(t('sa_create_assoc')) ?></button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
