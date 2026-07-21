<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_role('superadmin');

$S = db()->query("SELECT
    (SELECT COUNT(*) FROM complaints) AS total,
    (SELECT COUNT(*) FROM complaints WHERE status = 'pending') AS pending,
    (SELECT COUNT(*) FROM complaints WHERE status IN ('resolved','closed')) AS resolved,
    (SELECT COUNT(*) FROM users WHERE role = 'citizen') AS citizens,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') AS admins,
    (SELECT COUNT(*) FROM users WHERE role = 'association') AS assocs,
    (SELECT COUNT(*) FROM users WHERE role = 'association' AND is_verified = 0) AS assocs_pending")->fetch();

$recent = db()->query("SELECT x.id, x.ref, x.title, x.status, x.created_at, cm.name_ar AS commune_ar, cm.name_fr AS commune_fr
    FROM complaints x JOIN communes cm ON cm.id = x.commune_id
    ORDER BY x.id DESC LIMIT 10")->fetchAll();

$page_title = t('sa_title');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page">
  <div class="page-head"><h1>👑 <?= e(t('sa_title')) ?></h1></div>

  <div class="stat-row">
    <div class="stat-card"><span class="stat-num"><?= (int) $S['total'] ?></span><span class="stat-label"><?= e(t('stat_total')) ?></span></div>
    <div class="stat-card stat-warn"><span class="stat-num"><?= (int) $S['pending'] ?></span><span class="stat-label"><?= e(t('st_pending')) ?></span></div>
    <div class="stat-card stat-green"><span class="stat-num"><?= (int) $S['resolved'] ?></span><span class="stat-label"><?= e(t('stat_resolved')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= (int) $S['citizens'] ?></span><span class="stat-label"><?= e(t('sa_citizens')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= (int) $S['admins'] ?></span><span class="stat-label"><?= e(t('sa_admins')) ?></span></div>
    <div class="stat-card"><span class="stat-num"><?= (int) $S['assocs'] ?></span><span class="stat-label"><?= e(t('sa_assocs')) ?></span></div>
  </div>

  <?php if ((int) $S['assocs_pending'] > 0): ?>
  <div class="flash flash-warn">🤝 <?= e(t('sa_assoc_pending', (int) $S['assocs_pending'])) ?>
    <a href="<?= e(url('superadmin/users.php?role=association&verified=0')) ?>"><?= e(t('sa_review_now')) ?></a></div>
  <?php endif; ?>

  <div class="quick-links">
    <a class="card card-pad quick" href="<?= e(url('superadmin/complaints.php')) ?>">📋 <?= e(t('sa_all_complaints')) ?></a>
    <a class="card card-pad quick" href="<?= e(url('superadmin/admins.php')) ?>">🏛️ <?= e(t('sa_manage_admins')) ?></a>
    <a class="card card-pad quick" href="<?= e(url('superadmin/users.php')) ?>">👥 <?= e(t('sa_manage_users')) ?></a>
    <a class="card card-pad quick" href="<?= e(url('superadmin/categories.php')) ?>">🏷️ <?= e(t('sa_manage_categories')) ?></a>
    <a class="card card-pad quick" href="<?= e(url('superadmin/communes.php')) ?>">🗺️ <?= e(t('sa_manage_communes')) ?></a>
    <a class="card card-pad quick" href="<?= e(url('admin/notify.php')) ?>">📣 <?= e(t('notify_page_title')) ?></a>
    <a class="card card-pad quick" href="<?= e(url('superadmin/settings.php')) ?>">🎨 <?= e(t('sa_site_settings')) ?></a>
    <a class="card card-pad quick" href="<?= e(url('superadmin/assoc-categories.php')) ?>">🏷️ <?= e(t('sa_manage_assoc_cats')) ?></a>
  </div>

  <?php require BASE_PATH . '/includes/layout/push-card.php'; ?>

  <section class="dash-section">
    <h2><?= e(t('sa_recent')) ?></h2>
    <div class="table-wrap"><table class="table">
      <thead><tr><th><?= e(t('ref')) ?></th><th><?= e(t('nc_title')) ?></th><th><?= e(t('commune')) ?></th><th><?= e(t('status')) ?></th><th></th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
        <tr>
          <td>#<?= e($r['ref']) ?></td>
          <td><?= e($r['title']) ?></td>
          <td><?= e(lc($r, 'commune')) ?></td>
          <td><?= status_badge($r['status']) ?></td>
          <td><a class="btn btn-sm btn-ghost" href="<?= e(url('admin/complaint.php?id=' . (int) $r['id'])) ?>"><?= e(t('admin_manage')) ?></a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
