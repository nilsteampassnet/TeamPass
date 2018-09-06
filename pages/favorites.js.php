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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}
?>


<script type='text/javascript'>
// Open Item
$('.fav-open').click(function() {
    if ($(this).data('item-id') !== '') {
        document.location.href='index.php?page=items&group=' + $(this).data('tree-id') + '&id=' + $(this).data('item-id');
    }
});

// Trash Item
$('.fav-trash').click(function() {
    var item = $(this);
    if (item.data('item-id') !== '') {
        alertify
            .confirm(
                '<?php echo langHdl('item_menu_del_from_fav'); ?>',
                '<?php echo langHdl('confirm_del_from_fav'); ?>',
                function() {
                    // Launch ajax query                    
                    $.post(
                        "sources/favourites.queries.php",
                        {
                           type : "del_fav",
                           id   : item.data('item-id')
                        },
                        function(data) {
                            item.closest('tr').remove();

                            // Manage case where no more favorites exist
                            if ($('tr').length === 1) {
                                $('#no-favorite').removeClass('hidden');
                                $('#favorites').addClass('hidden');
                            }
                            alertify.success('<?php echo langHdl('success'); ?>', 1)
                        }
                    );
                }
                ,
                function(){
                    alertify.error('<?php echo langHdl('cancel'); ?>', 1)
                }
            );
    }
});
</script>