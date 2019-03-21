<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @author    Nils Laumaillé <nils@teamapss.net>
 * @copyright 2009-2019 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      https://www.teampass.net
 */
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

// Is maintenance on-going?
if (isset($SETTINGS['maintenance_mode']) === true
    && $SETTINGS['maintenance_mode'] === '1'
    && ($session_user_admin === null
    || $session_user_admin === '1')
) {
    ?>
<script type="text/javascript">
    showAlertify(
        '<?php echo langHdl('index_maintenance_mode_admin'); ?>',
        0,
        'top-right'
    )
</script>
    <?php
}
?>

<script type="text/javascript">
var userScrollPosition = 0;

// On page load
$(function() {
    // Init
    // Countdown
    countdown();
console.log(" LOGINs : " + ($('#user-login-attempts').length));
    // If login attempts experimented
    if ($('#user-login-attempts').length > 0) {
        alertify.confirm(
            '<?php echo langHdl('caution'); ?>',
            '<i class="fas fa-warning mr-3"></i><?php echo langHdl('login_attempts_identified_since_last_connection'); ?>',
            function() {
                document.location.href="index.php?page=profile&tab=timeline";
            },
            function() {
                // Nothing
            }
        )
        .set(
            'labels',
            {
                ok: '<?php echo langhdl('see_detail'); ?>',
                cancel: '<?php echo langhdl('cancel'); ?>'
            }
        );
    }

    // Show tooltips
    $('.infotip').tooltip();

    // Load user profiles
    $('.user-panel').click(function () {
        document.location.href="index.php?page=profile";
    });

    // Sidebar redirection
    $('.nav-link').click(function () {
        if ($(this).data('name') !== undefined) {
            NProgress.start();
            document.location.href="index.php?page=" + $(this).data('name');
        }
    });

    // User menu action
    $('.user-menu').click(function () {
        if ($(this).data('name') !== undefined) {
            if ($(this).data('name') === 'set_psk') {
                showPersonalSKDialog();
            } else if ($(this).data('name') === 'increase_session') {
                showExtendSession();
            } else if ($(this).data('name') === 'profile') {
                NProgress.start();
                document.location.href="index.php?page=profile";
            } else if ($(this).data('name') === 'logout') {
                alertify.confirm(
                    '<?php echo TP_TOOL_NAME; ?>',
                    '<?php echo langHdl('logout_confirm'); ?>',
                    function(){
                        alertify.success('<?php echo langHdl('ok'); ?>');
                        window.location.href = "./includes/core/logout.php?user_id=" + <?php echo $_SESSION['user_id']; ?>
                    },
                    function(){
                        alertify.error('<?php echo langHdl('cancel'); ?>');
                    }
                );
            }
        }
    });

    $('.close-element').click(function() {
        $(this).closest('.card').addClass('hidden');

        $('.content-header, .content').removeClass('hidden');
    });

    /**
     * When clicking save Personal saltkey
     */
    $('#button_save_user_psk').click(function() {
        alertify.message('<span class="fa fa-cog fa-spin fa-2x"></span>', 0);

        // Prepare data
        var data = {
            "psk" : sanitizeString($("#user_personal_saltkey").val()),
            "complexity" : $("#psk_strength_value").val()
        };
        
        //
        $.post(
            "sources/main.queries.php",
            {
                type    : "store_personal_saltkey",
                data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data, '<?php echo $_SESSION['key']; ?>');

                // Is there an error?
                if (data.error === true) {
                    alertify.dismissAll();
                    alertify
                        .alert(
                            '<?php echo langHdl('warning'); ?>',
                            data.message
                        );
                } else {
                    store.update(
                        'teampassUser',
                        function (teampassUser) {
                            teampassUser.pskDefinedInDatabase = 1;
                        }
                    )

                    store.update(
                        'teampassUser',
                        function (teampassUser) {
                            teampassUser.pskSetForSession = data.encrypted_psk;
                        }
                    )
                    
                    alertify
                        .success('<?php echo langHdl('alert_page_will_reload'); ?>', 1)
                        .dismissOthers();
                    location.reload();
                }
            }
        );
    });

    // For Personal Saltkey
    $("#user_personal_saltkey").simplePassMeter({
            "requirements": {},
            "container": "#psk_strength",
            "defaultText" : "<?php echo langHdl('index_pw_level_txt'); ?>",
            "ratings": [
                {"minScore": 0,
                    "className": "meterFail",
                    "text": "<?php echo langHdl('complex_level0'); ?>"
                },
                {"minScore": 25,
                    "className": "meterWarn",
                    "text": "<?php echo langHdl('complex_level1'); ?>"
                },
                {"minScore": 50,
                    "className": "meterWarn",
                    "text": "<?php echo langHdl('complex_level2'); ?>"
                },
                {"minScore": 60,
                    "className": "meterGood",
                    "text": "<?php echo langHdl('complex_level3'); ?>"
                },
                {"minScore": 70,
                    "className": "meterGood",
                    "text": "<?php echo langHdl('complex_level4'); ?>"
                },
                {"minScore": 80,
                    "className": "meterExcel",
                    "text": "<?php echo langHdl('complex_level5'); ?>"
                },
                {"minScore": 90,
                    "className": "meterExcel",
                    "text": "<?php echo langHdl('complex_level6'); ?>"
                }
            ]
        });
        $("#user_personal_saltkey").bind({
            "score.simplePassMeter" : function(jQEvent, score) {
                $("#psk_strength_value").val(score);
            }
        }).change({
            "score.simplePassMeter" : function(jQEvent, score) {
                $("#psk_strength_value").val(score);
            }
        });

    // Start real time
    // get list of last items
    if (store.get('teampassUser') !== undefined && store.get('teampassUser').user_id !== '') {
        $.when(
            // Load teampass settings
            loadSettings()
        ).then(function() {
            refreshListLastSeenItems();
        });
    }
    //-- end

    // Hide sidebar footer icons when reducing sidebar
    $('a[data-widget="pushmenu"]').click(function(event) {
        if ($('#sidebar-footer').hasClass('hidden') === true) {
            setTimeout(function() {$('#sidebar-footer').removeClass('hidden');}, 300);
        } else {
            $('#sidebar-footer').addClass('hidden');
        }
    });


    var clipboardCopy = new Clipboard(".clipboard-copy", {
        text: function(trigger) {
            var elementId = $(trigger).data('clipboard-text');
            return $('#' + elementId).val();
        }
    });

    clipboardCopy.on('success', function(e) {
        showAlertify(
            '<?php echo langHdl('copy_to_clipboard'); ?>',
            1,
            'top-right',
            'message'
        );
    });

    // Progress bar
    setTimeout(
        function() {
            NProgress.done();
            $(".fade").removeClass("out");
        },
        1000
    );
});


/**
 * MANAGE FORM FOR SHAREKEYS RE-ENCRYPTION
 */

// User hits the LAUNCH button for sharekeys re-encryption
$(document).on('click', '#button_do_sharekeys_reencryption', function() {
    // Start by changing the user password and send it by email
    alertify
        .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    $('#dialog-encryption-keys-progress').text('<?php echo langHdl('change_login_password'); ?>');

    // This sends a new password by email to user
    data = {
        'user_id' : $('#sharekeys_reencryption_target_user').val(),
        'special' : 'password_change_expected',
    }
    
    $.post(
        'sources/main.queries.php',
        {
            type    : 'initialize_user_password',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');
            console.log(data);
            console.log('new pwd: '+data.debug)

            if (data.error !== false) {
                // Show error
                alertify
                    .error('<i class="fa fa-ban mr-2"></i>' + data.message, 3)
                    .dismissOthers();
            } else {
                // Inform user
                userShareKeysReencryption($('#sharekeys_reencryption_target_user').val());
            }
        }
    );
});

// Manage close button for button_close_sharekeys_reencryption
$(document).on('click', '#button_close_sharekeys_reencryption', function() {
    // HIde
    $('.content-header, .content').removeClass('hidden');

    // SHow form
    $('#dialog-encryption-keys').addClass('hidden');

    // Clear field
    $('#sharekeys_reencryption_target_user').val('');
});

// --- END ---


function loadSettings()
{
    return $.post(
        "sources/main.queries.php",
        {
            type : "get_teampass_settings",
            key  : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            } catch (e) {
                // error
                alertify
                    .alert()
                    .setting({
                        'label' : '<?php echo langHdl('ok'); ?>',
                        'message' : '<i class="fa fa-info-circle text-error"></i>&nbsp;<?php echo langHdl('error'); ?>'
                    })
                    .show(); 
                return false;
            };
            
            // Store settings in localstorage
            store.update(
                'teampassSettings',
                {},
                function(teampassSettings) {
                    $.each(data, function(key, value) {
                        teampassSettings[key] = value;
                    });
                }
            );

            // Store some User info
            store.update(
                'teampassUser',
                {},
                function(teampassUser) {
                    teampassUser['user_admin'] = <?php echo isset($_SESSION['user_admin']) === true ? (int) $_SESSION['user_admin'] : 0; ?>;
                    teampassUser['user_id'] = <?php echo isset($_SESSION['user_id']) === true ? (int) $_SESSION['user_id'] : 0; ?>;
                    teampassUser['user_manager'] = <?php echo isset($_SESSION['user_manager']) === true ? (int) $_SESSION['user_manager'] : 0; ?>;
                    teampassUser['user_can_manage_all_users'] = <?php echo isset($_SESSION['user_can_manage_all_users']) === true ? (int) $_SESSION['user_can_manage_all_users'] : 0; ?>;
                    teampassUser['user_read_only'] = <?php echo isset($_SESSION['user_admin']) === true ? (int) $_SESSION['user_read_only'] : 1; ?>;
                    teampassUser['key'] = '<?php echo isset($_SESSION['key']) === true ? $_SESSION['key'] : 0; ?>';
                    teampassUser['login'] = "<?php echo isset($_SESSION['login']) === true ? $_SESSION['login'] : 0; ?>";
                    teampassUser['lastname'] = "<?php echo isset($_SESSION['lastname']) === true ? $_SESSION['lastname'] : 0; ?>";
                    teampassUser['name'] = "<?php echo isset($_SESSION['name']) === true ? $_SESSION['name'] : 0; ?>";
                    teampassUser['pskDefinedInDatabase'] = <?php echo isset($_SESSION['user_settings']['encrypted_psk']) === true ? 1 : 0; ?>;
                }
            );
        }
    );
}

/**
 * Undocumented function
 *
 * @return void
 */
function showExtendSession()
{
    alertify.prompt(
        '<?php echo langHdl('index_add_one_hour'); ?>',
        '<?php echo langHdl('index_session_duration').' ('.langHdl('minutes').')'; ?>',
        '<?php echo isset($_SESSION['user_settings']['session_duration']) === true ? (int) $_SESSION['user_settings']['session_duration'] / 60 : 60; ?>',
        function(evt, value) {
            IncreaseSessionTime('<?php echo langHdl('success'); ?>', value);
            alertify.message('<span class="fa fa-cog fa-spin fa-2x"></span>', 0);
        },
        function() {
            alertify.error('<?php echo langHdl('cancel'); ?>');
        }
    );
}

/**
 * Undocumented function
 *
 * @return void
 */
function showPersonalSKDialog()
{
    $('#dialog-request-psk').removeClass('hidden');

    // Hide other
    $('.content-header, .content').addClass('hidden');

    $('#user_personal_saltkey').focus();

    alertify.dismissAll();
}

/**
 * Loads the last seen items
 *
 * @return void
 */
function refreshListLastSeenItems()
{
    $.post(
        "sources/main.queries.php",
        {
            type : 'refresh_list_items_seen',
            key  : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            data = $.parseJSON(data);
            //check if format error
            if (data.error === '') {
                if (data.html_json === null) {
                    $('#index-last-pwds').html('<li><?php echo langHdl('none'); ?></li>');
                } else {
                    // Prepare HTML
                    var html_list = '';
                    $.each(data.html_json, function(i, value) {
                        html_list += '<li onclick="showItemCard($(this).closest(\'li\'))" class="pointer" data-item-edition="0" data-item-id="'+value.id+'" data-item-sk="'+value.perso+'" data-item-expired="0" data-item-restricted="'+value.restricted+'" data-item-display="1" data-item-open-edit="0" data-item-reload="0" data-item-tree-id="'+value.tree_id+'" data-is-search-result="0">' +
                        '<i class="fa fa-caret-right mr-2"></i>'+value.label+'</li>';
                    });
                    $('#index-last-pwds').html(html_list);
                }

                // show notification
                if (data.existing_suggestions !== 0) {
                    blink('#menu_button_suggestion', -1, 500, 'ui-state-error');
                }
            } else {
                alertify.message('<span class="fa fa-ban"></span>' + data.error, 0);
            }
        }
    );
}

/**
 * Show an item
 *
 * @return void
 */
function showItemCard(itemDefinition)
{
    // Show cog
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    if (window.location.href.indexOf('page=items') === -1) {
        location.replace('<?php echo $SETTINGS['cpassman_url']; ?>/index.php?page=items&group='+itemDefinition.data().itemTreeId+'&id='+itemDefinition.data().itemId);
    } else {
        $('#items_list').html('<ul class="liste_items" id="full_items_list"></ul>');
        Details(itemDefinition, 'show');
        if (itemDefinition.data().itemTreeId !== $('#open_folder').val()) {
            ListerItems(itemDefinition.data().itemTreeId, '', 0);
        }

        // Hide sidebar-mini
        $('body')
            .removeClass('control-sidebar-slide-open');
    }
}

/**
 * Open defect report page
 *
 * @return void
 */
function generateBugReport()
{
    $('#dialog-bug-report-text').html('');
    $('#dialog-bug-report').removeClass('hidden');

    var data = {
        'browser_name': platform.name,
        'browser_version': platform.version,
        'os': platform.os.family,
        'os_archi': platform.os.architecture,
    }

    $.post(
        "sources/main.queries.php",
        {
            type : 'generate_bug_report',
            data : JSON.stringify(data),
            key  : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            data = prepareExchangedData(data , 'decode', '<?php echo $_SESSION['key']; ?>');

            // Show data
            $('#dialog-bug-report-text').html(data.html);

            // Open Github
            $('#dialog-bug-report-github-button').click(function() {
                window.open('https://github.com/nilsteampassnet/TeamPass/issues/new','_blank');
                return false;
            });
        }
    );
}



function userShareKeysReencryption(userId = null)
{
    console.log('USER SHAREKEYS RE-ENCRYPTION START');

    $("#dialog-encryption-keys-progress").text("<?php echo langHdl('clearing_old_sharekeys'); ?>");

    alertify
        .message('<span class="fa fa-cog fa-spin fa-2x"></span>', 0)
        .dismissOthers();

    $.post(
        "sources/main.queries.php",
        {
            type   : "user_sharekeys_reencryption_start",
            userId : userId,
            key    : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            console.log(data)
            if (data.error === true) {
                // error
                alertify
                    .error('<i class="fa fa-ban mr-2"></i>' + data.message, 0)
                    .dismissOthers();
                return false;
            } else {
                // Start looping on all steps of re-encryption
                userShareKeysReencryptionNext(data.userId, data.step, data.start);
            }
        }
    );
}

function userShareKeysReencryptionNext(userId, step, start)
{
    var stepText = '';
    
    // Prepare progress string
    if (step === 'step1') {
        stepText = '<?php echo langHdl('items'); ?>';
    } else if (step === 'step2') {
        stepText = '<?php echo langHdl('logs'); ?>';
    } else if (step === 'step3') {
        stepText = '<?php echo langHdl('suggestions'); ?>';
    } else if (step === 'step4') {
        stepText = '<?php echo langHdl('fields'); ?>';
    } else if (step === 'step5') {
        stepText = '<?php echo langHdl('files'); ?>';
    }

    if (step !== 'finished') {
        // Inform user
        $("#dialog-encryption-keys-progress").text('<?php echo langHdl('encryption_keys'); ?> - ' + stepText);

        // Do query
        $.post(
            "sources/main.queries.php",
            {
                type   : "user_sharekeys_reencryption_next",
                'action' : step,
                'start' : start,
                'length' : 200,
                userId : userId,
                key    : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                console.log(data)
                if (data.error === true) {
                    // error
                    alertify
                    .error('<i class="fa fa-ban mr-2"></i>' + data.message, 0)
                    .dismissOthers();
                    return false;
                } else {
                    // Start looping on all steps of re-encryption
                    userShareKeysReencryptionNext(data.userId, data.step, data.start);
                }
            }
        );
    } else {
        // Finished
        $("#dialog-encryption-keys-progress").text('<?php echo langHdl('done'); ?>');

        alertify
            .success('<?php echo langHdl('done'); ?>', 5)
            .dismissOthers();
    }
}

// This permits to manage the column width of tree/items
$(document).on('click', '.columns-position', function() {
    var colLeft = $('#folders-tree-card').find('.column-left'),
        colRight = $('#folders-tree-card').find('.column-right'),
        counterLeft = $(colLeft).attr("class").match(/col-md-[\w-]*\b/)[0].split('-')[2],
        counterRight = $(colRight).attr("class").match(/col-md-[\w-]*\b/)[0].split('-')[2];

    // Toogle class
    if ($('#folders-tree-card').hasClass('hidden') === false) {
        if ($(this).hasClass('tree-decrease') === true && counterRight < 9) {
            $(colLeft).toggleClass('col-md-'+counterLeft + ' col-md-'+(parseInt(counterLeft) - 1));
            $(colRight).toggleClass('col-md-'+counterRight + ' col-md-'+(parseInt(counterRight) + 1));
        } else if ($(this).hasClass('tree-increase') === true && counterLeft < 9) {
            $(colLeft).toggleClass('col-md-'+counterLeft + ' col-md-'+(parseInt(counterLeft) + 1));
            $(colRight).toggleClass('col-md-'+counterRight + ' col-md-'+(parseInt(counterRight) - 1));
        }
    }
})

</script>
