<?php
/**
 * Teampass - a collaborative passwords manager
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 * @package   Load.js
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2018 Nils Laumaillé
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * @version   GIT: <git_id>
 * @link      http://www.teampass.net
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

    // prevent usage of symbols in Personal saltkey
    $(".text_without_symbols").bind("keydown", function (event) {
        switch (event.keyCode) {
            case 8:  // Backspace
            case 9:  // Tab
            case 13: // Enter
            case 37: // Left
            case 38: // Up
            case 39: // Right
            case 40: // Down
            break;
            default:
            var regex = new RegExp("^[a-zA-Z0-9.,/#&$@()%*]+$");
            var key = event.key;
            if (regex.test(key) === false) {
                alertify
                    .error('<?php echo langHdl('character_not_allowed'); ?>', 3);
                event.preventDefault();
                return false;
            }
            if (key !== "Alt" && key !== "Control" && key !== "Shift") {
                alertify.set('notifier','position', 'top-right');
                alertify
                    .message(key, 2)
                    .dismissOthers();
            }
            break;
        }
    }).bind("paste",function(e) {
        alertify
            .error('<?php echo langHdl('error_not_allowed_to'); ?>', 3);
        e.preventDefault();
    });

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
                        window.location.href = "logout.php?user_id=" + <?php echo $_SESSION["user_id"]; ?>
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
                //decrypt data
                try {
                    data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
                } catch (e) {
                    // error
                    alertify
                        .error('<i class="fa fa-ban fa-lg mr-3"></i>An error appears. Answer from Server cannot be parsed!<br />Returned data:<br />' + data, 0)
                        .dismissOthers();
                    return false;
                }
                console.log(data)

                // Is there an error?
                if (data.error === true) {
                    alertify
                    .error('<i class="fa fa-ban fa-lg mr-3"></i>' + data.message, 0)
                    .dismissOthers();
                } else {
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
    $('#dialog_request_psk').removeClass('hidden');

    $('#user_personal_saltkey').focus();
}
</script>
