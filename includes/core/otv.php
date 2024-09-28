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
 * @file      otv.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use voku\helper\AntiXSS;
use TeampassClasses\SessionManager\SessionManager;
use TeampassClasses\ConfigManager\ConfigManager;


// Load functions
require_once __DIR__.'/../../sources/main.functions.php';
loadClasses('DB');

$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$antiXSS = new AntiXSS();
$session = SessionManager::getSession();

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
?>
<body class="hold-transition login-page ">
    <div class="login-box" style="margin-top:100px; width:700px;">
        
        <!-- /.login-logo -->
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <a href="../../index.php" class="h1"><b><?php echo TP_TOOL_NAME; ?></b></a>
            </div>
            <div class="card-body login-card-body">
<?php
if (empty($request->query->get('code')) === false
    && empty($request->query->get('stamp')) === false
    && empty($request->query->get('key')) === false
) {
    if (isset($SETTINGS['otv_is_enabled']) === false
        || (int) $SETTINGS['otv_is_enabled'] === 0
    ) {
        echo '
        <div class="text-center text-danger">
        <h3><i class="fas fa-exclamation-triangle mr-2"></i>One-Time-View is not allowed!</h3>
        </div>';
        exit(true);
    }

    // check session validity
    $data = DB::queryfirstrow(
        'SELECT *
        FROM '.prefixTable('otv').'
        WHERE code = %s',
        filter_input(INPUT_GET, 'code', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    );
    
    if (DB::count() > 0  && (int) $data['timestamp'] === (int) filter_input(INPUT_GET, 'stamp', FILTER_VALIDATE_INT)) {
        // otv is too old
        if ($data['time_limit'] < time() || ($data['views'] + 1) > $data['max_views']) {
            $html = '<div class="text-center text-danger">
            <h3><i class="fas fa-exclamation-triangle mr-2"></i>Link is expired!</h3>
            </div>';
            // delete entry
            DB::delete(prefixTable('otv'), 'id = %i', $data['id']);

        } else {
            // Check if user origine is allowed to see the item
            // If shared_globaly enabled, then link must contain the subdomain
            if (empty($SETTINGS['shared_globaly']) === false && (int) $data['shared_globaly'] === 1 && str_contains(parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST), $SETTINGS['shared_globaly']) === false) {
                echo '
                <div class="text-center text-danger">
                <h3><i class="fas fa-exclamation-triangle mr-2"></i>This link is not valid!</h3>
                </div>';
                exit(true);
            }

            // get from DB
            $dataItem = DB::queryfirstrow(
                'SELECT *
                FROM '.prefixTable('items').' as i
                INNER JOIN '.prefixTable('log_items').' as l ON (l.id_item = i.id)
                INNER JOIN '.prefixTable('otv').' as otv ON (otv.item_id = i.id)
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
            if (DB::count() > 0 && isset($SETTINGS['enable_delete_after_consultation']) && (int) $SETTINGS['enable_delete_after_consultation'] === 1) {
                if ((int) $dataDelete['del_enabled'] === 1) {
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
                        echo '<div style="padding:10px; margin:90px 30px 30px 30px; text-align:center;" class="ui-widget-content ui-state-error ui-corner-all"><i class="fas fa-warning fa-2x"></i>&nbsp;'.
                        addslashes($LANG['not_allowed_to_see_pw_is_expired']).'</div>';
                        return false;
                    }
                }
            }

            // Uncrypt PW
            $password_decrypted = cryption(
                $data['encrypted'],
                filter_input(INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS),
                'decrypt',
                $SETTINGS
            );
            // get data
            $label = strip_tags($dataItem['label']);
            $url = $dataItem['url'];
            $description = preg_replace('/(?<!\\r)\\n+(?!\\r)/', '', strip_tags((string) $dataItem['description'], TP_ALLOWED_TAGS));
            $login = str_replace('"', '&quot;', $dataItem['login']);
            // display data
            $html = '<div class="text-center">
                <h3>One-Time item view page</h3>
                <p class="font-weight-light mt-3">- Here are the details of the Item that has been shared to you -</p>
                <div class="mt-5">
                <table class="table text-left" style="margin: 0 auto;">
                <tr><th>Label:</th><td>'.$label.'</td></tr>
                <tr><th>Password:</th><td>'.addslashes($password_decrypted['string']).'</td></tr>
                <tr><th>Description:</th><td>'.$description.'</td></tr>
                <tr><th>login:</th><td>'.$login.'</td></tr>
                <tr><th>URL:</th><td>'.$url.'</td></tr>
                </table></div>
                <p class="mt-3 text-info"><i class="fas fa-info mr-2"></i>Copy carefully the data you need.<br>This page is visible until <b>'.
                date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], (int) $dataItem['time_limit']).'</b> OR <b>'.($dataItem['max_views'] - ($dataItem['views']+1)).' more time(s)</b>.</div>
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

            // update views
            DB::update(
                prefixTable('otv'),
                [
                    'views' => $data['views'] + 1,
                ],
                'id = %i',
                $data['id']
            );

            $html .= "</div></div>";
        }

        // display
        // deepcode ignore ServerLeak: $html is generated by the script
        echo ($html);
    } else {
        echo '<div class="text-center text-danger">
        <h3><i class="fas fa-exclamation-triangle mr-2"></i>Not a valid page!</h3>
        </div>';
    }
} else {
    echo '
    <div class="text-center text-danger">
    <h3><i class="fas fa-exclamation-triangle mr-2"></i>No valid OTV parameters!</h3>
    </div>';
}
?>
            </div>
        </div>
    </div>
</body>
