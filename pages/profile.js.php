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
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('profile') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    <?php if (isset($SETTINGS['api']) === true && (int) $SETTINGS['api'] === 1) : ?>
        // If user api is empty then generate one
        if ($('#profile-user-api-token').text() === '') {
            generateNewUserApiKey('profile-user-api-token', true);
        }

        $('#profile-button-api_token').click(function() {
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
        max_file_size: '2mb',
        chunk_size: '1mb',
        unique_names: true,
        dragdrop: true,
        multiple_queues: false,
        multi_selection: false,
        max_file_count: 1,
        filters: [{
            title: 'PNG files',
            extensions: 'png'
        }],
        resize: {
            width: '90',
            height: '90',
            quality: '90'
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
                            user_token: data[0].token
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
                if (myData.error === false) {
                    $('#profile-user-avatar').attr('src', 'includes/avatars/' + myData.filename);
                    $('#profile-avatar-file-list').html('').addClass('hidden');
                } else {
                    toastr.remove();
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
        var data = {
            'name': formName,
            'lastname': formLastname,
            'email': formEmail,
            'timezone': $('#profile-user-timezone').val(),
            'language': $('#profile-user-language').val().toLowerCase(),
            'treeloadstrategy': $('#profile-user-treeloadstrategy').val().toLowerCase(),
            'agsescardid': $('#profile-user-agsescardid').length > 0 ? $('#profile-user-agsescardid').val() : '',
        }
        console.log(data);
        //return false;
        // " onmouseover="confirm(document.cookie)"
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
                    $('#profile-username').html(data.name + ' ' + data.lastname);
                    $('#profile-user-name').val(data.name)
                    $('#profile-user-lastname').val(data.lastname)
                    $('#profile-user-email').val(data.email)

                    // reload page in case of language change
                    if ($('#profile-user-language').val().toLowerCase() !== '<?php echo $session->get('user-language');?>') {
                        // prepare reload
                        $(this).delay(3000).queue(function() {
                            document.location.href = "index.php?page=profile";

                            $(this).dequeue();
                        });

                        // Inform user
                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('alert_page_will_reload') . ' ... ' . $lang->get('please_wait'); ?>',
                            '', {
                                timeOut: 3000,
                                progressBar: true
                            }
                        );

                    } else {
                        // just inform user
                        toastr.remove();
                        toastr.info(
                            '<?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 2000,
                                progressBar: true
                            }
                        );

                        // Force tree refresh
                        store.update(
                            'teampassApplication',
                            function(teampassApplication) {
                                teampassApplication.jstreeForceRefresh = 1
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
                    console.log(data)
                    
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
            'new_pw': DOMPurify.sanitize($('#profile-password').val()),
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

                    window.location.href = "index.php";
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

        // Prepare modal
        showModalDialogBox(
            '#warningModal',
            '<i class="fa-solid fa-user-shield fa-lg warning mr-2"></i><?php echo $lang->get('caution'); ?>',
            '<?php echo $lang->get('download_recovery_keys_confirmation'); ?>',
            '<?php echo $lang->get('download'); ?>',
            '<?php echo $lang->get('close'); ?>',
            false,
            false,
            false
        );

        // Actions on modal buttons
        $(document).on('click', '#warningModalButtonClose', function() {
            store.update(
                'teampassUser', {},
                function(teampassUser) {
                    teampassUser.shown_warning_unsuccessful_login = true;
                }
            );
        });
        let RequestOnGoing = false;
        $(document).on('click', '#warningModalButtonAction', function(event) {
            event.preventDefault();

            if (RequestOnGoing === true) {
                return false;
            }
            RequestOnGoing = true;

            // We have the password, start reencryption
            $('#warningModalButtonAction')
                .addClass('disabled')
                .html('<i class="fa-solid fa-spinner fa-spin"></i>');
            $('#warningModalButtonClose').addClass('disabled');

            // SHow user
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?><i class="fa-solid fa-circle-notch fa-spin fa-2x ml-3"></i>');

            // Do query
            $.post(
                "sources/main.queries.php", {
                    'type': "user_recovery_keys_download",
                    'type_category': 'action_key',
                    'key': '<?php echo $session->get('key'); ?>'
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
                        $("#user-current-defuse-psk-progress").html('<?php echo $lang->get('provide_current_psk_and_click_launch'); ?>');
                        $('#button_do_sharekeys_reencryption, #button_close_sharekeys_reencryption').removeAttr('disabled');
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

            // Action
            store.update(
                'teampassUser', {},
                function(teampassUser) {
                    teampassUser.shown_warning_unsuccessful_login = true;
                }
            );
        });
        
    });
</script>
