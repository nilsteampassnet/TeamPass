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
 * @file      tools.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request AS SymfonyRequest;
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('tools') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
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
        if ($(this).data('action') === 'fix_pf_items_but') {
            // check if possible
            if ($('#fix_pf_items_user_id').data('psk') !== 1) {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('user_config_not_compliant'); ?>',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            } else if ($('#fix_pf_items_user_id').data('pf') === '') {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('user_config_not_compliant'); ?>',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            // continue
            $('#fix_pf_items_results').html("");
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            var data = {
                'userId': $('#fix_pf_items_user_id').val(),
            }

            $.post(
                "sources/tools.queries.php", {
                    type: "perform_fix_pf_items-step1",
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(dataStep1) {
                    dataStep1 = prepareExchangedData(dataStep1, 'decode', '<?php echo $session->get('key'); ?>');
                    console.log(dataStep1);

                    if (dataStep1.error === true) {
                        // Show error
                        toastr.remove();
                        toastr.error(
                            dataStep1.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                //timeOut: 5000,
                                progressBar: true
                            }
                        );
                    } else {
                        $('#fix_pf_items_results').html(dataStep1.message + dataStep1.personalFolders);

                        // Launch step 2
                        var data = {
                            'userId': $('#fix_pf_items_user_id').val(),
                            'personalFolders': JSON.parse(dataStep1.personalFolders).join(","),
                        }

                        $.post(
                            "sources/tools.queries.php", {
                                type: "perform_fix_pf_items-step2",
                                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                                key: "<?php echo $session->get('key'); ?>"
                            },
                            function(dataStep2) {
                                dataStep2 = prepareExchangedData(dataStep2, 'decode', '<?php echo $session->get('key'); ?>');
                                console.log(dataStep2);

                                if (dataStep2.error === true) {
                                    // Show error
                                    toastr.remove();
                                    toastr.error(
                                        dataStep2.message,
                                        '<?php echo $lang->get('caution'); ?>', {
                                            //timeOut: 5000,
                                            progressBar: true
                                        }
                                    );
                                } else {
                                    $('#fix_pf_items_results').append(dataStep2.message + dataStep1.personalFolders);

                                    // Launch step 3
                                    var data = {
                                        'userId': $('#fix_pf_items_user_id').val(),
                                        'personalFolders': JSON.parse(dataStep1.personalFolders).join(","),
                                    }

                                    $.post(
                                        "sources/tools.queries.php", {
                                            type: "perform_fix_pf_items-step3",
                                            data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                                            key: "<?php echo $session->get('key'); ?>"
                                        },
                                        function(dataStep3) {
                                            dataStep3 = prepareExchangedData(dataStep3, 'decode', '<?php echo $session->get('key'); ?>');
                                            console.log(dataStep3);

                                            if (dataStep3.error === true) {
                                                // Show error
                                                toastr.remove();
                                                toastr.error(
                                                    dataStep3.message,
                                                    '<?php echo $lang->get('caution'); ?>', {
                                                        //timeOut: 5000,
                                                        progressBar: true
                                                    }
                                                );
                                            } else {
                                                $('#fix_pf_items_results').append(dataStep3.message + dataStep1.personalFolders);

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
                                }
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
        // Click on log in button with Azure Entra
        $('#but_perform_setup').click(function() {
            if (debugJavascript === true) {
                console.log('User starts setup with Azure');
            }
            document.location.href="sources/oauth.php";
            return false;
        });
    });

    //]]>
</script>
