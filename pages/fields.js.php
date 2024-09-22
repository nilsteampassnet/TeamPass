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
 * @file      fields.js.php
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('fields') === false) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}
?>


<script type='text/javascript'>
    //<![CDATA[

    loadFieldsList();

    $('input[type="checkbox"].flat-red').iCheck({
        checkboxClass: 'icheckbox_flat-red',
    });
    $('input[type="checkbox"].flat-blue').iCheck({
        checkboxClass: 'icheckbox_flat-blue',
    });

    /**
     * NEW CATEGORY
     */
    $('#button-new-category').click(function() {
        // Hide table
        $('#table-fields').addClass('hidden');

        $('#form-category-folders, #form-category-list').val('').change();
        $('#form-category-label').val('');

        // Show form
        $('#form-category').removeClass('hidden');
    });



    /**
     * NEW FIELD
     */
    $('#button-new-field').click(function() {
        // Hide table
        $('#table-fields').addClass('hidden');

        // Clear form
        $('#form-field input[type="checkbox"]').iCheck('uncheck');
        $('#form-field-type, #form-field-roles').val('').change();
        $('#form-field-order, #form-field-category').html('');
        $('#form-field-label').val('');
        $('#form-field-regex').val('');

        // Show Category selection
        var categoriesOptions = [];
        categoriesOptions.push({
            id: '',
            text: '-- <?php echo $lang->get('select'); ?> --'
        });
        $('.category').each(function() {
            categoriesOptions.push({
                id: $(this).data('category'),
                text: $(this).find('td:eq(1)').text()
            });
        });
        $('#form-field-category').select2({
            tags: true,
            data: categoriesOptions
        });
        $('#form-field-category-div').removeClass('hidden');

        // Change button save attribute
        $('#button-save-field').data('edit', false);

        // Force empty position select
        $('#form-field-order').val('').change();

        // Show form
        $('#form-field').removeClass('hidden');
    });

    // Update list of Field options in case of Category seleciton
    $('#form-field-category').change(function() {
        var selectedCategory = $(this).val(),
            fields = [];

        // Add top
        fields.push({
            id: 'top',
            text: '<?php echo $lang->get('top'); ?>'
        });

        // CLear existing list
        $('#form-field-order').html('');


        // Build list of fields in this category
        $('.field[data-category]').each(function() {
            if (parseInt($(this).data('category')) === parseInt(selectedCategory)) {
                fields.push({
                    id: $(this).data('order'),
                    text: '<?php echo $lang->get('before') . ' '; ?>' + $(this).find('td:eq(1)').text()
                });
            }
        });
        fields.push({
            id: 'bottom',
            text: '<?php echo $lang->get('bottom'); ?>'
        });

        // Update select of Roles
        $('#form-field-order').select2({
            tags: true,
            data: fields
        });

        // Select order BOTTOM
        $('#form-field-order').val('bottom').change();
    });

    /*
    $(document).on('click', '.save', function() {
        var form = $(this).data('action');
    });
    */

    /**
     * CANCEL BUTTON
     */
    $(document).on('click', '.cancel', function() {
        $('#table-fields').removeClass('hidden');
        $('#form-' + $(this).data('action')).addClass('hidden');
    });


    $(document).on('click', '.save', function() {
        var button = $(this),
            action = button.data('action'),
            editionOngoing = button.data('edit');

        if (action === 'category') {
            // CATEGORY SAVE
            if ($('#form-category-label').val() !== '' &&
                $('#form-category-folders').val() !== '' &&
                $('#form-category-list').val() !== ''
            ) {
                // Prepare data
                var data = {
                        'label': DOMPurify.sanitize($('#form-category-label').val()),
                        'folders': $('#form-category-folders').val(),
                        'position': $('#form-category-list').val(),
                        'edit': $('#button-save-category').data('edit') === true ? true : false,
                        'categoryId': $('#form-category-label').data('id'),
                    },
                    actionToPerform = '';

                if (editionOngoing === true) {
                    actionToPerform = 'edit_category';
                } else {
                    actionToPerform = 'add_new_category';
                }

                // Launch action
                $.post(
                    'sources/fields.queries.php', {
                        type: actionToPerform,
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');

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
                            // Reload list
                            loadFieldsList()

                            // Inform user
                            toastr.remove();
                            toastr.success(
                                '<?php echo $lang->get('done'); ?>',
                                '', {
                                    timeOut: 1000
                                }
                            );

                            // Show
                            $('#table-fields').removeClass('hidden');
                            $('#form-category').addClass('hidden');
                        }

                        $('#button-save-category').data('edit', false);
                    }
                );
            } else {
                // ERROR
                toastr.remove();
                toastr.warning(
                    '<?php echo $lang->get('all_fields_are_required'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
            }
        } else if (action === 'field') {
            // FIELD SAVE
            if ($('#form-field-label').val() !== '' &&
                $('#form-field-type').val().length > 0 &&
                $('#form-field-roles').val().length > 0 &&
                $('#form-field-order').val() !== ''
            ) {
                // Prepare data
                var data = {
                        'label': $('#form-field-label').val(),
                        'regex': $('#form-field-regex').val(),
                        'type': $('#form-field-type').val(),
                        'roles': $('#form-field-roles').val(),
                        'mandatory': $('#form-field-mandatory').prop('checked') === true ? 1 : 0,
                        'masked': $('#form-field-masked').prop('checked') === true ? 1 : 0,
                        'encrypted': $('#form-field-encrypted').prop('checked') === true ? 1 : 0,
                        'order': $('#form-field-order').val(),
                        'fieldId': $('#form-field-label').data('id'),
                        'categoryId': $('#form-field-label').data('category'),
                    },
                    actionToPerform = '';

                if (editionOngoing === true) {
                    actionToPerform = 'edit_field';
                } else {
                    actionToPerform = 'add_new_field';

                    // Check if category is selected
                    if ($('#form-field-category').val() === '') {
                        toastr.remove();
                        toastr.warning(
                            '<?php echo $lang->get('all_fields_are_required'); ?>',
                            '', {
                                timeOut: 5000,
                                progressBar: true
                            }
                        );
                        return false;
                    } else {
                        data.categoryId = $('#form-field-category').val();
                    }
                }

                // Launch action
                $.post(
                    'sources/fields.queries.php', {
                        type: actionToPerform,
                        data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                        key: '<?php echo $session->get('key'); ?>'
                    },
                    function(data) {
                        //decrypt data
                        data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                        console.log(data);

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
                            // Reload list
                            loadFieldsList()

                            // Inform user
                            toastr.remove();
                            toastr.success(
                                '<?php echo $lang->get('done'); ?>',
                                '', {
                                    timeOut: 1000
                                }
                            );

                            // Show
                            $('#table-fields').removeClass('hidden');
                            $('#form-field').addClass('hidden');
                        }

                        $('#button-save-field').data('edit', false);
                    }
                );
            } else {
                // ERROR
                toastr.remove();
                toastr.warning(
                    '<?php echo $lang->get('all_fields_are_required'); ?>',
                    '', {
                        timeOut: 5000,
                        progressBar: true
                    }
                );
            }
        }
    });


    $(document).on('click', '.action-category', function() {
        var action = $(this).data('action'),
            row = $(this).closest('tr');

        if (action === 'edit') {
            var categoryId = row.data('category'),
                categoryOrder = row.data('order') - 1,
                categoryText = $(row).find('td:eq(1)').text(),
                foldersHtml = $(row).find('td:eq(2)').html(),
                categoryFolders = [];

            // This to manage Top and Bottom
            if (categoryOrder === 0) {
                categoryOrder = 'top';
            } else if ($('#form-category-list').children('option').length - 2 === categoryOrder) {
                categoryOrder = 'bottom';
            }

            // Get folder
            $(foldersHtml).find('span').each(function(i, element) {
                categoryFolders.push($(element).data('folder'));
            });

            // Prefill the form
            $('#form-category-label')
                .val(categoryText)
                .data('id', categoryId);
            $('#form-category-list').val(categoryOrder).change();
            $('#form-category-folders').val(categoryFolders).change();

            // Show
            $('#table-fields').addClass('hidden');
            $('#form-category').removeClass('hidden');

            // Set as edit
            $('#button-save-category').data('edit', true);

        } else if (action === 'delete') {
            var categoryId = row.data('category'),
                fieldId = row.data('field');

            $('.row-delete').remove();

            // Add new row
            $(row).after(
                '<tr class="table-danger row-delete" data-category="' + categoryId + '">' +
                '<td colspan="4">' +
                '<div class="alert alert-danger">' +
                '<p><i class="fas fa-warning mr-2"></i><?php echo $lang->get('caution_while_deleting_category'); ?></p>' +
                '<p><input type="checkbox" class="form-check-input form-control flat-red" id="delete-confirm">' +
                '<label class="form-check-label ml-3 pointer" for="delete-confirm"><?php echo $lang->get('please_confirm_by_clicking_checkbox'); ?></label></p>' +
                '</div>' +
                '<div>' +
                '<button type="button" class="btn btn-warning" id="button-delete" data-type="category"><?php echo $lang->get('submit'); ?></button>' +
                '<button type="button" class="btn btn-default float-right" id="button-cancel"><?php echo $lang->get('cancel'); ?></button>' +
                '</div></td></tr>'
            );

            // iCheck for checkbox and radio inputs
            $('.flat-red').iCheck({
                checkboxClass: 'icheckbox_flat-red'
            });
        }
    });

    /**
     * SUBMIT DELETE
     */
    $(document).on('click', '#button-delete', function() {
        // If confirmed
        if ($('#delete-confirm').prop("checked") === true) {
            // Show cog
            toastr.remove();
            toastr.info('<?php echo $lang->get('in_progress'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

            var row = $(this).closest('tr'),
                type = $(this).data('type'),
                idToRemove = '';

            // What to remove
            if (type === 'category') {
                idToRemove = row.data('category');
            } else {
                idToRemove = row.data('field');
            }

            // Prepare data
            var data = {
                'idToRemove': idToRemove,
                'action': type,
            };

            //send query
            $.post(
                "sources/fields.queries.php", {
                    type: "delete",
                    data: prepareExchangedData(JSON.stringify(data), "encode", "<?php echo $session->get('key'); ?>"),
                    key: "<?php echo $session->get('key'); ?>"
                },
                function(data) {
                    //decrypt data
                    data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                    console.log(data);

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
                        // Delete rows
                        if (type === 'category') {
                            $('.field, .table-primary').each(function(i, row) {
                                if ($(this).data('category') === idToRemove) {
                                    $(this).remove();
                                }
                            });
                        } else {
                            $('.field').each(function(i, row) {
                                if ($(this).data('field') === idToRemove) {
                                    $(this).remove();
                                }
                            });
                        }

                        // Delete row
                        $(row).remove();

                        // Inform user
                        toastr.remove();
                        toastr.success(
                            '<i class="fas fa-info-circle mr-2"></i><?php echo $lang->get('done'); ?>',
                            '', {
                                timeOut: 1000
                            }
                        );
                    }
                }
            );
        }
    });

    /**
     * CANCEL DELETE
     */
    $(document).on('click', '#button-cancel', function() {
        $(this).closest('tr').remove();
    });




    //------


    $(document).on('click', '.action-field', function() {
        var action = $(this).data('action'),
            row = $(this).closest('tr');

        if (action === 'edit') {
            var categoryId = row.data('category'),
                fieldId = row.data('field'),
                fieldPosition = row.data('position'),
                fieldRegex = row.data('regex'),
                fieldOrder = row.data('order'),
                fieldText = $(row).find('td:eq(1)').text(),
                characteristicsHtml = $(row).find('td:eq(2)').html(),
                rolesHtml = $(row).find('td:eq(3)').html(),
                roles = [],
                fields = [{
                    id: 'top',
                    text: '<?php echo $lang->get('top'); ?>'
                }];

            // Category already selected
            $('#form-field-category-div').addClass('hidden');

            // Clear
            $('#form-field :input[type="checkbox"]').iCheck('uncheck');

            // Build list of fields in this category
            $("#form-field-order").html('');
            $('.field[data-category]').each(function() {
                if ($(this).data('category') === categoryId) {
                    fields.push({
                        id: $(this).data('order'),
                        text: '<?php echo $lang->get('before') . ' '; ?>' + $(this).find('td:eq(1)').text()
                    });
                }
            });
            fields.push({
                id: 'bottom',
                text: '<?php echo $lang->get('bottom'); ?>'
            });

            // Update select of Roles
            $("#form-field-order").select2({
                tags: true,
                data: fields
            });

            // This to manage Top and Bottom
            if (fieldOrder === 1) {
                fieldOrder = 'top';
            } else if ($('#form-field-order').children('option').length - 2 === fieldOrder) {
                fieldOrder = 'bottom';
            }

            $('#form-field-regex').val(fieldRegex).change();
            $('#form-field-order').val(fieldOrder).change();

            // Field characterics
            $(characteristicsHtml).each(function(i, value) {
                // Type
                if ($(value).hasClass('text') === true) {
                    $('#form-field-type').val('text').change();
                } else if ($(value).hasClass('textarea') === true) {
                    $('#form-field-type').val('textarea').change();
                }
                // Is mandatory
                if ($(value).hasClass('mandatory') === true) {
                    $('#form-field-mandatory').iCheck('check');
                }
                // Is masked
                if ($(value).hasClass('masked') === true) {
                    $('#form-field-masked').iCheck('check');
                }
                // Is encrypted
                if ($(value).hasClass('encrypted') === true) {
                    $('#form-field-encrypted').iCheck('check');
                }
            });

            // Get roles
            $(rolesHtml).each(function(i, element) {
                roles.push($(element).data('id'));
            });

            // Prepare form
            $('#form-field-label')
                .val(fieldText)
                .data('id', fieldId)
                .data('category', categoryId);
            $('#form-field-roles').val(roles).change();

            // Show
            $('#table-fields').addClass('hidden');
            $('#form-field').removeClass('hidden');

            // Set as edit
            $('#button-save-field').data('edit', true);
        } else if (action === 'delete') {
            var categoryId = row.data('category'),
                fieldId = row.data('field');

            $('.row-delete').remove();

            // Add new row
            $(row).after(
                '<tr class="table-danger row-delete" data-category="' + categoryId + '" data-field="' + fieldId + '">' +
                '<td colspan="4">' +
                '<div class="alert alert-danger">' +
                '<p><i class="fas fa-warning mr-2"></i><?php echo $lang->get('caution_while_deleting_field'); ?></p>' +
                '<p><input type="checkbox" class="form-check-input form-control flat-red" id="delete-confirm">' +
                '<label class="form-check-label ml-3 pointer" for="delete-confirm"><?php echo $lang->get('please_confirm_by_clicking_checkbox'); ?></label></p>' +
                '</div>' +
                '<div>' +
                '<button type="button" class="btn btn-warning" id="button-delete" data-type="field"><?php echo $lang->get('submit'); ?></button>' +
                '<button type="button" class="btn btn-default float-right" id="button-cancel"><?php echo $lang->get('cancel'); ?></button>' +
                '</div></td></tr>'
            );

            // iCheck for checkbox and radio inputs
            $('.flat-red').iCheck({
                checkboxClass: 'icheckbox_flat-red'
            });
        }
    });


    //------



    /**
     * Loading table
     *
     * @return void
     */
    function loadFieldsList() {
        // Show cog
        toastr.remove();
        toastr.info('<?php echo $lang->get('loading_data'); ?> ... <i class="fas fa-circle-notch fa-spin fa-2x"></i>');

        $('#table-loading').removeClass('hidden');

        //send query
        $.post(
            "sources/fields.queries.php", {
                type: "loadFieldsList",
                //option  : prepareExchangedData(JSON.stringify(option), "encode", "<?php echo $session->get('key'); ?>"),
                key: "<?php echo $session->get('key'); ?>"
            },
            function(data) {
                //decrypt data
                data = decodeQueryReturn(data, '<?php echo $session->get('key'); ?>');
                console.log(data);

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
                    if (data.array.length > 0) {
                        // Init
                        var html = '',
                            categoryId = '',
                            positionCategory = 0,
                            positionField = 0,
                            categoriesList = '<option value="top"><?php echo $lang->get('top'); ?></option>';

                        // Parse array and build table
                        $(data.array).each(function(i, val) {
                            // Is CATEGORY or FIELD?
                            if (val.category === true) {
                                //--- This is a Category
                                categoryId = val.id;
                                positionField = 0;

                                // Loop on associated folders
                                var foldersList = '';
                                $(val.folders).each(function(j, folder) {
                                    foldersList += '<span class="badge badge-info folder mr-2" data-folder="' + folder.id + '">' +
                                        '<i class="far fa-folder mr-1"></i>' + folder.title + '</span>';
                                });

                                // Prepare html
                                html += '<tr class="table-primary category" data-category="' + categoryId + '" data-order="' + val.order + '" data-position="' + positionCategory + '">' +
                                    '<td class="text-left" width="80px">' +
                                    '<i class="far fa-edit pointer action-category mr-1" data-action="edit"></i>' +
                                    '<i class="far fa-trash-alt pointer action-category" data-action="delete"></i>' +
                                    '</td>' +
                                    '<td class="text-left" colspan="2"><strong>' + val.title + '</strong></td>' +
                                    '<td class="no-ellipsis" width="50%"><small>' + foldersList + '</small></td>' +
                                    '</tr>';

                                // Prepare list of categories for Form
                                categoriesList += '<option value="' + categoryId + '"><?php echo $lang->get('before') . ' '; ?>' + val.title + '</option>';

                                positionCategory += 1;
                            } else {
                                //--- This is a Field
                                // Init
                                var encrypted = '',
                                    masked = '',
                                    mandatory = '',
                                    regex = '',
                                    type = '<i class="fas fa-paragraph ml-2 infotip text" title="<?php echo $lang->get('text'); ?>"></i>',
                                    roles = '';

                                if (val.encrypted === 1) {
                                    encrypted = '<i class="fas fa-shield-alt ml-2 infotip encrypted" title="<?php echo $lang->get('encrypted_data'); ?>"></i>';
                                }

                                if (val.masked === 1) {
                                    masked = '<i class="fas fa-mask ml-2 infotip masked" title="<?php echo $lang->get('data_is_masked'); ?>"></i>';
                                }

                                if (val.mandatory === 1) {
                                    mandatory = '<i class="fas fa-fire text-danger ml-2 infotip mandatory" title="<?php echo $lang->get('is_mandatory'); ?>"></i>';
                                }

                                if (val.regex !== '') {
                                    regex = '<i class="fas fa-filter ml-2 infotip regex" title="Regex"></i>';
                                }

                                if (val.type === 'textarea') {
                                    type = '<i class="fas fa-align-justify ml-2 infotip textarea" title="<?php echo $lang->get('textarea'); ?>"></i>';
                                }

                                if (val.roles.length > 0) {
                                    $(val.roles).each(function(k, role) {
                                        roles += '<span class="badge badge-secondary mr-1" data-id="' + role.id + '">' + role.title + '</span>';
                                    });
                                }

                                // Prepare html
                                html += '<tr class="field" data-category="' + categoryId + '" data-field="' + val.id + '" data-order="' + val.order + '" data-position="' + positionField + '" data-regex="' + val.regex + '">' +
                                    '<td class="text-left">' +
                                    '<i class="far fa-edit pointer mr-1 action-field" data-action="edit"></i>' +
                                    '<i class="far fa-trash-alt pointer action-field" data-action="delete"></i>' +
                                    '</td>' +
                                    '<td class="text-left"><i class="fas fa-angle-right mr-2"></i>' + val.title + '</td>' +
                                    '<td class="text-center">' + regex + mandatory + encrypted + masked + type + '</td>' +
                                    '<td class="">' + roles + '</td>' +
                                    '</tr>';

                                positionField += 1;
                            }
                        });

                        /*// Store some values
                        store.update(
                            'teampassApplication',
                            function(teampassApplication)
                            {
                                teampassApplication.numberOfCategories = positionCategory + 1;
                            }
                        );*/

                        $('.overlay').addClass('hidden');

                        // Display
                        $('#table-fields > tbody').html(html);

                        $('#table-fields').removeClass('hidden');

                        // Show tooltips
                        $('.infotip').tooltip();

                        $('#form-category-list')
                            .find('option')
                            .remove()
                            .end()
                            .append(categoriesList + '<option value="bottom"><?php echo $lang->get('bottom'); ?></option>');
                    } else {
                        // No fields is defined
                        $("#fields-message")
                            .html("<?php echo $lang->get('no_category_defined'); ?>")
                            .removeClass("hidden");
                    }

                    // Inform user
                    toastr.remove();
                    toastr.success(
                        '<i class="fas fa-info-circle mr-2"></i><?php echo $lang->get('done'); ?>',
                        '', {
                            timeOut: 1000
                        }
                    );

                    $('#table-loading').addClass('hidden');
                }
            }
        );
    }
    //]]>
</script>
