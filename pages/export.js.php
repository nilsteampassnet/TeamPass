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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}
?>


<script type='text/javascript'>

// Prepare list of folders
$('.select2').val('');
$.each(store.get('teampassApplication').foldersList, function(index, item) {
    $('#export-folders').append('<option value="' + item.id + '">' + item.title + '</option>');
});

// Prepare Select2 inputs
$('.select2').select2({
    language: '<?php echo $_SESSION['user_language_code']; ?>'
});

// On type selection
$('#export-format').on("change", function (e) {
    if ($(this).val() === 'pdf') {
        $('#pdf-password').removeClass('hidden');
        $('#export-password').val('');
    } else {
        $('#pdf-password').addClass('hidden');
        $('#export-password').val('');
    }
});

// Action
$(document).on('click', '#form-item-export-perform', function() {
    exportItemsToFile();
});

function exportItemsToFile()
{
    showAlertify(
        '<?php echo langHdl('exporting_items'); ?>...<i class="fas fa-cog fa-spin ml-2"></i>',
        0,
        'bottom-right',
        'message'
    );

    $('#export-progress')
        .removeClass('hidden')
        .find('span')
        .html('<?php echo langHdl('starting'); ?>');

    //Get list of selected folders
    var ids = [];
    $("#export-folders :selected").each(function(i, selected) {
        ids.push($(selected).val());
    });
    
    // No selection of folders done
    if (ids.length === 0) {
        $('#export-progress').find('span').html('<i class="fas fa-exclamation-triangle text-danger mr-2 fa-lg"></i><?php echo langHdl('error_no_selected_folder'); ?>');

        alertify
            .error('<?php echo langHdl('done'); ?>', 1)
            .dismissOthers();

        return;
    }

    // Get PDF encryption password and make sure it is set
    if (($('#export-password').val() == '') && ($('#export-format').val() === 'pdf')) {
        $('#export-progress').find('span')
            .html('<i class="fas fa-exclamation-triangle text-danger mr-2 fa-lg"></i><?php echo langHdl('pdf_password_warning'); ?>');

        alertify
            .error('<?php echo langHdl('done'); ?>', 1)
            .dismissOthers();

        return;
    }

    $('#form-item-export-perform').attr('disabled', 'disabled');


    // Export to PDF
    if ($('#export-format').val() === 'pdf') {
        // Initialize
        $.post(
            "sources/export.queries.php",
            {
                type : "initialize_export_table"
            },
            function() {
                // launch export by building content of export table
                var currentID = ids[0];
                ids.shift();
                var counterRemainingFolders = ids.length,
                    totalFolders = counterRemainingFolders + 1;
                pollExport('export_to_pdf_format', ids, currentID, counterRemainingFolders, totalFolders);
            }
        );
        // ---
        // ---
    } else if ($('#export-format').val() === 'csv') {
        // Export to CSV
        $.post(
            "sources/export.queries.php",
            {
                type : 'export_to_csv_format',
                ids  : (JSON.stringify(ids))
            },
            function(data) {
                $("#export-progress")
                    .addClass('hidden')
                    .find('span')
                    .html('');

                alertify
                    .success('<?php echo langHdl('done'); ?>', 1)
                    .dismissOthers();

                download(new Blob([atob(data[0].content)]), $('#export-filename').val(), "text/csv");
            },
            'json'
        );
    }

    
    $('#form-item-export-perform').removeAttr('disabled');
}


function pollExport(export_format, remainingIds, currentID, counterRemainingFolders, totalFolders)
{
    var data = {
        id  : currentID,
        ids : remainingIds
    };

    $("#export-progress")
        .find('span')
        .html('<?php echo langHdl('operation_progress'); ?> <b>' +
            Math.round((totalFolders-counterRemainingFolders)*100/totalFolders).toFixed() +
            '%</b> ... <i class="fas fa-spinner fa-pulse"></i>');
    
    $.post(
        'sources/export.queries.php',
        {
            type : export_format,
            data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
            key  : '<?php echo $_SESSION['key']; ?>'
        },
        function(data) {
            //decrypt data
            data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
            console.log(data);

            //check if format error
            if (data.error === true) {
                // ERROR
                alertify
                    .error(
                        '<i class="fas fa-warning fa-lg mr-2"></i>' + data.message,
                        3
                    )
                    .dismissOthers();
            } else {
                currentID = remainingIds[0];
                remainingIds.shift();
                counterRemainingFolders = remainingIds.length;
                
                if (currentID !== "" && currentID !== undefined) {
                    pollExport(export_format, remainingIds, currentID, counterRemainingFolders, totalFolders);
                } else {
                    $("#export-progress")
                        .find('span')
                        .html('</i>Preparing PDF file ... <i class="fas fa-cog fa-spin ml-2">');

                    // Prepare
                    var dataLocal = {
                        pdf_password : $("#export-password").val()
                    };

                    // Build XMLHttpRequest parameters
                    var data = new FormData();
                    data.append('type', 'finalize_export_pdf');
                    data.append('data', prepareExchangedData(JSON.stringify(dataLocal), 'encode', '<?php echo $_SESSION['key']; ?>'));
                    data.append('key', '<?php echo $_SESSION['key']; ?>');

                    // Build XMLHttpRequest
                    var xhr = new XMLHttpRequest();
                    xhr.open("POST", 'sources/export.queries.php', true);
                    xhr.responseType = "blob";

                    xhr.onload = function () {
                        if (this.status === 200) {
                            var blob = new Blob([xhr.response], {type: "application/pdf"}),
                                objectUrl = URL.createObjectURL(blob),
                                a = document.createElement("a");
                            a.href = objectUrl;
                            a.download = $('#export-filename').val()+ '.pdf';
                            document.body.appendChild(a);
                            a.target = "_blank";

                            $("#export-progress")
                                .addClass('hidden')
                                .find('span')
                                .html('');

                            alertify
                                .success('<?php echo langHdl('done'); ?>', 1)
                                .dismissOthers();

                            // Shown download dialog
                            a.click();
                        }
                    };
                    xhr.send(data);

                    /*
                    //Send query
                    var xhr = new XMLHttpRequest();
                    $.ajax({
                        cache: false,
                        type: 'POST',
                        url: 'sources/export.queries.php',
                        contentType: false,
                        processData: false,
                        data: {
                            type : "finalize_export_pdf",
                            //data :  prepareExchangedData(JSON.stringify(dataLocal), 'encode', '<?php echo $_SESSION['key']; ?>'),
                            pdf_password : $("#export-password").val(),
                            key  : '<?php echo $_SESSION['key']; ?>'
                        },
                        xhrFields: {
                            responseType: 'blob' 
                        }
                    }).done(function(response){
                        var filename = "";                   
                        
                        var linkelem = document.createElement('a');
                        try {
                            var blob = new Blob([response], { type: 'application/pdf' });                        

                            if (typeof window.navigator.msSaveBlob !== 'undefined') {
                                //   IE workaround for "HTML7007: One or more blob URLs were revoked by closing the blob for which they were created. These URLs will no longer resolve as the data backing the URL has been freed."
                                window.navigator.msSaveBlob(blob, filename);
                            } else {
                                var URL = window.URL || window.webkitURL;
                                var downloadUrl = URL.createObjectURL(blob);

                                if (filename) { 
                                    // use HTML5 a[download] attribute to specify filename
                                    var a = document.createElement("a");

                                    // safari doesn't support this yet
                                    if (typeof a.download === 'undefined') {
                                        window.location = downloadUrl;
                                    } else {
                                        a.href = downloadUrl;
                                        a.download = filename;
                                        document.body.appendChild(a);
                                        a.target = "_blank";
                                        a.click();
                                    }
                                } else {
                                    //window.location = downloadUrl;
                                }
                            }   
                            $("#export-progress")
                                .addClass('hidden')
                                .find('span')
                                .html('');

                            alertify
                                .success('<?php echo langHdl('done'); ?>', 1)
                                .dismissOthers();

                        } catch (ex) {
                            console.log(ex);
                        } 
                    });
*/
/*
                    $.post(
                        "sources/export.queries.php",
                        {
                            type : "finalize_export_pdf",
                            data :  prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                            key  : '<?php echo $_SESSION['key']; ?>'
                        },
                        function(data) {
                            //decrypt data
                            //data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                            console.log(data);

                            //check if format error
                            if (data.error === true) {
                                // ERROR
                                alertify
                                    .error(
                                        '<i class="fas fa-warning fa-lg mr-2"></i>' + data.message,
                                        3
                                    )
                                    .dismissOthers();
                            } else {
                                $("#export-progress")
                                    .addClass('hidden')
                                    .find('span')
                                    .html('');

                                alertify
                                    .success('<?php echo langHdl('done'); ?>', 1)
                                    .dismissOthers();

                                // Display file
                                //download(new Blob([(data)]), 'export_pdf_' + Date.now() + '.pdf', "application/pdf");

                                console.log(data.size);
                                var link=document.createElement('a');
                                link.href=window.URL.createObjectURL(data);
                                link.download="Dossier_" + new Date() + ".pdf";
                                link.click();
                            }
                        }
                    );*/
                }
            }
        }
    );
};

</script>