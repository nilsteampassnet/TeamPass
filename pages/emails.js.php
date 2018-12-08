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

$(document).on('click', '.button', function() {
    var action = $(this).data('action');

    alertify
            .message('<i class="fa fa-cog fa-spin fa-2x"></i>', 0)
            .dismissOthers();

    if (action === 'send-test-email') {
        $.post(
            'sources/admin.queries.php',
            {
                type    : 'admin_email_test_configuration',
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    // ERROR
                    alertify
                        .error(
                            '<i class="fa fa-warning fa-lg mr-2"></i>' + data.message,
                            3
                        )
                        .dismissOthers();
                } else {
                    // Inform user
                    alertify
                        .success('<?php echo langHdl('done'); ?>', 1)
                        .dismissOthers();
                }
            }
        );
    } else if (action === 'send-waiting-emails') {
        $.post(
            'sources/admin.queries.php',
            {
                type    : 'admin_email_send_backlog',
                key     : '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

                if (data.error === true) {
                    // ERROR
                    alertify
                        .error(
                            '<i class="fa fa-warning fa-lg mr-2"></i>' + data.message,
                            3
                        )
                        .dismissOthers();
                } else {
                    // Inform user
                    alertify
                        .success('<?php echo langHdl('done'); ?>', 1)
                        .dismissOthers();
                }
            }
        );
    }
})


$(document).on('click', '#button-duo-save', function() {
    var data = "{\"akey\":\""+sanitizeString($("#duo_akey").val())+"\", \"ikey\":\""+sanitizeString($("#duo_ikey").val())+"\", \"skey\":\""+sanitizeString($("#duo_skey").val())+"\", \"host\":\""+sanitizeString($("#duo_host").val())+"\"}";
    
    // Prepare data
    var data = {
        'akey'  : $('#duo_akey').val(),
        'ikey'  : $('#duo_ikey').val(),
        'skey'  : $('#duo_skey').val(),
        'host'  : $('#duo_host').val(),
    }    
    console.log(data);

    // Launch action
    $.post(
        'sources/admin.queries.php',
        {
            type    : 'save_duo_in_sk_file',
            data    : prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $_SESSION['key']; ?>"),
            key     : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');

            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fa fa-warning fa-lg mr-2"></i>Message: ' + data.message,
                        0
                    )
                    .dismissOthers();
            } else {
                // Inform user
                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();
            }
        }
    );
});

    
//]]>
</script>