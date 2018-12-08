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
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
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
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], '2fa', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}
?>


<script type='text/javascript'>
//<![CDATA[

var requestRunning = false;

$(document).on('click', '.button-action', function() {
    var askedAction = $(this).data('action'),
        option = {};

    if (askedAction === 'config-file') {
        action = 'admin_action_rebuild_config_file';
    } else if (askedAction === 'personal-folder') {
        action = 'admin_action_check_pf';
    } else if (askedAction === 'remove-orphans') {
        action = 'admin_action_db_clean_items';
    } else if (askedAction === 'optimize-db') {
        action = 'admin_action_db_optimize';
    } else if (askedAction === 'purge-files') {
        action = 'admin_action_purge_old_files';
    } else if (askedAction === 'reload-cache') {
        action = 'admin_action_reload_cache_table';
    } else if (askedAction === 'change-sk') {
        action = 'admin_action_change_sk';
    } else if (askedAction === 'file-encryption') {
        action = 'admin_action_change_file_encryption';
    }


    if (requestRunning === true) {
        return false;
    }
    requestRunning = true;

    // Show cog
    alertify
        .message('<i class="fas fa-cog fa-spin fa-2x"></i>', 0)
        .dismissOthers();

    
    // Store in DB   
    $.post(
        "sources/admin.queries.php",
        {
            type    : action,
            option  : prepareExchangedData(JSON.stringify(option), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            // Handle server answer
            try {
                data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");
            }
            catch (e) {
                // error
                showAlertify(
                    '<?php echo langHdl('server_answer_error').'<br />'.langHdl('server_returned_data').':<br />'; ?>' + data.error,
                    0,
                    'top-right',
                    'error'
                );
                return false;
            }
            console.log(data)
            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fa fa-warning fa-lg mr-2"></i>' + data.message,
                        5
                    )
                    .dismissOthers();
            } else {
                showAlertify(
                    '<?php echo langHdl('done'); ?>',
                    2,
                    'top-bottom',
                    'success'
                );
                
                // Show time
                $('#' + askedAction + "-result").html(
                    data.message
                );
            }
            requestRunning = false;
        }
    );
})
    
//]]>
</script>