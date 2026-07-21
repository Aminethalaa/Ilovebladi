<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/associations.php';
$me = require_role('superadmin');
assoc_tables();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $do = $_POST['do'] ?? '';
    if ($do === 'create') {
        $ar = trim($_POST['name_ar'] ?? '');
        $fr = trim($_POST['name_fr'] ?? '');
        $icon = trim($_POST['icon'] ?? '🏷️');
        if ($ar !== '' && $fr !== '') {
            $max = (int) db()->query('SELECT COALESCE(MAX(sort),0) m FROM assoc_categories')->fetch()['m'];
            db()->prepare('INSERT INTO assoc_categories (icon, name_ar, name_fr, is_active, sort) VALUES (?,?,?,1,?)')
                ->execute([mb_substr($icon, 0, 8), $ar, $fr, $max + 1]);
            flash_set('success', t('msg_saved'));
        }
    } elseif ($do === 'toggle') {
        db()->prepare('UPDATE assoc_categories SET is_active = 1 - is_active WHERE id = ?')
            ->execute([(int) ($_POST['id'] ?? 0)]);
        flash_set('success', t('msg_saved'));
    }
    redirect('superadmin/assoc-categories.php');
}

$cats = db()->query('SELECT c.*,
        (SELECT COUNT(*) FROM user_assoc_categories WHERE category_id = c.id) AS used
    FROM assoc_categories c ORDER BY c.sort, c.id')->fetchAll();

$page_title = t('sa_manage_assoc_cats');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page">
  <p><a class="muted" href="<?= e(url('superadmin/index.php')) ?>">← <?= e(t('back')) ?></a></p>
  <div class="page-head"><h1>🏷️ <?= e(t('sa_manage_assoc_cats')) ?></h1></div>

  <div class="detail-grid">
    <div class="table-wrap"><table class="table">
      <thead><tr><th></th><th><?= e(t('cat_name_ar')) ?></th><th><?= e(t('cat_name_fr')) ?></th><th><?= e(t('dir_associations')) ?></th><th><?= e(t('status')) ?></th></tr></thead>
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
        <?php if (!$cats): ?><tr><td colspan="5" class="empty"><?= e(t('no_results')) ?></td></tr><?php endif; ?>
      </tbody>
    </table></div>

    <form method="post" class="form card card-pad">
      <?= csrf_field() ?><input type="hidden" name="do" value="create">
      <h3>➕ <?= e(t('sa_new_assoc_cat')) ?></h3>
      <label class="field"><span><?= e(t('cat_icon')) ?></span><input name="icon" maxlength="8" placeholder="🌿"></label>
      <label class="field"><span><?= e(t('cat_name_ar')) ?></span><input required name="name_ar" maxlength="80"></label>
      <label class="field"><span><?= e(t('cat_name_fr')) ?></span><input required name="name_fr" maxlength="80"></label>
      <button class="btn btn-primary"><?= e(t('save')) ?></button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
