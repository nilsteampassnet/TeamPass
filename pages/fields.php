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
 * @file      fields.php
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
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'fields', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load template
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
//Build tree
$tree = new SplClassLoader('Tree\NestedTree', './includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
$tree->rebuild();

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-keyboard mr-2"></i><?php echo langHdl('fields'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->


<!-- Main content -->
<div class='content'>
    <div class='container-fluid'>
        <div class='row'>
            <div class='col-md-12'>
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo langHdl('configuration'); ?></h3>
                    </div>

                    <div class='card-body'>

                        <div class='row mb-2'>
                            <div class='col-10'>
                                <?php echo langHdl('settings_item_extra_fields'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo langHdl('settings_item_extra_fields_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='item_extra_fields' data-toggle-on='<?php echo isset($SETTINGS['item_extra_fields']) === true && $SETTINGS['item_extra_fields'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='item_extra_fields_input' value='<?php echo isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] === '1' ? '1' : '0'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2'>
                            <div class='col-10'>
                                <?php echo langHdl('create_item_based_upon_template'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo langHdl('create_item_based_upon_template_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='item_creation_templates' data-toggle-on='<?php echo isset($SETTINGS['item_creation_templates']) === true && $SETTINGS['item_creation_templates'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='item_creation_templates_input' value='<?php echo isset($SETTINGS['item_creation_templates']) && $SETTINGS['item_creation_templates'] === '1' ? '1' : '0'; ?>'>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class='row'>
            <div class='col-md-12'>
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo langHdl('definition'); ?>
                            <button class="btn btn-default mr-2" id="button-new-category">
                                <i class="far fa-plus-square mr-1"></i><?php echo langHdl('category'); ?>
                            </button>
                            <button class="btn btn-default mr-2" id="button-new-field">
                                <i class="far fa-plus-square mr-1"></i><?php echo langHdl('field'); ?>
                            </button>
                        </h3>
                    </div>


                    <div class="card-body" id="fields-list">
                        <table id="table-fields" class="table table-hover hidden" style="width:100%">
                            <tbody>

                            </tbody>
                        </table>

                        <div class="card card-secondary hidden" id="form-category">
                            <div class='card-header'>
                                <h3 class='card-title'><?php echo langHdl('category'); ?>
                            </div>

                            <div class="card-body">
                                <div class="form-group">
                                    <label><?php echo langHdl('label'); ?></label>
                                    <input type="text" class="form-control form-item-control" id="form-category-label" data-id="">
                                </div>
                                <div class="form-group">
                                    <label><?php echo langHdl('folders'); ?></label>
                                    <select class="form-control form-item-control select2" multiple="multiple" style="width:100%;" id="form-category-folders">
                                        <?php
                                        $folders = $tree->getDescendants();
foreach ($folders as $folder) {
    DB::query(
        'SELECT * FROM ' . prefixTable('nested_tree') . '
                                        WHERE personal_folder = %i AND id = %i',
        '0',
        $folder->id
    );
    $counter = DB::count();
    if ($counter > 0) {
        $ident = '';
        for ($x = 1; $x < $folder->nlevel; ++$x) {
            $ident .= '-';
        }
        echo '
                                        <option value="' . $folder->id . '">' . $ident . '&nbsp;' . str_replace('&', '&amp;', $folder->title) . '</option>';
    }
}
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><?php echo langHdl('position'); ?></label>
                                    <select class="form-control form-item-control select2" style="width:100%;" id="form-category-list"></select>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="button" class="btn btn-info save" data-action="category" data-edit="false" id="button-save-category"><?php echo langHdl('save'); ?></button>
                                <button type="button" class="btn btn-default float-right cancel" data-action="category"><?php echo langHdl('cancel'); ?></button>
                            </div>
                        </div>

                        <div class="card hidden" id="form-field">

                            <div class="card-body">
                                <div class="form-group">
                                    <label><?php echo langHdl('label'); ?></label>
                                    <input type="text" class="form-control form-item-control" id="form-field-label" data-id="">
                                </div>

                                <div class="form-group">
                                    <label><?php echo langHdl('type'); ?></label>
                                    <select class="form-control form-item-control select2" style="width:100%;" id="form-field-type">
                                        <?php
                                        // Build list of Types
                                        echo '<option value="">-- ' . langHdl('select') . ' --</option>
                                            <option value="text">' . langHdl('text') . '</option>
                                            <option value="textarea">' . langHdl('textarea') . '</option>';
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <input type="checkbox" class="form-check-input form-control flat-blue" id="form-field-mandatory">
                                    <label for="form-field-mandatory" class="pointer ml-2"><?php echo langHdl('is_mandatory'); ?></label>
                                </div>

                                <div class="form-group">
                                    <input type="checkbox" class="form-check-input form-control flat-blue" id="form-field-masked">
                                    <label for="form-field-masked" class="pointer ml-2"><?php echo langHdl('masked_text'); ?></label>
                                </div>

                                <div class="form-group">
                                    <input type="checkbox" class="form-check-input form-control flat-blue" id="form-field-encrypted">
                                    <label for="form-field-encrypted" class="pointer ml-2"><?php echo langHdl('encrypted_data'); ?></label>
                                    <small class="form-text text-muted">
                                        <?php echo langHdl('caution_on_field_encryption_change'); ?>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label><?php echo langHdl('restrict_visibility_to'); ?></label>
                                    <select class="form-control form-item-control select2" multiple="multiple" style="width:100%;" id="form-field-roles">
                                        <?php
                                        // Build list of Roles
                                        echo '<option value="all">' . langHdl('every_roles') . '</option>';
$rows = DB::query(
    'SELECT id, title
                                    FROM ' . prefixTable('roles_title') . '
                                    ORDER BY title ASC'
);
foreach ($rows as $record) {
    echo '<option value="' . $record['id'] . '">' . addslashes($record['title']) . '</option>';
}
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group hidden" id="form-field-category-div">
                                    <label><?php echo langHdl('category'); ?></label>
                                    <select class="form-control form-item-control select2" style="width:100%;" id="form-field-category"></select>
                                </div>

                                <div class="form-group">
                                    <label><?php echo langHdl('position'); ?></label>
                                    <select class="form-control form-item-control select2" style="width:100%;" id="form-field-order"></select>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="button" class="btn btn-info save" data-action="field" data-edit="false" id="button-save-field"><?php echo langHdl('confirm'); ?></button>
                                <button type="button" class="btn btn-default float-right cancel" data-action="field"><?php echo langHdl('cancel'); ?></button>
                            </div>
                        </div>

                        <div class="callout callout-info hidden" id="fields-message">
                        </div>
                    </div>


                    <div class="overlay" id="table-loading">
                        <i class="fas fa-refresh fa-spin"></i>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>
