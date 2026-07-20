<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('superadmin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';
    if ($do === 'create') {
        $ar = trim($_POST['name_ar'] ?? '');
        $fr = trim($_POST['name_fr'] ?? '');
        $icon = trim($_POST['icon'] ?? '🏷️');
        if ($ar !== '' && $fr !== '') {
            db()->prepare('INSERT INTO categories (name_ar, name_fr, icon, is_active) VALUES (?,?,?,1)')
                ->execute([$ar, $fr, mb_substr($icon, 0, 8)]);
            flash_set('success', t('msg_saved'));
        }
    } elseif ($do === 'toggle') {
        db()->prepare('UPDATE categories SET is_active = 1 - is_active WHERE id = ?')
            ->execute([(int) ($_POST['id'] ?? 0)]);
        flash_set('success', t('msg_saved'));
    }
    redirect('superadmin/categories.php');
}

$cats = db()->query('SELECT c.*, (SELECT COUNT(*) FROM complaints WHERE category_id = c.id) AS used
    FROM categories c ORDER BY c.id')->fetchAll();

$page_title = t('sa_manage_categories');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page">
  <p><a class="muted" href="<?= e(url('superadmin/index.php')) ?>">← <?= e(t('back')) ?></a></p>
  <div class="page-head"><h1>🏷️ <?= e(t('sa_manage_categories')) ?></h1></div>

  <div class="detail-grid">
    <div class="table-wrap"><table class="table">
      <thead><tr><th></th><th><?= e(t('cat_name_ar')) ?></th><th><?= e(t('cat_name_fr')) ?></th><th><?= e(t('lb_reports')) ?></th><th><?= e(t('status')) ?></th></tr></thead>
      <tbody>
        <?php foreach ($cats as $c): ?>
        <tr>
          <td class="cat-ico"><?= e($c['icon']) ?></td>
          <td><?= e($c['name_ar']) ?></td>
          <td><?= e($c['name_fr']) ?></td>
          <td><?= (int) $c['used'] ?></td>
          <td>
            <form method="post"><?= csrf_field() ?>
              <input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="btn btn-sm <?= (int) $c['is_active'] === 1 ? 'btn-ghost' : 'btn-danger' ?>">
                <?= (int) $c['is_active'] === 1 ? '✅ ' . e(t('active')) : '🚫 ' . e(t('inactive')) ?>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>

    <form method="post" class="form card card-pad">
      <?= csrf_field() ?><input type="hidden" name="do" value="create">
      <h3>➕ <?= e(t('sa_new_category')) ?></h3>
      <label class="field"><span><?= e(t('cat_icon')) ?></span><input name="icon" maxlength="8" placeholder="🛣️"></label>
      <label class="field"><span><?= e(t('cat_name_ar')) ?></span><input required name="name_ar" maxlength="80"></label>
      <label class="field"><span><?= e(t('cat_name_fr')) ?></span><input required name="name_fr" maxlength="80"></label>
      <button class="btn btn-primary"><?= e(t('save')) ?></button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
