<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @file      admin.js.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */
Use TeampassClasses\SuperGlobal\SuperGlobal;
Use TeampassClasses\NestedTree\NestedTree;
Use TeampassClasses\PerformChecks\PerformChecks;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    exit();
}

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => isset($_POST['type']) === true ? $_POST['type'] : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => isset($_SESSION['user_id']) === false ? null : $_SESSION['user_id'],
        'user_key' => isset($_SESSION['key']) === false ? null : $_SESSION['key'],
        'CPM' => isset($_SESSION['CPM']) === false ? null : $_SESSION['CPM'],
    ]
);
// Handle the case
$checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('admin') === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load language file
require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user']['user_language'].'.php';

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

?>

<script type="text/javascript">
    var requestRunning = false;

    /**
     * ADMIN
     */
    // <- PREPARE TOGGLES
    $('.toggle').toggles({
        drag: true,
        click: true,
        text: {
            on: '<?php echo langHdl('yes'); ?>',
            off: '<?php echo langHdl('no'); ?>'
        },
        on: true,
        animate: 250,
        easing: 'swing',
        width: 50,
        height: 20,
        type: 'compact'
    });
    $('.toggle').on('toggle', function(e, active) {
        if (active) {
            $("#" + e.target.id + "_input").val(1);
            if (e.target.id == "allow_print") {
                $("#roles_allowed_to_print_select").prop("disabled", false);
            }
            if (e.target.id == "anyone_can_modify") {
                $("#form-item-row-modify").removeClass('hidden');
            }
            if (e.target.id == "restricted_to") {
                $("#form-item-row-restricted").removeClass('hidden');
            }
        } else {
            $("#" + e.target.id + "_input").val(0);
            if (e.target.id == "allow_print") {
                $("#roles_allowed_to_print_select").prop("disabled", true);
            }
            if (e.target.id == "anyone_can_modify") {
                $("#form-item-row-modify").addClass('hidden');
            }
            if (e.target.id == "restricted_to") {
                $("#form-item-row-restricted").addClass('hidden');
            }
        }

        var data = {
            "field": e.target.id,
            "value": $("#" + e.target.id + "_input").val(),
        }
        console.log(data)
        // Store in DB   
        $.post(
            "sources/admin.queries.php", {
                type: "save_option_change",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                // Handle server answer
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                        '', {
                            closeButton: true,
                            positionClass: 'toastr-top-right'
                        }
                    );
                    return false;
                }
                console.log(data)
                if (data.error === false) {
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('saved'); ?>',
                        '', {
                            timeOut: 2000,
                            progressBar: true
                        }
                    );
                }
            }
        );
    });
    // .-> END. TOGGLES

    // <- PREPARE SELECT2
    $('.select2').select2({
        language: '<?php echo isset($_SESSION['user_language_code']) === true ? $_SESSION['user_language_code'] : 'EN'; ?>'
    });

    /**
     */
    $(document).on('change', '.form-control-sm, .setting-ldap', function() {
        var field = $(this).attr('id'),
            value = $.isArray($(this).val()) === false ? $(this).val() : JSON.stringify($(this).val().map(Number));

        if (field === '' || field === undefined || $(this).hasClass('no-save') === true) return false;
        
        // prevent launch of similar query in case of doubleclick
        if (requestRunning === true) {
            return false;
        }

        // Sanitize value
        if ($(this).hasClass('select2') === false) {
            value = fieldDomPurifierWithWarning('#' + field, false, false, false, true);
        }
        if (value === false) {
            return false;
        }
        $('#' + field).val(value);

        requestRunning = true;

        var data = {
            "field": field,
            "value": value,
        }
        console.log(data);

        // Store in DB   
        $.post(
            "sources/admin.queries.php", {
                type: "save_option_change",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                // Handle server answer
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo langHdl('server_answer_error') . '<br />' . langHdl('server_returned_data') . ':<br />'; ?>' + data.error,
                        '', {
                            closeButton: true,
                            positionClass: 'toastr-top-right'
                        }
                    );
                    return false;
                }
                console.log(data)
                if (data.error === false) {
                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('saved'); ?>',
                        '', {
                            timeOut: 2000,
                            progressBar: true
                        }
                    );
                }
                requestRunning = false;
            }
        );
    });
</script>
