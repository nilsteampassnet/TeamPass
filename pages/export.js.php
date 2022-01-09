<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 *
 * @file      export.js.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

if (
    isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1
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
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'profile', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
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

    // Select2 with buttons selectall        
    $.fn.select2.amd.define('select2/selectAllAdapter', [
        'select2/utils',
        'select2/dropdown',
        'select2/dropdown/attachBody'
    ], function(Utils, Dropdown, AttachBody) {

        function SelectAll() {}
        SelectAll.prototype.render = function(decorated) {
            var self = this,
                $rendered = decorated.call(this),
                $selectAll = $(
                    '<button class="btn btn-xs btn-primary" type="button" style="margin-left:6px;"><i class="far fa-check-square mr-1"></i><?php echo langHdl('select_all'); ?></button>'
                ),
                $unselectAll = $(
                    '<button class="btn btn-xs btn-primary" type="button" style="margin-left:6px;"><i class="far fa-square mr-1"></i><?php echo langHdl('unselect_all'); ?></button>'
                ),
                $btnContainer = $('<div style="margin:3px 0px 3px 0px;">').append($selectAll).append($unselectAll);
            if (!this.$element.prop("multiple")) {
                // this isn't a multi-select -> don't add the buttons!
                return $rendered;
            }
            $rendered.find('.select2-dropdown').prepend($btnContainer);
            $selectAll.on('click', function(e) {
                var $results = $rendered.find('.select2-results__option[aria-selected=false]');
                $results.each(function() {
                    self.trigger('select', {
                        data: Utils.GetData(this, 'data')
                    });
                });
                self.trigger('close');
            });
            $unselectAll.on('click', function(e) {
                var $results = $rendered.find('.select2-results__option[aria-selected=true]');
                $results.each(function() {
                    self.trigger('unselect', {
                        data: Utils.GetData(this, 'data')
                    });
                });
                self.trigger('close');
            });
            return $rendered;
        };

        return Utils.Decorate(
            Utils.Decorate(
                Dropdown,
                AttachBody
            ),
            SelectAll
        );

    });

    $('.select2-all').select2({
        language: '<?php echo $_SESSION['user_language_code']; ?>',
        dropdownAdapter: $.fn.select2.amd.require('select2/selectAllAdapter')
    });

    // On type selection
    $('#export-format').on("change", function(e) {
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

    function exportItemsToFile() {
        toastr.remove();
        toastr.info('<?php echo langHdl('exporting_items'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

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

            toastr.remove();
            toastr.success(
                '<?php echo langHdl('done'); ?>',
                '', {
                    timeOut: 1000
                }
            );

            return;
        }

        // Get PDF encryption password and make sure it is set
        if (($('#export-password').val() == '') && ($('#export-format').val() === 'pdf')) {
            $('#export-progress').find('span')
                .html('<i class="fas fa-exclamation-triangle text-danger mr-2 fa-lg"></i><?php echo langHdl('pdf_password_warning'); ?>');


            toastr.remove();
            toastr.success(
                '<?php echo langHdl('done'); ?>',
                '', {
                    timeOut: 1000
                }
            );

            return;
        }

        $('#form-item-export-perform').attr('disabled', 'disabled');


        // Export to PDF
        if ($('#export-format').val() === 'pdf') {
            // Initialize
            $.post(
                "sources/export.queries.php", {
                    type: "initialize_export_table"
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
                "sources/export.queries.php", {
                    type: 'export_to_csv_format',
                    ids: (JSON.stringify(ids))
                },
                function(data) {
                    $("#export-progress")
                        .addClass('hidden')
                        .find('span')
                        .html('');


                    toastr.remove();
                    toastr.success(
                        '<?php echo langHdl('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    download(new Blob([atob(data[0].content)]), $('#export-filename').val(), "text/csv");
                },
                'json'
            );
        }


        $('#form-item-export-perform').removeAttr('disabled');
    }


    function pollExport(export_format, remainingIds, currentID, counterRemainingFolders, totalFolders) {
        var data = {
            id: currentID,
            ids: remainingIds
        };

        $("#export-progress")
            .find('span')
            .html('<?php echo langHdl('operation_progress'); ?> <b>' +
                Math.round((totalFolders - counterRemainingFolders) * 100 / totalFolders).toFixed() +
                '%</b> ... <i class="fas fa-spinner fa-pulse"></i>');

        $.post(
            'sources/export.queries.php', {
                type: export_format,
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $_SESSION['key']; ?>'),
                key: '<?php echo $_SESSION['key']; ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $_SESSION['key']; ?>');
                console.log(data);

                //check if format error
                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo langHdl('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
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
                            pdf_password: $("#export-password").val()
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

                        xhr.onload = function() {
                            if (this.status === 200) {
                                var blob = new Blob([xhr.response], {
                                        type: "application/pdf"
                                    }),
                                    objectUrl = URL.createObjectURL(blob),
                                    a = document.createElement("a");
                                a.href = objectUrl;
                                a.download = $('#export-filename').val() + '.pdf';
                                document.body.appendChild(a);
                                a.target = "_blank";

                                $("#export-progress")
                                    .addClass('hidden')
                                    .find('span')
                                    .html('');

                                toastr.remove();
                                toastr.success(
                                    '<?php echo langHdl('done'); ?>',
                                    '', {
                                        timeOut: 1000
                                    }
                                );

                                // Shown download dialog
                                a.click();
                            }
                        };
                        xhr.send(data);
                    }
                }
            }
        );
    };
</script>
