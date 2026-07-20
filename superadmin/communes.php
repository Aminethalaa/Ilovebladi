<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('superadmin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $wid = (int) ($_POST['wilaya_id'] ?? 0);
    $ar = trim($_POST['name_ar'] ?? '');
    $fr = trim($_POST['name_fr'] ?? '');
    $st = db()->prepare('SELECT id FROM wilayas WHERE id = ?');
    $st->execute([$wid]);
    if ($st->fetch() && $ar !== '' && $fr !== '') {
        db()->prepare('INSERT INTO communes (wilaya_id, name_ar, name_fr) VALUES (?,?,?)')
            ->execute([$wid, $ar, $fr]);
        flash_set('success', t('msg_saved'));
    } else {
        flash_set('error', t('err_commune'));
    }
    redirect('superadmin/communes.php?wilaya=' . $wid);
}

$fWilaya = (int) ($_GET['wilaya'] ?? 16);
$wilayas = all_wilayas();
$communes = db()->prepare('SELECT c.*, (SELECT COUNT(*) FROM complaints WHERE commune_id = c.id) AS used
    FROM communes c WHERE c.wilaya_id = ? ORDER BY c.name_fr');
$communes->execute([$fWilaya]);
$communes = $communes->fetchAll();

$page_title = t('sa_manage_communes');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page">
  <p><a class="muted" href="<?= e(url('superadmin/index.php')) ?>">← <?= e(t('back')) ?></a></p>
  <div class="page-head"><h1>🗺️ <?= e(t('sa_manage_communes')) ?></h1>
    <p class="muted"><?= e(t('sa_communes_note')) ?></p></div>

  <form method="get" class="filters card">
    <select name="wilaya" onchange="this.form.submit()">
      <?php foreach ($wilayas as $w): ?>
      <option value="<?= (int) $w['id'] ?>" <?= $fWilaya === (int) $w['id'] ? 'selected' : '' ?>><?= (int) $w['id'] ?> — <?= e(lc($w, 'name')) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <div class="detail-grid">
    <div class="table-wrap"><table class="table">
      <thead><tr><th><?= e(t('cat_name_ar')) ?></th><th><?= e(t('cat_name_fr')) ?></th><th><?= e(t('lb_reports')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($communes as $c): ?>
        <tr><td><?= e($c['name_ar']) ?></td><td><?= e($c['name_fr']) ?></td><td><?= (int) $c['used'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$communes): ?><tr><td colspan="3" class="empty"><?= e(t('no_results')) ?></td></tr><?php endif; ?>
      </tbody>
    </table></div>

    <form method="post" class="form card card-pad">
      <?= csrf_field() ?>
      <h3>➕ <?= e(t('sa_new_commune')) ?></h3>
      <label class="field"><span><?= e(t('wilaya')) ?></span>
        <select name="wilaya_id" required>
          <?php foreach ($wilayas as $w): ?>
          <option value="<?= (int) $w['id'] ?>" <?= $fWilaya === (int) $w['id'] ? 'selected' : '' ?>><?= (int) $w['id'] ?> — <?= e(lc($w, 'name')) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field"><span><?= e(t('cat_name_ar')) ?></span><input required name="name_ar" maxlength="120"></label>
      <label class="field"><span><?= e(t('cat_name_fr')) ?></span><input required name="name_fr" maxlength="120"></label>
      <button class="btn btn-primary"><?= e(t('save')) ?></button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
