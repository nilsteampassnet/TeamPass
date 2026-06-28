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
 * @copyright 2009-2026 Teampass.net
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
require_once __DIR__.'/../sources/main.functions.php';
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');
$antiXSS = new AntiXSS();
$session = SessionManager::getSession();

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

/**
 * Render the passphrase prompt form for a passphrase-protected Secure Send link.
 * The form posts back to the same URL. The One-Time-View endpoint is exempt from
 * CSRFGuard (see public/index.php) because it is unauthenticated, so no CSRF token
 * is needed here.
 *
 * @param object $lang  Language helper
 * @param string $error Optional error message to display above the field
 *
 * @return string
 */
function secureSendPassphraseForm($lang, string $error = ''): string
{
    $errorHtml = $error === ''
        ? ''
        : '<div class="alert alert-danger">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';

    $actionHtml = htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? ''), ENT_QUOTES, 'UTF-8');

    return '<div class="text-center">
        <h3><i class="fa-solid fa-lock mr-2"></i>' . htmlspecialchars($lang->get('secure_send_protected'), ENT_QUOTES, 'UTF-8') . '</h3>
        <p class="font-weight-light mt-3">' . htmlspecialchars($lang->get('secure_send_enter_passphrase'), ENT_QUOTES, 'UTF-8') . '</p>
        ' . $errorHtml . '
        <form method="post" action="' . $actionHtml . '" class="mt-4">
            <div class="form-group">
                <input type="password" name="passphrase" class="form-control" autocomplete="off" autofocus required>
            </div>
            <button type="submit" class="btn btn-primary btn-block">' . htmlspecialchars($lang->get('confirm'), ENT_QUOTES, 'UTF-8') . '</button>
        </form>
        </div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo TP_TOOL_NAME; ?> - <?php echo $lang->get('one_time_view'); ?></title>
    <link rel="stylesheet" href="./plugins/adminlte/css/adminlte.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <link rel="stylesheet" href="./plugins/fontawesome-free/css/fontawesome.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <link rel="stylesheet" href="./plugins/fontawesome-free/css/solid.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <link rel="stylesheet" href="./plugins/fontawesome-free/css/regular.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <link rel="stylesheet" href="./plugins/fontawesome-free/css/brands.min.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
    <link rel="stylesheet" href="./assets/css/teampass.css?v=<?php echo TP_VERSION . '.' . TP_VERSION_MINOR; ?>">
</head>
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
    $data = DB::queryFirstRow(
        'SELECT *
        FROM '.prefixTable('otv').'
        WHERE code = %s',
        filter_input(INPUT_GET, 'code', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
    );
    
    if (DB::count() > 0  && intval($data['timestamp']) === intval(filter_input(INPUT_GET, 'stamp', FILTER_VALIDATE_INT))) {
        // otv is too old
        if ($data['time_limit'] < time() || (intval($data['views']) + 1) > $data['max_views']) {
            $html = '<div class="text-center text-danger">
            <h3><i class="fas fa-exclamation-triangle mr-2"></i>Link is expired!</h3>
            </div>';
            // delete entry
            DB::delete(prefixTable('otv'), 'id = %i', $data['id']);

        } else {
            // Check if user origine is allowed to see the item
            // If shared_globaly enabled, then link must contain the subdomain
            if (empty($SETTINGS['shared_globaly']) === false && intval($data['shared_globaly']) === 1 && str_contains(parse_url($_SERVER['REQUEST_URI'], PHP_URL_HOST), $SETTINGS['shared_globaly']) === false) {
                echo '
                <div class="text-center text-danger">
                <h3><i class="fas fa-exclamation-triangle mr-2"></i>This link is not valid!</h3>
                </div>';
                exit(true);
            }

            // Resolve the decryption key.
            $urlKey = filter_input(INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $hasProtectedKey = empty($data['protected_key']) === false;

            if ($hasProtectedKey === false) {
                // Legacy model: the URL carries the unlocked Defuse key directly.
                $decryptionKey = $urlKey;
            } else {
                // New model: the URL carries the link secret; the Defuse key is wrapped
                // by it (and the recipient passphrase when one was set).
                if ((int) ($data['has_passphrase'] ?? 0) === 1) {
                    $submittedPassphrase = (string) (filter_input(INPUT_POST, 'passphrase', FILTER_UNSAFE_RAW) ?? '');
                    if ($submittedPassphrase === '') {
                        // Prompt for the passphrase, without consuming a view
                        echo secureSendPassphraseForm($lang);
                        echo '</div></div></div></body></html>';
                        exit;
                    }
                    $wrapPassword = hash('sha256', $urlKey . '|' . $submittedPassphrase);
                } else {
                    $wrapPassword = $urlKey;
                }

                $decryptionKey = defuse_validate_personal_key($wrapPassword, (string) $data['protected_key']);

                if (strpos($decryptionKey, 'Error') === 0) {
                    // Wrong passphrase or tampered link: never consume a view
                    $newFailedAttempts = (int) ($data['failed_attempts'] ?? 0) + 1;
                    if ($newFailedAttempts >= 5) {
                        DB::delete(prefixTable('otv'), 'id = %i', $data['id']);
                        echo '<div class="text-center text-danger"><h3><i class="fas fa-exclamation-triangle mr-2"></i>'
                            . htmlspecialchars($lang->get('secure_send_too_many_attempts'), ENT_QUOTES, 'UTF-8') . '</h3></div>';
                    } else {
                        DB::update(prefixTable('otv'), ['failed_attempts' => $newFailedAttempts], 'id = %i', $data['id']);
                        echo secureSendPassphraseForm($lang, $lang->get('secure_send_wrong_passphrase'));
                    }
                    echo '</div></div></div></body>';
                    exit;
                }
            }

            // Decrypt the payload using the resolved key
            $payload_decrypted = cryption(
                $data['encrypted'],
                $decryptionKey,
                'decrypt',
                $SETTINGS
            );

            $sendType = (isset($data['send_type']) === true && $data['send_type'] === 'note') ? 'note' : 'item';

            if ($sendType === 'note') {
                // Self-contained note/secret send (not bound to an item)
                $note = json_decode((string) ($payload_decrypted['string'] ?? ''), true);
                if (is_array($note) === false) {
                    $note = array();
                }
                $noteTitle = htmlspecialchars((string) ($note['title'] ?? ''), ENT_QUOTES, 'UTF-8');
                $noteSecret = htmlspecialchars((string) ($note['secret'] ?? ''), ENT_QUOTES, 'UTF-8');
                $noteText = htmlspecialchars((string) ($note['note'] ?? ''), ENT_QUOTES, 'UTF-8');
                $noteLogin = htmlspecialchars((string) ($note['login'] ?? ''), ENT_QUOTES, 'UTF-8');
                $noteUrlRaw = (string) ($note['url'] ?? '');
                $noteUrl = preg_match('#^https?://#i', $noteUrlRaw) === 1
                    ? htmlspecialchars($noteUrlRaw, ENT_QUOTES, 'UTF-8')
                    : '';

                $rows = '';
                if ($noteTitle !== '') {
                    $rows .= '<tr><th>' . $lang->get('label') . ':</th><td>' . $noteTitle . '</td></tr>';
                }
                if ($noteSecret !== '') {
                    $rows .= '<tr><th>' . $lang->get('password') . ':</th><td>' . $noteSecret . '</td></tr>';
                }
                if ($noteLogin !== '') {
                    $rows .= '<tr><th>' . $lang->get('login') . ':</th><td>' . $noteLogin . '</td></tr>';
                }
                if ($noteUrl !== '') {
                    $rows .= '<tr><th>' . $lang->get('url') . ':</th><td>' . $noteUrl . '</td></tr>';
                }
                if ($noteText !== '') {
                    $rows .= '<tr><th>' . $lang->get('description') . ':</th><td>' . nl2br($noteText) . '</td></tr>';
                }

                $html = '<div class="text-center">
                    <h3>' . $lang->get('secure_send') . '</h3>
                    <p class="font-weight-light mt-3">- ' . $lang->get('secure_send_recipient_intro') . ' -</p>
                    <div class="mt-5">
                    <table class="table text-left" style="margin: 0 auto;">' . $rows . '</table></div>
                    <p class="mt-3 text-info"><i class="fas fa-info mr-2"></i>' . $lang->get('secure_send_copy_carefully') . '<br>'
                    . str_replace(
                        ['#DATE#', '#VIEWS#'],
                        [
                            '<b>' . date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], intval($data['time_limit'])) . '</b>',
                            '<b>' . (intval($data['max_views']) - (intval($data['views']) + 1)) . '</b>',
                        ],
                        $lang->get('secure_send_visibility')
                    ) . '</p>
                    </div>';

                // log redemption (no item bound)
                logItems(
                    $SETTINGS,
                    0,
                    'secure-send-note',
                    (int) OTV_USER_ID,
                    'at_shown',
                    'otv'
                );

                // update views
                DB::update(
                    prefixTable('otv'),
                    ['views' => intval($data['views']) + 1],
                    'id = %i',
                    $data['id']
                );
            } else {
                // Item send: read display fields from the shared item
                $dataItem = DB::queryFirstRow(
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
                $dataDelete = DB::queryFirstRow(
                    'SELECT * FROM '.prefixTable('automatic_del').' WHERE item_id=%i',
                    $data['item_id']
                );
                if (DB::count() > 0 && isset($SETTINGS['enable_delete_after_consultation']) && intval($SETTINGS['enable_delete_after_consultation']) === 1) {
                    if (intval($dataDelete['del_enabled']) === 1) {
                        if (intval($dataDelete['del_type']) === 1 && intval($dataDelete['del_value']) >= 1) {
                            // decrease counter
                            DB::update(
                                prefixTable('automatic_del'),
                                [
                                    'del_value' => intval($dataDelete['del_value']) - 1,
                                ],
                                'item_id = %i',
                                $data['item_id']
                            );
                        } elseif ((intval($dataDelete['del_type']) === 1 && intval($dataDelete['del_value']) <= 1)
                            || (intval($dataDelete['del_type']) === 2 && intval($dataDelete['del_value']) < time())
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
                                $SETTINGS,
                                intval($data['item_id']),
                                $dataItem['label'],
                                (int) OTV_USER_ID,
                                'at_delete',
                                'otv',
                                'at_automatically_deleted'
                            );
                            echo '<div style="padding:10px; margin:90px 30px 30px 30px; text-align:center;" class="ui-widget-content ui-state-error ui-corner-all"><i class="fas fa-warning fa-2x"></i>&nbsp;'.
                            addslashes($lang->get('not_allowed_to_see_pw_is_expired')).'</div>';
                            return false;
                        }
                    }
                }

                // Item password (already decrypted above)
                $password_decrypted = $payload_decrypted;
                // get data
                $label = htmlspecialchars(strip_tags($dataItem['label']), ENT_QUOTES, 'UTF-8');
                $url = htmlspecialchars(strval($dataItem['url'] ?? ''), ENT_QUOTES, 'UTF-8');
                $description = preg_replace('/(?<!\\r)\\n+(?!\\r)/', '', strip_tags(strval($dataItem['description'] ?? ''), TP_ALLOWED_TAGS));
                $login = htmlspecialchars(strval($dataItem['login'] ?? ''), ENT_QUOTES, 'UTF-8');
                // display data
                $html = '<div class="text-center">
                    <h3>One-Time item view page</h3>
                    <p class="font-weight-light mt-3">- Here are the details of the Item that has been shared to you -</p>
                    <div class="mt-5">
                    <table class="table text-left" style="margin: 0 auto;">
                    <tr><th>Label:</th><td>'.$label.'</td></tr>
                    <tr><th>Password:</th><td>'.htmlspecialchars($password_decrypted['string'], ENT_QUOTES, 'UTF-8').'</td></tr>
                    <tr><th>Description:</th><td>'.$description.'</td></tr>
                    <tr><th>login:</th><td>'.$login.'</td></tr>
                    <tr><th>URL:</th><td>'.$url.'</td></tr>
                    </table></div>
                    <p class="mt-3 text-info"><i class="fas fa-info mr-2"></i>Copy carefully the data you need.<br>This page is visible until <b>'.
                    date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], intval($dataItem['time_limit'])).'</b> OR <b>'.(intval($dataItem['max_views']) - (intval($dataItem['views'])+1)).' more time(s)</b>.</div>
                    </div>';
                // log
                logItems(
                    $SETTINGS,
                    intval($data['item_id']),
                    $dataItem['label'],
                    (int) OTV_USER_ID,
                    'at_shown',
                    'otv'
                );

                // update views
                DB::update(
                    prefixTable('otv'),
                    [
                        'views' => intval($data['views']) + 1,
                    ],
                    'id = %i',
                    $data['id']
                );
            }

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
</html>
