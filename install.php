<?php
/**
 * One-time installer: creates the super admin account.
 * Refuses to run once a super admin exists. Safe to delete afterwards.
 */
require_once __DIR__ . '/includes/auth.php';

try {
    $exists = (int) db()->query("SELECT COUNT(*) c FROM users WHERE role = 'superadmin'")->fetch()['c'] > 0;
} catch (PDOException $e) {
    http_response_code(500);
    die('Tables not found — import database.sql via phpMyAdmin first. — الجداول غير موجودة: استورد ملف database.sql عبر phpMyAdmin أولاً.');
}
$errors = [];
$done = false;

if (!$exists && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = mb_strtolower(trim($_POST['email'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    if (mb_strlen($name) < 3)                       $errors[] = t('err_name');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = t('err_email');
    if (strlen($pass) < 8)                          $errors[] = t('err_password');

    if (!$errors) {
        db()->prepare("INSERT INTO users (role, name, email, password_hash, lang, is_verified, created_at)
                       VALUES ('superadmin',?,?,?,?,1,NOW())")
            ->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), lang()]);
        $done = true;
    }
}

$page_title = 'Installation';
require __DIR__ . '/includes/layout/header.php';
?>
<div class="container auth-wrap">
  <div class="card auth-card">
    <?php if ($exists): ?>
      <h1 class="auth-title">✅</h1>
      <p class="center"><?= e(lang() === 'ar' ? 'المنصة مثبتة من قبل. احذف ملف install.php من الخادم.' : 'La plateforme est déjà installée. Supprimez le fichier install.php du serveur.') ?></p>
      <p class="center"><a class="btn btn-primary" href="<?= e(url('login.php')) ?>"><?= e(t('nav_login')) ?></a></p>
    <?php elseif ($done): ?>
      <h1 class="auth-title">🎉</h1>
      <p class="center"><?= e(lang() === 'ar' ? 'تم إنشاء حساب الإدارة العامة بنجاح! احذف الآن ملف install.php من الخادم ثم سجل الدخول.' : 'Compte super admin créé ! Supprimez maintenant install.php du serveur, puis connectez-vous.') ?></p>
      <p class="center"><a class="btn btn-primary" href="<?= e(url('login.php')) ?>"><?= e(t('nav_login')) ?></a></p>
    <?php else: ?>
      <h1 class="auth-title">🇩🇿 <?= e(app_name()) ?></h1>
      <p class="muted center"><?= e(lang() === 'ar' ? 'الخطوة الأخيرة: أنشئ حساب الإدارة العامة (Super Admin).' : 'Dernière étape : créez le compte super admin.') ?></p>
      <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" class="form">
        <?= csrf_field() ?>
        <label class="field"><span><?= e(t('reg_name')) ?></span><input required name="name" maxlength="120"></label>
        <label class="field"><span><?= e(t('reg_email')) ?></span><input required type="email" name="email" maxlength="190"></label>
        <label class="field"><span><?= e(t('reg_password')) ?></span><input required type="password" name="password" minlength="8"></label>
        <button class="btn btn-primary btn-lg btn-block"><?= e(lang() === 'ar' ? 'تثبيت' : 'Installer') ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/layout/footer.php'; ?>
