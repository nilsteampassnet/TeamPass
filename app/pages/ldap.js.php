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
 * @file      ldap.js.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('ldap') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[
    /**
     * TOP MENU BUTTONS ACTIONS
     */
    $(document).on('click', '.tp-action', function() {
        $('#ldap-test-config-results-text').html('');
        $('#ldap-test-config-results-steps').empty();

        if ($(this).data('action') === 'ldap-check-settings') {
            refreshLdapConfigCheck(true);
            return;
        }

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

                    // The step list is the point of the test: it is shown on failure too, so the
                    // administrator sees which step of the real login refused the credentials.
                    renderLdapTestSteps(data.steps)
                    renderLdapConfigFindings(data.findings)

                    $('#ldap-test-config-results-text')
                        .removeClass('text-danger text-success')
                        .addClass(data.error === true ? 'text-danger' : 'text-success')
                        .html(htmlEncode(data.message || ''));
                    $('#ldap-test-config-results').removeClass('hidden');

                    toastr.remove();
                    if (data.error === true) {
                        toastr.error(
                            htmlEncode(data.message || ''),
                            '<?php echo $lang->get('caution'); ?>', {
                                progressBar: true
                            }
                        );
                    } else {
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
     * Icon and colour of a test step or of a finding.
     *
     * @param {string} status ok|ko|warning|skipped|error|info
     * @return {object}
     */
    const ldapStatusDecoration = (status) => {
        const map = {
            ok: { icon: 'fa-check-circle', css: 'text-success' },
            ko: { icon: 'fa-times-circle', css: 'text-danger' },
            error: { icon: 'fa-times-circle', css: 'text-danger' },
            warning: { icon: 'fa-exclamation-triangle', css: 'text-warning' },
            info: { icon: 'fa-info-circle', css: 'text-info' },
            skipped: { icon: 'fa-minus-circle', css: 'text-muted' }
        }
        return map[status] || map.info
    }

    /**
     * Render the steps of the last configuration test.
     *
     * @param {Array} steps Steps returned by the handler
     * @return {void}
     */
    const renderLdapTestSteps = (steps) => {
        const $list = $('#ldap-test-config-results-steps').empty()
        if (Array.isArray(steps) === false) {
            return
        }

        steps.forEach((step) => {
            const decoration = ldapStatusDecoration(step.status)
            let html = '<li class="mb-1"><i class="fas ' + decoration.icon + ' ' + decoration.css + ' mr-2"></i>'
            html += '<strong>' + htmlEncode(step.label || '') + '</strong>'
            if (step.detail) {
                html += '<br><span class="ml-4 small text-muted">' + htmlEncode(step.detail) + '</span>'
            }
            $list.append(html + '</li>')
        })
    }

    /**
     * Render the configuration findings.
     *
     * @param {Array} findings Findings returned by the handler
     * @return {void}
     */
    const renderLdapConfigFindings = (findings) => {
        const $target = $('#ldap-config-check-findings').empty()

        if (Array.isArray(findings) === false || findings.length === 0) {
            $target.html('<div class="text-success"><i class="fas fa-check-circle mr-2"></i><?php echo $lang->get('ldap_config_check_all_good'); ?></div>')
            return
        }

        findings.forEach((finding) => {
            const decoration = ldapStatusDecoration(finding.severity)
            let html = '<div class="mb-2"><i class="fas ' + decoration.icon + ' ' + decoration.css + ' mr-2"></i>'
            html += htmlEncode(finding.label || '')
            if (finding.suggestion !== '') {
                html += '<div class="ml-4 mt-1 small">'
                html += '<code>' + htmlEncode(finding.suggestion) + '</code>'
                html += ' <button type="button" class="btn btn-outline-primary btn-xs ml-2 ldap-apply-suggestion"'
                html += ' data-field="' + htmlEncode(finding.field) + '"'
                html += ' data-value="' + htmlEncode(finding.suggestion) + '">'
                html += '<?php echo $lang->get('ldap_config_check_apply'); ?></button>'
                html += '</div>'
            }
            $target.append(html + '</div>')
        })
    }

    /**
     * Ask the server to audit the saved settings.
     *
     * @param {boolean} notify Show a toast while the audit runs
     * @return {void}
     */
    const refreshLdapConfigCheck = (notify) => {
        if (notify === true) {
            toastr.remove()
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>')
        }

        $.post(
            'sources/ldap.queries.php', {
                type: 'ldap_check_settings',
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                try {
                    data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>')
                } catch (e) {
                    return
                }
                if (notify === true) {
                    toastr.remove()
                }
                if (data.error === true) {
                    return
                }
                renderLdapConfigFindings(data.findings)
            }
        )
    }

    /**
     * Write a suggested value into its field and let the generic settings handler save it.
     */
    $(document).on('click', '.ldap-apply-suggestion', function() {
        const $field = $('#' + $(this).data('field'))
        if ($field.length === 0) {
            return
        }
        $field.val($(this).data('value')).trigger('change')
        // The audit is re-run once the value is stored, not before.
        setTimeout(function() {
            refreshLdapConfigCheck(false)
        }, 1200)
    })

    /**
     * Re-audit after any LDAP setting is saved.
     */
    $(document).on('change', '.setting-ldap, #ldap_type, #ldap_tls_certificate_check', function() {
        setTimeout(function() {
            refreshLdapConfigCheck(false)
        }, 1200)
    })

    /**
     * On page loaded
     */
    $(function() {
        refreshLdapConfigCheck(false);

        //requestRunning = true;
        // Load list of groups
        $("#ldap_new_user_is_administrated_by").empty();
        var data = {
            'source_page': 'ldap',
        }
        $.post(
            "sources/admin.queries.php", {
                type: "get_list_of_roles",
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");

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
