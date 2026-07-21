</main>
<footer class="footer">
  <div class="container footer-inner">
    <div>
      <div class="brand">
        <?php if (site_logo_url()): ?><img class="brand-logo" src="<?= e(site_logo_url()) ?>" alt="">
        <?php else: ?><span class="brand-mark">★</span><?php endif; ?>
        <?= e(app_name()) ?>
      </div>
      <p class="footer-tag"><?= e(site_text('site_tagline', 'footer_tagline')) ?></p>
      <?php
      $fEmail = site_setting('contact_email');
      $fPhone = site_setting('contact_phone');
      $fFb = site_setting('social_facebook');
      if ($fEmail || $fPhone || $fFb): ?>
      <p class="footer-contact">
        <?php if ($fEmail): ?><a href="mailto:<?= e($fEmail) ?>">✉️ <?= e($fEmail) ?></a><?php endif; ?>
        <?php if ($fPhone): ?><a href="tel:<?= e($fPhone) ?>">📞 <?= e($fPhone) ?></a><?php endif; ?>
        <?php if ($fFb): ?><a href="<?= e($fFb) ?>" rel="noopener" target="_blank">📘 Facebook</a><?php endif; ?>
      </p>
      <?php endif; ?>
    </div>
    <nav class="footer-links">
      <?php if (module_on('complaints')): ?><a href="<?= e(url('complaints.php')) ?>"><?= e(t('nav_complaints')) ?></a><?php endif; ?>
      <?php if (module_on('trees')): ?><a href="<?= e(url('trees.php')) ?>"><?= e(t('nav_trees')) ?></a><?php endif; ?>
      <?php if (module_on('leaderboard')): ?><a href="<?= e(url('leaderboard.php')) ?>"><?= e(t('nav_leaderboard')) ?></a><?php endif; ?>
      <a href="<?= e(url('register.php')) ?>"><?= e(t('nav_register')) ?></a>
    </nav>
  </div>
  <div class="footer-bottom"><?= e(app_name()) ?> © <?= date('Y') ?> 🇩🇿</div>
</footer>
<button id="installBtn" class="install-btn" hidden>📲 <?= e(t('pwa_install')) ?></button>
<?php
// Mobile bottom navigation (app-style, module-aware). Active section from the current script.
$bnScript = $_SERVER['SCRIPT_NAME'] ?? '';
$bnSect = 'home';
if (strpos($bnScript, 'complaint') !== false) $bnSect = 'complaints';
if (strpos($bnScript, 'tree') !== false) $bnSect = 'trees';
if (strpos($bnScript, 'leaderboard') !== false) $bnSect = 'lb';
if (strpos($bnScript, '/dashboard/') !== false || strpos($bnScript, '/admin/') !== false
    || strpos($bnScript, '/superadmin/') !== false) $bnSect = 'me';
$bnMe = current_user();

if ($bnMe && in_array($bnMe['role'], ['admin', 'superadmin'], true)) {
    $bnFab = [url('admin/notify.php'), '📣', t('bn_notify')];
} elseif (module_on('complaints')) {
    $bnFab = [$bnMe ? url('dashboard/new-complaint.php') : url('register.php'), '📣', t('bn_report')];
} else {
    $bnFab = [url('trees.php'), '🌳', t('bn_trees')];
}

$bnItems = [[url('index.php'), '🏠', t('nav_home'), $bnSect === 'home']];
if (module_on('complaints')) {
    $bnItems[] = [url('complaints.php'), '🗺️', t('nav_complaints'), $bnSect === 'complaints'];
}
if (module_on('trees')) {
    $bnItems[] = [url('trees.php'), '🌳', t('bn_trees'), $bnSect === 'trees'];
}
if (!module_on('complaints') && module_on('leaderboard')) {
    $bnItems[] = [url('leaderboard.php'), '🏆', t('nav_leaderboard'), $bnSect === 'lb'];
}
$bnItems[] = $bnMe
    ? [url(role_home($bnMe)), '👤', t('nav_dashboard'), $bnSect === 'me']
    : [url('login.php'), '👤', t('nav_login'), $bnSect === 'me'];
$bnHalf = (int) ceil(count($bnItems) / 2);
?>
<nav class="bottomnav" style="grid-template-columns: repeat(<?= count($bnItems) + 1 ?>, 1fr);">
  <?php foreach ($bnItems as $i => $it): ?>
  <?php if ($i === $bnHalf): ?>
  <a class="bn-fab-wrap" href="<?= e($bnFab[0]) ?>">
    <span class="bn-fab"><?= $bnFab[1] ?></span><span class="bn-label"><?= e($bnFab[2]) ?></span></a>
  <?php endif; ?>
  <a class="<?= $it[3] ? 'on' : '' ?>" href="<?= e($it[0]) ?>">
    <span class="bn-ico"><?= $it[1] ?></span><span class="bn-label"><?= e($it[2]) ?></span></a>
  <?php endforeach; ?>
</nav>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
