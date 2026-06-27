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
 * @file      api.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
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
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('api') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    $('[data-mask]').inputmask();

    /**
     * TOGGLE API STATUS (ENABLED/DISABLED)
     */
    $(document).on('click', '.api-clickme-action', function() {
        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // prepare data
        var data = {
            'increment_id': $(this).data('increment-id'),
            'field': $(this).data('field'),
            'value': $(this).hasClass('fa-toggle-off') === true ? 1 : 0,
        },
        selectedIcon = $(this);

        $.post(
            'sources/admin.queries.php', {
                type: 'save_user_change',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                if (debugJavascript === true) console.log(data);

                if (data.error !== false) {
                    // Show error
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('caution'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    // CHange icon format
                    if (selectedIcon.hasClass('fa-toggle-off') === true) {
                        selectedIcon
                            .removeClass('fa-toggle-off text-danger')
                            .addClass('fa-toggle-on text-info')
                            .prop('data-user-auth-type', 'ldap');
                    } else {
                        selectedIcon
                            .removeClass('fa-toggle-on text-info')
                            .addClass('fa-toggle-off')
                            .prop('data-user-auth-type', 'local');
                    }

                    $('.infotip').tooltip();

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
    });

    $(document).on('click', '#button-refresh-users-api', function() {
        toastr.remove();
        toastr.info(
            '<i class="fa-solid fa-circle-notch fa-spin fa-2x"></i>',
            '<?php echo $lang->get('please_wait'); ?>'
        );

        // Launch action
        $.post(
            'sources/admin.queries.php', {
                type: 'admin_action_refresh-users-api',
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.warning(
                        '<?php echo $lang->get('none_selected_text'); ?>',
                        '', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    if (data.countUpdatedUsers > 0) {
                        // Inform user
                        toastr.remove();
                        toastr.success(
                            data.message,
                            '<?php echo $lang->get('alert_page_will_reload'); ?>', {
                                timeOut: 3000,
                                progressBar: true
                            }
                        );

                        // Delay page submit
                        $(this).delay(2000).queue(function() {
                            document.location.reload(true);
                            $(this).dequeue();
                        });
                    } else {
                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                }
            }
        );

    });

    

    // Handle the copy in clipboard button for api key
    $(document).on('click', '#copy-extension-key', function() {
        const apiKey = $('#browser_extension_key').val();
        navigator.clipboard.writeText(apiKey).then(function() {
            // Display message.
            toastr.remove();
            toastr.info(
                '<?php echo $lang->get('copy_to_clipboard'); ?>',
                '', {
                    timeOut: 2000,
                    progressBar: true,
                    positionClass: 'toast-bottom-right'
                }
            );
        }, function(err) {
            // nothing
        });
    });

    // Handle generate new extension key
    $(document).on('click', '#generate-extension-key', function() {
        toastr.remove();
        toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        // generate a token
        $.post(
            'sources/main.queries.php', {
                type: 'generate_token',
                type_category: 'action_system',
                size: 64,
                capital: true,
                secure: false,
                numeric: true,
                symbols: false,
                lowercase: true,
                unique_names: false,
                reason: 'extension_key_generation',
                duration: 10,
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

                // Update key value
                $('#browser_extension_key').val(data.token);

                // Store in DB
                var data = {
                    "field": 'browser_extension_key',
                    "value": data.token,
                }
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
                        if (debugJavascript === true) {
                            console.log('Response from server:');
                            console.log(data);
                        }
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
            }
        );
    });

    //]]>
</script>
