<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) {
    redirect(role_home(current_user()));
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && (int) $u['is_blocked'] === 1) {
        $error = t('err_blocked');
    } elseif ($u && password_verify($pass, $u['password_hash'])) {
        login_user($u);
        redirect(role_home($u));
    } else {
        $error = t('err_login');
    }
}

$page_title = t('nav_login');
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container auth-wrap">
  <div class="card auth-card">
    <h1 class="auth-title"><?= e(t('login_title')) ?></h1>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label class="field"><span><?= e(t('reg_email')) ?></span>
        <input required type="email" name="email" autofocus>
      </label>
      <label class="field"><span><?= e(t('reg_password')) ?></span>
        <input required type="password" name="password">
      </label>
      <button class="btn btn-primary btn-lg btn-block"><?= e(t('nav_login')) ?></button>
    </form>
    <p class="center"><a class="muted" href="<?= e(url('forgot-password.php')) ?>"><?= e(t('pr_link')) ?></a></p>
    <p class="center muted"><?= e(t('no_account')) ?> <a href="<?= e(url('register.php')) ?>"><?= e(t('nav_register')) ?></a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
