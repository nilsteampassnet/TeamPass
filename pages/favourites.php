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
 * @file      favourites.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = Request::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config if $SETTINGS not defined
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

// Check user access and favourites enabled
echo $checkUserAccess->caseHandler();
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('favourites') === false
    || isset($SETTINGS['enable_favourites']) === false || (int) $SETTINGS['enable_favourites'] === 0
    || (int) $session_user_admin === 1) {
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
$lang = new Language($session->get('user-language') ?? 'english');

// --------------------------------- //


?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0 text-dark"><i class="fas fa-star mr-2"></i><?php echo $lang->get('favorites'); ?></h1>
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
                <?php
                if (count($session->get('user-favorites')) > 0) {
                    ?>
                <div class="card-body p-0" id="favorites">
                    <table class="table table-condensed table-responsive">
                        <tr>
                            <th style="width: 100px"></th>
                            <th style="min-width:15%;"><?php echo $lang->get('label'); ?></th>
                            <th style="min-width:50%;"><?php echo $lang->get('description'); ?></th>
                            <th style="min-width:20%;"><?php echo $lang->get('group'); ?></th>
                        </tr>
                        <?php
                        foreach ($session->get('user-favorites') as $fav) {
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
                                        <td><?php echo $data['title'] === $session->get('user-id') ? $session->get('user-login') : $data['title']; ?></td>
                                    </tr>
                        <?php
                                }
                            }
                        } ?>
                    </table>
                </div>
                <?php
                } else {?>

                <div class="card-body" id="no-favorite">
                    <div class="alert alert-info">
                        <h5><i class="icon fa fa-info mr-2"></i><?php echo $lang->get('currently_no_favorites'); ?></h5>
                    </div>
                </div>
                <?php
                } ?>
            </div>
        </div>
    </div>
</section>
