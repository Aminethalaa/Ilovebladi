<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password-reset.php';

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$user = reset_lookup($token);
$errors = [];
$done = false;

if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $pass = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');
    if (strlen($pass) < 8) {
        $errors[] = t('err_password');
    }
    if ($pass !== $pass2) {
        $errors[] = t('err_password_match');
    }
    if (!$errors) {
        reset_complete((int) $user['reset_id'], (int) $user['id'], $pass);
        $done = true;
    }
}

$page_title = t('pr_new_title');
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container auth-wrap">
  <div class="card auth-card">
    <?php if ($done): ?>
      <h1 class="auth-title">✅</h1>
      <div class="flash flash-success"><?= e(t('pr_changed')) ?></div>
      <p class="center"><a class="btn btn-primary btn-lg" href="<?= e(url('login.php')) ?>"><?= e(t('nav_login')) ?></a></p>
    <?php elseif (!$user): ?>
      <h1 class="auth-title">⌛</h1>
      <div class="flash flash-error"><?= e(t('pr_invalid')) ?></div>
      <p class="center"><a class="btn btn-primary" href="<?= e(url('forgot-password.php')) ?>"><?= e(t('pr_request_again')) ?></a></p>
    <?php else: ?>
      <h1 class="auth-title">🔑 <?= e(t('pr_new_title')) ?></h1>
      <p class="muted center"><?= e($user['email']) ?></p>
      <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label class="field"><span><?= e(t('pr_new_password')) ?></span>
          <input required type="password" name="password" minlength="8" autofocus>
        </label>
        <label class="field"><span><?= e(t('reg_password2')) ?></span>
          <input required type="password" name="password2" minlength="8">
        </label>
        <button class="btn btn-primary btn-lg btn-block"><?= e(t('pr_save_btn')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
