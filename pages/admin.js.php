<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      admin.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('admin') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

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
            on: '<?php echo $lang->get('yes'); ?>',
            off: '<?php echo $lang->get('no'); ?>'
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
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                // Handle server answer
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                        '', {
                            closeButton: true,
                            positionClass: 'toast-bottom-right'
                        }
                    );
                    return false;
                }
                console.log(data)
                if (data.error === false) {
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('saved'); ?>',
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
        language: '<?php echo $userLang = $session->get('user-language_code'); echo isset($userLang) === null ? $userLang : 'EN'; ?>'
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
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                // Handle server answer
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                        '', {
                            closeButton: true,
                            positionClass: 'toast-bottom-right'
                        }
                    );
                    return false;
                }
                console.log(data)
                if (data.error === false) {
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('saved'); ?>',
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

    $(document).ready(function() {
        // Perform DB integrity check
        setTimeout(
            performDBIntegrityCheck,
            2500
        );
    });


    function performDBIntegrityCheck()
    {
        $.post(
            "sources/admin.queries.php", {
                type: "tablesIntegrityCheck",
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                // Handle server answer
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + data.error,
                        '', {
                            closeButton: true,
                            positionClass: 'toast-bottom-right'
                        }
                    );
                    return false;
                }
                console.log(data)
                let html = '',
                    tablesInError = '',
                    cnt = 0,
                    tables = JSON.parse(data.tables);
                if (data.error === false) {
                    $.each(tables, function(i, value) {
                        if (cnt < 5) {
                            tablesInError += '<li>' + value + '</li>';
                        } else {
                            tablesInError += '<li>...</li>';
                            return false;
                        }
                        cnt++;
                    });

                    if (tablesInError === '') {
                        html = '<i class="fa-solid fa-circle-check text-success mr-2"></i><span class="badge badge-secondary mr-2">Experimental</span>Database integrity check is successfull';
                    } else {
                        html = '<i class="fa-solid fa-circle-xmark text-warning mr-2"></i><span class="badge badge-secondary mr-2">Experimental</span>Database integrity check has identified issues with the following tables:'
                            + '<i class="fa-regular fa-circle-question infotip ml-2 text-info" title="You should consider to run Upgrade process to fix this or perform manual changes on tables"></i>';
                        html += '<ul class="fs-6">' + tablesInError + '</ul>';
                    }
                } else {
                    html = '<i class="fa-solid fa-circle-xmark text-danger mr-2"></i><span class="badge badge-secondary mr-2">Experimental</span>Database integrity check could not be performed!'
                        + 'Error returned: ' + data.message;
                }
                $('#db-integrity-check-status').html(html);                
    
                // Show tooltips
                $('.infotip').tooltip();

                requestRunning = false;
            }
        );
    }
</script>
