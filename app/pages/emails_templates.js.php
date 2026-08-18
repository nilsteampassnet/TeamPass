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
 * @file      emails_templates.js.php
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
if (
    $checkUserAccess->checkSession() === false
    || $checkUserAccess->userAccessPage('emails_templates') === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Labels of the catalog groups, in the administrator language
$groupLabels = [
    'users' => $lang->get('emails_templates_group_users'),
    'auth' => $lang->get('emails_templates_group_auth'),
    'security' => $lang->get('emails_templates_group_security'),
    'items' => $lang->get('emails_templates_group_items'),
    'maintenance' => $lang->get('emails_templates_group_maintenance'),
];
?>


<script type='text/javascript'>
    //<![CDATA[

    // Currently opened template, empty when the editor is closed
    var emailsTemplatesCurrent = '',
        emailsTemplatesGroupLabels = <?php echo json_encode($groupLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

    /**
     * Send an action to the templates handler.
     *
     * Neither direction goes through the client purifier: it returns plain text for every
     * field that is not named 'description'/'desc'/'html', and this page exchanges nothing
     * but the HTML of the email bodies.
     * On the way OUT it would strip the markup from the whole payload before it is even
     * sent — the administrator saves bold text and stores plain text. On the way IN it
     * would show a tag-stripped body, which the next save then makes permanent.
     * Safety therefore lives at each sink: .text() for plain values, DOMPurify for the
     * rendered preview, and the server runs xss_clean on every save.
     */
    function emailsTemplatesPost(type, data, onSuccess) {
        $.post(
            'sources/emails_templates.queries.php', {
                type: type,
                data: prepareExchangedData(
                    JSON.stringify(data),
                    'encode',
                    '<?php echo $session->get('key'); ?>',
                    'emails_templates.js.php',
                    type,
                    false
                ),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(receivedData) {
                var answer;
                try {
                    answer = prepareExchangedData(
                        receivedData,
                        'decode',
                        '<?php echo $session->get('key'); ?>',
                        'emails_templates.js.php',
                        type,
                        false
                    );
                } catch (exception) {
                    emailsTemplatesFailed('<?php echo $lang->get('error'); ?>');
                    return;
                }

                if (answer === false || answer === undefined) {
                    // prepareExchangedData() already reported it in a modal
                    toastr.remove();
                    return;
                }

                if (answer.error === true) {
                    emailsTemplatesFailed(
                        answer.message ? answer.message : '<?php echo $lang->get('error'); ?>'
                    );
                    return;
                }

                onSuccess(answer);
            }
        ).fail(function() {
            // Without this the "in progress" toast stays on screen for ever
            emailsTemplatesFailed('<?php echo $lang->get('error'); ?>');
        });
    }

    /**
     * Clear any pending progress toast and report the failure.
     */
    function emailsTemplatesFailed(message) {
        toastr.remove();
        toastr.error(
            htmlEncode(message),
            '', {
                timeOut: 5000,
                progressBar: true
            }
        );
    }

    /**
     * Is the rich text editor available?
     *
     * summernote is loaded only for this page (see public/index.php). Calling it when it is
     * absent throws, and every statement after the call in the same callback is skipped —
     * which is exactly how a missing script tag once looked like "the body stays empty, the
     * preview does nothing and the loader never stops". Fail loudly instead.
     */
    function emailsTemplatesEditorReady() {
        if (typeof $.fn.summernote !== 'undefined') {
            return true;
        }
        emailsTemplatesFailed('<?php echo $lang->get('emails_templates_editor_missing'); ?>');
        return false;
    }

    /**
     * Load a body into the editor, recreating it so no content leaks between templates.
     */
    function emailsTemplatesSetBody(html) {
        if (emailsTemplatesEditorReady() === false) {
            return;
        }

        if ($('#emails-templates-body').next('.note-editor').length > 0) {
            $('#emails-templates-body').summernote('destroy');
        }
        // Only the formatting that survives the sanitizing applied when the email is
        // actually sent is offered. Colours, highlighting and alignment are inline
        // styles, and xss_clean() drops every style attribute, so those buttons would
        // let the administrator format text that the recipient never sees.
        $('#emails-templates-body').summernote({
            height: 260,
            toolbar: [
                ['font', ['bold', 'italic', 'underline', 'clear']],
                ['para', ['ul', 'ol']],
                ['insert', ['link']],
                ['view', ['codeview']]
            ],
            codeviewFilter: false,
            codeviewIframeFilter: true
        });
        $('#emails-templates-body').summernote('code', html);
        $('.btn-light').addClass('btn-secondary').removeClass('btn-light');
    }

    /**
     * Current content of the editor, or null when it is unavailable.
     */
    function emailsTemplatesGetBody() {
        if (emailsTemplatesEditorReady() === false) {
            return null;
        }

        return $('#emails-templates-body').summernote('code');
    }

    /**
     * Refresh the language selector and the templates list.
     */
    function emailsTemplatesLoadList(language) {
        emailsTemplatesPost(
            'load_templates_list', {
                language: language
            },
            function(answer) {
                // Language selector, built once
                if ($('#emails-templates-language option').length === 0) {
                    $.each(answer.languages, function(index, item) {
                        $('#emails-templates-language').append(
                            $('<option></option>').attr('value', item.name).text(item.label)
                        );
                    });
                }
                $('#emails-templates-language').val(answer.language);

                // Templates list, one collapsible block per group
                var $list = $('#emails-templates-list').empty();
                $.each(answer.groups, function(groupId, templates) {
                    var customized = 0;
                    $.each(templates, function(index, template) {
                        if (template.customized === true) {
                            customized++;
                        }
                    });

                    var $group = $('<div class="mb-3"></div>'),
                        $header = $('<div class="font-weight-bold text-uppercase small text-muted mb-1"></div>')
                            .text(emailsTemplatesGroupLabels[groupId] !== undefined
                                ? emailsTemplatesGroupLabels[groupId] : groupId);
                    if (customized > 0) {
                        $header.append($('<span class="badge badge-primary ml-2"></span>').text(customized));
                    }
                    $group.append($header);

                    var $items = $('<div class="list-group list-group-flush"></div>');
                    $.each(templates, function(index, template) {
                        var $item = $('<a href="#" class="list-group-item list-group-item-action py-1 px-2 emails-templates-item"></a>')
                            .attr('data-template', template.id)
                            .text(template.label);
                        if (template.customized === true) {
                            $item.prepend('<i class="fas fa-circle text-primary mr-2" style="font-size:.6rem;"></i>');
                        }
                        if (template.id === emailsTemplatesCurrent) {
                            $item.addClass('active');
                        }
                        $items.append($item);
                    });
                    $group.append($items);
                    $list.append($group);
                });
            }
        );
    }

    /**
     * Open one template in the editor.
     */
    function emailsTemplatesLoad(templateId) {
        emailsTemplatesPost(
            'get_template', {
                template: templateId,
                language: $('#emails-templates-language').val()
            },
            function(answer) {
                emailsTemplatesCurrent = answer.template;

                $('#emails-templates-title').text(answer.label);
                $('#emails-templates-description').text(answer.description);
                $('#emails-templates-editor').removeClass('hidden');

                // Subject — absent on a fragment
                if (answer.subject_key === '') {
                    $('#emails-templates-subject-group').addClass('hidden');
                } else {
                    $('#emails-templates-subject-group').removeClass('hidden');
                    $('#emails-templates-subject').val(answer.subject);
                }

                // Subject shared with other emails
                if (answer.shared_subject_with.length > 0) {
                    $('#emails-templates-shared-subject')
                        .text('<?php echo $lang->get('emails_templates_shared_subject'); ?> ' +
                            answer.shared_subject_with.join(', '))
                        .removeClass('hidden');
                } else {
                    $('#emails-templates-shared-subject').addClass('hidden');
                }

                // No row in this language, but an English customization applies
                if (answer.inherited_from_english === true) {
                    $('#emails-templates-inherited')
                        .text('<?php echo $lang->get('emails_templates_inherited_from_english'); ?>')
                        .removeClass('hidden');
                } else {
                    $('#emails-templates-inherited').addClass('hidden');
                }

                // Body editor
                emailsTemplatesSetBody(answer.body);

                // Token chips
                var $tokens = $('#emails-templates-tokens').empty();
                $.each(answer.tokens, function(index, token) {
                    var required = answer.required_tokens.indexOf(token) !== -1;
                    $tokens.append(
                        $('<button type="button" class="btn btn-xs mr-1 mb-1 emails-templates-token"></button>')
                            .addClass(required === true ? 'btn-warning' : 'btn-outline-secondary')
                            .attr('data-token', token)
                            .text(token)
                    );
                });

                // Shipped default, collapsed
                $('#emails-templates-default-subject').text(answer.subject_shipped);
                $('#emails-templates-default-body').html(
                    DOMPurify.sanitize(answer.body_shipped, {USE_PROFILES: {html: true}})
                );
                $('#emails-templates-default').addClass('hidden');

                // Audit trail — the author login is inserted as text, never as markup
                if (answer.updated_at !== '') {
                    var $audit = $('#emails-templates-audit').empty();
                    $audit.append('<i class="fas fa-clock-rotate-left mr-2"></i>');
                    $audit.append($('<span></span>').text(
                        '<?php echo $lang->get('emails_templates_customized_on'); ?> ' +
                        answer.updated_at + ' — '
                    ));
                    $audit.append($('<span></span>').text(answer.updated_by));
                    $audit.removeClass('hidden');
                } else {
                    $('#emails-templates-audit').addClass('hidden');
                }

                $('.emails-templates-item').removeClass('active');
                $('.emails-templates-item[data-template="' + answer.template + '"]').addClass('active');
            }
        );
    }

    $(function() {
        emailsTemplatesLoadList('');

        // Change of language: the list and the editor both belong to a language
        $(document).on('change', '#emails-templates-language', function() {
            emailsTemplatesCurrent = '';
            $('#emails-templates-editor').addClass('hidden');
            $('#emails-templates-title').text('<?php echo $lang->get('emails_templates_select_one'); ?>');
            emailsTemplatesLoadList($(this).val());
        });

        $(document).on('click', '.emails-templates-item', function(event) {
            event.preventDefault();
            emailsTemplatesLoad($(this).data('template'));
        });

        // Insert a token where the caret is
        $(document).on('click', '.emails-templates-token', function() {
            var token = $(this).data('token');
            if ($('#emails-templates-subject').is(':focus') === true) {
                var field = $('#emails-templates-subject')[0],
                    start = field.selectionStart,
                    value = $('#emails-templates-subject').val();
                $('#emails-templates-subject').val(
                    value.substring(0, start) + token + value.substring(field.selectionEnd)
                );
                return;
            }
            if (emailsTemplatesEditorReady() === false) {
                return;
            }
            // The click blurred the editor: restore the caret before inserting
            $('#emails-templates-body').summernote('editor.restoreRange');
            $('#emails-templates-body').summernote('editor.focus');
            $('#emails-templates-body').summernote('editor.insertText', token);
        });

        $(document).on('click', '#emails-templates-show-default', function(event) {
            event.preventDefault();
            $('#emails-templates-default').toggleClass('hidden');
        });

        $(document).on('click', '#emails-templates-save', function() {
            var payload = emailsTemplatesCurrent === '' ? null : emailsTemplatesEditorPayload();
            if (payload === null) {
                return;
            }

            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            emailsTemplatesPost(
                'save_template',
                payload,
                function(answer) {
                    toastr.remove();
                    toastr.success('<?php echo $lang->get('done'); ?>', '', {
                        timeOut: 1000
                    });

                    if (answer.unused_tokens.length > 0) {
                        toastr.warning(
                            '<?php echo $lang->get('emails_templates_warning_unused_tokens'); ?> ' +
                                answer.unused_tokens.join(' '),
                            '', {
                                timeOut: 6000,
                                progressBar: true
                            }
                        );
                    }

                    emailsTemplatesLoadList($('#emails-templates-language').val());
                    emailsTemplatesLoad(emailsTemplatesCurrent);
                }
            );
        });

        // Preview and test both render what is currently in the editor, unsaved
        // changes included, through the same normalization a save would apply.
        function emailsTemplatesEditorPayload() {
            var body = emailsTemplatesGetBody();
            if (body === null) {
                return null;
            }

            return {
                template: emailsTemplatesCurrent,
                language: $('#emails-templates-language').val(),
                subject: $('#emails-templates-subject').val(),
                body: body
            };
        }

        $(document).on('click', '#emails-templates-preview', function() {
            var payload = emailsTemplatesCurrent === '' ? null : emailsTemplatesEditorPayload();
            if (payload === null) {
                return;
            }

            emailsTemplatesPost(
                'preview_template',
                payload,
                function(answer) {
                    if (answer.fragment === true) {
                        $('#emails-templates-preview-fragment').removeClass('hidden');
                        $('#emails-templates-preview-subject-group').addClass('hidden');
                    } else {
                        $('#emails-templates-preview-fragment').addClass('hidden');
                        $('#emails-templates-preview-subject-group').removeClass('hidden');
                        $('#emails-templates-preview-subject').text(answer.subject);
                    }
                    $('#emails-templates-preview-body').html(
                        DOMPurify.sanitize(answer.body, {USE_PROFILES: {html: true}})
                    );
                    $('#emails-templates-preview-modal').modal('show');
                }
            );
        });

        $(document).on('click', '#emails-templates-send-test', function() {
            // Build the payload BEFORE showing the progress toast, so a failure here
            // cannot leave it spinning for ever.
            var payload = emailsTemplatesCurrent === '' ? null : emailsTemplatesEditorPayload();
            if (payload === null) {
                return;
            }

            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            emailsTemplatesPost(
                'send_test_template',
                payload,
                function(answer) {
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('emails_templates_send_test_done'); ?> ' +
                            htmlEncode(answer.recipient),
                        '', {
                            timeOut: 3000
                        }
                    );
                }
            );
        });

        $(document).on('click', '#emails-templates-reset', function() {
            if (emailsTemplatesCurrent === '') {
                return;
            }

            launchConfirmDialog(
                <?php echo json_encode($lang->get('emails_templates_reset'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                <?php echo json_encode($lang->get('emails_templates_reset_confirm'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                function() {
                    emailsTemplatesPost(
                        'reset_template', {
                            template: emailsTemplatesCurrent,
                            language: $('#emails-templates-language').val()
                        },
                        function() {
                            toastr.remove();
                            toastr.success('<?php echo $lang->get('done'); ?>', '', {
                                timeOut: 1000
                            });
                            emailsTemplatesLoadList($('#emails-templates-language').val());
                            emailsTemplatesLoad(emailsTemplatesCurrent);
                        }
                    );
                },
                <?php echo json_encode($lang->get('emails_templates_reset'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
                <?php echo json_encode($lang->get('cancel'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
            );
        });
    });

    //]]>
</script>
