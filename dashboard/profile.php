<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('citizen', 'association');

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
    if (!$errors) {
        db()->prepare('UPDATE users SET name = ?, commune_id = ?, wilaya_id = ? WHERE id = ?')
            ->execute([$name, $communeId, (int) $cw['wilaya_id'], (int) $me['id']]);
        if ($pass !== '') {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), (int) $me['id']]);
        }
        flash_set('success', t('msg_saved'));
        redirect('dashboard/profile.php');
    }
}

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

  <form method="post" class="form card card-pad">
    <?= csrf_field() ?>
    <h3><?= e(t('profile_edit')) ?></h3>
    <label class="field"><span><?= e(t('reg_name')) ?></span>
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
