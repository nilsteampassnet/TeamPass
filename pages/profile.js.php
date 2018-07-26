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

// AVATAR IMPORT
var uploader_photo = new plupload.Uploader({
    runtimes : 'gears,html5,flash,silverlight,browserplus',
    browse_button : 'profile-avatar-file',
    container : 'profile-avatar-file-container',
    max_file_size : '2mb',
    chunk_size : '1mb',
    unique_names : true,
    dragdrop : true,
    multiple_queues : false,
    multi_selection : false,
    max_file_count : 1,
    filters : [
        {title : 'PNG files', extensions : 'png'}
    ],
    resize : {
        width : '90',
        height : '90',
        quality : '90'
    },
    url : '<?php echo $SETTINGS['cpassman_url']; ?>/sources/upload/upload.files.php',
    flash_swf_url : '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.swf',
    silverlight_xap_url : '<?php echo $SETTINGS['cpassman_url']; ?>/includes/libraries/Plupload/Moxie.xap',
    init: {
        FilesAdded: function(up, files) {
            // generate and save token
            $.post(
                'sources/main.queries.php',
                {
                    type : 'save_token',
                    size : 25,
                    capital: true,
                    numeric: true,
                    ambiguous: true,
                    reason: 'avatar_profile_upload',
                    duration: 10
                },
                function(data) {
                    $('#profile-user-token').val(data[0].token);
                    up.start();
                },
                'json'
            );
        },
        BeforeUpload: function (up, file) {
            var tmp = Math.random().toString(36).substring(7);

            up.settings.multipart_params = {
                'PHPSESSID':'<?php echo $_SESSION['user_id']; ?>',
                'type_upload':'upload_profile_photo',
                'user_token': $('#profile-user-token').val()
            };
        }
    }
});

// Show runtime status
uploader_photo.bind('Init', function(up, params) {
    $('#profile-plupload-runtime')
        .html('<?php echo langHdl('runtime_upload'); ?> ' + params.runtime)
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

    // get response
uploader_photo.bind('FileUploaded', function(up, file, object) {
    // Decode returned data
    var myData = prepareExchangedData(object.response, 'decode', '<?php echo $_SESSION['key']; ?>');
console.log(myData);
    // update form
    $('#profile-user-avatar').attr('src', 'includes/avatars/' + myData.filename);
    $('#profile-avatar-file-list').html('').addClass('hidden');
});

uploader_photo.init();

$('#profile_photo').click(function() {
    $('#div_change_psk, #div_reset_psk, #div_change_password').hide();
    $('#dialog_user_profil').dialog('option', 'height', 450);
});


</script>