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
        '<?php echo langHdl('index_maintenance_mode_admin');?>',
        0,
        'top-right'
    )
</script>
    <?php
}
?>

<script type="text/javascript">
// On page load
$(function() {
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
                //div_set_personal_saltkey
            } else if ($(this).data('name') === 'increase_session') {
                //div_increase_session_time
            } else if ($(this).data('name') === 'my_profile') {
                //loadProfileDialog
            } else if ($(this).data('name') === 'deconnexion') {
                //div_set_personal_saltkey
            }
            NProgress.start();
            document.location.href="index.php?page=" + $(this).data('name');
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
        '<?php echo langHdl('index_add_one_hour');?>',
        '<?php echo langHdl('index_session_duration').' ('.langHdl('minutes').')';?>',
        '<?php echo isset($_SESSION['user_settings']['session_duration']) === true ? (int) $_SESSION['user_settings']['session_duration'] / 60 : 60;?>',
        function(evt, value) {
            IncreaseSessionTime('<?php echo langHdl('success');?>', value);
            alertify.message('<span class="fa fa-cog fa-spin fa-2x"></span>', 0);
        },
        function() {
            alertify.error('<?php echo langHdl('cancel');?>');
        }
    );
}
</script>
