<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      export.js.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses();
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

if ($session->get('key') === null) {
    die('Hacking attempt...');
}

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
$checkUserAccess = new PerformChecks(
    dataSanitizer(
        [
            'type' => $request->request->get('type', '') !== '' ? htmlspecialchars($request->request->get('type')) : '',
        ],
        [
            'type' => 'trim|escape',
        ],
    ),
    [
        'user_id' => returnIfSet($session->get('user-id'), null),
        'user_key' => returnIfSet($session->get('key'), null),
    ]
);
// Handle the case
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('export') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    // Prepare list of folders
    $('.select2').val('');
    /*$.each(store.get('teampassApplication').foldersList, function(index, item) {
        $('#export-folders').append('<option value="' + item.id + '">' + item.title + '</option>');
    });*/
    $('#export-folders').append(store.get('teampassUser').folders);
    console.log(store.get('teampassUser'))

    // Prepare Select2 inputs
    $('.select2').select2({
        language: '<?php echo $session->get('user-language_code'); ?>'
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
                    '<button class="btn btn-xs btn-primary" type="button" style="margin-left:6px;"><i class="far fa-check-square mr-1"></i><?php echo $lang->get('select_all'); ?></button>'
                ),
                $unselectAll = $(
                    '<button class="btn btn-xs btn-primary" type="button" style="margin-left:6px;"><i class="far fa-square mr-1"></i><?php echo $lang->get('unselect_all'); ?></button>'
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
        language: '<?php echo $session->get('user-language_code'); ?>',
        dropdownAdapter: $.fn.select2.amd.require('select2/selectAllAdapter')
    })
    .on("change", function(e) {
        $('#download-export-file').attr('onclick', "").addClass('hidden');
    });

    // On type selection
    $('#export-format').on("change", function(e) {
        $('#download-export-file').attr('onclick', "").addClass('hidden');
        if ($(this).val() === 'pdf' || $(this).val() === 'html') {
            $('#pwd').removeClass('hidden');
            $('#export-password').val('');
        } else {
            $('#pwd').addClass('hidden');
            $('#export-password').val('');
        }
    });

    // Password strength
    var pwdOptions = {};
    pwdOptions = {
        common: {
            zxcvbn: true,
            debug: false,
            minChar: 4,
            onScore: function (options, word, totalScoreCalculated) {
                if (word.length === 20 && totalScoreCalculated < options.ui.scores[1]) {
                    // Score doesn't meet the score[1]. So we will return the min
                    // numbers of points to get that score instead.
                    return options.ui.score[1]
                }
                $("#export-password-complex").val(totalScoreCalculated);
                return totalScoreCalculated;
            },
        },
        rules: {},
        ui: {
            colorClasses: ["text-danger", "text-danger", "text-danger", "text-warning", "text-warning", "text-success"],
            showPopover: false,
            showStatus: true,
            showErrors: false,
            showVerdictsInsideProgressBar: true,
            container: "#pwd",
            viewports: {
                progress: "#export-password-strength",
                score: "#export-password-strength"
            },
            scores: [<?php echo TP_PW_STRENGTH_1;?>, <?php echo TP_PW_STRENGTH_2;?>, <?php echo TP_PW_STRENGTH_3;?>, <?php echo TP_PW_STRENGTH_4;?>, <?php echo TP_PW_STRENGTH_5;?>],
        },
        i18n : {
            t: function (key) {
                var phrases = {
                    weak: '<?php echo $lang->get('complex_level1'); ?>',
                    normal: '<?php echo $lang->get('complex_level2'); ?>',
                    medium: '<?php echo $lang->get('complex_level3'); ?>',
                    strong: '<?php echo $lang->get('complex_level4'); ?>',
                    veryStrong: '<?php echo $lang->get('complex_level5'); ?>'
                };
                var result = phrases[key];

                return result === key ? '' : result;
            }
        }
    };
    $('#export-password').pwstrength(pwdOptions);

    // Action
    $(document).on('click', '#form-item-export-perform', function() {
        $('#download-export-file').attr('onclick', "").addClass('hidden');
        exportItemsToFile();
    });

    function exportItemsToFile() {
        toastr.remove();
        toastr.info('<i class="fas fa-circle-notch fa-spin fa-2x mr-2"></i><?php echo $lang->get('exporting_items'); ?> ...');

        $('#export-progress')
            .removeClass('hidden')
            .find('span')
            .html('<?php echo $lang->get('starting'); ?>');

        //Get list of selected folders
        var ids = [];
        $("#export-folders :selected").each(function(i, selected) {
            ids.push($(selected).val());
        });
        
        // No selection of folders done
        if (ids.length === 0) {
            $('#export-progress').find('span').html('<i class="fas fa-exclamation-triangle text-danger mr-2 fa-lg"></i><?php echo $lang->get('error_no_selected_folder'); ?>');

            toastr.remove();
            toastr.success(
                '<?php echo $lang->get('done'); ?>',
                '', {
                    timeOut: 1000
                }
            );

            return;
        } else if (null === $('#export-format').val()) {
            $('#export-progress').find('span').html('<i class="fas fa-exclamation-triangle text-danger mr-2 fa-lg"></i><?php echo $lang->get('export_format_type'); ?>');

            toastr.remove();
            toastr.success(
                '<?php echo $lang->get('done'); ?>',
                '', {
                    timeOut: 1000
                }
            );

            return;
        }

        // Get PDF encryption password and make sure it is set
        if (($('#export-password').val() == '') && ($('#export-format').val() === 'pdf' || $('#export-format').val() === 'html')) {
            $('#export-progress').find('span')
                .html('<i class="fas fa-exclamation-triangle text-danger mr-2 fa-lg"></i><?php echo $lang->get('pdf_password_warning'); ?>');


            toastr.remove();
            toastr.success(
                '<?php echo $lang->get('done'); ?>',
                '', {
                    timeOut: 1000
                }
            );

            return;
        }

        $('#form-item-export-perform').attr('disabled', 'disabled');


        // Export to PDF
        if ($('#export-format').val() === 'pdf') {
            // launch export by building content of export table
            var currentID = ids[0];
            ids.shift();
            var counterRemainingFolders = ids.length,
                totalFolders = counterRemainingFolders + 1;
            pollExport('pdf', ids, currentID, counterRemainingFolders, totalFolders, CreateRandomString(20));
            // ---
            // ---
        } else if ($('#export-format').val() === 'html') {
            var currentID = ids[0],
                counterRemainingFolders = ids.length,
                totalFolders = counterRemainingFolders + 1;
            
            ids.shift();
            pollExport('html', ids, currentID, counterRemainingFolders, totalFolders, CreateRandomString(20));
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
                        '<?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );
                    
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                    
                    // download VSC file
                    download(new Blob([data.csv_content]), $('#export-filename').val() + ".csv", "text/csv");//decodeURI(data[0].content)
                }
            );
        }


        $('#form-item-export-perform').removeAttr('disabled');
    }


    function pollExport(export_format, remainingIds, currentID, counterRemainingFolders, totalFolders, export_tag) {
        var data = {
            id: currentID,
            ids: remainingIds,
            export_tag : export_tag,
        };
        //console.log(data);

        $("#export-progress")
            .find('span')
            .html(' <i class="fas fa-spinner fa-pulse mr-2"></i><?php echo $lang->get('operation_progress'); ?> <b>' +
                Math.round((totalFolders - counterRemainingFolders) * 100 / totalFolders).toFixed() +
                '%</b> ...');

        $.post(
            'sources/export.queries.php', {
                type: 'export_prepare_data',
                data: prepareExchangedData(JSON.stringify(data), 'encode', '<?php echo $session->get('key'); ?>'),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                //console.log(data);
                var exportTag = data.exportTag;

                //check if format error
                if (data.error === true) {
                    // ERROR
                    toastr.remove();
                    toastr.error(
                        data.message,
                        '<?php echo $lang->get('error'); ?>', {
                            timeOut: 5000,
                            progressBar: true
                        }
                    );
                } else {
                    currentID = remainingIds[0];
                    remainingIds.shift();
                    counterRemainingFolders = remainingIds.length;

                    if (currentID !== "" && currentID !== undefined) {
                        // loop on remaining folders
                        pollExport(export_format, remainingIds, currentID, counterRemainingFolders, totalFolders, export_tag);
                    } else {
                        $("#export-progress")
                            .find('span')
                            .html('<i class="fas fa-cog fa-spin mr-2"></i>Preparing file ...');

                        if (export_format === 'pdf') {
                            // Prepare
                            var dataLocal = {
                                pdf_password: $("#export-password").val(),
                                pdf_filename : $('#export-filename').val() + '.pdf',
                                export_tag: exportTag,
                            };

                            // Build XMLHttpRequest parameters
                            var data = new FormData();
                            data.append('type', 'finalize_export_pdf');
                            data.append('data', prepareExchangedData(JSON.stringify(dataLocal), 'encode', '<?php echo $session->get('key'); ?>'));
                            data.append('key', '<?php echo $session->get('key'); ?>');

                            // Build XMLHttpRequest
                            var xhr = new XMLHttpRequest();
                            xhr.open("POST", 'sources/export.queries.php', true);
                            xhr.responseType = "blob";

                            xhr.onload = function() {
                                if (this.status === 200) {
                                    var blob = new Blob(
                                        [xhr.response],
                                        {
                                            type: "application/pdf"
                                        }
                                    ),
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
                                        '<?php echo $lang->get('done'); ?>',
                                        '', {
                                            timeOut: 1000
                                        }
                                    );

                                    // clean Export table
                                    $.post(
                                        'sources/export.queries.php', {
                                            type: 'clean_export_table',
                                            data: prepareExchangedData(
                                                JSON.stringify({
                                                    export_tag: exportTag,
                                                }),
                                                'encode',
                                                '<?php echo $session->get('key'); ?>'
                                            ),
                                            key: '<?php echo $session->get('key'); ?>'
                                        }
                                    );

                                    // Shown download dialog
                                    a.click();
                                }
                            };
                            xhr.send(data);

                        } else if (export_format === 'html') {
                            // Prepare
                            var dataLocal = {
                                password: $("#export-password").val(),
                                filename : $('#export-filename').val() + '.html',
                                export_tag : exportTag,
                            };
                            
                            generateOfflineFile(dataLocal);
                        }
                    }
                }
            }
        );
    };


    //----- OFFLINE MODE -----
    /*
    * Export to Offline mode file - step 1
    */
    function generateOfflineFile(vars)
    {
        if (vars.password == "") {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('password_cannot_be_empty'); ?>',
                '', {
                    timeOut: 2000
                }
            );
            return;
        }

        if (parseInt($("#offline_pw_strength_value").val()) < parseInt($("#min_offline_pw_strength_value").val())) {
            toastr.remove();
            toastr.error(
                '<?php echo $lang->get('error_complex_not_enought'); ?>',
                '', {
                    timeOut: 2000
                }
            );
            return;
        }

        //Send query
        $.post(
            'sources/export.queries.php', {
                type: 'export_to_html_format',
                data: prepareExchangedData(
                    JSON.stringify(vars),
                    'encode',
                    '<?php echo $session->get('key'); ?>'
                ),
                key: '<?php echo $session->get('key'); ?>'
            },
            function(data) {
                //decrypt data
                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                //console.log(data);

                if (data.error === true) {
                    toastr.remove();
                    toastr.error(
                        data.detail,
                        data.message,
                        {
                            timeOut: 3000
                        }
                    );
                    return;
                }
                

                if (data.loop !== null && data.loop === true) {
                    exportHTMLLoop(
                        data.ids_list,
                        data.file_path,
                        data.ids_count,
                        vars.password,
                        data.file_link,
                        data.export_tag
                    );
                } else {
                    toastr.remove();
                    toastr.error(
                        '<?php echo $lang->get('error_unknown'); ?>',
                        '',
                        {
                            timeOut: 3000
                        }
                    );
                    return;
                }
            }
        );
    }

    /*
     * Loading Item details step 2
     */
    function exportHTMLLoop(idsList, file, number, password, file_link, export_tag)
    {
        var numberInLoop = 10;
        // prpare list of ids to treat during this run
        if (idsList != "") {
            idsArray = JSON.parse(idsList);
            idsToTreat = idsArray.slice(0, numberInLoop);
            idsArray = idsArray.slice(numberInLoop);
            cpt = parseInt(idsToTreat.length);

            $('#export-progress').find('span').html('<i class="fas fa-cog fa-spin mr-2"></i><?php echo $lang->get('please_wait'); ?> - ' + Math.round((parseInt(cpt)*100)/parseInt(number)) + "%");

            jqData = {
                idsList : idsToTreat,
                idsListRemaining : idsArray,
                filename : file,
                cpt : cpt,
                password : password,
                file_link : file_link,
                number : number,
                export_tag : export_tag,
            };
            $.post(
                "sources/export.queries.php",
                {
                    type    : "export_to_html_format_loop",
                        data: prepareExchangedData(
                        JSON.stringify(jqData),
                        'encode',
                        '<?php echo $session->get('key'); ?>'
                    ),
                    key: '<?php echo $session->get('key'); ?>'
                },
                function(data) {
                    //decrypt data
                    data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");
                    //console.log(data);

                    if (data.error === true) {
                        toastr.remove();
                        toastr.error(
                            data.detail,
                            data.message,
                            {
                                timeOut: 3000
                            }
                        );
                        return;
                    }

                    if (data.loop === true) {
                        // relaunch for next run
                        exportHTMLLoop (
                            data.ids_list,
                            file,
                            data.ids_count,
                            password,
                            file_link,
                            export_tag
                        );
                    } else {
                        // clean Export table
                        $.post(
                            'sources/export.queries.php', {
                                type: 'clean_export_table',
                                data: prepareExchangedData(
                                    JSON.stringify({
                                        export_tag: export_tag,
                                    }),
                                    'encode',
                                    '<?php echo $session->get('key'); ?>'
                                ),
                                key: '<?php echo $session->get('key'); ?>'
                            }
                        );

                        // end - do file finalization
                        $.post(
                            'sources/export.queries.php', {
                                type: 'export_to_html_format_finalize',
                                data: prepareExchangedData(
                                    JSON.stringify({
                                        filename: file,
                                        file_link: file_link,
                                        password : password,
                                    }),
                                    'encode',
                                    '<?php echo $session->get('key'); ?>'
                                ),
                                key: '<?php echo $session->get('key'); ?>'
                            },
                            function(data) {
                                //decrypt data
                                data = prepareExchangedData(data, "decode", "<?php echo $session->get('key'); ?>");

                                $('#export-progress').find('span').html('');
                                $('#export-progress').addClass('hidden')
                                $('#download-export-file').attr('href', data.filelink).removeClass('hidden');

                                toastr.remove();
                                toastr.success(
                                    '<?php echo $lang->get('done'); ?>',
                                    '',
                                    {
                                        timeOut: 1000
                                    }
                                );
                            }
                        );
                    }
                }
            );
        }
    };
</script>
