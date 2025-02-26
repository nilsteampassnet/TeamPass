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
 * @file      oauth.js.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('oauth') === false) {
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
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data) {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
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
        /**
         * Update fields with tenant ID
         * 
         * @param {string} tenantId 
         */
        function updateFieldsWithTenantId(tenantId) {
            // Update field oauth2_client_endpoint
            let endpointUrl = $('#oauth2_client_endpoint').val();
            endpointUrl = endpointUrl.replace(/([^\/]*)\/oauth2\/v2.0\/authorize/, tenantId + '/oauth2/v2.0/authorize');
            $('#oauth2_client_endpoint').val(endpointUrl);

            // Update field oauth2_client_token
            let tokenUrl = $('#oauth2_client_token').val();
            tokenUrl = tokenUrl.replace(/([^\/]*)\/oauth2\/v2.0\/token/, tenantId + '/oauth2/v2.0/token');
            $('#oauth2_client_token').val(tokenUrl);
        }

        /**
         * Update a setting in DB
         * 
         * @param {string} field 
         * @param {string} value 
         * @returns {Promise}
         */
        function updateTeampassSetting(field, value)
        {
            // Check if field and value are not empty
            if (field === '' || value === '') {
                return false;
            }
            
            // Launch the request
            return $.Deferred(function(defer) {
                $.post(
                    "sources/admin.queries.php", {
                        type: "save_option_change",
                        data: prepareExchangedData(
                            JSON.stringify({"field": field, "value": value}),
                            "encode",
                            "<?php echo $session->get('key'); ?>"
                        ),
                        key: "<?php echo $session->get('key'); ?>"
                    },
                    function(data) {
                        // Handle server answer
                        try {
                            data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                        } catch (e) {
                            // error
                            defer.reject(e.error);
                        }
                        
                        if (data.error === false) {
                            defer.resolve(data);
                        } else {
                            defer.reject(data.error);
                        }
                    }
                );
            }).promise();
        }

        $('#oauth2_tenant_id').change(function() {
            let tenantId = $(this).val();
            
            // Update the fields
            updateFieldsWithTenantId(tenantId);
            
            // Update settings in DB
            $.when(
                updateTeampassSetting('oauth2_client_endpoint', $('#oauth2_client_endpoint').val())
            ).then(function() {
                return updateTeampassSetting('oauth2_client_token', $('#oauth2_client_token').val());
            }).fail(function(error) {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('server_answer_error') . '<br />' . $lang->get('server_returned_data') . ':<br />'; ?>' + error,
                    '', {
                        closeButton: true,
                        positionClass: 'toast-bottom-right'
                    }
                );
                return false;
            });
        });
    });

    //]]>
</script>
