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
 * @copyright 2009-2026 Teampass.net
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
            'type' => htmlspecialchars($request->request->get('type', ''), ENT_QUOTES, 'UTF-8'),
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
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

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
                <h1 class="m-0 text-dark"><i class="fas fa-person-drowning mr-2"></i><?php echo $lang->get('tools'); ?></h1>
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
                        <div class="alert alert-danger pt-4 pb-4" role="alert">
                            <h5 class="m-0 text-dark">
                                <i class="fas fa-bullhorn mr-2"></i>
                                <?php echo $lang->get('tools_usage_warning'); ?>
                            </h5>
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
                                        <?php echo sprintf($lang->get('fix_personal_items_empty_tip'), DB_PREFIX, DB_PREFIX); ?>
                                    </small>
                                </div>
                            </div>
<?php                            
// Check if table  exists
$tableExists = DB::queryFirstField('SHOW TABLES LIKE %s', prefixTable('items_v2'));
if (is_null($tableExists) === true) {
    echo '
                            <div class="alert alert-warning" role="warning"><i class="fas fa-lightbulb mr-2"></i>'.$lang->get('table_not_exists').'</div>';
} else {
    // Get list of users
    $selectOptions = '';
    $users = DB::query('
        SELECT id, login, lastname, name, personal_folder, encrypted_psk 
        FROM ' . prefixTable('users') . ' 
        WHERE disabled = 0 AND (login NOT LIKE "%_deleted%")
        ORDER BY login');
    foreach ($users as $user) {
        $selectOptions .= '<option value="'.strval($user['id']).'" data-pf="'.strval($user['personal_folder']).'" data-psk="'.htmlspecialchars(strval($user['encrypted_psk'] ?? ''), ENT_QUOTES, 'UTF-8').'">'
            .htmlspecialchars(strval($user['lastname']), ENT_QUOTES, 'UTF-8').' '
            .htmlspecialchars(strval($user['name']), ENT_QUOTES, 'UTF-8').' ('
            .htmlspecialchars(strval($user['login']), ENT_QUOTES, 'UTF-8').')'.
            ((is_null($user['encrypted_psk']) === true || empty($user['encrypted_psk']) === true) ? ' - '.$lang->get('tools_no_user_psk') : '').
            (intval($user['personal_folder']) !== 1 ? ' - '.$lang->get('tools_personal_folder_disabled') : '').
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
         
        <div class='card card-primary'>
            <div class='card-header'>
                <h3 class='card-title'><?php echo $lang->get('tools_restore_items_otp_title'); ?></h3>
            </div>
            <!-- /.card-header -->
            <!-- form start -->
            <form role='form-horizontal'>
                <div class='card-body'>

                    <div class='row mb-3'>
                        <div class='col-12'>
                            <small id='passwordHelpBlock' class='form-text text-muted'>
                                <?php echo $lang->get('tools_restore_items_otp_tip'); ?>
                            </small>
                        </div>
                    </div>
                    <?php                            
// Check if table  exists
$backups = [];
$result = DB::queryFirstField(
    'SELECT COUNT(*) FROM information_schema.tables 
    WHERE table_schema = %s AND table_name = %s',
    DB_NAME, 
    prefixTable('sharekeys_backup')
);
if ($result > 0) {
    // Get list of backups
    $backups = DB::query('SELECT sb.operation_code, sb.created_at, sb.user_id, u.login
        FROM '.prefixTable('sharekeys_backup').' AS sb
        INNER JOIN '.prefixTable('users').' AS u ON sb.user_id = u.id
        GROUP BY sb.operation_code, sb.created_at, sb.user_id, u.login
        ORDER BY sb.created_at DESC;'
    );
}
$selectOptions = '';
// Get list of backups
foreach ($backups as $bck) {
    $selectOptions .= '<option value="'.htmlspecialchars(strval($bck['operation_code']), ENT_QUOTES, 'UTF-8').'">'
        .htmlspecialchars(strval($bck['login']), ENT_QUOTES, 'UTF-8').' - '
        .sprintf($lang->get('tools_backup_date_fmt'), htmlspecialchars(strval($bck['created_at']), ENT_QUOTES, 'UTF-8')).'</option>';
}
?>
                    <div class='row mb-2'>
                        <div class='col-5'>
                            <?php echo $lang->get('tools_select_backup'); ?>
                        </div>
                        <div class='col-7'>
                            <select class='form-control' id='restore_items_master_keys_id'>
                                <?php echo $selectOptions; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class='row mb-3'>
                        <button type='button' class='btn btn-primary btn-sm tp-action mr-2' id="restore_items_master_keys_but" data-action='restore_items_master_keys_but'>
                            <i class='fas fa-cog mr-2'></i><?php echo $lang->get('tools_restore_keys'); ?>
                        </button>
                        <button type='button' class='btn btn-secundary btn-sm tp-action mr-2' id="delete_restore_backup_but" data-action='delete_restore_backup_but'>
                            <i class='fas fa-trash mr-2'></i><?php echo $lang->get('tools_delete_backup'); ?>
                        </button>
                    </div>
                    
                    <div class='row mb-2'>
                        <div id='restore_items_master_keys_results'>
                        </div>
                    </div>

                </div>
            </form>
        </div>

        <div class='card card-primary'>
            <div class='card-header'>
                <h3 class='card-title'><?php echo $lang->get('restore_missing_sharekeys'); ?></h3>
            </div>
            <!-- /.card-header -->
            <!-- form start -->
            <form role='form-horizontal'>
                <div class='card-body'>

                    <div class='row mb-3'>
                        <div class='col-12'>
                            <small class='form-text text-muted'>
                                <?php echo $lang->get('restore_missing_sharekeys_tip'); ?>
                            </small>
                        </div>
                    </div>

                    <?php
// Users whose keys the repair may open the objects with (see restoreSharekeysSourceUser()), with
// the number of shared item keys each one holds, to help choosing who can open the most.
$sourceKeyCounts = [];
foreach (DB::query(
    'SELECT sk.user_id, COUNT(*) AS nb
    FROM ' . prefixTable('sharekeys_items') . ' AS sk
    INNER JOIN ' . prefixTable('items') . ' AS i ON (i.id = sk.object_id AND i.perso = 0)
    WHERE sk.share_key != ""
    GROUP BY sk.user_id'
) as $row) {
    $sourceKeyCounts[(int) $row['user_id']] = (int) $row['nb'];
}
$sourceUsers = DB::query(
    'SELECT id, login, name, lastname
    FROM ' . prefixTable('users') . '
    WHERE deleted_at IS NULL AND public_key != "" AND id != %i AND id NOT IN %li
    ORDER BY login',
    (int) $session->get('user-id'),
    [TP_USER_ID, OTV_USER_ID, SSH_USER_ID, API_USER_ID]
);
?>
                    <div class='row mb-2'>
                        <div class='col-5'>
                            <?php echo $lang->get('restore_missing_sharekeys_source'); ?>
                        </div>
                        <div class='col-7'>
                            <select class='form-control' id='restore_missing_sharekeys_source_user'>
                                <option value='0'><?php echo htmlspecialchars($lang->get('restore_missing_sharekeys_source_self'), ENT_QUOTES, 'UTF-8'); ?></option>
<?php foreach ($sourceUsers as $sourceUser) { ?>
                                <option value='<?php echo (int) $sourceUser['id']; ?>'><?php echo htmlspecialchars(trim((string) $sourceUser['lastname'] . ' ' . (string) $sourceUser['name']) . ' (' . (string) $sourceUser['login'] . ') - ' . sprintf($lang->get('restore_missing_sharekeys_source_keys_fmt'), $sourceKeyCounts[(int) $sourceUser['id']] ?? 0), ENT_QUOTES, 'UTF-8'); ?></option>
<?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class='row mb-2 hidden' id='restore_missing_sharekeys_source_pwd_row'>
                        <div class='col-5'>
                            <?php echo $lang->get('password'); ?>
                        </div>
                        <div class='col-7'>
                            <input type='password' class='form-control' id='restore_missing_sharekeys_source_pwd' autocomplete='new-password' placeholder='<?php echo htmlspecialchars($lang->get('user_password'), ENT_QUOTES, 'UTF-8'); ?>'>
                        </div>
                    </div>

                    <div class='row mb-3'>
                        <div class='col-12'>
                            <small class='form-text text-muted'>
                                <?php echo $lang->get('restore_missing_sharekeys_source_tip'); ?>
                            </small>
                        </div>
                    </div>

                    <div class='row mb-3'>
                        <button type='button' class='btn btn-primary btn-sm tp-action mr-2' id='restore_missing_sharekeys_analyze_but' data-action='restore_missing_sharekeys_analyze_but'>
                            <i class='fas fa-magnifying-glass mr-2'></i><?php echo $lang->get('restore_missing_sharekeys_analyze'); ?>
                        </button>
                        <button type='button' class='btn btn-danger btn-sm tp-action mr-2' id='restore_missing_sharekeys_repair_but' data-action='restore_missing_sharekeys_repair_but' disabled>
                            <i class='fas fa-wrench mr-2'></i><?php echo $lang->get('restore_missing_sharekeys_repair'); ?>
                        </button>
                    </div>

                    <div class='row mb-2'>
                        <div class='col-12'>
                            <div id='restore_missing_sharekeys_results'>
                            </div>
                        </div>
                    </div>

                </div>
            </form>
        </div>

    </div><!-- /.container-fluid -->
</div>
<!-- /.content -->
