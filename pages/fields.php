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
 * @file      fields.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

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

// Define Timezone
date_default_timezone_set(isset($SETTINGS['timezone']) === true ? $SETTINGS['timezone'] : 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //

//Load Tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
$tree->rebuild();

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-keyboard mr-2"></i><?php echo $lang->get('fields'); ?></h1>
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
                        <h3 class='card-title'><?php echo $lang->get('configuration'); ?></h3>
                    </div>

                    <div class='card-body'>

                        <div class='row mb-2'>
                            <div class='col-10'>
                                <?php echo $lang->get('settings_item_extra_fields'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('settings_item_extra_fields_tip'); ?>
                                </small>
                            </div>
                            <div class='col-2'>
                                <div class='toggle toggle-modern' id='item_extra_fields' data-toggle-on='<?php echo isset($SETTINGS['item_extra_fields']) === true && $SETTINGS['item_extra_fields'] === '1' ? 'true' : 'false'; ?>'></div><input type='hidden' id='item_extra_fields_input' value='<?php echo isset($SETTINGS['item_extra_fields']) && $SETTINGS['item_extra_fields'] === '1' ? '1' : '0'; ?>'>
                            </div>
                        </div>

                        <div class='row mb-2'>
                            <div class='col-10'>
                                <?php echo $lang->get('create_item_based_upon_template'); ?>
                                <small class='form-text text-muted'>
                                    <?php echo $lang->get('create_item_based_upon_template_tip'); ?>
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
                        <h3 class='card-title'><?php echo $lang->get('definition'); ?>
                            <button class="btn btn-default mr-2" id="button-new-category">
                                <i class="far fa-plus-square mr-1"></i><?php echo $lang->get('category'); ?>
                            </button>
                            <button class="btn btn-default mr-2" id="button-new-field">
                                <i class="far fa-plus-square mr-1"></i><?php echo $lang->get('field'); ?>
                            </button>
                        </h3>
                    </div>


                    <div class="card-body" id="fields-list">
                        <table id="table-fields" class="table table-hover table-responsive hidden" style="width:100%">
                            <tbody>

                            </tbody>
                        </table>

                        <div class="card card-secondary hidden" id="form-category">
                            <div class='card-header'>
                                <h3 class='card-title'><?php echo $lang->get('category'); ?>
                            </div>

                            <div class="card-body">
                                <div class="form-group">
                                    <label><?php echo $lang->get('label'); ?></label>
                                    <input type="text" class="form-control form-item-control" id="form-category-label" data-id="">
                                </div>
                                <div class="form-group">
                                    <label><?php echo $lang->get('folders'); ?></label>
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
                                    <label><?php echo $lang->get('position'); ?></label>
                                    <select class="form-control form-item-control select2" style="width:100%;" id="form-category-list"></select>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="button" class="btn btn-info save" data-action="category" data-edit="false" id="button-save-category"><?php echo $lang->get('save'); ?></button>
                                <button type="button" class="btn btn-default float-right cancel" data-action="category"><?php echo $lang->get('cancel'); ?></button>
                            </div>
                        </div>

                        <div class="card hidden" id="form-field">

                            <div class="card-body">
                                <div class="form-group">
                                    <label><?php echo $lang->get('label'); ?></label>
                                    <input type="text" class="form-control form-item-control" id="form-field-label" data-id="">
                                </div>

                                <div class="form-group">
                                    <label><?php echo $lang->get('type'); ?></label>
                                    <select class="form-control form-item-control select2" style="width:100%;" id="form-field-type">
                                        <?php
                                        // Build list of Types
                                        echo '<option value="">-- ' . $lang->get('select') . ' --</option>
                                            <option value="text">' . $lang->get('text') . '</option>
                                            <option value="textarea">' . $lang->get('textarea') . '</option>';
                                        ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label>Regex</label>
                                    <input type="text" class="form-control form-item-control" id="form-field-regex" data-id="">
                                </div>

                                <div class="form-group">
                                    <input type="checkbox" class="form-check-input form-control flat-blue" id="form-field-mandatory">
                                    <label for="form-field-mandatory" class="pointer ml-2"><?php echo $lang->get('is_mandatory'); ?></label>
                                </div>

                                <div class="form-group">
                                    <input type="checkbox" class="form-check-input form-control flat-blue" id="form-field-masked">
                                    <label for="form-field-masked" class="pointer ml-2"><?php echo $lang->get('masked_text'); ?></label>
                                </div>

                                <div class="form-group">
                                    <input type="checkbox" class="form-check-input form-control flat-blue" id="form-field-encrypted">
                                    <label for="form-field-encrypted" class="pointer ml-2"><?php echo $lang->get('encrypted_data'); ?></label>
                                    <small class="form-text text-muted">
                                        <?php echo $lang->get('caution_on_field_encryption_change'); ?>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label><?php echo $lang->get('restrict_visibility_to'); ?></label>
                                    <select class="form-control form-item-control select2" multiple="multiple" style="width:100%;" id="form-field-roles">
                                        <?php
                                        // Build list of Roles
                                        echo '<option value="all">' . $lang->get('every_roles') . '</option>';
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
                                    <label><?php echo $lang->get('category'); ?></label>
                                    <select class="form-control form-item-control select2" style="width:100%;" id="form-field-category"></select>
                                </div>

                                <div class="form-group">
                                    <label><?php echo $lang->get('position'); ?></label>
                                    <select class="form-control form-item-control select2" style="width:100%;" id="form-field-order"></select>
                                </div>
                            </div>

                            <div class="card-footer">
                                <button type="button" class="btn btn-info save" data-action="field" data-edit="false" id="button-save-field"><?php echo $lang->get('confirm'); ?></button>
                                <button type="button" class="btn btn-default float-right cancel" data-action="field"><?php echo $lang->get('cancel'); ?></button>
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
