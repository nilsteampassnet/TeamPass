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
 * @file      statistics.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('statistics') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    // calculate statistic values
    showStatsValues();

    // Prepare iCheck format for checkboxes
    $('input[type="checkbox"].flat-blue').iCheck({
        checkboxClass: 'icheckbox_flat-blue',
    });

    // Save on button click
    $(document).on('click', '#statistics-save', function() {
        // SHow user
        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // Send query
        saveOptions();
    });

    // Select/Deselect button
    $('#cb_select_all')
        .on('ifChecked', function() {
            $(".stat_option").iCheck('check');
        })
        .on('ifUnchecked', function() {
            cons
            $(".stat_option").iCheck('uncheck');
        });


    /**
     * Get the values to be shared when statistics enabled
     *
     * @return void
     */
    function showStatsValues() {
        // send query
        $.post(
            "sources/admin.queries.php", {
                type: "get_values_for_statistics",
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                try {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                } catch (e) {
                    // error
                    $("#message_box").html("An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />" + data).show().fadeOut(4000);

                    return;
                }
                if (data.error === "") {
                    $("#value_items").html(data.stat_items);
                    var ips = "";
                    $.each(data.stat_country, function(index, value) {
                        if (value > 0) {
                            if (ips === "") ips = index + ":" + value;
                            else ips += " ; " + index + ":" + value;
                        }
                    });
                    $("#value_country").html(ips);
                    $("#value_folders").html(data.stat_folders);
                    $("#value_items_shared").html(data.stat_items_shared);
                    $("#value_folders_shared").html(data.stat_folders_shared);
                    $("#value_php").html(data.stat_phpversion);
                    $("#value_users").html(data.stat_users);
                    $("#value_admin").html(data.stat_admins);
                    $("#value_manager").html(data.stat_managers);
                    $("#value_ro").html(data.stat_ro);
                    $("#value_teampassv").html(data.stat_teampassversion);
                    $("#value_duo").html(data.stat_duo);
                    $("#value_kb").html(data.stat_kb);
                    $("#value_pf").html(data.pf);
                    $("#value_ldap").html(data.stat_ldap);
                    $("#value_agses").html(data.stat_agses);
                    $("#value_suggestion").html(data.stat_suggestion);
                    $("#value_api").html(data.stat_api);
                    $("#value_customfields").html(data.stat_customfields);
                    $("#value_syslog").html(data.stat_syslog);
                    $("#value_2fa").html(data.stat_2fa);
                    $("#value_https").html(data.stat_stricthttps);
                    $("#value_mysql").html(data.stat_mysqlversion);
                    $("#value_pf").html(data.stat_pf);
                    $("#value_fav").html(data.stat_fav);
                    var langs = "";
                    $.each(data.stat_languages, function(index, value) {
                        if (value > 0) {
                            if (langs === "") langs = index + ":" + value;
                            else langs += " ; " + index + ":" + value;
                        }
                    });
                    $("#value_languages").html(langs);
                }
            }
        );
    }

    /**
     * Permits to save the statistics to be shared
     *
     * @return void
     */
    function saveOptions() {
        var list = "";
        $(".stat_option:checked").each(function() {
            list += $(this).attr("id") + ";";
        });

        // store in DB
        $.post(
            "sources/admin.queries.php", {
                type: "save_sending_statistics",
                list: list,
                status: $("#send_stats_input").val(),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                if (data[0].error === false) {
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    // if enabled, then send stats right now
                    if (parseInt($("#send_stats_input").val()) === 1) {
                        // send statistics
                        $.post(
                            "sources/main.queries.php", {
                                type: "sending_statistics",
                                key: "<?php echo $session->get('key'); ?>"
                            }
                        );
                    }
                }
            },
            "json"
        );
    }
</script>
