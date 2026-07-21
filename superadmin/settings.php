<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/webpush.php';
$me = require_role('superadmin');
push_tables(); // ensures the settings table exists

/** Cover-crop the uploaded logo into square PWA icons stored in uploads/. */
function generate_site_icons(string $logoFile): void
{
    $src = UPLOAD_PATH . '/' . $logoFile;
    $info = @getimagesize($src);
    if (!$info) {
        return;
    }
    switch ($info['mime']) {
        case 'image/jpeg': $im = @imagecreatefromjpeg($src); break;
        case 'image/png':  $im = @imagecreatefrompng($src); break;
        case 'image/webp': $im = @imagecreatefromwebp($src); break;
        default:           $im = null;
    }
    if (!$im) {
        return;
    }
    $w = imagesx($im);
    $h = imagesy($im);
    $side = min($w, $h);
    foreach ([512 => 'site_icon_512', 192 => 'site_icon_192'] as $size => $key) {
        $dst = imagecreatetruecolor($size, $size);
        imagefilledrectangle($dst, 0, 0, $size, $size, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $im, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $size, $size, $side, $side);
        $name = $key . '_' . substr(md5((string) mt_rand()), 0, 6) . '.png';
        imagepng($dst, UPLOAD_PATH . '/' . $name, 6);
        imagedestroy($dst);
        setting_set($key, $name);
    }
    imagedestroy($im);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // feature modules — complaints and trees cannot both be off
    $modComplaints = !empty($_POST['mod_complaints']);
    $modTrees = !empty($_POST['mod_trees']);
    if (!$modComplaints && !$modTrees) {
        flash_set('error', t('err_modules'));
        redirect('superadmin/settings.php');
    }
    setting_set('mod_complaints', $modComplaints ? '' : '0');
    setting_set('mod_trees', $modTrees ? '' : '0');
    setting_set('mod_leaderboard', !empty($_POST['mod_leaderboard']) ? '' : '0');
    setting_set('mod_associations', !empty($_POST['mod_associations']) ? '' : '0');

    // texts (empty value = fall back to the built-in default)
    foreach (['site_name_ar', 'site_name_fr', 'site_tagline_ar', 'site_tagline_fr',
              'hero_title_ar', 'hero_title_fr', 'hero_sub_ar', 'hero_sub_fr',
              'contact_email', 'contact_phone', 'social_facebook'] as $k) {
        setting_set($k, mb_substr(trim($_POST[$k] ?? ''), 0, 300));
    }

    // colors
    if (!empty($_POST['colors_reset'])) {
        foreach (['color_primary', 'color_accent', 'color_gold'] as $k) {
            setting_set($k, '');
        }
    } else {
        foreach (['color_primary', 'color_accent', 'color_gold'] as $k) {
            $c = trim($_POST[$k] ?? '');
            if (preg_match('/^#[0-9a-fA-F]{6}$/', $c)) {
                setting_set($k, strtolower($c));
            }
        }
    }

    // logo
    if (!empty($_POST['logo_remove'])) {
        foreach (['site_logo', 'site_icon_192', 'site_icon_512'] as $k) {
            setting_set($k, '');
        }
    } elseif (!empty($_FILES['logo']['name'])) {
        $logo = upload_photo($_FILES['logo'], $lerr);
        if ($logo) {
            setting_set('site_logo', $logo);
            generate_site_icons($logo);
        } else {
            flash_set('error', t($lerr ?? 'err_photo_upload'));
            redirect('superadmin/settings.php');
        }
    }

    flash_set('success', t('msg_saved'));
    redirect('superadmin/settings.php');
}

$S = fn(string $k): string => site_setting($k);
$page_title = t('sa_site_settings');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page page-narrow">
  <p><a class="muted" href="<?= e(url('superadmin/index.php')) ?>">← <?= e(t('back')) ?></a></p>
  <div class="page-head">
    <div>
      <h1>🎨 <?= e(t('sa_site_settings')) ?></h1>
      <p class="muted"><?= e(t('ss_sub')) ?></p>
    </div>
  </div>

  <form method="post" enctype="multipart/form-data" class="form">
    <?= csrf_field() ?>

    <div class="card card-pad">
      <h3>🏷️ <?= e(t('ss_identity')) ?></h3>
      <p class="hint"><?= e(t('ss_placeholder_default')) ?></p>
      <div class="field-row">
        <label class="field"><span><?= e(t('ss_name_ar')) ?></span>
          <input name="site_name_ar" dir="rtl" value="<?= e($S('site_name_ar')) ?>" placeholder="<?= e(APP_NAME_AR) ?>"></label>
        <label class="field"><span><?= e(t('ss_name_fr')) ?></span>
          <input name="site_name_fr" dir="ltr" value="<?= e($S('site_name_fr')) ?>" placeholder="<?= e(APP_NAME_FR) ?>"></label>
      </div>
      <label class="field"><span><?= e(t('ss_tagline_ar')) ?></span>
        <textarea name="site_tagline_ar" dir="rtl" rows="2" maxlength="300"><?= e($S('site_tagline_ar')) ?></textarea></label>
      <label class="field"><span><?= e(t('ss_tagline_fr')) ?></span>
        <textarea name="site_tagline_fr" dir="ltr" rows="2" maxlength="300"><?= e($S('site_tagline_fr')) ?></textarea></label>
    </div>

    <div class="card card-pad">
      <h3>🖼️ <?= e(t('ss_logo')) ?></h3>
      <?php if (site_logo_url()): ?>
      <p><img src="<?= e(site_logo_url()) ?>" alt="" style="max-height:80px;border-radius:12px;"></p>
      <label class="field"><span><input type="checkbox" name="logo_remove" value="1"> <?= e(t('ss_logo_remove')) ?></span></label>
      <?php endif; ?>
      <label class="field"><span><?= e(t('ss_logo_upload')) ?></span>
        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp"></label>
      <p class="hint"><?= e(t('ss_logo_hint')) ?></p>
    </div>

    <div class="card card-pad">
      <h3>🏠 <?= e(t('ss_homepage')) ?></h3>
      <div class="field-row">
        <label class="field"><span><?= e(t('ss_hero_title_ar')) ?></span>
          <input name="hero_title_ar" dir="rtl" value="<?= e($S('hero_title_ar')) ?>"></label>
        <label class="field"><span><?= e(t('ss_hero_title_fr')) ?></span>
          <input name="hero_title_fr" dir="ltr" value="<?= e($S('hero_title_fr')) ?>"></label>
      </div>
      <label class="field"><span><?= e(t('ss_hero_sub_ar')) ?></span>
        <textarea name="hero_sub_ar" dir="rtl" rows="2" maxlength="300"><?= e($S('hero_sub_ar')) ?></textarea></label>
      <label class="field"><span><?= e(t('ss_hero_sub_fr')) ?></span>
        <textarea name="hero_sub_fr" dir="ltr" rows="2" maxlength="300"><?= e($S('hero_sub_fr')) ?></textarea></label>
    </div>

    <div class="card card-pad">
      <h3>🎨 <?= e(t('ss_colors')) ?></h3>
      <div class="color-row">
        <label class="field color-field"><span><?= e(t('ss_color_primary')) ?></span>
          <input type="color" name="color_primary" value="<?= e($S('color_primary') ?: '#006233') ?>"></label>
        <label class="field color-field"><span><?= e(t('ss_color_accent')) ?></span>
          <input type="color" name="color_accent" value="<?= e($S('color_accent') ?: '#d21034') ?>"></label>
        <label class="field color-field"><span><?= e(t('ss_color_gold')) ?></span>
          <input type="color" name="color_gold" value="<?= e($S('color_gold') ?: '#c8a951') ?>"></label>
      </div>
      <label class="field"><span><input type="checkbox" name="colors_reset" value="1"> <?= e(t('ss_colors_reset')) ?></span></label>
    </div>

    <div class="card card-pad">
      <h3>🧩 <?= e(t('ss_modules')) ?></h3>
      <p class="hint"><?= e(t('ss_modules_hint')) ?></p>
      <div class="modules-grid">
        <label class="field"><span><input type="checkbox" name="mod_complaints" value="1" <?= module_on('complaints') ? 'checked' : '' ?>> 📣 <?= e(t('ss_mod_complaints')) ?></span></label>
        <label class="field"><span><input type="checkbox" name="mod_trees" value="1" <?= module_on('trees') ? 'checked' : '' ?>> 🌳 <?= e(t('ss_mod_trees')) ?></span></label>
        <label class="field"><span><input type="checkbox" name="mod_leaderboard" value="1" <?= module_on('leaderboard') ? 'checked' : '' ?>> 🏆 <?= e(t('ss_mod_leaderboard')) ?></span></label>
        <label class="field"><span><input type="checkbox" name="mod_associations" value="1" <?= module_on('associations') ? 'checked' : '' ?>> 🤝 <?= e(t('ss_mod_assoc')) ?></span></label>
      </div>
    </div>

    <div class="card card-pad">
      <h3>📞 <?= e(t('ss_contact')) ?></h3>
      <div class="field-row">
        <label class="field"><span><?= e(t('ss_contact_email')) ?></span>
          <input type="email" name="contact_email" dir="ltr" value="<?= e($S('contact_email')) ?>"></label>
        <label class="field"><span><?= e(t('ss_contact_phone')) ?></span>
          <input name="contact_phone" dir="ltr" value="<?= e($S('contact_phone')) ?>"></label>
      </div>
      <label class="field"><span><?= e(t('ss_facebook')) ?></span>
        <input name="social_facebook" dir="ltr" value="<?= e($S('social_facebook')) ?>" placeholder="https://facebook.com/..."></label>
    </div>

    <button class="btn btn-primary btn-lg btn-block">💾 <?= e(t('save')) ?></button>
  </form>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
