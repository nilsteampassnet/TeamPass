<?php

declare(strict_types=1);

// Included only after the calling page (Items, Folders) has validated the session.
if (!defined('TEAMPASS_APP') || !isset($lang, $session)) {
    exit;
}
$iconPickerMessages = [];
foreach (['search', 'search_placeholder', 'suggested', 'all', 'brands', 'no_result', 'default', 'choose'] as $key) {
    $iconPickerMessages[$key] = $lang->get('icon_picker_' . $key);
}
$iconPickerMessages['loading'] = $lang->get('loading_data');
$iconPickerMessages['error'] = $lang->get('server_answer_error');
?>
<script src="assets/js/icon-picker.js?v=<?php echo filemtime(TEAMPASS_ROOT . '/public/assets/js/icon-picker.js'); ?>"></script>
<script>
    window.tpIconPicker = createIconPicker(<?php echo json_encode([
        'key' => $session->get('key'),
        'messages' => $iconPickerMessages,
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);
</script>
