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
 * @copyright 2009-2019 Nils Laumaillé
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

$(document).on('change', '#ldap_type', function() {
    $(".tr-ldap").addClass('hidden');
    $(".tr-" + $(this).val()).removeClass('hidden');
});

$(function() {
    // Load list of groups
    $("#ldap_new_user_is_administrated_by, #ldap_new_user_role").empty();
    $.post(
        "sources/admin.queries.php",
        {
            type    : "get_list_of_roles",
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            data = prepareExchangedData(data , "decode", "<?php echo $_SESSION['key']; ?>");

            var html_admin_by = '<option value="">-- <?php echo langHdl('select'); ?> --</option>',
                html_roles = '<option value="">-- <?php echo langHdl('select'); ?> --</option>',
                selected_admin_by = 0,
                selected_role = 0;

            for (var i=0; i<data.length; i++) {
                if (data[i].selected_administrated_by === 1) {
                    selected_admin_by = data[i].id;
                }
                if (data[i].selected_role === 1) {
                    selected_role = data[i].id;
                }
                html_admin_by += '<option value="'+data[i].id+'"><?php echo langHdl('managers_of').' '; ?>'+data[i].title+'</option>';
                html_roles += '<option value="'+data[i].id+'">'+data[i].title+'</option>';
            }
            $("#ldap_new_user_is_administrated_by").append(html_admin_by);
            $("#ldap_new_user_is_administrated_by").val(selected_admin_by).change();
            $("#ldap_new_user_role").append(html_roles);
            $("#ldap_new_user_role").val(selected_role).change();
        }
   );
});
    
//]]>
</script>