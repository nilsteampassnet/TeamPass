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
 * @file      profile.js.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('profile') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}
?>


<script type='text/javascript'>
    <?php if (isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1) : ?>
        // If user api is empty then generate one
        if ($('#profile-user-api-token').text() === '') {
            generateNewUserApiKey('profile-user-api-token', true);
        }

        $('#generate-api-key').click(function() {
            generateNewUserApiKey('profile-user-api-token', false);
        });
    <?php endif; ?>

    //iCheck for checkbox and radio inputs
    $('#tab_reset_psk input[type="checkbox"]').iCheck({
        checkboxClass: 'icheckbox_flat-blue'
    })

    // Select user properties
    $('#profile-user-language option[value=<?php echo $session->get('user-language');?>').attr('selected','selected');


    // AVATAR IMPORT
    var uploader_photo = new plupload.Uploader({
        runtimes: 'gears,html5,flash,silverlight,browserplus',
        browse_button: 'profile-avatar-file',
        container: 'profile-avatar-file-container',
        max_file_size: '10mb',
        chunk_size: '1mb',
        unique_names: true,
        dragdrop: true,
        multiple_queues: false,
        multi_selection: false,
        max_file_count: 1,
        filters: [{
            title: 'Image files',
            extensions: 'png,jpg,jpeg'
        }],
        resize: {
            width: '256',
            height: '256',
            quality: '85'
        },
        url: '<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload.files.php',
        flash_swf_url: '<?php echo $SETTINGS['cpassman_url']; ?>/plugins/plupload/js/Moxie.swf',
        silverlight_xap_url: '<?php echo $SETTINGS['cpassman_url']; ?>/plugins/plupload/js/Moxie.xap',
        init: {
            FilesAdded: function(up, files) {
                // generate and save token
                $.post(
                    'sources/main.queries.php', {
                        type: 'save_token',
                        type_category: 'action_system',
                        size: 25,
                        capital: true,
                        secure: true,
                        numeric: true,
                        symbols: true,
                        lowercase: true,
                        unique_names: false,
                        reason: 'avatar_profile_upload',
                        duration: 10,
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(data) {
                        $('#profile-user-token').val(data[0].token);

                        up.setOption('multipart_params', {
                            PHPSESSID: '<?php echo $session->get('key'); ?>',
                            type_upload: "upload_profile_photo",
                            user_upload_token: data[0].token
                        });

                        up.start();
                    },
                    'json'
                );
            },
            BeforeUpload: function(up, file) {
                // Show spinner
                toastr.remove();
                toastr.info('<i class="fa-solid fa-ellipsis fa-2x fa-fade ml-2"></i>');
            },
            FileUploaded: function(upldr, file, object) {
                console.log('BeforeUpload', 'File: ', object.response)
                // Decode returned data
                var myData = prepareExchangedData(object.response, 'decode', '<?php echo $session->get('key'); ?>');
                // update form
                toastr.remove();
                if (myData.error === false) {
                    $('#profile-user-avatar').attr('src', 'assets/avatars/' + myData.filename);
                    $('#profile-avatar-delete').removeClass('hidden');
                    $('#profile-avatar-file-list').html('').addClass('hidden');
                } else {
                    toastr.error(
                        'An error occurred.<br />Returned data:<br />' + myData.message,
                        '', {
                            closeButton: true
                        }
                    );
                    return false;
                }
            }
        }
    });

    // Show runtime status
    uploader_photo.bind('Init', function(up, params) {
        $('#profile-plupload-runtime')
            .html(params.runtime)
            .removeClass('text-danger')
            .addClass('text-info')
            .data('enabled', 1);
    });

    // get error
    uploader_photo.bind('Error', function(up, err) {
        $('#profile-avatar-file-list').html('<div class="ui-state-error ui-corner-all">Error: ' + err.code +
            ', Message: ' + err.message +
            (err.file ? ', File: ' + err.file.name : '') +
            '</div>'
        );
        up.refresh(); // Reposition Flash/Silverlight
    });

    uploader_photo.init();

    try { $('#profile-avatar-delete[data-toggle="tooltip"]').tooltip(); } catch (e) {}

    $(document).on('click', '#profile-avatar-delete', function(e) {
        e.preventDefault();

        launchConfirmDialog(
            '<?php echo addslashes($lang->get('delete_current_avatar')); ?>',
            '<?php echo addslashes($lang->get('delete_current_avatar_confirm')); ?>',
            function() {
                toastr.remove();
                toastr.info('<i class="fas fa-cog fa-spin fa-2x"></i>');

                $.post(
                    'sources/users.queries.php',
                    {
                        type: 'user_profile_avatar_delete',
                        data: prepareExchangedData(JSON.stringify({}), 'encode', '<?php echo $session->get('key'); ?>'),
                        isprofileupdate: true,
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(data) {
                        try {
                            data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                        } catch (e) {
                            toastr.remove();
                            toastr.error(
                                'An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data,
                                '',
                                { closeButton: true }
                            );
                            return false;
                        }

                        toastr.remove();
                        if (data.error === true) {
                            toastr.error(
                                data.message || '<?php echo addslashes($lang->get('avatar_delete_failed')); ?>',
                                '',
                                { closeButton: true }
                            );
                            return false;
                        }

                        $('#profile-user-avatar').attr('src', data.avatar_url || './assets/images/photo.jpg');
                        $('#profile-avatar-delete').addClass('hidden');
                        toastr.success(
                            data.message || '<?php echo addslashes($lang->get('avatar_deleted')); ?>',
                            '',
                            {
                                timeOut: 2000,
                                progressBar: true
                            }
                        );
                    }
                );
            },
            '<?php echo addslashes($lang->get('delete')); ?>'
        );
    });


    // Save user settings
    $('#profile-user-save-settings').click(function() {
        // Sanitize text fields
        let formName = fieldDomPurifier('#profile-user-name', false, false, false),
            formLastname = fieldDomPurifier('#profile-user-lastname', false, false, false),
            formEmail = fieldDomPurifier('#profile-user-email', false, false, false);
        if (formName === false || formLastname === false || formEmail === false) {
            // Label is empty
            toastr.remove();
            toastr.warning(
                'XSS attempt detected. Field has been emptied.',
                'Error', {
                    timeOut: 5000,
                    progressBar: true
                }
            );
            return false;
        }

        // Prepare data
        const data = {
            'name': formName,
            'lastname': formLastname,
            'email': formEmail,
            'timezone': $('#profile-user-timezone').val() ?? '',
            'language': ($('#profile-user-language').val() ?? '').toLowerCase(),
            'split_view_mode': $('#profile-user-split_view_mode').val(),
            'show_subfolders': $('#profile-user-show_subfolders').val(),
        };

        const treeLoadStrategyField = $('#profile-user-treeloadstrategy');
        if (treeLoadStrategyField.length > 0) {
            data.treeloadstrategy = (treeLoadStrategyField.val() ?? '').toLowerCase();
        }
        if (debugJavascript === true) console.log(data);
        // Inform user
        toastr.remove();
        toastr.info('<i class="fas fa-cog fa-spin fa-2x"></i>');

        //Send query
        $.post(
            "sources/users.queries.php", {
                type: 'user_profile_update',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                isprofileupdate: true,
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                //decrypt data
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                } catch (e) {
                    // error
                    toastr.remove();
                    toastr.error(
                        'An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data,
                        '', {
                            closeButton: true
                        }
                    );
                    return false;
                }

                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '', {
                            closeButton: true
                        }
                    );
                } else {
                    const selectedLanguage = ($('#profile-user-language').val() ?? '').toLowerCase();
                    const selectedSplitViewMode = String($('#profile-user-split_view_mode').val() ?? '');
                    const selectedShowSubfolders = String($('#profile-user-show_subfolders').val() ?? '');

                    const languageChanged = selectedLanguage !== '<?php echo strtolower((string) ($session->get('user-language') ?? 'english')); ?>';
                    const splitViewModeChanged = selectedSplitViewMode !== '<?php echo (int) ($session->get('user-split_view_mode') ?? 0); ?>';
                    const showSubfoldersChanged = selectedShowSubfolders !== '<?php echo (int) ($session->get('user-show_subfolders') ?? 0); ?>';

                    $('#profile-username').html(data.name + ' ' + data.lastname);
                    $('#profile-user-name').val(data.name);
                    $('#profile-user-lastname').val(data.lastname);
                    $('#profile-user-email').val(data.email);

                    // Force session update
                    store.update(
                        'teampassUser',
                        function(teampassUser) {
                            teampassUser.name = data.name;
                            teampassUser.lastname = data.lastname;
                            teampassUser.email = data.email;
                            teampassUser.user_name = data.name;
                            teampassUser.user_lastname = data.lastname;
                            teampassUser.user_email = data.email;
                            teampassUser.user_language = data.language;
                            teampassUser.user_timezone = data.timezone;
                            teampassUser.user_treeloadstrategy = data.treeloadstrategy;
                            teampassUser.split_view_mode = parseInt(data.split_view_mode, 10);
                            teampassUser.show_subfolders = parseInt(data.show_subfolders, 10);
                        }
                    );

                    if (languageChanged === true || splitViewModeChanged === true || showSubfoldersChanged === true) {
                        const reloadTarget = (splitViewModeChanged === true || showSubfoldersChanged === true)
                            ? 'index.php?page=items'
                            : 'index.php?page=profile';

                        setTimeout(function() {
                            document.location.href = reloadTarget;
                        }, 3000);

                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('alert_page_will_reload') . ' ... ' . $lang->get('please_wait'); ?>',
                            '', {
                                timeOut: 3000,
                                progressBar: true
                            }
                        );
                    } else {
                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 2000,
                                progressBar: true
                            }
                        );

                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.jstreeForceRefresh = 1;
                            }
                        );
                    }
                }

            }
        );
    });

    /**
     * Undocumented function
     *
     * @return void
     */
    function generateNewUserApiKey(target, silent) {
        var newApiKey = "";

        // Generate key
        $.post(
            "sources/main.queries.php", {
                type: "generate_password",
                type_category: 'action_user',
                size: "39",
                lowercase: "true",
                numerals: "true",
                capitalize: "true",
                symbols: "false",
                secure: "false",
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");

                if (data.key !== "") {
                    newApiKey = data.key;

                    // Save key in session and database
                    var data = {
                        'field' : 'user_api_key',
                        'value' : newApiKey[0],
                        'user_id' : <?php echo $session->get('user-id'); ?>,
                        'context' : '',
                    };
                    
                    $.post(
                        "sources/users.queries.php", {
                            type: "save_user_change",
                            data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                            isprofileupdate: true,
                            key: "<?php echo $session->get('key'); ?>"
                        },
                        function(data) {
                            data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                            $("#" + target).text(newApiKey);
                            if (silent === false) {
                                $('#profile-tabs a[href="#tab_information"]').tab('show');
                                toastr.remove();
                                toastr.info(
                                    '<?php echo $lang->get('done'); ?>',
                                    '', {
                                        timeOut: 2000,
                                        progressBar: true
                                    }
                                );
                            }
                        }
                    );
                }
            }
        );
    }


    //-------------------
    $("#profile-password").simplePassMeter({
        "requirements": {},
        "container": "#profile-password-strength",
        "defaultText": "<?php echo $lang->get('index_pw_level_txt'); ?>",
        "ratings": [
            {
                "minScore": <?php echo TP_PW_STRENGTH_1;?>,
                "className": "meterWarn",
                "text": "<?php echo $lang->get('complex_level1'); ?>"
            },
            {
                "minScore": <?php echo TP_PW_STRENGTH_2;?>,
                "className": "meterWarn",
                "text": "<?php echo $lang->get('complex_level2'); ?>"
            },
            {
                "minScore": <?php echo TP_PW_STRENGTH_3;?>,
                "className": "meterGood",
                "text": "<?php echo $lang->get('complex_level3'); ?>"
            },
            {
                "minScore": <?php echo TP_PW_STRENGTH_4;?>,
                "className": "meterGood",
                "text": "<?php echo $lang->get('complex_level4'); ?>"
            },
            {
                "minScore": <?php echo TP_PW_STRENGTH_5;?>,
                "className": "meterExcel",
                "text": "<?php echo $lang->get('complex_level5'); ?>"
            }
        ]
    });
    $("#profile-password").bind({
        "score.simplePassMeter": function(jQEvent, score) {
            $("#profile-password-complex").val(score);
        }
    }).change({
        "score.simplePassMeter": function(jQEvent, score) {
            $("#profile-password-complex").val(score);
        }
    });

    $('#profile-save-password-change').click(function() {
        // Check if passwords are the same
        if ($('#profile-password').val() !== $('#profile-password-confirm').val() ||
            $('#profile-password').val() === '' ||
            $('#profile-password-confirm').val() === ''
        ) {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('index_pw_error_identical'); ?>',
                '', {
                    timeOut: 10000,
                    closeButton: true,
                    progressBar: true
                }
            );
            return false;
        }
        // Inform user
        toastr.remove();
        toastr.info('<i class="fas fa-cog fa-spin fa-2x"></i>');

        var data = {
            // IMPORTANT: Do NOT sanitize passwords (fix 3.1.5.10)
            'new_pw': $('#profile-password').val(),
            'complexity': $('#profile-password-complex').val(),
            "change_request": 'user_decides_to_change_password',
            "user_id": store.get('teampassUser').user_id,
        };

        //Send query
        $.post(
            "sources/main.queries.php", {
                type: "change_pw",
                type_category: 'action_password',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $session->get('key'); ?>');
                console.log(data);

                if (data.error === true) {
                    $('#profile-password').focus();
                    toastr.remove();
                    toastr.warning(
                        '<?php echo $lang->get('your_attention_is_required'); ?>',
                        data.message, {
                            timeOut: 10000,
                            closeButton: true,
                            progressBar: true
                        }
                    );
                } else {
                    $('#profile-password, #profile-password-confirm').val('');
                    toastr.remove();
                    toastr.success(
                        '<?php echo $lang->get('done'); ?>',
                        data.message, {
                            timeOut: 2000,
                            progressBar: true
                        }
                    );

                    window.location.href = "./index.php";
                }

            }
        );
    });


    // ----
    $("#profile-saltkey").simplePassMeter({
        "requirements": {},
        "container": "#profile-saltkey-strength",
        "defaultText": "<?php echo $lang->get('index_pw_level_txt'); ?>",
        "ratings": [
            {
                "minScore": <?php echo TP_PW_STRENGTH_1;?>,
                "className": "meterWarn",
                "text": "<?php echo $lang->get('complex_level1'); ?>"
            },
            {
                "minScore": <?php echo TP_PW_STRENGTH_2;?>,
                "className": "meterWarn",
                "text": "<?php echo $lang->get('complex_level2'); ?>"
            },
            {
                "minScore": <?php echo TP_PW_STRENGTH_3;?>,
                "className": "meterGood",
                "text": "<?php echo $lang->get('complex_level3'); ?>"
            },
            {
                "minScore": <?php echo TP_PW_STRENGTH_4;?>,
                "className": "meterGood",
                "text": "<?php echo $lang->get('complex_level4'); ?>"
            },
            {
                "minScore": <?php echo TP_PW_STRENGTH_5;?>,
                "className": "meterExcel",
                "text": "<?php echo $lang->get('complex_level5'); ?>"
            }
        ]
    });
    $("#profile-saltkey").bind({
        "score.simplePassMeter": function(jQEvent, score) {
            $("#profile-saltkey-complex").val(score);
        }
    }).change({
        "score.simplePassMeter": function(jQEvent, score) {
            $("#profile-saltkey-complex").val(score);
        }
    });

    $('#profile-keys_download-date').text('<?php echo null === $session->get('user-keys_recovery_time') ? $lang->get('none') : date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $session->get('user-keys_recovery_time')); ?>');

    $("#open-dialog-keys-download").on('click', function(event) {
        event.preventDefault();
        $('#dialog-recovery-keys-download').removeClass('hidden');

        // Default text on dialog box
        let dialog_content = '<?php echo $lang->get('download_recovery_keys_confirmation'); ?>'

        // Request authentication on local and ldap accounts
        if (store.get('teampassUser').auth_type !== 'oauth2') {
            dialog_content += '<br/><br/><?php echo $lang->get('confirm_password'); ?>' +
                '<input type="password" placeholder="<?php echo $lang->get('password'); ?>" class="form-control" id="keys-download-confirm-pwd" />';
        }

        // Prepare modal
        showModalDialogBox(
            '#warningModal',
            '<i class="fa-solid fa-user-shield fa-lg warning mr-2"></i><?php echo $lang->get('caution'); ?>',
            dialog_content,
            '<?php echo $lang->get('download'); ?>',
            '<?php echo $lang->get('close'); ?>',
            false,
            false,
            false
        );

        let RequestOnGoing = false;
        $(document).on('click', '#warningModalButtonAction', function(event) {
            event.preventDefault();

            // Ensure that a password is provided by user
            const user_pasword = $('#keys-download-confirm-pwd').val() ?? '';
            if (store.get('teampassUser').auth_type !== 'oauth2' && !user_pasword) {
                toastr.remove();
                toastr.error(
                    '<?php echo $lang->get('password_cannot_be_empty'); ?>',
                    '<?php echo $lang->get('caution'); ?>', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
                return false;
            }

            if (RequestOnGoing === true) {
                return false;
            }
            RequestOnGoing = true;

            $('#warningModalButtonAction')
                .addClass('disabled')
                .html('<i class="fa-solid fa-spinner fa-spin"></i>');
            $('#warningModalButtonClose').addClass('disabled');

            // SHow user
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>');

            let data = {
                password: user_pasword,
            };
            // Do query
            $.post(
                "sources/main.queries.php", {
                    'type': "user_recovery_keys_download",
                    'type_category': 'action_key',
                    'key': '<?php echo $session->get('key'); ?>',
                    'data': prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                },
                function(data) {
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                    if (debugJavascript === true) console.log(data)
                    if (data.error === true) {
                        // error
                        toastr.remove();
                        toastr.error(
                            data.message,
                            '<?php echo $lang->get('caution'); ?>', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );

                        // Enable buttons
                        $('#warningModalButtonAction')
                            .removeClass('disabled')
                            .html('<?php echo $lang->get('download'); ?>');
                        RequestOnGoing = false;

                        return false;
                    } else {
                        $('#profile-keys_download-date').text(data.datetime);
                        $('#keys_not_recovered, #open_user_keys_management').addClass('hidden');
                        store.update(
                            'teampassUser', {},
                            function(teampassUser) {
                                teampassUser.keys_recovery_time = data.datetime;
                            }
                        );

                        download(new Blob([atob(data.content)]), "teampass_recovery_key_"+data.login+"_"+data.timestamp+".txt", "text/text");

                        $("#warningModalButtonAction").addClass('hidden');
                        $('#warningModalButtonClose').removeClass('disabled');

                        toastr.remove();
                        RequestOnGoing = true;
                    }
                    $('#warningModalButtonAction').removeClass('disabled')
                }
            );
        });        
    });

    // Handle the copy in clipboard button for api key
    document.getElementById('copy-api-key').addEventListener('click', function() {
        const apiKey = document.getElementById('profile-user-api-token').textContent;
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

    <?php if (isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1
        && isset($SETTINGS['oauth2_api_enabled']) === true && (int) $SETTINGS['oauth2_api_enabled'] === 1
        && $session->get('user-auth_type') === 'oauth2') : ?>
    /**
     * Browser extension tokens (Personal Access Tokens) for OAuth2 users.
     */
    var extensionTokenKey = '<?php echo $session->get('key'); ?>';

    function renderExtensionTokens(tokens) {
        var list = $('#extension-tokens-list');
        if (list.length === 0) {
            return;
        }
        if (!tokens || tokens.length === 0) {
            list.html('<span class="text-muted"><?php echo $lang->get('extension_token_none'); ?></span>');
            return;
        }
        var html = '<table class="table table-sm table-striped mb-0"><tbody>';
        tokens.forEach(function(token) {
            var created = new Date(token.created_at * 1000).toLocaleString();
            var lastUsed = token.last_used_at ? new Date(token.last_used_at * 1000).toLocaleString() : '<?php echo $lang->get('extension_token_never_used'); ?>';
            // Label is escaped to prevent XSS (also sanitized server-side on insert).
            var label = token.label ? $('<span>').text(token.label).html() : '<i class="text-muted">&mdash;</i>';
            html += '<tr>'
                + '<td>' + label + '</td>'
                + '<td class="text-muted small"><?php echo $lang->get('extension_token_created'); ?>: ' + created + '<br><?php echo $lang->get('extension_token_last_used'); ?>: ' + lastUsed + '</td>'
                + '<td class="text-right"><button type="button" class="btn btn-sm btn-danger revoke-extension-token" data-id="' + parseInt(token.id, 10) + '" title="<?php echo $lang->get('extension_token_revoke'); ?>"><i class="fa-solid fa-trash"></i></button></td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        list.html(html);
    }

    function loadExtensionTokens() {
        $.post(
            'sources/users.queries.php', {
                type: 'list_extension_tokens',
                data: prepareExchangedData(JSON.stringify({}), 'encode', extensionTokenKey),
                key: extensionTokenKey
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', extensionTokenKey);
                if (data.error === false) {
                    renderExtensionTokens(data.tokens);
                }
            }
        );
    }

    if ($('#extension-tokens-block').length > 0) {
        loadExtensionTokens();
    }

    $(document).on('click', '#generate-extension-token', function() {
        $.post(
            'sources/users.queries.php', {
                type: 'generate_extension_token',
                data: prepareExchangedData(JSON.stringify({ label: '' }), 'encode', extensionTokenKey),
                key: extensionTokenKey
            },
            function(response) {
                response = prepareExchangedData(response, 'decode', extensionTokenKey);
                if (response.error === false) {
                    $('#extension-token-value').val(response.token);
                    $('#extension-token-modal').modal('show');
                    loadExtensionTokens();
                } else {
                    toastr.remove();
                    toastr.error(response.message, '', {
                        closeButton: true,
                        positionClass: 'toast-bottom-right'
                    });
                }
            }
        );
    });

    $(document).on('click', '.revoke-extension-token', function() {
        if (window.confirm('<?php echo $lang->get('extension_token_revoke_confirm'); ?>') === false) {
            return;
        }
        var tokenId = parseInt($(this).data('id'), 10);
        $.post(
            'sources/users.queries.php', {
                type: 'revoke_extension_token',
                data: prepareExchangedData(JSON.stringify({ id: tokenId }), 'encode', extensionTokenKey),
                key: extensionTokenKey
            },
            function(response) {
                response = prepareExchangedData(response, 'decode', extensionTokenKey);
                if (response.error === false) {
                    loadExtensionTokens();
                    toastr.remove();
                    toastr.success('<?php echo $lang->get('done'); ?>', '', {
                        timeOut: 1500
                    });
                }
            }
        );
    });

    document.getElementById('copy-extension-token').addEventListener('click', function() {
        const tokenValue = document.getElementById('extension-token-value').value;
        navigator.clipboard.writeText(tokenValue).then(function() {
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
    <?php endif; ?>

    <?php if (isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1) : ?>
    /**
     * Active API sessions (one per issued JWT) — list and revoke.
     */
    var apiSessionsKey = '<?php echo $session->get('key'); ?>';

    function renderApiSessions(sessions) {
        var list = $('#api-sessions-list');
        if (list.length === 0) {
            return;
        }
        if (!sessions || sessions.length === 0) {
            list.html('<span class="text-muted"><?php echo $lang->get('api_sessions_none'); ?></span>');
            return;
        }
        var html = '<table class="table table-sm table-striped mb-0"><tbody>';
        sessions.forEach(function(apiSession) {
            var created = new Date(apiSession.created_at * 1000).toLocaleString();
            var expires = new Date(apiSession.expires_at * 1000).toLocaleString();
            var lastUsed = apiSession.last_used_at ? new Date(apiSession.last_used_at * 1000).toLocaleString() : '<?php echo $lang->get('extension_token_never_used'); ?>';
            // User agent is escaped to prevent XSS
            var client = apiSession.user_agent ? $('<span>').text(apiSession.user_agent).html() : '<i class="text-muted">&mdash;</i>';
            html += '<tr>'
                + '<td class="small">' + client + '</td>'
                + '<td class="text-muted small"><?php echo $lang->get('extension_token_created'); ?>: ' + created
                + '<br><?php echo $lang->get('extension_token_last_used'); ?>: ' + lastUsed
                + '<br><?php echo $lang->get('expiration_date'); ?>: ' + expires + '</td>'
                + '<td class="text-right"><button type="button" class="btn btn-sm btn-danger revoke-api-session" data-id="' + parseInt(apiSession.id, 10) + '" title="<?php echo $lang->get('api_session_revoke'); ?>"><i class="fa-solid fa-ban"></i></button></td>'
                + '</tr>';
        });
        html += '</tbody></table>';
        list.html(html);
    }

    function loadApiSessions() {
        $.post(
            'sources/users.queries.php', {
                type: 'list_api_sessions',
                data: prepareExchangedData(JSON.stringify({}), 'encode', apiSessionsKey),
                key: apiSessionsKey
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', apiSessionsKey);
                if (data.error === false) {
                    renderApiSessions(data.sessions);
                }
            }
        );
    }

    if ($('#api-sessions-block').length > 0) {
        loadApiSessions();
    }

    $(document).on('click', '.revoke-api-session', function() {
        if (window.confirm('<?php echo $lang->get('api_session_revoke_confirm'); ?>') === false) {
            return;
        }
        var apiSessionId = parseInt($(this).data('id'), 10);
        $.post(
            'sources/users.queries.php', {
                type: 'revoke_api_session',
                data: prepareExchangedData(JSON.stringify({ id: apiSessionId }), 'encode', apiSessionsKey),
                key: apiSessionsKey
            },
            function(response) {
                response = prepareExchangedData(response, 'decode', apiSessionsKey);
                if (response.error === false) {
                    loadApiSessions();
                    toastr.remove();
                    toastr.success('<?php echo $lang->get('done'); ?>', '', {
                        timeOut: 1500
                    });
                }
            }
        );
    });
    <?php endif; ?>

</script>
