<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('superadmin');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['do'] ?? '') === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = mb_strtolower(trim($_POST['email'] ?? ''));
        $pass = (string) ($_POST['password'] ?? '');
        $communeId = (int) ($_POST['commune_id'] ?? 0);

        if (mb_strlen($name) < 3)                       $errors[] = t('err_name');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('err_email');
        if (strlen($pass) < 8)                          $errors[] = t('err_password');
        $st = db()->prepare('SELECT wilaya_id FROM communes WHERE id = ?');
        $st->execute([$communeId]);
        $cw = $st->fetch();
        if (!$cw) {
            $errors[] = t('err_commune');
        }
        if (!$errors) {
            $st = db()->prepare('SELECT id FROM users WHERE email = ?');
            $st->execute([$email]);
            if ($st->fetch()) {
                $errors[] = t('err_email_taken');
            }
        }
        if (!$errors) {
            db()->prepare("INSERT INTO users (role, name, email, password_hash, wilaya_id, commune_id, lang, is_verified, created_at)
                           VALUES ('admin',?,?,?,?,?,?,1,NOW())")
                ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), (int) $cw['wilaya_id'], $communeId, lang()]);
            flash_set('success', t('msg_admin_created'));
            redirect('superadmin/admins.php');
        }
    } elseif (($_POST['do'] ?? '') === 'delete') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        db()->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'")->execute([$uid]);
        flash_set('success', t('msg_deleted'));
        redirect('superadmin/admins.php');
    }
}

$admins = db()->query("SELECT u.*, cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
        w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr,
        (SELECT COUNT(*) FROM complaints WHERE handler_id = u.id AND status IN ('resolved','closed')) AS fixed
    FROM users u
    LEFT JOIN communes cm ON cm.id = u.commune_id
    LEFT JOIN wilayas w ON w.id = u.wilaya_id
    WHERE u.role = 'admin' ORDER BY u.id DESC")->fetchAll();
$wilayas = all_wilayas();

$page_title = t('sa_manage_admins');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page">
  <p><a class="muted" href="<?= e(url('superadmin/index.php')) ?>">← <?= e(t('back')) ?></a></p>
  <div class="page-head"><h1>🏛️ <?= e(t('sa_manage_admins')) ?></h1></div>
  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

  <div class="detail-grid">
    <div class="table-wrap"><table class="table">
      <thead><tr><th><?= e(t('reg_name')) ?></th><th><?= e(t('commune')) ?></th><th><?= e(t('lb_fixed')) ?></th><th></th></tr></thead>
      <tbody>
        <?php foreach ($admins as $a): ?>
        <tr>
          <td><strong><?= e($a['name']) ?></strong><br><span class="muted"><?= e($a['email']) ?></span></td>
          <td><?= e(lc($a, 'commune')) ?> <span class="muted">— <?= e(lc($a, 'wilaya')) ?></span></td>
          <td><?= (int) $a['fixed'] ?></td>
          <td>
            <form method="post" onsubmit="return confirm('<?= e(t('confirm_delete')) ?>');">
              <?= csrf_field() ?><input type="hidden" name="do" value="delete"><input type="hidden" name="user_id" value="<?= (int) $a['id'] ?>">
              <button class="btn btn-sm btn-danger">🗑️</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$admins): ?><tr><td colspan="4" class="empty"><?= e(t('no_results')) ?></td></tr><?php endif; ?>
      </tbody>
    </table></div>

    <form method="post" class="form card card-pad">
      <?= csrf_field() ?><input type="hidden" name="do" value="create">
      <h3>➕ <?= e(t('sa_new_admin')) ?></h3>
      <label class="field"><span><?= e(t('reg_name')) ?></span><input required name="name" maxlength="120"></label>
      <label class="field"><span><?= e(t('reg_email')) ?></span><input required type="email" name="email" maxlength="190"></label>
      <label class="field"><span><?= e(t('reg_password')) ?></span><input required type="password" name="password" minlength="8"></label>
      <label class="field"><span><?= e(t('wilaya')) ?></span>
        <select id="wilayaSel" required data-communes-url="<?= e(url('api/communes.php')) ?>">
          <option value=""><?= e(t('choose')) ?></option>
          <?php foreach ($wilayas as $w): ?><option value="<?= (int) $w['id'] ?>"><?= (int) $w['id'] ?> — <?= e(lc($w, 'name')) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span><?= e(t('commune')) ?></span>
        <select name="commune_id" id="communeSel" required><option value=""><?= e(t('choose')) ?></option></select>
      </label>
      <button class="btn btn-primary"><?= e(t('sa_create_admin')) ?></button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
