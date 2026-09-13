<?php

declare(strict_types=1);

// Included only after the Items or Search page has validated the session.
if (!defined('TEAMPASS_APP') || !isset($lang, $session, $SETTINGS)) {
    exit;
}
$renewalMessages = [];
foreach (['period', 'none', 'explanation', 'due', 'estimate', 'existing', 'expired', 'unknown', 'unavailable', 'move_confirm', 'effective', 'source_item', 'source_folder', 'source_none'] as $key) {
    $renewalMessages[$key] = $lang->get('renewal_notice_' . $key);
}
$renewalMessages['loading'] = $lang->get('please_wait');
foreach (['scheduled', 'soon', 'expired', 'unknown'] as $key) {
    $renewalMessages['badge_' . $key] = $lang->get('renewal_badge_' . $key);
}
?>
<script src="assets/js/renewal-preview.js?v=<?php echo filemtime(TEAMPASS_ROOT . '/public/assets/js/renewal-preview.js'); ?>"></script>
<script>
    window.tpRenewal = createRenewalPreview(<?php echo json_encode([
        'key' => $session->get('key'),
        'messages' => $renewalMessages,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
</script>
