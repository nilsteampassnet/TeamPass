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
 * @version   3.0.0.22
 * @file      otv.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2023 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

if (file_exists('../sources/SecureHandler.php')) {
    include_once '../sources/SecureHandler.php';
} elseif (file_exists('./sources/SecureHandler.php')) {
    include_once './sources/SecureHandler.php';
} else {
    throw new Exception("Error file '/sources/SecureHandler.php' not exists", 1);
}
if (isset($_SESSION) === false) {
    session_name('teampass_session');
    session_start();
}
if (isset($_SESSION['CPM']) === false || $_SESSION['CPM'] !== 1) {
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
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
$superGlobal = new protect\SuperGlobal\SuperGlobal();
?>
<body class="hold-transition otv-page">
    <div class="login-box" style="margin-top:100px; width:750px;">
        <!-- /.login-logo -->
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <a href="index.php" class="h1"><b><?php echo TP_TOOL_NAME; ?> - ACTIA Telecom</b></a>
            </div>
            <div class="card-body login-card-body">
                <?php
                if (!empty($superGlobal->get('code', 'GET')) === true
                    && !empty($superGlobal->get('stamp', 'GET')) === true
                    && !empty($superGlobal->get('key', 'GET')) === true
                ) {
                    //Include files
                    include_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
                    include_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
                    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
                    include_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
                    // Open MYSQL database connection
                    include_once './includes/libraries/Database/Meekrodb/db.class.php';
                    DB::$host = DB_HOST;
                    DB::$user = DB_USER;
                    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
                    DB::$dbName = DB_NAME;
                    DB::$port = DB_PORT;
                    DB::$encoding = DB_ENCODING;
                    DB::$ssl = DB_SSL;
                    DB::$connect_options = DB_CONNECT_OPTIONS;
                    if (isset($SETTINGS['otv_is_enabled']) === false
                        || (int) $SETTINGS['otv_is_enabled'] === 0
                    ) {
                        echo '
                        <div class="text-center text-danger">
                        <h1><i class="fas fa-exclamation-triangle mr-2"></i>' . langHdl('otv_not_allowed') . '<i class="fas fa-exclamation-triangle mr-2"></i></h3>
                        </div>';
                        exit(true);
                    }

                    // check session validity
                    $data = DB::queryfirstrow(
                        'SELECT id, timestamp, code, item_id, encrypted
                        FROM '.prefixTable('otv').'
                        WHERE code = %s',
                        filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING)
                    );

                    if (DB::count() > 0  && (int) $data['timestamp'] === (int) filter_input(INPUT_GET, 'stamp', FILTER_VALIDATE_INT)) {
                        // otv is too old
                        if ($data['timestamp'] < time() - ($SETTINGS['otv_expiration_period'] * 86400)) {
                            $html = '<div class="text-center text-danger">
                            <h1><i class="fas fa-exclamation-triangle mr-2"></i>' . langHdl('otv_link_to_old') . '<i class="fas fa-exclamation-triangle mr-2"></i></h3>
                            </div>';
                        } else {
                            // get from DB
                            $dataItem = DB::queryfirstrow(
                                'SELECT *
                                FROM '.prefixTable('items').' as i
                                INNER JOIN '.prefixTable('log_items').' as l ON (l.id_item = i.id)
                                WHERE i.id = %i AND l.action = %s',
                                $data['item_id'],
                                'at_creation'
                            );
                            // is Item still valid regarding number of times being seen
                            // Decrement the number before being deleted
                            $dataDelete = DB::queryfirstrow(
                                'SELECT * FROM '.prefixTable('automatic_del').' WHERE item_id=%i',
                                $data['item_id']
                            );
                            if (isset($SETTINGS['enable_delete_after_consultation']) && (int) $SETTINGS['enable_delete_after_consultation'] === 1) {
                                if (!empty($dataDelete) && (int) $dataDelete['del_enabled'] === 1) {
                                    if ((int) $dataDelete['del_type'] === 1 && (int) $dataDelete['del_value'] >= 1) {
                                        // decrease counter
                                        DB::update(
                                            prefixTable('automatic_del'),
                                            [
                                                'del_value' => $dataDelete['del_value'] - 1,
                                            ],
                                            'item_id = %i',
                                            $data['item_id']
                                        );
                                    } elseif (((int) $dataDelete['del_type'] === 1 && (int) $dataDelete['del_value'] <= 1)
                                        || ((int) $dataDelete['del_type'] === 2 && (int) $dataDelete['del_value'] < time())
                                    ) {
                                        // delete item
                                        DB::delete(prefixTable('automatic_del'), 'item_id = %i', $data['item_id']);
                                        // make inactive object
                                        DB::update(
                                            prefixTable('items'),
                                            [
                                                'inactif' => '1',
                                            ],
                                            'id = %i',
                                            $data['item_id']
                                        );
                                        // log
                                        logItems(
                                            $SETTIGNS,
                                            (int) $data['item_id'],
                                            $dataItem['label'],
                                            (int) OTV_USER_ID,
                                            'at_delete',
                                            'otv',
                                            'at_automatically_deleted'
                                        );
                                        echo '<div style="padding:10px; margin:90px 30px 30px 30px; text-align:center;" class="ui-widget-content ui-state-error ui-corner-all"><i class="fa fa-warning fa-2x"></i>&nbsp;'.
                                        addslashes($LANG['not_allowed_to_see_pw_is_expired']).'</div>';
                                        return false;
                                    }
                                }
                            }

                            // Uncrypt PW
                            $password_decrypted = cryption(
                                $data['encrypted'],
                                filter_input(INPUT_GET, 'key', FILTER_SANITIZE_STRING),
                                'decrypt',
                                $SETTINGS
                            );
                            // get data
                            $url = $dataItem['url'];
                            $description = preg_replace('/(?<!\\r)\\n+(?!\\r)/', '', strip_tags($dataItem['description'], TP_ALLOWED_TAGS));
                            $login = str_replace('"', '&quot;', $dataItem['login']);
                            // display data
                            $html = '
                            <div class="text-center">
                                <h3>' . langHdl('otv_view_page_details_share') . '</h3>
                                <div class="mt-5">
                                    <table class="table text-left" style="margin: 0 auto; border-collapse: unset; table-layout: unset;">';

                            if (!empty($login)) { $html .= '<tr><th style="width: 150px;">' . langHdl('otv_login') . '</th><td>'.$login.'</td></tr>'; }
                            if (!empty($password_decrypted['string'])) { $html .= '<tr><th style="width: 150px;">' . langHdl('otv_password') . '</th><td>'.addslashes($password_decrypted['string']).'</td></tr>'; }
                            if (!empty($description)) { $html .= '<tr><th style="width: 150px;">' . langHdl('otv_description') . '</th><td>'.$description.'</td></tr>'; }
                            if (!empty($url)) { $html .= '<tr><th style="width: 150px;">' . langHdl('otv_url') . '</th><td><a href="'.$url.'" target="_blank">'.$url.'</a></td></tr>'; }

                            $html .= '
                                    </table>
                                </div>
                                <h3 class="mt-3 text-info">' . langHdl('otv_view_page_information') . '</h3>
                            </div>';
                            // log
                            logItems(
                                $SETTINGS,
                                (int) $data['item_id'],
                                $dataItem['label'],
                                (int) OTV_USER_ID,
                                'at_shown',
                                'otv'
                            );
                            // delete entry
                            DB::delete(prefixTable('otv'), 'id = %i', $data['id']);
                            // display
                            echo $html;
                        }
                    } else {
                        echo '<div class="text-center text-danger">
                        <h1><i class="fas fa-exclamation-triangle mr-2"></i>' . langHdl('otv_not_valid_page') . ' <i class="fas fa-exclamation-triangle mr-2"></i></h3>
                        </div>';
                    }
                } else {
                    echo '<div class="text-center text-danger">
                    <h1><i class="fas fa-exclamation-triangle mr-2"></i>' . langHdl('otv_no_valid_parameters') . ' <i class="fas fa-exclamation-triangle mr-2"></i></h3>
                    </div>';
                }
                ?>
            </div>
        </div>
    </div>
</body>
