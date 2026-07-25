<?php
// Share buttons. Expects $shareUrl and $shareText to be set by the page.
$sUrl = $shareUrl ?? abs_url();
$sText = $shareText ?? app_name();
?>
<div class="share-bar" data-share-url="<?= e($sUrl) ?>" data-share-text="<?= e($sText) ?>"
     data-copied="<?= e(t('sh_copied')) ?>">
  <span class="share-label"><?= e(t('sh_label')) ?></span>
  <a class="share-btn share-fb" target="_blank" rel="noopener"
     href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($sUrl) ?>" title="Facebook">📘</a>
  <a class="share-btn share-wa" target="_blank" rel="noopener"
     href="https://wa.me/?text=<?= rawurlencode($sText . ' — ' . $sUrl) ?>" title="WhatsApp">💬</a>
  <button type="button" class="share-btn share-copy" title="<?= e(t('sh_copy')) ?>">🔗</button>
</div>
