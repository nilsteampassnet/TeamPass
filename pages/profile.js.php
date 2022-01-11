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
 *
 * @file      profile.js.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id']) === true
    || isset($_SESSION['key']) === false || empty($_SESSION['key']) === true
) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php') === true) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php') === true) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception('Error file "/includes/config/tp.config.php" not exists', 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    // If user api is empty then generate one
    if ($('#profile-user-api-token').text().length !== 39) {
        generateNewUserApiKey('profile-user-api-token', true);
    }


    $('#profile-button-api_token').click(function() {
        generateNewUserApiKey('profile-user-api-token', false);
    });


    //iCheck for checkbox and radio inputs
    $('#tab_reset_psk input[type="checkbox"]').iCheck({
        checkboxClass: 'icheckbox_flat-blue'
    })


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
        flash_swf_url: '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.swf',
        silverlight_xap_url: '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.xap',
        init: {
            FilesAdded: function(up, files) {
                // generate and save token
                $.post(
                    'sources/main.queries.php', {
                        type: 'save_token',
                        size: 25,
                        capital: true,
                        secure: true,
                        numeric: true,
                        symbols: true,
                        lowercase: true,
                        reason: 'avatar_profile_upload',
                        duration: 10,
                        key: '<?php echo $_SESSION['key']; ?>'
                    },
                    function(data) {
                        $('#profile-user-token').val(data[0].token);
                        up.start();
                    },
                    'json'
                );
            },
            BeforeUpload: function(up, file) {
                var tmp = Math.random().toString(36).substring(7);

                up.settings.multipart_params = {
                    'PHPSESSID': '<?php echo $_SESSION['user_id']; ?>',
                    'type_upload': 'upload_profile_photo',
                    'user_token': $('#profile-user-token').val()
                };
            },
            FileUploaded: function(upldr, file, object) {
                // Decode returned data
                var myData = prepareExchangedData(object.response, 'decode', '<?php echo $_SESSION['key']; ?>');
                // update form
                $('#profile-user-avatar').attr('src', 'includes/avatars/' + myData.filename);
                $('#profile-avatar-file-list').html('').addClass('hidden');
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
        var data = {
            'name': $('#profile-user-name').val(),
            'lastname': $('#profile-user-lastname').val(),
            'email': $('#profile-user-email').val(),
            'timezone': $('#profile-user-timezone').val(),
            'language': $('#profile-user-language').val().toLowerCase(),
            'treeloadstrategy': $('#profile-user-treeloadstrategy').val().toLowerCase(),
            'agsescardid': $('#profile-user-agsescardid').length > 0 ? $('#profile-user-agsescardid').val() : '',
        }
        console.log(data)
        // Inform user
        toastr.remove();
        toastr.info('<i class="fas fa-cog fa-spin fa-2x"></i>');

        //Send query
        $.post(
            "sources/users.queries.php", {
                type: 'user_profile_update',
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                isprofileupdate: true,
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                //decrypt data
                try {
                    data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");
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
                //console.log(data)

                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '', {
                            closeButton: true
                        }
                    );
                } else {
                    $('#profile-username').html($('#profile-user-name').val() + ' ' + $('#profile-user-lastname').val());

                    // reload page in case of language change
                    if ($('#profile-user-language').val().toLowerCase() !== '<?php echo $_SESSION['user_language'];?>') {
                        // prepare reload
                        $(this).delay(3000).queue(function() {
                            document.location.href = "index.php?page=profile";

                            $(this).dequeue();
                        });

                        // Inform user
                        toastr.remove();
                        toastr.info(
                            '<?php echo langHdl('alert_page_will_reload') . ' ... ' . langHdl('please_wait'); ?>',
                            '', {
                                timeOut: 3000,
                                progressBar: true
                            }
                        );

                    } else {
                        // just inform user
                        toastr.remove();
                        toastr.info(
                            '<?php echo langHdl('done'); ?>',
                            '', {
                                timeOut: 2000,
                                progressBar: true
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
                size: "39",
                lowercase: "true",
                numerals: "true",
                capitalize: "true",
                symbols: "false",
                secure: "false"
            },
            function(data) {
                data = prepareExchangedData(data, "decode", "<?php echo $_SESSION['key']; ?>");

                if (data.key !== "") {
                    newApiKey = data.key;

                    // Save key in session and database
                    var data = "{\"field\":\"user_api_key\" ,\"new_value\":\"" + newApiKey + "\" ,\"user_id\":\"<?php echo $_SESSION['user_id']; ?>\"}";

                    $.post(
                        "sources/main.queries.php", {
                            type: "update_user_field",
                            data: prepareExchangedData(data, "encode", "<?php echo $_SESSION['key']; ?>"),
                            key: "<?php echo $_SESSION['key']; ?>"
                        },
                        function(data) {
                            $("#" + target).text(newApiKey);
                            if (silent === false) {
                                $('#profile-tabs a[href="#tab_information"]').tab('show');
                                toastr.remove();
                                toastr.info(
                                    '<?php echo langHdl('done'); ?>',
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
        "defaultText": "<?php echo langHdl('index_pw_level_txt'); ?>",
        "ratings": [{
                "minScore": 0,
                "className": "meterFail",
                "text": "<?php echo langHdl('complex_level0'); ?>"
            },
            {
                "minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo langHdl('complex_level1'); ?>"
            },
            {
                "minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo langHdl('complex_level2'); ?>"
            },
            {
                "minScore": 60,
                "className": "meterGood",
                "text": "<?php echo langHdl('complex_level3'); ?>"
            },
            {
                "minScore": 70,
                "className": "meterGood",
                "text": "<?php echo langHdl('complex_level4'); ?>"
            },
            {
                "minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo langHdl('complex_level5'); ?>"
            },
            {
                "minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo langHdl('complex_level6'); ?>"
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
                '<?php echo langHdl('index_pw_error_identical'); ?>',
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
            'new_pw': $('#profile-password').val(),
            'complexity': $('#profile-password-complex').val(),
            "change_request": 'user_decides_to_change_password',
            "user_id": store.get('teampassUser').user_id,
        };

        //Send query
        $.post(
            "sources/main.queries.php", {
                type: "change_pw",
                data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key: "<?php echo $_SESSION['key']; ?>"
            },
            function(data) {
                data = prepareExchangedData(data, 'decode', '<?php echo $_SESSION['key']; ?>');
                console.log(data);

                if (data.error === true) {
                    $('#profile-password').focus();
                    toastr.remove();
                    toastr.warning(
                        '<?php echo langHdl('your_attention_is_required'); ?>',
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
                        '<?php echo langHdl('done'); ?>',
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
        "defaultText": "<?php echo langHdl('index_pw_level_txt'); ?>",
        "ratings": [{
                "minScore": 0,
                "className": "meterFail",
                "text": "<?php echo langHdl('complex_level0'); ?>"
            },
            {
                "minScore": 25,
                "className": "meterWarn",
                "text": "<?php echo langHdl('complex_level1'); ?>"
            },
            {
                "minScore": 50,
                "className": "meterWarn",
                "text": "<?php echo langHdl('complex_level2'); ?>"
            },
            {
                "minScore": 60,
                "className": "meterGood",
                "text": "<?php echo langHdl('complex_level3'); ?>"
            },
            {
                "minScore": 70,
                "className": "meterGood",
                "text": "<?php echo langHdl('complex_level4'); ?>"
            },
            {
                "minScore": 80,
                "className": "meterExcel",
                "text": "<?php echo langHdl('complex_level5'); ?>"
            },
            {
                "minScore": 90,
                "className": "meterExcel",
                "text": "<?php echo langHdl('complex_level6'); ?>"
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
</script>
