<?php

declare(strict_types=1);

// Included only after the Items or Search page has validated the session.
if (!defined('TEAMPASS_APP') || !isset($lang, $session, $SETTINGS)) {
    exit;
}
$renewalMessages = [];
foreach (['period', 'none', 'explanation', 'due', 'estimate', 'existing', 'expired', 'unknown', 'unavailable', 'move_confirm', 'effective', 'source_item', 'source_folder', 'source_none', 'source_lapr'] as $key) {
    $renewalMessages[$key] = $lang->get('renewal_notice_' . $key);
}
$renewalMessages['loading'] = $lang->get('please_wait');
foreach (['scheduled', 'soon', 'expired', 'unknown'] as $key) {
    $renewalMessages['badge_' . $key] = $lang->get('renewal_badge_' . $key);
}
?>
<div class="modal fade" id="renewal-move-modal" tabindex="-1" role="dialog" aria-labelledby="renewal-move-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renewal-move-title"><?php echo htmlspecialchars($renewalMessages['move_confirm'], ENT_QUOTES, 'UTF-8'); ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?php echo htmlspecialchars($lang->get('close'), ENT_QUOTES, 'UTF-8'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body"><div id="renewal-move-details"></div></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php echo htmlspecialchars($lang->get('cancel'), ENT_QUOTES, 'UTF-8'); ?></button>
                <button type="button" class="btn btn-primary" id="renewal-move-confirm"><?php echo htmlspecialchars($lang->get('perform'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </div>
</div>
<script src="assets/js/renewal-preview.js?v=<?php echo filemtime(TEAMPASS_ROOT . '/public/assets/js/renewal-preview.js'); ?>"></script>
<script>
    window.tpRenewal = createRenewalPreview(<?php echo json_encode([
        'key' => $session->get('key'),
        'messages' => $renewalMessages,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
</script>
