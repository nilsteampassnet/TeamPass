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
 * @file      ldap.js.php
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


use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$superGlobal = new SuperGlobal();
$lang = new Language(); 

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
try {
    include_once __DIR__.'/../includes/config/tp.config.php';
} catch (Exception $e) {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => returnIfSet($superGlobal->get('type', 'POST')),
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($superGlobal->get('user_id', 'SESSION'), null),
        'user_key' => returnIfSet($superGlobal->get('key', 'SESSION'), null),
        'CPM' => returnIfSet($superGlobal->get('CPM', 'SESSION'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('ldap') === false) {
    // Not allowed page
    $superGlobal->put('code', ERR_NOT_ALLOWED, 'SESSION', 'error');
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[
    /**
     * TOP MENU BUTTONS ACTIONS
     */
    $(document).on('click', '.tp-action', function() {
        console.log($(this).data('action'))
        $('#ldap-test-config-results-text').html('');
        if ($(this).data('action') === 'ldap-test-config') {
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            var data = {
                'username': $('#ldap-test-config-username').val(),
                'password': $('#ldap-test-config-pwd').val(),
            }

            $.post(
                "sources/ldap.queries.php", {
                    type: "ldap_test_configuration",
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $superGlobal->get('key', 'SESSION'); ?>"),
                    key: "<?php echo $superGlobal->get('key', 'SESSION'); ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $superGlobal->get('key', 'SESSION'); ?>');
                    console.log(data);

                    if (data.error === true) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                //timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        $('#ldap-test-config-results-text').html(data.message);
                        $('#ldap-test-config-results').removeClass('hidden');

                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                }
            );
            // ---
            // END
            // ---
        }
    });

    /**
     * On page loaded
     */
    $(function() {
        //requestRunning = true;
        // Load list of groups
        $("#ldap_new_user_is_administrated_by").empty();
        $.post(
            "sources/admin.queries.php", {
                type: "get_list_of_roles",
                key: "<?php echo $superGlobal->get('key', 'SESSION'); ?>"
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $superGlobal->get('key', 'SESSION'); ?>");

                var html_admin_by = '<option value="">-- <?php echo $lang->get('select'); ?> --</option>',
                    html_roles = '<option value="">-- <?php echo $lang->get('select'); ?> --</option>',
                    selected_admin_by = 0,
                    selected_role = 0;

                for (var i = 0; i < data.length; i++) {
                    if (data[i].selected_administrated_by === 1) {
                        selected_admin_by = data[i].id;
                    }
                    if (data[i].selected_role === 1) {
                        selected_role = data[i].id;
                    }
                    html_admin_by += '<option value="' + data[i].id + '"><?php echo $lang->get('managers_of') . ' '; ?>' + data[i].title + '</option>';
                    html_roles += '<option value="' + data[i].id + '">' + data[i].title + '</option>';
                }
                $("#ldap_new_user_is_administrated_by").append(html_admin_by);
                $("#ldap_new_user_is_administrated_by").val(selected_admin_by).change();
            }
        );
    });

    //]]>
</script>
