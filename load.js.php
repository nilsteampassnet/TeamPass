<?php
/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 *
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 *
 * @version   GIT: <git_id>
 *
 * @see      http://www.teampass.net
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
                        window.location.href = "logout.php?user_id=" + <?php echo $_SESSION['user_id']; ?>
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
    });

    /**
     * When clicking save Personal saltkey
     */
    $('#button_save_user_psk').click(function() {
        alertify.message('<span class="fa fa-cog fa-spin fa-2x"></span>', 0);

        // Prepare data
        var data = '{"psk":"'+sanitizeString($("#user_personal_saltkey").val())+'" , "score":"'+$("#psk_strength_value").val()+'"}';
        
        //
        $.post(
            "sources/main.queries.php",
            {
                type    : "store_personal_saltkey",
                data    : prepareExchangedData(data, "encode", "<?php echo $_SESSION['key']; ?>"),
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                // Is there an error?
                if (data[0].error !== "") {
                    var error = "";
                    if (data[0].error === "key_not_conform") {
                        error = '<?php echo langHdl('error_unknown'); ?>';
                    } else if (data[0].error === "security_level_not_reached") {
                        error = '<?php echo langHdl('error_security_level_not_reached'); ?>';
                    } else if (data[0].error === "psk_is_empty") {
                        error = '<?php echo langHdl('psk_required'); ?>';
                    } else if (data[0].error === "psk_not_correct") {
                        error = '<?php echo langHdl('bad_psk'); ?>';
                    }
                    // display error
                    alertify.alert().set({onshow:function(){ alertify.message('',0.1).dismissOthers()}});
                    alertify.alert(
                        '<?php echo langHdl('warning'); ?>',
                        error
                    );
                } else if (data[0].status === "security_level_not_reached_but_psk_correct") {
                    alertify.alert(
                        '<?php echo langHdl('warning'); ?>',
                        '<?php echo langHdl('error_security_level_not_reached'); ?>'
                    );
                } else {
                    alertify
                        .success('<?php echo langHdl('alert_page_will_reload'); ?>', 1)
                        .dismissOthers();
                    setTimeout(function(){$("#main_info_box").effect( "fade", "slow" );}, 1000);
                    location.reload();
                }
            },
            "json"
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
    if ($('#form_user_id').val() !== '') {
        setTimeout(
            function() {
                refreshListLastSeenItems();
            },
            500
        );
        
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
    setTimeout(function() { NProgress.done(); $(".fade").removeClass("out"); }, 1000);
});


/**
 * Undocumented function
 *
 * @return void
 */
function showExtendSession() {
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

    $('#user_personal_saltkey').focus();
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
            .removeClass('control-sidebar-slide-open')
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
</script>
