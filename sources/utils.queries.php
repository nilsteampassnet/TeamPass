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
 * @file      utils.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use EZimuel\PHPSecureSession;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;
use TeampassClasses\NestedTree\NestedTree;

// Load functions
require_once 'main.functions.php';

// init
loadClasses('DB');
$session = SessionManager::getSession();
$request = SymfonyRequest::createFromGlobals();
$lang = new Language($session->get('user-language') ?? 'english');

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

// Do checks
// Instantiate the class with posted data
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
if (
    $checkUserAccess->userAccessPage('items') === false ||
    $checkUserAccess->checkSession() === false
) {
    // Not allowed page
    $session->set('system-error_code', ERR_NOT_ALLOWED);
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// --------------------------------- //
// Load tree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_freq = filter_input(INPUT_POST, 'freq', FILTER_SANITIZE_NUMBER_INT);
$post_ids = filter_input(INPUT_POST, 'ids', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_salt_key = filter_input(INPUT_POST, 'salt_key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

// Construction de la requ?te en fonction du type de valeur
if (null !== $post_type) {
    switch ($post_type) {
        //CASE export in CSV format
        case 'export_to_csv_format':
            // Init
            $full_listing = array();
            $full_listing[0] = array(
                'id' => 'id',
                'label' => 'label',
                'description' => 'description',
                'pw' => 'pw',
                'login' => 'login',
                'restricted_to' => 'restricted_to',
                'perso' => 'perso',
            );

            foreach (explode(';', $post_ids) as $id) {
                if (in_array($id, $session->get('user-forbiden_personal_folders')) === false && in_array($id, $session->get('user-accessible_folders')) === true) {
                    $rows = DB::query(
                        'SELECT i.id as id, i.restricted_to as restricted_to, i.perso as perso,
                        i.label as label, i.description as description, i.pw as pw, i.login as login, i.pw_iv as pw_iv
                        l.date as date,
                        n.renewal_period as renewal_period
                        FROM '.prefixTable('items').' as i
                        INNER JOIN '.prefixTable('nested_tree').' as n ON (i.id_tree = n.id)
                        INNER JOIN '.prefixTable('log_items').' as l ON (i.id = l.id_item)
                        WHERE i.inactif = %i
                        AND i.id_tree= %i
                        AND (l.action = %s OR (l.action = %s AND l.raison LIKE %ss))
                        ORDER BY i.label ASC, l.date DESC',
                        0,
                        $id,
                        'at_creation',
                        'at_modification',
                        'at_pw :'
                    );

                    $id_managed = '';
                    $i = 1;
                    foreach ($rows as $record) {
                        $restricted_users_array = explode(';', $record['restricted_to']);
                        //exclude all results except the first one returned by query
                        if (empty($id_managed) || $id_managed != $record['id']) {
                            if ((in_array($id, $session->get('user-personal_visible_folders'))
                                && !($record['perso'] === '1'
                                    && $session->get('user-id') === $record['restricted_to'])
                                && !empty($record['restricted_to']))
                                ||
                                (!empty($record['restricted_to'])
                                    && !in_array($session->get('user-id'), $restricted_users_array)
                                )
                            ) {
                                //exclude this case
                            } else {
                                //encrypt PW
                                if (empty($post_salt_key) === false && null !== $post_salt_key) {
                                    $pw = cryption(
                                        $record['pw'],
                                        $post_salt_key,
                                        'decrypt',
                                        $SETTINGS
                                    );
                                } else {
                                    $pw = cryption(
                                        $record['pw'],
                                        '',
                                        'decrypt',
                                        $SETTINGS
                                    );
                                }

                                $full_listing[$i] = array(
                                    'id' => $record['id'],
                                    'label' => $record['label'],
                                    'description' => htmlentities(str_replace(';', '.', $record['description']), ENT_QUOTES, 'UTF-8'),
                                    'pw' => substr(addslashes($pw['string']), strlen($record['rand_key'])),
                                    'login' => $record['login'],
                                    'restricted_to' => $record['restricted_to'],
                                    'perso' => $record['perso'],
                                );
                            }
                            ++$i;
                        }
                        $id_managed = $record['id'];
                    }
                }
                //save the file
                $handle = fopen($settings['bck_script_path'].'/'.$settings['bck_script_filename'].'-'.time().'.sql', 'w+');
                if ($handle !== false) {
                    foreach ($full_listing as $line) {
                        $return = $line['id'].';'.$line['label'].';'.$line['description'].';'.$line['pw'].';'.$line['login'].';'.$line['restricted_to'].';'.$line['perso'].'/n';
                        fwrite($handle, $return);
                    }
                    fclose($handle);
                }
            }
            break;

        case '    $database,
    (int) $port
);_frequency':
            if ($post_key !== $session->get('key')
                || null === filter_input(INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
                || null === filter_input(INPUT_POST, 'freq', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            ) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // store new frequency
            DB::update(
                prefixTable('items'),
                [
                    'auto_update_pwd_frequency' => $post_freq,
                    'auto_update_pwd_next_date' => time() + (2592000 * $post_freq),
                ],
                'id = %i',
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS)
            );

            echo '[{"error" : ""}]';

            break;
    }
}
