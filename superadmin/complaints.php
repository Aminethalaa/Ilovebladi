<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('superadmin');

$fStatus = (string) ($_GET['status'] ?? '');
$statuses = ['pending', 'published', 'in_progress', 'resolved', 'closed', 'rejected'];
if (!in_array($fStatus, $statuses, true)) {
    $fStatus = '';
}
$fWilaya = (int) ($_GET['wilaya'] ?? 0);

$where = ['1=1'];
$bind = [];
if ($fStatus) { $where[] = 'x.status = ?';    $bind[] = $fStatus; }
if ($fWilaya) { $where[] = 'x.wilaya_id = ?'; $bind[] = $fWilaya; }

$page = max(1, (int) ($_GET['p'] ?? 1));
$per = 25;
$sql = 'FROM complaints x
        JOIN communes cm ON cm.id = x.commune_id
        JOIN wilayas w ON w.id = x.wilaya_id
        JOIN users u ON u.id = x.user_id
        WHERE ' . implode(' AND ', $where);
$st = db()->prepare("SELECT COUNT(*) c $sql");
$st->execute($bind);
$total = (int) $st->fetch()['c'];
$st = db()->prepare("SELECT x.*, cm.name_ar AS commune_ar, cm.name_fr AS commune_fr,
    w.name_ar AS wilaya_ar, w.name_fr AS wilaya_fr, u.name AS reporter
    $sql ORDER BY x.id DESC LIMIT $per OFFSET " . ($page - 1) * $per);
$st->execute($bind);
$rows = $st->fetchAll();
$wilayas = all_wilayas();

$page_title = t('sa_all_complaints');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page">
  <p><a class="muted" href="<?= e(url('superadmin/index.php')) ?>">← <?= e(t('back')) ?></a></p>
  <div class="page-head"><h1>📋 <?= e(t('sa_all_complaints')) ?></h1>
    <p class="muted"><?= e(t('browse_count', $total)) ?></p></div>

  <form class="filters card" method="get">
    <select name="status">
      <option value=""><?= e(t('all_statuses')) ?></option>
      <?php foreach ($statuses as $s): ?>
      <option value="<?= $s ?>" <?= $fStatus === $s ? 'selected' : '' ?>><?= e(t(status_meta($s)['key'])) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="wilaya">
      <option value=""><?= e(t('all_wilayas')) ?></option>
      <?php foreach ($wilayas as $w): ?>
      <option value="<?= (int) $w['id'] ?>" <?= $fWilaya === (int) $w['id'] ? 'selected' : '' ?>><?= (int) $w['id'] ?> — <?= e(lc($w, 'name')) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-primary"><?= e(t('filter')) ?></button>
  </form>

  <div class="table-wrap"><table class="table">
    <thead><tr><th><?= e(t('ref')) ?></th><th><?= e(t('nc_title')) ?></th><th><?= e(t('commune')) ?></th>
      <th><?= e(t('reported_by')) ?></th><th><?= e(t('status')) ?></th><th></th></tr></thead>
    <tbody>
      <?php foreach ($rows as $r): ?>
      <tr>
        <td>#<?= e($r['ref']) ?></td>
        <td><?= e($r['title']) ?></td>
        <td><?= e(lc($r, 'commune')) ?> <span class="muted">— <?= e(lc($r, 'wilaya')) ?></span></td>
        <td><?= e($r['reporter']) ?></td>
        <td><?= status_badge($r['status']) ?></td>
        <td><a class="btn btn-sm btn-ghost" href="<?= e(url('admin/complaint.php?id=' . (int) $r['id'])) ?>"><?= e(t('admin_manage')) ?></a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="6" class="empty"><?= e(t('no_results')) ?></td></tr><?php endif; ?>
    </tbody>
  </table></div>

  <?php $pages = (int) ceil($total / $per); if ($pages > 1): ?>
  <nav class="pager">
    <?php for ($i = 1; $i <= $pages; $i++):
        $qs = http_build_query(array_merge($_GET, ['p' => $i])); ?>
      <a class="<?= $i === $page ? 'on' : '' ?>" href="?<?= e($qs) ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
