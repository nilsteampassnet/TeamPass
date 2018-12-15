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
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

/* do checks */
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'fields', $SETTINGS) === false) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

// Load template
require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';

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
                        <h3 class='card-title'><?php echo langHdl('definition'); ?></h3>
                    </div>


                    <div class="card-body" id="fields-list">
                        <table id="table-fields" class="table table-hover hidden" style="width:100%">
                            <tbody>

                            </tbody>
                        </table>

                        <div class="callout callout-info hidden" id="fields-message">
                        </div>
                    </div>

                </div>

            </div>
        </div>
    </div>
</div>


