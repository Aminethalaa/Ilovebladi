<?php
// Contextual notification opt-in card. Include on dashboards after auth.
require_once BASE_PATH . '/includes/webpush.php';
$vapid = push_supported() ? vapid_keys() : null;
if ($vapid): ?>
<div class="card card-pad push-card" id="pushCard" hidden
     data-vapid="<?= e($vapid['public']) ?>"
     data-endpoint="<?= e(url('api/push-subscribe.php')) ?>"
     data-csrf="<?= e(csrf_token()) ?>"
     data-done-text="<?= e(t('push_card_done')) ?>">
  <h3>🔔 <?= e(t('push_card_title')) ?></h3>
  <p class="muted push-body"><?= e(t('push_card_sub')) ?></p>
  <button class="btn btn-primary" id="pushBtn"><?= e(t('push_enable_btn')) ?></button>
</div>
<?php endif; ?>
