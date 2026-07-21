<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/trees.php';
if (!module_on('trees')) {
    redirect('index.php');
}
$me = require_role('admin', 'association', 'superadmin');
if ($me['role'] === 'association' && (int) $me['is_verified'] !== 1) {
    flash_set('error', t('assoc_pending_banner'));
    redirect('dashboard/index.php');
}
tree_tables();

$errors = [];
$old = ['title' => '', 'description' => '', 'goal' => 100, 'ends_at' => '',
        'wilaya_id' => (int) $me['wilaya_id'], 'commune_id' => (int) $me['commune_id']];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $old['title'] = trim($_POST['title'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $old['goal'] = (int) ($_POST['goal'] ?? 0);
    $old['ends_at'] = trim($_POST['ends_at'] ?? '');
    $old['commune_id'] = (int) ($_POST['commune_id'] ?? 0);

    if (mb_strlen($old['title']) < 5)        $errors[] = t('err_title');
    if (mb_strlen($old['description']) < 20) $errors[] = t('err_description');
    if ($old['goal'] < 5 || $old['goal'] > 100000) $errors[] = t('err_tree_goal');
    $st = db()->prepare('SELECT wilaya_id FROM communes WHERE id = ?');
    $st->execute([$old['commune_id']]);
    $cw = $st->fetch();
    if (!$cw) {
        $errors[] = t('err_commune');
    } else {
        $old['wilaya_id'] = (int) $cw['wilaya_id'];
    }
    // zone admins create campaigns for their own commune only
    if ($me['role'] === 'admin' && $old['commune_id'] !== (int) $me['commune_id']) {
        $errors[] = t('err_commune');
    }
    $ends = null;
    if ($old['ends_at'] !== '') {
        $ts = strtotime($old['ends_at']);
        if (!$ts || $ts < time()) {
            $errors[] = t('err_tree_date');
        } else {
            $ends = date('Y-m-d', $ts);
        }
    }
    if (!$errors) {
        db()->prepare("INSERT INTO tree_campaigns
                (creator_id, wilaya_id, commune_id, title, description, goal, ends_at, status, created_at)
                VALUES (?,?,?,?,?,?,?,'active',NOW())")
            ->execute([(int) $me['id'], $old['wilaya_id'], $old['commune_id'],
                       $old['title'], $old['description'], $old['goal'], $ends]);
        flash_set('success', t('msg_campaign_created'));
        redirect('tree-campaign.php?id=' . (int) db()->lastInsertId());
    }
}

$wilayas = all_wilayas();
$communes = $old['wilaya_id'] ? communes_of((int) $old['wilaya_id']) : [];
$page_title = t('trees_new_campaign');
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container page page-narrow">
  <p><a class="muted" href="<?= e(url('trees.php')) ?>">← <?= e(t('back')) ?></a></p>
  <div class="page-head"><h1>🌳 <?= e(t('trees_new_campaign')) ?></h1>
    <p class="muted"><?= e(t('trees_new_sub')) ?></p></div>
  <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

  <form method="post" class="form card card-pad wizard" data-back="<?= e(t('wz_back')) ?>" data-next="<?= e(t('wz_next')) ?>">
    <?= csrf_field() ?>
    <div class="wizard-step" data-title="<?= e(t('nc_step_details')) ?>">
      <label class="field"><span><?= e(t('nc_title')) ?></span>
        <input required name="title" minlength="5" maxlength="180" value="<?= e($old['title']) ?>" placeholder="<?= e(t('trees_title_ph')) ?>">
      </label>
      <label class="field"><span><?= e(t('description')) ?></span>
        <textarea required name="description" rows="4" minlength="20" maxlength="2000" placeholder="<?= e(t('trees_desc_ph')) ?>"><?= e($old['description']) ?></textarea>
      </label>
    </div>
    <div class="wizard-step" data-title="<?= e(t('trees_step_goal')) ?>">
      <div class="field-row">
        <label class="field"><span>🎯 <?= e(t('trees_goal')) ?></span>
          <input required type="number" name="goal" min="5" max="100000" value="<?= (int) $old['goal'] ?>">
        </label>
        <label class="field"><span>⏳ <?= e(t('trees_deadline')) ?></span>
          <input type="date" name="ends_at" value="<?= e($old['ends_at']) ?>">
        </label>
      </div>
    </div>
    <div class="wizard-step" data-title="<?= e(t('nc_step_location')) ?>">
      <div class="field-row">
        <label class="field"><span><?= e(t('wilaya')) ?></span>
          <select id="wilayaSel" data-communes-url="<?= e(url('api/communes.php')) ?>" <?= $me['role'] === 'admin' ? 'disabled' : '' ?>>
            <?php foreach ($wilayas as $w): ?>
            <option value="<?= (int) $w['id'] ?>" <?= (int) $old['wilaya_id'] === (int) $w['id'] ? 'selected' : '' ?>><?= (int) $w['id'] ?> — <?= e(lc($w, 'name')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field"><span><?= e(t('commune')) ?></span>
          <select name="commune_id" id="communeSel" required <?= $me['role'] === 'admin' ? 'disabled' : '' ?>>
            <?php foreach ($communes as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $old['commune_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e(lc($c, 'name')) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if ($me['role'] === 'admin'): ?>
          <input type="hidden" name="commune_id" value="<?= (int) $me['commune_id'] ?>">
          <?php endif; ?>
        </label>
      </div>
      <button class="btn btn-primary btn-lg btn-block wizard-submit">🌱 <?= e(t('trees_create_btn')) ?></button>
    </div>
  </form>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
