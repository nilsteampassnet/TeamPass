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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('oauth') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[
    /**
     * "Test a user": what the next SSO sign-in of a Microsoft Entra ID user would do,
     * and what the directory answers for each allowed group. Read-only.
     */
    const oauth2TestLabels = {
        groups: <?php echo json_encode($lang->get('oauth2_test_groups'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        allowedGroup: <?php echo json_encode($lang->get('oauth2_test_allowed_group'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        groupWithoutName: <?php echo json_encode($lang->get('oauth2_test_group_without_name'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        roles: <?php echo json_encode($lang->get('oauth2_test_roles_at_creation'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        fallbackRole: <?php echo json_encode($lang->get('oauth2_test_fallback_role'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        none: <?php echo json_encode($lang->get('none'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
    };

    /**
     * Post a request of the test and render its answer
     *
     * @param {string} type
     * @param {object} payload
     * @param {function} render
     */
    function oauth2TestPost(type, payload, render) {
        toastr.remove();
        toastr.info(<?php echo json_encode($lang->get('in_progress'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?> + ' ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        $.post(
            'sources/admin.queries.php', {
                type: type,
                data: prepareExchangedData(JSON.stringify(payload), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                toastr.remove();
                try {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                } catch (e) {
                    toastr.error(<?php echo json_encode($lang->get('server_answer_error'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, '', {closeButton: true});
                    return;
                }

                if (data.error === true) {
                    toastr.error(data.message, <?php echo json_encode($lang->get('caution'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, {timeOut: 15000, closeButton: true, progressBar: true});
                    return;
                }
                render(data);
            }
        );
    }

    /**
     * Show the nodes in the results panel
     *
     * @param {Array} nodes
     */
    function oauth2TestShow(nodes) {
        $('#oauth2-test-results-body').empty().append(nodes);
        $('#oauth2-test-results').removeClass('hidden');
    }

    /**
     * Render the directory users of a login, each with the outcome of its next sign-in
     *
     * @param {object} data
     */
    function oauth2TestRenderUsers(data) {
        const nodes = [];
        if (data.notice !== '') {
            nodes.push($('<p class="mb-2"></p>').text(data.notice));
        }

        data.users.forEach(function(user) {
            const block = $('<div class="border rounded p-2 mb-2"></div>');
            block.append(
                $('<div class="font-weight-bold"></div>').text(
                    user.displayName !== '' ? user.displayName + ' - ' + user.userPrincipalName : user.userPrincipalName
                )
            );
            block.append(
                $('<div></div>')
                    .addClass(user.accepted === true ? 'text-success' : 'text-danger')
                    .append($('<i class="fas mr-2"></i>').addClass(user.accepted === true ? 'fa-check-circle' : 'fa-times-circle'))
                    .append($('<span></span>').text(user.outcome))
            );

            const groups = $('<div class="small text-muted mt-1"></div>').text(oauth2TestLabels.groups + ': ');
            if (user.groups.length === 0) {
                groups.append(document.createTextNode(oauth2TestLabels.none));
            }
            user.groups.forEach(function(group, index) {
                if (index > 0) {
                    groups.append(document.createTextNode(', '));
                }
                const label = $('<span></span>')
                    .attr('title', group.id)
                    .text(group.name !== '' ? group.name : group.id + ' (' + oauth2TestLabels.groupWithoutName + ')');
                if (group.allowed === true) {
                    label.addClass('font-weight-bold text-success')
                        .append(document.createTextNode(' (' + oauth2TestLabels.allowedGroup + ')'));
                }
                groups.append(label);
            });
            block.append(groups);

            // Only for an account this sign-in would create
            if (user.roles !== null) {
                block.append(
                    $('<div class="small mt-1"></div>').text(
                        oauth2TestLabels.roles + ': '
                        + (user.roles.length > 0 ? user.roles.join(', ') : oauth2TestLabels.none)
                        + (user.fallbackRole === true ? ' ' + oauth2TestLabels.fallbackRole : '')
                    )
                );
            }
            nodes.push(block);
        });

        oauth2TestShow(nodes);
    }

    /**
     * Render what the directory answered for each allowed group
     *
     * @param {object} data
     */
    function oauth2TestRenderGroups(data) {
        const nodes = [];
        if (data.notice !== '') {
            nodes.push($('<p class="mb-2"></p>').text(data.notice));
        }

        const list = $('<ul class="list-unstyled mb-0"></ul>');
        data.groups.forEach(function(group) {
            const icon = group.found === false
                ? 'fa-times-circle text-danger'
                : (group.message === '' ? 'fa-check-circle text-success' : 'fa-exclamation-triangle text-warning');
            const item = $('<li class="mb-1"></li>')
                .append($('<i class="fas mr-2"></i>').addClass(icon))
                .append($('<span class="font-weight-bold"></span>').text(group.entry));
            if (group.matches.length > 0) {
                item.append(document.createTextNode(' : ' + group.matches.map(function(match) {
                    return match.name !== '' ? match.name + ' (' + match.id + ')' : match.id;
                }).join(', ')));
            }
            if (group.message !== '') {
                item.append($('<div class="small"></div>').text(group.message));
            }
            list.append(item);
        });
        if (data.groups.length > 0) {
            nodes.push(list);
        }

        oauth2TestShow(nodes);
    }

    $(document).on('click', '#oauth2-test-user', function() {
        const login = $('#oauth2-test-user-login').val().trim();
        if (login === '') {
            $('#oauth2-test-user-login').focus();
            return;
        }
        // The groups as the page shows them, even if their last change is still being saved
        oauth2TestPost(
            'oauth2_test_user',
            {'login': login, 'groups': $('#oauth_self_register_groups').val()},
            oauth2TestRenderUsers
        );
    });

    $(document).on('keydown', '#oauth2-test-user-login', function(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('#oauth2-test-user').trigger('click');
        }
    });

    $(document).on('click', '#oauth2-check-groups', function() {
        oauth2TestPost(
            'oauth2_check_groups',
            {'groups': $('#oauth_self_register_groups').val()},
            oauth2TestRenderGroups
        );
    });

    // The test reads Microsoft Entra ID only
    $(document).on('change', '#oauth2_client_endpoint', function() {
        const isEntra = $(this).val().indexOf('login.microsoftonline.com') !== -1;
        $('.oauth2-test-entra').toggleClass('hidden', isEntra === false);
        $('.oauth2-test-generic').toggleClass('hidden', isEntra === true);
        if (isEntra === false) {
            $('#oauth2-test-results').addClass('hidden');
        }
    });

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
                'sources/admin.queries.php', {
                    type: 'save_option_change',
                    data: prepareExchangedData(
                        JSON.stringify({'field': field, 'value': value}),
                        'encode',
                        '<?php echo $session->get('key'); ?>'
                    ),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    // Handle server answer
                    try {
                        data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
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
    
    /**
     * On page loaded
     */
    $(function() {
        // Load list of groups
        $('#oauth_new_user_is_administrated_by, #oauth_selfregistered_user_belongs_to_role').empty();  
        var data = {
            'source_page': 'oauth',
        }
        $.post(
            'sources/admin.queries.php', {
                type: 'get_list_of_roles',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                var html_admin_by = '<option value="">-- <?php echo $lang->get('select'); ?> --</option>',
                    html_roles = '<option value="">-- <?php echo $lang->get('select'); ?> --</option>',
                    selected_admin_by = 0,
                    selected_role = '';

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
                $('#oauth_new_user_is_administrated_by')
                    .append(html_admin_by)
                    .val(selected_admin_by);
                $('#oauth_selfregistered_user_belongs_to_role')
                    .append(html_roles);
                if (selected_role !== '') {
                    $('#oauth_selfregistered_user_belongs_to_role').val(selected_role);
                }
            }
        );

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
