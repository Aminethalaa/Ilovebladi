<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('superadmin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $uid = (int) ($_POST['user_id'] ?? 0);
    $do = $_POST['do'] ?? '';
    if ($do === 'verify') {
        db()->prepare("UPDATE users SET is_verified = 1 WHERE id = ? AND role = 'association'")->execute([$uid]);
        notify($uid, 'assoc_verified', null);
        flash_set('success', t('msg_assoc_verified'));
    } elseif ($do === 'block') {
        db()->prepare("UPDATE users SET is_blocked = 1 - is_blocked WHERE id = ? AND role IN ('citizen','association')")->execute([$uid]);
        flash_set('success', t('msg_saved'));
    }
    redirect('superadmin/users.php?' . http_build_query(array_filter([
        'role' => $_GET['role'] ?? '', 'verified' => $_GET['verified'] ?? '',
    ], function ($v) { return $v !== ''; })));
}

$fRole = $_GET['role'] ?? '';
if (!in_array($fRole, ['citizen', 'association'], true)) {
    $fRole = '';
}
$fVerified = ($_GET['verified'] ?? '') === '0' ? '0' : '';

$where = ["u.role IN ('citizen','association')"];
$bind = [];
if ($fRole)            { $where[] = 'u.role = ?';        $bind[] = $fRole; }
if ($fVerified === '0') { $where[] = 'u.is_verified = 0'; }

$st = db()->prepare('SELECT u.*, cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
        w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr
    FROM users u
    LEFT JOIN communes cm ON cm.id = u.commune_id
    LEFT JOIN wilayas w ON w.id = u.wilaya_id
    WHERE ' . implode(' AND ', $where) . ' ORDER BY u.id DESC LIMIT 200');
$st->execute($bind);
$rows = $st->fetchAll();

$page_title = t('sa_manage_users');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page">
  <p><a class="muted" href="<?= e(url('superadmin/index.php')) ?>">← <?= e(t('back')) ?></a></p>
  <div class="page-head"><h1>👥 <?= e(t('sa_manage_users')) ?></h1></div>

  <nav class="tabs">
    <a class="<?= $fRole === '' ? 'on' : '' ?>" href="?"><?= e(t('sa_all_users')) ?></a>
    <a class="<?= $fRole === 'citizen' ? 'on' : '' ?>" href="?role=citizen">🧑 <?= e(t('reg_type_citizen')) ?></a>
    <a class="<?= $fRole === 'association' && $fVerified === '' ? 'on' : '' ?>" href="?role=association">🤝 <?= e(t('reg_type_assoc')) ?></a>
    <a class="<?= $fRole === 'association' && $fVerified === '0' ? 'on' : '' ?>" href="?role=association&verified=0">⏳ <?= e(t('sa_awaiting_verification')) ?></a>
  </nav>

  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= e(t('reg_name')) ?></th><th><?= e(t('commune')) ?></th><th><?= e(t('lb_points')) ?></th><th><?= e(t('status')) ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $u): ?>
      <tr>
        <td>
          <strong><?= e($u['name']) ?></strong>
          <?php if ($u['role'] === 'association'): ?><span class="chip chip-sm">🤝</span><?php endif; ?>
          <br><span class="muted"><?= e($u['email']) ?></span>
          <?php if ($u['about']): ?><br><span class="muted"><?= e(mb_substr($u['about'], 0, 120)) ?></span><?php endif; ?>
        </td>
        <td><?= e(lc($u, 'commune')) ?> <span class="muted">— <?= e(lc($u, 'wilaya')) ?></span></td>
        <td><?= (int) $u['points'] ?></td>
        <td>
          <?php if ((int) $u['is_blocked'] === 1): ?><span class="badge st-rejected"><?= e(t('blocked')) ?></span>
          <?php elseif ($u['role'] === 'association' && (int) $u['is_verified'] === 0): ?><span class="badge st-pending"><?= e(t('sa_awaiting_verification')) ?></span>
          <?php else: ?><span class="badge st-closed"><?= e(t('active')) ?></span><?php endif; ?>
        </td>
        <td class="actions-cell">
          <?php if ($u['role'] === 'association' && (int) $u['is_verified'] === 0): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="verify"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
            <button class="btn btn-sm btn-primary">✅ <?= e(t('sa_verify')) ?></button>
          </form>
          <?php endif; ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="block"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
            <button class="btn btn-sm <?= (int) $u['is_blocked'] === 1 ? 'btn-ghost' : 'btn-danger' ?>">
              <?= (int) $u['is_blocked'] === 1 ? e(t('sa_unblock')) : e(t('sa_block')) ?>
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="empty"><?= e(t('no_results')) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
