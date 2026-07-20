<?php
require_once __DIR__ . '/../includes/auth.php';
$me = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([(int) $me['id']]);
    redirect('dashboard/notifications.php');
}

$st = db()->prepare('SELECT n.*, c.title, c.ref FROM notifications n
    LEFT JOIN complaints c ON c.id = n.complaint_id
    WHERE n.user_id = ? ORDER BY n.id DESC LIMIT 50');
$st->execute([(int) $me['id']]);
$rows = $st->fetchAll();

$page_title = t('nav_notifications');
require __DIR__ . '/../includes/layout/header.php';
?>
<div class="container page page-narrow">
  <div class="page-head">
    <h1>🔔 <?= e(t('nav_notifications')) ?></h1>
    <?php if ($rows): ?>
    <form method="post"><?= csrf_field() ?>
      <button class="btn btn-ghost btn-sm"><?= e(t('mark_all_read')) ?></button>
    </form>
    <?php endif; ?>
  </div>
  <?php if ($rows): ?>
  <div class="list">
    <?php foreach ($rows as $n): ?>
    <a class="list-item <?= (int) $n['is_read'] === 0 ? 'unread' : '' ?>"
       href="<?= $n['complaint_id'] ? e(url('complaint.php?id=' . (int) $n['complaint_id'])) : e(url('dashboard/profile.php')) ?>">
      <div class="list-main">
        <strong><?= e(t('notif_' . $n['type'])) ?></strong>
        <?php if ($n['title']): ?><span class="muted">#<?= e($n['ref']) ?> — <?= e($n['title']) ?></span><?php endif; ?>
      </div>
      <span class="muted"><?= e(time_ago($n['created_at'])) ?></span>
    </a>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="empty">🔕 <?= e(t('no_notifications')) ?></p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout/footer.php'; ?>
