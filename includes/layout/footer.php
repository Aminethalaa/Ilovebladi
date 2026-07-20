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
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
