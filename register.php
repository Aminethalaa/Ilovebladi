<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) {
    redirect(role_home(current_user()));
}

$errors = [];
$old = ['type' => 'citizen', 'name' => '', 'email' => '', 'wilaya_id' => '', 'commune_id' => '', 'about' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['type']       = (module_on('associations') && ($_POST['type'] ?? '') === 'association') ? 'association' : 'citizen';
    $old['name']       = trim($_POST['name'] ?? '');
    $old['email']      = mb_strtolower(trim($_POST['email'] ?? ''));
    $old['wilaya_id']  = (int) ($_POST['wilaya_id'] ?? 0);
    $old['commune_id'] = (int) ($_POST['commune_id'] ?? 0);
    $old['about']      = trim($_POST['about'] ?? '');
    $pass  = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');

    if (mb_strlen($old['name']) < 3)                          $errors[] = t('err_name');
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))    $errors[] = t('err_email');
    if (strlen($pass) < 8)                                    $errors[] = t('err_password');
    if ($pass !== $pass2)                                     $errors[] = t('err_password_match');
    if ($old['commune_id'] <= 0)                              $errors[] = t('err_commune');
    if ($old['type'] === 'association' && mb_strlen($old['about']) < 10) $errors[] = t('err_about');

    if ($old['commune_id'] > 0) {
        $st = db()->prepare('SELECT wilaya_id FROM communes WHERE id = ?');
        $st->execute([$old['commune_id']]);
        $cw = $st->fetch();
        if (!$cw) {
            $errors[] = t('err_commune');
        } else {
            $old['wilaya_id'] = (int) $cw['wilaya_id'];
        }
    }
    if (!$errors) {
        $st = db()->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$old['email']]);
        if ($st->fetch()) {
            $errors[] = t('err_email_taken');
        }
    }
    if (!$errors) {
        db()->prepare('INSERT INTO users (role, name, email, password_hash, wilaya_id, commune_id, about, lang, is_verified, created_at)
                       VALUES (?,?,?,?,?,?,?,?,?,NOW())')
            ->execute([
                $old['type'], $old['name'], $old['email'],
                password_hash($pass, PASSWORD_DEFAULT),
                $old['wilaya_id'], $old['commune_id'],
                $old['type'] === 'association' ? $old['about'] : null,
                lang(),
                $old['type'] === 'association' ? 0 : 1,
            ]);
        $st = db()->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$old['email']]);
        login_user($st->fetch());
        flash_set('success', $old['type'] === 'association' ? t('msg_assoc_registered') : t('msg_registered'));
        redirect('dashboard/index.php');
    }
}

$wilayas = all_wilayas();
$communes = $old['wilaya_id'] ? communes_of((int) $old['wilaya_id']) : [];
$page_title = t('nav_register');
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container auth-wrap">
  <div class="card auth-card">
    <h1 class="auth-title"><?= e(t('register_title')) ?></h1>
    <p class="muted center"><?= e(t('register_sub')) ?></p>
    <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form wizard" data-back="<?= e(t('wz_back')) ?>" data-next="<?= e(t('wz_next')) ?>">
      <?= csrf_field() ?>
      <?php if (module_on('associations')): ?>
      <div class="wizard-step" data-title="<?= e(t('reg_step_type')) ?>">
        <label class="field"><span><?= e(t('reg_type_q')) ?></span></label>
        <div class="type-toggle">
          <label class="type-opt <?= $old['type'] === 'citizen' ? 'on' : '' ?>">
            <input type="radio" name="type" value="citizen" <?= $old['type'] === 'citizen' ? 'checked' : '' ?>>
            🧑 <?= e(t('reg_type_citizen')) ?>
          </label>
          <label class="type-opt <?= $old['type'] === 'association' ? 'on' : '' ?>">
            <input type="radio" name="type" value="association" <?= $old['type'] === 'association' ? 'checked' : '' ?>>
            🤝 <?= e(t('reg_type_assoc')) ?>
          </label>
        </div>
      </div>
      <?php else: ?>
      <input type="hidden" name="type" value="citizen">
      <?php endif; ?>

      <div class="wizard-step" data-title="<?= e(t('reg_step_identity')) ?>">
        <label class="field"><span data-label-citizen="<?= e(t('reg_name')) ?>" data-label-assoc="<?= e(t('reg_assoc_name')) ?>" id="nameLabel"><?= e($old['type'] === 'association' ? t('reg_assoc_name') : t('reg_name')) ?></span>
          <input required name="name" value="<?= e($old['name']) ?>" maxlength="120">
        </label>
        <label class="field"><span><?= e(t('reg_email')) ?></span>
          <input required type="email" name="email" value="<?= e($old['email']) ?>" maxlength="190">
        </label>
        <label class="field assoc-only" <?= $old['type'] !== 'association' ? 'hidden' : '' ?>><span><?= e(t('reg_about')) ?></span>
          <textarea name="about" rows="3" maxlength="600"><?= e($old['about']) ?></textarea>
        </label>
        <p class="hint assoc-only" <?= $old['type'] !== 'association' ? 'hidden' : '' ?>>ℹ️ <?= e(t('reg_assoc_note')) ?></p>
      </div>

      <div class="wizard-step" data-title="<?= e(t('reg_step_location')) ?>">
        <div class="field-row">
          <label class="field"><span><?= e(t('wilaya')) ?></span>
            <select name="wilaya_id" id="wilayaSel" required data-communes-url="<?= e(url('api/communes.php')) ?>">
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
      </div>

      <div class="wizard-step" data-title="<?= e(t('reg_step_password')) ?>">
        <div class="field-row">
          <label class="field"><span><?= e(t('reg_password')) ?></span>
            <input required type="password" name="password" minlength="8">
          </label>
          <label class="field"><span><?= e(t('reg_password2')) ?></span>
            <input required type="password" name="password2" minlength="8">
          </label>
        </div>
        <button class="btn btn-primary btn-lg btn-block wizard-submit"><?= e(t('nav_register')) ?></button>
      </div>
    </form>
    <p class="center muted"><?= e(t('have_account')) ?> <a href="<?= e(url('login.php')) ?>"><?= e(t('nav_login')) ?></a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
