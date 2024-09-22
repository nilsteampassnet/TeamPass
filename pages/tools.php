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
 * @file      tools.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
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
if ($checkUserAccess->checkSession() === false || $checkUserAccess->userAccessPage('tools') === false) {
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
 
// LDAP type currently loaded
$ldap_type = $SETTINGS['ldap_type'] ?? '';

?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1 class="m-0 text-dark"><i class="fas fa-screwdriver-wrench mr-2"></i><?php echo $lang->get('tools'); ?></h1>
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
                <div class='row mb-3'>
                    <div class='col-12'>
                        <div class="alert alert-danger" role="alert">
                            <?php echo $lang->get('tools_usage_warning'); ?>
                        </div>
                    </div>
                </div>
                <div class='card card-primary'>
                    <div class='card-header'>
                        <h3 class='card-title'><?php echo $lang->get('fix_personal_items_empty'); ?></h3>
                    </div>
                    <!-- /.card-header -->
                    <!-- form start -->
                    <form role='form-horizontal'>
                        <div class='card-body'>

                            <div class='row mb-3'>
                                <div class='col-12'>
                                    <small id='passwordHelpBlock' class='form-text text-muted'>
                                        <?php echo $lang->get('fix_personal_items_empty_tip'); ?>
                                    </small>
                                </div>
                            </div>
<?php                            
// Check if table  exists
$tableExists = DB::queryFirstField('SHOW TABLES LIKE %s', 'teampass_items_v2');;
if (is_null($tableExists) === true) {
    echo '
                            <div class="alert alert-warning" role="warning"><i class="fas fa-lightbulb mr-2"></i>'.$lang->get('table_not_exists').'</div>';
} else {
    // Get list of users
    $selectOptions = '';
    $users = DB::query('
        SELECT id, login, lastname, name, personal_folder, encrypted_psk 
        FROM teampass_users 
        WHERE disabled = 0 AND (login NOT LIKE "%_deleted%")
        ORDER BY login');
    foreach ($users as $user) {
        $selectOptions .= '<option value="'.$user['id'].'" data-pf="'.$user['personal_folder'].'" data-psk="'.$user['encrypted_psk'].'">'.$user['lastname'].' '.$user['name'].' ('.$user['login'].')'.
            ((is_null($user['encrypted_psk']) === true || empty($user['encrypted_psk']) === true) ? ' - No user PSK exists in DB' : '').
            ((int) $user['personal_folder'] !== 1 ? ' - Personal Folder disabled for user' : '').
            '</option>';
    }
    ?>
                            <div class='row mb-2'>
                                <div class='col-5'>
                                    <?php echo $lang->get('username'); ?>
                                </div>
                                <div class='col-7'>
                                    <select class='form-control form-control-sm' id='fix_pf_items_user_id'>
                                        <option value='0'><?php echo $lang->get('select_user'); ?></option>
                                        <?php echo $selectOptions; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class='row mb-3'>
                                <button type='button' class='btn btn-primary btn-sm tp-action mr-2' data-action='fix_pf_items_but'>
                                    <i class='fas fa-cog mr-2'></i><?php echo $lang->get('perform'); ?>
                                </button>
                            </div>
                            
                            <div class='row mb-2'>
                                <div id='fix_pf_items_results'>
                                </div>
                            </div>
    <?php
}
?>

                        </div>
                    </form>
                </div>
                
            </div>
            <!-- /.col-md-6 -->
        </div>
        <!-- /.row -->
    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->
