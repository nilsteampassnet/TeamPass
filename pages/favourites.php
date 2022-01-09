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
 * @file      favourites.php
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
if (! checkUser($_SESSION['user_id'], $_SESSION['key'], curPage($SETTINGS), $SETTINGS)) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    //not allowed page
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Load
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark"><i class="fas fa-star mr-2"></i><?php echo langHdl('favorites'); ?></h1>
            </div><!-- /.col -->
        </div><!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <!--<div class="card-header">
                    <h3 class="card-title">&nbsp;</h3>
                </div>-->
                <!-- /.card-header -->
                <div class="card-body p-0<?php echo empty($_SESSION['favourites']) === false ? '' : ' hidden'; ?>" id="favorites">
                    <table class="table table-condensed">
                        <tr>
                            <th style="width: 100px"></th>
                            <th style="min-width:15%;"><?php echo langHdl('label'); ?></th>
                            <th style="min-width:50%;"><?php echo langHdl('description'); ?></th>
                            <th style="min-width:20%;"><?php echo langHdl('group'); ?></th>
                        </tr>
                        <?php
                        foreach ($_SESSION['favourites'] as $fav) {
                            if (empty($fav) === false) {
                                $data = DB::queryFirstRow(
                                    'SELECT i.label, i.description, i.id, i.id_tree, t.title
                                    FROM ' . prefixTable('items') . ' as i
                                    INNER JOIN ' . prefixTable('nested_tree') . ' as t ON (t.id = i.id_tree)
                                    WHERE i.id = %i',
                                    $fav
                                );
                                if (! empty($data['label'])) {
                                    ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-external-link-alt pointer mr-2 fav-open" data-tree-id="<?php echo $data['id_tree']; ?>" data-item-id="<?php echo $data['id']; ?>"></i>
                                            <i class="fas fa-trash pointer text-danger mr-2 fav-trash" data-item-id="<?php echo $data['id']; ?>"></i>
                                        </td>
                                        <td><?php echo $data['label']; ?></td>
                                        <td><?php echo $data['description']; ?></td>
                                        <td><?php echo $data['title'] === $_SESSION['user_id'] ? $_SESSION['login'] : $data['title']; ?></td>
                                    </tr>
                        <?php
                                }
                            }
                        } ?>
                    </table>
                </div>

                <div class="card-body<?php echo empty($_SESSION['favourites']) === false ? ' hidden' : ''; ?>" id="no-favorite">
                    <div class="alert alert-info">
                        <h5><i class="icon fa fa-info mr-2"></i><?php echo langHdl('currently_no_favorites'); ?></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
