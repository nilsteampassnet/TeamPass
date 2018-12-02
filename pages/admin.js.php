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
?>


<script type="text/javascript">
var requestRunning = false;

/**
 * ADMIN
 */
// <- PREPARE TOGGLES
$('.toggle').toggles({
    drag: true,
    click: true,
    text: {
        on: '<?php echo langHdl('yes'); ?>',
        off: '<?php echo langHdl('no'); ?>'
    },
    on: true,
    animate: 250,
    easing: 'swing',
    width: 50,
    height: 20,
    type: 'compact'
});
$('.toggle').on('toggle', function(e, active) {
    if (active) {
        $("#"+e.target.id+"_input").val(1);
        if (e.target.id == "allow_print") {
            $("#roles_allowed_to_print_select").prop("disabled", false);
        }
        if (e.target.id == "anyone_can_modify") {
            $("#form-item-row-modify").removeClass('hidden');
        }
        if (e.target.id == "restricted_to") {
            $("#form-item-row-restricted").removeClass('hidden');
        }
        if (e.target.id == "ldap_mode") {
            $("#div_ldap_configuration").show();
        }
    } else {
        $("#"+e.target.id+"_input").val(0);
        if (e.target.id == "allow_print") {
            $("#roles_allowed_to_print_select").prop("disabled", true);
        }
        if (e.target.id == "anyone_can_modify") {
            $("#form-item-row-modify").addClass('hidden');
        }
        if (e.target.id == "restricted_to") {
            $("#form-item-row-restricted").addClass('hidden');
        }
        if (e.target.id == "ldap_mode") {
            $("#div_ldap_configuration").hide();
        }
    }

    // store in DB
    updateSetting(e.target.id, $("#"+e.target.id+"_input").val());
});
// .-> END. TOGGLES

// <- PREPARE SELECT2
$('.select2').select2({
    language: '<?php echo $_SESSION['user_language_code']; ?>'
});
if ($('#allow_print_input').val() === '0') {
    $("#roles_allowed_to_print_select").prop("disabled", true);
}

/**
 */
$(document).on('change', '.form-control-sm', function() {
    var field = $(this).attr('id'),
        value = $(this).val();

    console.log('> '+field+' -- '+value)
    if (field === '') return false;

    // prevent launch of similar query in case of doubleclick
    if (requestRunning === true) {
        return false;
    }
    requestRunning = true;
    
    // Store in DB   
    $.post(
        "sources/admin.queries.php",
        {
            type    : "save_option_change",
            data    : prepareExchangedData(
                JSON.stringify({"field":field, "value":value}),
                "encode",
                "<?php echo $_SESSION['key']; ?>"
            ),
            key     : "<?php echo $_SESSION['key']; ?>"
        },
        function(data) {
            // Force page reload in case of encryptClientServer
            if (field === 'encryptClientServer') {
                location.reload(true);
                return false;
            }
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
            if (data.error == "") {
                showAlertify(
                    '<?php echo langHdl('saved'); ?>',
                    2,
                    'top-bottom',
                    'success'
                );
            }
            requestRunning = false;
        }
    );
});



/**
* LDAP
*/
// On click on LDAP type
$('#ldap_type').change(function() {
    updateSetting($(this).attr("id"), $(this).val());
    $(".setting-ldap").addClass('hidden');
    $(".ldap-" + $(this).val()).removeClass('hidden');
    console.log(".ldap-" + $(this).val())
});
</script>
