</main>
<footer class="footer">
  <div class="container footer-inner">
    <div>
      <div class="brand"><span class="brand-mark">★</span> <?= e(app_name()) ?></div>
      <p class="footer-tag"><?= e(t('footer_tagline')) ?></p>
    </div>
    <nav class="footer-links">
      <a href="<?= e(url('complaints.php')) ?>"><?= e(t('nav_complaints')) ?></a>
      <a href="<?= e(url('leaderboard.php')) ?>"><?= e(t('nav_leaderboard')) ?></a>
      <a href="<?= e(url('register.php')) ?>"><?= e(t('nav_register')) ?></a>
    </nav>
  </div>
  <div class="footer-bottom"><?= e(app_name()) ?> © <?= date('Y') ?> 🇩🇿</div>
</footer>
<button id="installBtn" class="install-btn" hidden>📲 <?= e(t('pwa_install')) ?></button>
<?php
// Mobile bottom navigation (app-style). Active section from the current script.
$bnScript = $_SERVER['SCRIPT_NAME'] ?? '';
$bnSect = 'home';
if (strpos($bnScript, 'complaint') !== false) $bnSect = 'complaints';
if (strpos($bnScript, 'tree') !== false) $bnSect = 'trees';
if (strpos($bnScript, 'leaderboard') !== false) $bnSect = 'lb';
if (strpos($bnScript, '/dashboard/') !== false || strpos($bnScript, '/admin/') !== false
    || strpos($bnScript, '/superadmin/') !== false) $bnSect = 'me';
$bnMe = current_user();
if ($bnMe && in_array($bnMe['role'], ['admin', 'superadmin'], true)) {
    $bnFabUrl = url('admin/notify.php');
    $bnFabIcon = '📣';
    $bnFabLabel = t('bn_notify');
} elseif ($bnMe) {
    $bnFabUrl = url('dashboard/new-complaint.php');
    $bnFabIcon = '📣';
    $bnFabLabel = t('bn_report');
} else {
    $bnFabUrl = url('register.php');
    $bnFabIcon = '📣';
    $bnFabLabel = t('bn_report');
}
?>
<nav class="bottomnav">
  <a class="<?= $bnSect === 'home' ? 'on' : '' ?>" href="<?= e(url('index.php')) ?>">
    <span class="bn-ico">🏠</span><span class="bn-label"><?= e(t('nav_home')) ?></span></a>
  <a class="<?= $bnSect === 'complaints' ? 'on' : '' ?>" href="<?= e(url('complaints.php')) ?>">
    <span class="bn-ico">🗺️</span><span class="bn-label"><?= e(t('nav_complaints')) ?></span></a>
  <a class="bn-fab-wrap" href="<?= e($bnFabUrl) ?>">
    <span class="bn-fab"><?= $bnFabIcon ?></span><span class="bn-label"><?= e($bnFabLabel) ?></span></a>
  <a class="<?= $bnSect === 'trees' ? 'on' : '' ?>" href="<?= e(url('trees.php')) ?>">
    <span class="bn-ico">🌳</span><span class="bn-label"><?= e(t('bn_trees')) ?></span></a>
  <?php if ($bnMe): ?>
  <a class="<?= $bnSect === 'me' ? 'on' : '' ?>" href="<?= e(url(role_home($bnMe))) ?>">
    <span class="bn-ico">👤</span><span class="bn-label"><?= e(t('nav_dashboard')) ?></span></a>
  <?php else: ?>
  <a class="<?= $bnSect === 'me' ? 'on' : '' ?>" href="<?= e(url('login.php')) ?>">
    <span class="bn-ico">👤</span><span class="bn-label"><?= e(t('nav_login')) ?></span></a>
  <?php endif; ?>
</nav>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
