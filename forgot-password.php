<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/password-reset.php';
if (current_user()) {
    redirect(role_home(current_user()));
}

$sent = false;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('err_email');
    } else {
        // Rate limit: one request per email per minute, per session.
        $last = $_SESSION['pr_last'] ?? 0;
        if (time() - $last < 60) {
            $error = t('pr_too_soon');
        } else {
            $_SESSION['pr_last'] = time();
            reset_request($email);
            $sent = true;
        }
    }
}

$page_title = t('pr_title');
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container auth-wrap">
  <div class="card auth-card">
    <h1 class="auth-title">🔑 <?= e(t('pr_title')) ?></h1>
    <?php if ($sent): ?>
      <div class="flash flash-success">📧 <?= e(t('pr_sent')) ?></div>
      <p class="center muted"><?= e(t('pr_sent_hint')) ?></p>
      <p class="center"><a class="btn btn-ghost" href="<?= e(url('login.php')) ?>"><?= e(t('nav_login')) ?></a></p>
    <?php else: ?>
      <p class="muted center"><?= e(t('pr_sub')) ?></p>
      <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <label class="field"><span><?= e(t('reg_email')) ?></span>
          <input required type="email" name="email" autofocus maxlength="190">
        </label>
        <button class="btn btn-primary btn-lg btn-block"><?= e(t('pr_send_btn')) ?></button>
      </form>
      <p class="center muted"><a href="<?= e(url('login.php')) ?>">← <?= e(t('nav_login')) ?></a></p>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
