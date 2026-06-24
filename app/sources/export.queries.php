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
 * @file      export.queries.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */


use voku\helper\AntiXSS;
use TeampassClasses\NestedTree\NestedTree;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\PerformChecks\PerformChecks;
use TeampassClasses\ConfigManager\ConfigManager;


// Load functions
require_once 'main.functions.php';
$session = SessionManager::getSession();
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
    include TEAMPASS_ROOT . '/public/error.php';
    exit;
}

// Define Timezone
date_default_timezone_set($SETTINGS['timezone'] ?? 'UTC');

// Set header properties
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
error_reporting(E_ERROR);
set_time_limit(0);

// --------------------------------- //

// Prepare nestedTree
$tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Load AntiXSS
$antiXss = new AntiXSS();

// User's language loading
require_once TEAMPASS_APP . '/includes/language/' . $session->get('user-language') . '.php';

// Prepare POST variables
$id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_idTree = filter_input(INPUT_POST, 'idTree', FILTER_SANITIZE_NUMBER_INT);
$post_idsList = filter_input(INPUT_POST, 'idsList', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_file = filter_input(INPUT_POST, 'file', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
// IMPORTANT: Passwords (even PDF passwords) should NOT be sanitized (fix 3.1.5.10)
$post_pdf_password = filter_input(INPUT_POST, 'pdf_password', FILTER_UNSAFE_RAW);
$post_number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_cpt = filter_input(INPUT_POST, 'cpt', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_file_link = filter_input(INPUT_POST, 'file_link', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_ids = filter_input(INPUT_POST, 'ids', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

//Manage type of action asked
if (null !== $post_type) {
    switch ($post_type) {
            //CASE export in CSV format
        case 'export_to_csv_format':
            //Init
            $full_listing = array();
            $full_listing[0] = array(
                'id' => 'id',
                'label' => 'label',
                'description' => 'description',
                'pw' => 'pw',
                'login' => 'login',
                'restricted_to' => 'restricted_to',
                'perso' => 'perso',
                'url' => 'url',
                'email' => 'email',
                'kbs' => 'kb',
                'tags' => 'tag',
                'folder' => 'folder',
            );

            $id_managed = '';
            $i = 1;
            $items_id_list = array();

            foreach (json_decode(html_entity_decode($post_ids)) as $id) {
                if (
                    in_array($id, $session->get('user-forbiden_personal_folders')) === false
                    && in_array($id, $session->get('user-accessible_folders')) === true
                ) {
                    $rows = DB::query(
                        'SELECT i.id as id, i.id_tree as id_tree, i.restricted_to as restricted_to, i.perso as perso,
                            i.label as label, i.description as description, i.pw as pw, i.login as login, i.url as url,
                            i.email as email, IFNULL(l.date, 0) as date, i.pw_iv as pw_iv, n.renewal_period as renewal_period
                        FROM ' . prefixTable('items') . ' as i
                        INNER JOIN ' . prefixTable('nested_tree') . ' as n ON (i.id_tree = n.id)
                        LEFT JOIN ' . prefixTable('log_items') . ' as l
                            ON (i.id = l.id_item AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s)))
                        WHERE i.inactif = %i
                        AND i.id_tree= %i
                        ORDER BY i.label ASC, l.date DESC',
                        'at_creation',
                        'at_modification',
                        'at_pw :%',
                        '0',
                        intval($id)
                    );
                    foreach ($rows as $record) {
                        $restricted_users_array = empty($record['restricted_to']) === false ? explode(';', $record['restricted_to']) : [];
                        //exclude all results except the first one returned by query
                        if (empty($id_managed) === true || (int) $id_managed !== (int) $record['id']) {
                            if ((in_array($record['id_tree'], $session->get('user-personal_visible_folders')) === true)
                                || (in_array($record['id_tree'], $session->get('user-accessible_folders')) === true
                                    && (empty($record['restricted_to']) === true
                                        || (in_array($session->get('user-id'), explode(';', $record['restricted_to'])) === true)))
                            ) {
                                // Run query
                                $dataItem = DB::queryFirstRow(
                                    'SELECT i.pw AS pw, i.pw_len AS pw_len, s.share_key AS share_key
                                    FROM ' . prefixTable('items') . ' AS i
                                    INNER JOIN ' . prefixTable('sharekeys_items') . ' AS s ON (s.object_id = i.id)
                                    WHERE user_id = %i AND i.id = %i',
                                    $session->get('user-id'),
                                    $record['id']
                                );

                                // Uncrypt PW
                                if (DB::count() === 0 || empty($dataItem['pw']) === true) {
                                    // No share key found OR item has no password
                                    $pw = '';
                                } else {
                                    $pw = teampassDecryptPasswordValue(
                                        $dataItem['pw'],
                                        decryptUserObjectKey(
                                            $dataItem['share_key'],
                                            $session->get('user-private_key')
                                        ),
                                        (int) ($dataItem['pw_len'] ?? 0)
                                    );
                                }

                                // get KBs
                                $arr_kbs = [];
                                $rows_kb = DB::query(
                                    'SELECT b.label, b.id
                                    FROM ' . prefixTable('kb_items') . ' AS a
                                    INNER JOIN ' . prefixTable('kb') . ' AS b ON (a.kb_id = b.id)
                                    WHERE a.item_id = %i',
                                    $record['id']
                                );
                                foreach ($rows_kb as $rec_kb) {
                                    array_push($arr_kbs, $rec_kb['label']);
                                }

                                // get TAGS
                                $arr_tags = [];
                                $rows_tag = DB::query(
                                    'SELECT tag
                                    FROM ' . prefixTable('tags') . '
                                    WHERE item_id = %i',
                                    $record['id']
                                );
                                foreach ($rows_tag as $rec_tag) {
                                    array_push($arr_tags, $rec_tag['tag']);
                                }

                                // get FOLDERS tree
                                $arr_trees = [];
                                $rows_child_tree = DB::query(
                                    'SELECT t.id, t.title
                                    FROM ' . prefixTable('nested_tree') . ' AS t
                                    INNER JOIN ' . prefixTable('items') . ' AS i ON (t.id = i.id_tree)
                                    WHERE i.id = %i',
                                    $record['id']
                                );
                                foreach ($rows_child_tree as $rec_child_tree) {
                                    $stack = array();
                                    $parent = $rec_child_tree['id'];
                                    while($parent != 0){
                                        $rows_parent_tree = DB::query(
                                            'SELECT parent_id, title
                                            FROM ' . prefixTable('nested_tree') . '
                                            WHERE id = %i',
                                            $parent
                                        );
                                        foreach ($rows_parent_tree as $rec_parent_tree) {
                                            $parent = $rec_parent_tree['parent_id'];
                                            array_push($arr_trees, $rec_parent_tree['title']);
                                        }
                                    }
                                    
                                    $arr_trees = array_reverse($arr_trees);
                                }

                                $full_listing[$i] = array(
                                    'id' => $record['id'],
                                    'label' => empty($record['label']) === true ? '' : html_entity_decode($record['label'], ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                                    'description' => empty($record['description']) === true ? '' : html_entity_decode($record['description'], ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                                    'pw' => empty($pw) === true ? '' : html_entity_decode($pw, ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                                    'login' => empty($record['login']) === true ? '' : html_entity_decode($record['login'], ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                                    'restricted_to' => isset($record['restricted_to']) === true ? $record['restricted_to'] : '',
                                    'perso' => $record['perso'] === '0' ? 'False' : 'True',
                                    'url' => $record['url'] !== 'none' && is_null($record['url']) === false && empty($record['url']) === false ? htmlspecialchars_decode($record['url']) : '',
                                    'email' => $record['email'] !== 'none' ? (is_null($record['email']) === false ? html_entity_decode($record['email'], ENT_QUOTES | ENT_XHTML, 'UTF-8') : '') : '',
                                    'kbs' => implode(' | ', $arr_kbs),
                                    'tags' => implode(' ', $arr_tags),
                                    'folder' => implode('/', $arr_trees),
                                );
                                ++$i;

                                // log
                                logItems(
                                    $SETTINGS,
                                    (int) $record['id'],
                                    $record['label'],
                                    $session->get('user-id'),
                                    'at_export',
                                    $session->get('user-login'),
                                    'csv'
                                );
                            }
                        }
                        $id_managed = $record['id'];
                    }
                }
            }

            // Loop on Results, decode to UTF8 and write in CSV file
            $tmp = '';
            foreach ($full_listing as $value) {
                $tmp .= array2csv($value);
            }
            
            // deepcode ignore XSS: Data is encrypted before being sent to the client
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'csv_content' => $tmp,
                ),
                'encode'
            );
            break;

            /*
         * PDF - step 1 - Prepare database
         */
        case 'clean_export_table':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_export_tag = filter_var($dataReceived['export_tag'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if (empty($post_export_tag) === false) {
                DB::query('DELETE FROM ' . prefixTable('export') . ' WHERE export_tag = %s', $post_export_tag);
            }
            break;

            /*
         * PDF - step 2 - Export the items inside database
         */
        case 'export_prepare_data':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);
            $post_ids = filter_var_array($dataReceived['ids'], FILTER_SANITIZE_NUMBER_INT);
            $post_export_tag = filter_var($dataReceived['export_tag'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            if (
                in_array($post_id, $session->get('user-forbiden_personal_folders')) === false
                && in_array($post_id, $session->get('user-accessible_folders')) === true
            ) {
                // get path
                $tree->rebuild();
                $folders = $tree->getPath($post_id, true);
                $path = array();
                foreach ($folders as $val) {
                    array_push($path, $val->title);
                }
                $path = implode(' » ', $path);

                // send query
                $rows = DB::query(
                    'SELECT i.id as id, i.restricted_to as restricted_to, i.perso as perso, i.label as label, i.description as description, i.pw as pw, i.login as login, i.url as url, i.email as email,
                        IFNULL(l.date, 0) as date, i.pw_iv as pw_iv,
                        n.renewal_period as renewal_period,
                        i.id_tree as tree_id
                        FROM ' . prefixTable('items') . ' as i
                        INNER JOIN ' . prefixTable('nested_tree') . ' as n ON (i.id_tree = n.id)
                        LEFT JOIN ' . prefixTable('log_items') . ' as l
                            ON (i.id = l.id_item AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s)))
                        WHERE i.inactif = %i
                        AND i.id_tree= %i
                        ORDER BY i.label ASC, l.date DESC',
                    'at_creation',
                    'at_modification',
                    'at_pw :%',
                    '0',
                    $post_id
                );

                $id_managed = '';
                $items_id_list = array();
                foreach ($rows as $record) {
                    $restricted_users_array = empty($record['restricted_to']) === false ? explode(';', $record['restricted_to']) : [];
                    //exclude all results except the first one returned by query
                    if (empty($id_managed) || $id_managed != $record['id']) {
                        if ((in_array($post_id, $session->get('user-personal_visible_folders')) && !($record['perso'] == 1 && $session->get('user-id') == $record['restricted_to']) && !empty($record['restricted_to']))
                            || (!empty($record['restricted_to']) && !in_array($session->get('user-id'), $restricted_users_array))
                        ) {
                            //exclude this case
                        } else {
                            // Run query
                            $dataItem = DB::queryFirstRow(
                                'SELECT i.pw AS pw, i.pw_len AS pw_len, s.share_key AS share_key
                                FROM ' . prefixTable('items') . ' AS i
                                INNER JOIN ' . prefixTable('sharekeys_items') . ' AS s ON (s.object_id = i.id)
                                WHERE user_id = %i AND i.id = %i',
                                $session->get('user-id'),
                                $record['id']
                            );

                            // Uncrypt PW
                            if (DB::count() === 0 || empty($dataItem['pw']) === true) {
                                // No share key found OR password is empty
                                $pw = '';
                            } else {
                                $pw = teampassDecryptPasswordValue(
                                    $dataItem['pw'],
                                    decryptUserObjectKey(
                                        $dataItem['share_key'],
                                        $session->get('user-private_key')
                                    ),
                                    (int) ($dataItem['pw_len'] ?? 0)
                                );
                            }

                            // get KBs
                            $arr_kbs = '';
                            $rows_kb = DB::query(
                                'SELECT b.label, b.id
                                FROM ' . prefixTable('kb_items') . ' AS a
                                INNER JOIN ' . prefixTable('kb') . ' AS b ON (a.kb_id = b.id)
                                WHERE a.item_id = %i',
                                $record['id']
                            );
                            foreach ($rows_kb as $rec_kb) {
                                if (empty($arr_kbs)) {
                                    $arr_kbs = $rec_kb['label'];
                                } else {
                                    $arr_kbs .= ' | ' . $rec_kb['label'];
                                }
                            }

                            // get TAGS
                            $arr_tags = '';
                            $rows_tag = DB::query(
                                'SELECT tag
                                FROM ' . prefixTable('tags') . '
                                WHERE item_id = %i',
                                $record['id']
                            );
                            foreach ($rows_tag as $rec_tag) {
                                if (empty($arr_tags)) {
                                    $arr_tags = $rec_tag['tag'];
                                } else {
                                    $arr_tags .= ' ' . $rec_tag['tag'];
                                }
                            }

                            // store
                            DB::insert(
                                prefixTable('export'),
                                array(
                                    'export_tag' => $post_export_tag,
                                    'item_id' => $record['id'],
                                    'description' => cleanStringForExport((string) $record['description']),
                                    'label' => cleanStringForExport((string) $record['label']),
                                    'pw' => cleanStringForExport(html_entity_decode($pw, ENT_QUOTES | ENT_XHTML, 'UTF-8'), true),
                                    'login' => cleanStringForExport((string) $record['login']),
                                    'path' => $path,
                                    'url' => cleanStringForExport((string) $record['url']),
                                    'email' => cleanStringForExport((string) $record['email']),
                                    'kbs' => $arr_kbs,
                                    'tags' => $arr_tags,
                                    'folder_id' => $record['tree_id'],
                                    'perso' => $record['perso'],
                                    'restricted_to' => $record['restricted_to'],
                                )
                            );

                            // log
                            logItems(
                                $SETTINGS,
                                (int) $record['id'],
                                $record['label'],
                                $session->get('user-id'),
                                'at_export',
                                $session->get('user-login'),
                                'pdf'
                            );
                        }
                    }
                    $id_managed = $record['id'];
                }
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    //'message' => 'Loop on folder id finished',
                    'exportTag' => $post_export_tag,
                ),
                'encode'
            );
            break;

        

        case 'finalize_export_pdf':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                'decode'
            );

            // Adapt header to pdf
            //header('Content-type: application/pdf');

            // query
            $rows = DB::query(
                'SELECT * 
                FROM ' . prefixTable('export') . ' 
                WHERE export_tag = %s',
                $dataReceived['export_tag']
            );
            $counter = DB::count();
            if ($counter > 0) {
                define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
                // print
                //Some variables
                $prev_path = '';

                //Prepare the PDF file
                require_once TEAMPASS_APP . '/vendor/tecnickcom/tcpdf/tcpdf.php';

                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $pdf->SetProtection(array('print'), $dataReceived['pdf_password'], null);

                // set document information
                $pdf->SetCreator(PDF_CREATOR);
                $pdf->SetAuthor($session->get('user-lastname')." ".$session->get('user-name'));
                $pdf->SetTitle('Teampass export');

                // set default header data
                $pdf->SetHeaderData(
                    TEAMPASS_ROOT . '/public/assets/images/teampass-logo2-home.png',
                    PDF_HEADER_LOGO_WIDTH,
                    'Teampass export',
                    $session->get('user-lastname')." ".$session->get('user-name').' @ '.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], (int) time())
                );

                // set header and footer fonts
                $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
                $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

                // set default monospaced font
                $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

                // set margins
                $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
                $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
                $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

                // set auto page breaks
                $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
                
                // set image scale factor
                $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

                $pdf->getAliasNbPages();
                $pdf->addPage('L');

                $prev_path = '';
                $html_table = '
                <html><head>
                <style>
                    table {
                        border-collapse: collapse;
                        margin: 25px 0;
                        /*!font-size: 0.9em;
                        font-family: sans-serif;
                        box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);*/
                    }
                    thead tr {
                        background-color: #009879;
                        color: #ffffff;
                        text-align: left;
                    }
                    th,
                    td {
                        padding: 12px 15px;
                    }
                    tbody tr {
                        border-bottom: 1px solid #dddddd;
                    }
                    tbody tr:nth-of-type(even) {
                        background-color: #f3f3f3;
                    }
                    tbody tr:last-of-type {
                        border-bottom: 2px solid #009879;
                    }
                </style>
                </head>
                <body>
                <table>
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Login</th>
                            <th>Password</th>
                            <th>URL</th>
                            <th>Description</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>';

                foreach ($rows as $record) {
                    // Manage path
                    if ($prev_path !== $record['path']) {
                        $html_table .= '
                            <tr>
                                <td colspan="6">'.htmlspecialchars($record['path']).'</td>
                            </tr>';
                    }
                    $prev_path = $record['path'];

                    // build
                    $html_table .= '
                    <tr>
                        <td>'.htmlspecialchars($record['label']).'</td>
                        <td>'.htmlspecialchars($record['login']).'</td>
                        <td>'.htmlspecialchars($record['pw']).'</td>
                        <td>'.htmlspecialchars($record['url']).'</td>
                        <td>'.htmlspecialchars($record['description']).'</td>
                        <td>'.htmlspecialchars($record['email']).'</td>
                    </tr>';
                }
                $html_table .= '
                    </tbody>
                </table>
                </body></html>';

                // set default font subsetting mode
                $pdf->setFontSubsetting(true);

                // set font
                $pdf->SetFont('freeserif', '', 12);

                $pdf->writeHTML($html_table, true, false, false, false, '');

                //log
                logEvents($SETTINGS, 'pdf_export', '', (string) $session->get('user-id'), $session->get('user-login'));

                //clean table
                DB::query('TRUNCATE TABLE ' . prefixTable('export'));

                // Clean any content of the output buffer
                ob_end_clean();

                // prepare output
                echo $pdf->Output($dataReceived['pdf_filename'], 'I');
            }
            break;


            //CASE export in HTML format (offline mode - standalone encrypted file)
        case 'export_to_html_format':
            // Check KEY
            if ($post_key !== $session->get('key')) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => $lang->get('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // Make sure offline mode is enabled
            if (isset($SETTINGS['settings_offline_mode']) === false || (int) $SETTINGS['settings_offline_mode'] !== 1) {
                echo prepareExchangedData(
                    array('error' => true, 'message' => $lang->get('error_not_allowed_to')),
                    'encode'
                );
                break;
            }

            // Decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // IMPORTANT: the export password must NOT be sanitized (fix 3.1.5.10)
            $offlinePassword = (string) ($dataReceived['password'] ?? '');
            $offlineFolders = array_map('intval', (array) ($dataReceived['ids'] ?? []));
            if ($offlinePassword === '' || count($offlineFolders) === 0) {
                echo prepareExchangedData(
                    array('error' => true, 'message' => $lang->get('error_empty_data')),
                    'encode'
                );
                break;
            }

            $userPrivateKey = (string) $session->get('user-private_key');
            $userPublicKey = (string) $session->get('user-public_key');
            $userRoles = (string) $session->get('user-roles');

            // Build the list of exportable items (same access rules as the CSV export)
            $offlineItems = array();
            foreach ($offlineFolders as $folderId) {
                if (
                    in_array($folderId, $session->get('user-forbiden_personal_folders')) === true
                    || in_array($folderId, $session->get('user-accessible_folders')) === false
                ) {
                    continue;
                }

                $rows = DB::query(
                    'SELECT i.id AS id, i.id_tree AS id_tree, i.restricted_to AS restricted_to,
                        i.label AS label, i.description AS description, i.login AS login, i.url AS url,
                        i.email AS email
                    FROM ' . prefixTable('items') . ' AS i
                    WHERE i.inactif = %i AND i.id_tree = %i
                    ORDER BY i.label ASC',
                    0,
                    $folderId
                );
                foreach ($rows as $record) {
                    // Item-level restriction check
                    if (
                        !(
                            in_array($record['id_tree'], $session->get('user-personal_visible_folders')) === true
                            || (
                                in_array($record['id_tree'], $session->get('user-accessible_folders')) === true
                                && (
                                    empty($record['restricted_to']) === true
                                    || in_array($session->get('user-id'), explode(';', $record['restricted_to'])) === true
                                )
                            )
                        )
                    ) {
                        continue;
                    }

                    // Decrypt the password through the user's sharekey (migration-aware)
                    $dataItem = DB::queryFirstRow(
                        'SELECT i.pw AS pw, i.pw_len AS pw_len, s.share_key AS share_key, s.increment_id AS sharekey_id
                        FROM ' . prefixTable('items') . ' AS i
                        INNER JOIN ' . prefixTable('sharekeys_items') . ' AS s ON (s.object_id = i.id)
                        WHERE s.user_id = %i AND i.id = %i',
                        $session->get('user-id'),
                        $record['id']
                    );
                    if (DB::count() === 0 || empty($dataItem['pw']) === true) {
                        $pw = '';
                    } else {
                        $pw = teampassDecryptPasswordValue(
                            $dataItem['pw'],
                            decryptUserObjectKeyWithMigration(
                                $dataItem['share_key'],
                                $userPrivateKey,
                                $userPublicKey,
                                (int) $dataItem['sharekey_id'],
                                'sharekeys_items'
                            ),
                            (int) ($dataItem['pw_len'] ?? 0)
                        );
                    }

                    // Tags
                    $arrTags = array();
                    foreach (DB::query('SELECT tag FROM ' . prefixTable('tags') . ' WHERE item_id = %i', $record['id']) as $recTag) {
                        if (empty($recTag['tag']) === false) {
                            $arrTags[] = $recTag['tag'];
                        }
                    }

                    // Folder path
                    $arrPath = array();
                    foreach ($tree->getPath($record['id_tree'], true) as $folder) {
                        $arrPath[] = $folder->title;
                    }

                    // Custom fields (decrypted, role-visibility enforced)
                    $offlineFields = getOfflineItemCustomFields(
                        (int) $record['id'],
                        (int) $record['id_tree'],
                        (int) $session->get('user-id'),
                        $userPrivateKey,
                        $userPublicKey,
                        $userRoles
                    );

                    $offlineItems[] = array(
                        'folder' => implode(' / ', $arrPath),
                        'label' => empty($record['label']) === true ? '' : html_entity_decode((string) $record['label'], ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                        'description' => empty($record['description']) === true ? '' : html_entity_decode(strip_tags((string) $record['description']), ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                        'login' => empty($record['login']) === true ? '' : html_entity_decode((string) $record['login'], ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                        'password' => empty($pw) === true ? '' : html_entity_decode((string) $pw, ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                        'url' => (empty($record['url']) === true || $record['url'] === 'none') ? '' : htmlspecialchars_decode((string) $record['url']),
                        'email' => (empty($record['email']) === true || $record['email'] === 'none') ? '' : html_entity_decode((string) $record['email'], ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                        'tags' => implode(' ', $arrTags),
                        'fields' => $offlineFields,
                    );

                    // Log the export
                    logItems(
                        $SETTINGS,
                        (int) $record['id'],
                        (string) $record['label'],
                        (int) $session->get('user-id'),
                        'at_export',
                        $session->get('user-login'),
                        'html'
                    );
                }
            }

            // Encrypt the dataset with the export password and build the standalone file
            $offlineBundle = aesGcmEncryptForOffline(
                (string) json_encode($offlineItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $offlinePassword
            );
            $offlineHtml = buildOfflineExportHtml($offlineBundle, $lang, $session);

            // base64-encode the HTML so the client-side transport purifier (DOMPurify in
            // prepareExchangedData/purifyData) cannot strip its tags; the client decodes it back.
            // deepcode ignore XSS: content is encrypted before being sent to the client
            echo prepareExchangedData(
                array(
                    'error' => false,
                    'html_content' => base64_encode($offlineHtml),
                ),
                'encode'
            );
            break;
    }
}

//SPECIFIC FUNCTIONS FOR FPDF

/**
 * Converts an array to CSV format.
 *
 * @param array  $fields      Array of fields
 * @param string $delimiter   What delimiter is used in CSV
 * @param string $enclosure   What enclosure is used in CSV
 * @param string $escape_char What escape character is used in CSV
 *
 * @return string
 */
function array2csv($fields, $delimiter = ',', $enclosure = '"', $escape_char = '\\')
{
    $buffer = fopen('php://temp', 'r+');
    if ($buffer === false) {
        return "";
    }
    fputcsv($buffer, $fields, $delimiter, $enclosure, $escape_char);
    rewind($buffer);
    $csv = fgets($buffer);
    fclose($buffer);

    return $csv;
}


/**
 * Derive a key from the export password and encrypt the JSON payload with AES-256-GCM.
 *
 * The returned structure is consumed by the standalone viewer through the WebCrypto API:
 * PBKDF2-SHA256 derives the key, AES-256-GCM provides authenticated encryption. The ciphertext
 * and the 16-byte GCM tag are concatenated (WebCrypto layout) before being base64-encoded.
 *
 * @param string $json       Plain JSON payload to protect.
 * @param string $password   User-provided export password.
 * @param int    $iterations PBKDF2 iteration count.
 *
 * @return array{v:int, kdf:string, it:int, salt:string, iv:string, data:string}
 */
function aesGcmEncryptForOffline(string $json, string $password, int $iterations = 250000): array
{
    $salt = random_bytes(16);
    $iv = random_bytes(12);
    $key = hash_pbkdf2('sha256', $password, $salt, $iterations, 32, true);
    $tag = '';
    $cipher = openssl_encrypt($json, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    if ($cipher === false) {
        throw new RuntimeException('Offline export encryption failed');
    }

    return [
        'v' => 1,
        'kdf' => 'PBKDF2-SHA256',
        'it' => $iterations,
        'salt' => base64_encode($salt),
        'iv' => base64_encode($iv),
        'data' => base64_encode($cipher . $tag),
    ];
}


/**
 * Build the standalone, password-protected HTML export from the encrypted bundle.
 *
 * Loads the viewer template and injects the encrypted bundle, the localized UI strings and the
 * generation metadata. Each dynamic value is escaped for its target context (HTML text vs JSON).
 *
 * @param array  $bundle  Encrypted payload from aesGcmEncryptForOffline().
 * @param object $lang     Language helper.
 * @param object $session  Current user session.
 *
 * @return string
 */
function buildOfflineExportHtml(array $bundle, $lang, $session): string
{
    $template = (string) file_get_contents(TEAMPASS_APP . '/includes/templates/offline-export.tpl.php');

    // UI strings consumed by the viewer (rendered client-side via textContent)
    $i18n = [
        'enter_password' => $lang->get('offline_enter_password'),
        'unlock' => $lang->get('offline_unlock'),
        'decrypting' => $lang->get('offline_decrypting'),
        'wrong_password' => $lang->get('offline_wrong_password'),
        'password_empty' => $lang->get('password_cannot_be_empty'),
        'search' => $lang->get('offline_search'),
        'reveal' => $lang->get('offline_reveal'),
        'hide' => $lang->get('offline_hide'),
        'copy' => $lang->get('copy'),
        'copied' => $lang->get('copied'),
        'details' => $lang->get('details'),
        'hide_all' => $lang->get('offline_hide_all'),
        'lock_duration' => $lang->get('offline_lock_duration'),
        'extend' => $lang->get('offline_extend'),
        'locks_in' => $lang->get('offline_locks_in'),
        'no_webcrypto' => $lang->get('offline_no_webcrypto'),
        'items' => $lang->get('items'),
        'col_folder' => $lang->get('folder'),
        'col_label' => $lang->get('label'),
        'col_login' => $lang->get('user_login'),
        'col_password' => $lang->get('password'),
        'col_url' => $lang->get('url'),
        'col_email' => $lang->get('email'),
        'col_description' => $lang->get('description'),
        'col_tags' => $lang->get('tags'),
    ];
    // Decode HTML entities so the strings render correctly as plain text in the viewer
    $i18n = array_map(
        static function ($value) {
            return html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        },
        $i18n
    );

    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;

    $toolName = defined('TP_TOOL_NAME') ? TP_TOOL_NAME : 'TeamPass';
    $version = defined('TP_VERSION') ? TP_VERSION : '';
    $heading = (string) $lang->get('offline_heading');
    $generated = sprintf(
        (string) $lang->get('offline_generated_by'),
        trim((string) $session->get('user-name') . ' ' . (string) $session->get('user-lastname')),
        date('Y-m-d H:i')
    );

    $replacements = [
        '{{TITLE}}' => htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'),
        '{{HEADING}}' => htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'),
        '{{GENERATED_INFO}}' => htmlspecialchars($generated, ENT_QUOTES, 'UTF-8'),
        '{{BRANDING}}' => htmlspecialchars(trim($toolName . ' ' . $version), ENT_QUOTES, 'UTF-8'),
        '{{BUNDLE_JSON}}' => (string) json_encode($bundle, $jsonFlags),
        '{{I18N_JSON}}' => (string) json_encode($i18n, $jsonFlags),
    ];

    return strtr($template, $replacements);
}


/**
 * Read and decrypt the custom fields of an item for the offline export.
 *
 * Mirrors ItemModel::getItemCustomFields(): only fields whose category is associated to the
 * item's folder are returned, fields restricted to roles the user does not hold are skipped, and
 * encrypted values are decrypted with migration-aware sharekey handling.
 *
 * @param int    $itemId         Item ID.
 * @param int    $folderId       Folder the item belongs to.
 * @param int    $userId         Requesting user ID.
 * @param string $userPrivateKey User private key (already decrypted).
 * @param string $userPublicKey  User public key.
 * @param string $userRoles      Requesting user roles (';'-separated).
 *
 * @return array<int, array{title:string, value:string, masked:int}>
 */
function getOfflineItemCustomFields(int $itemId, int $folderId, int $userId, string $userPrivateKey, string $userPublicKey, string $userRoles = ''): array
{
    $catRows = DB::query(
        'SELECT id_category FROM ' . prefixTable('categories_folders') . ' WHERE id_folder = %i',
        $folderId
    );
    if (DB::count() === 0) {
        return [];
    }
    $arrCatList = array_map('intval', array_column($catRows, 'id_category'));

    $rows = DB::query(
        'SELECT i.id AS object_id, i.field_id AS field_id, i.data AS data,
            i.encryption_type AS encryption_type, c.encrypted_data AS encrypted_data,
            c.title AS title, c.masked AS masked, c.role_visibility AS role_visibility
        FROM ' . prefixTable('categories_items') . ' AS i
        INNER JOIN ' . prefixTable('categories') . ' AS c ON (i.field_id = c.id)
        WHERE i.item_id = %i AND c.parent_id IN %li',
        $itemId,
        $arrCatList
    );

    $fields = [];
    foreach ($rows as $row) {
        // Skip fields restricted to roles the user does not hold ('all' = everyone)
        if (
            $row['role_visibility'] !== 'all'
            && count(array_intersect(explode(';', $userRoles), explode(',', (string) $row['role_visibility']))) === 0
        ) {
            continue;
        }

        $value = '';
        $isEncrypted = (int) $row['encrypted_data'] === 1 && $row['encryption_type'] !== 'not_set';
        if ($isEncrypted === true) {
            $userKey = DB::queryFirstRow(
                'SELECT share_key, increment_id
                FROM ' . prefixTable('sharekeys_fields') . '
                WHERE user_id = %i AND object_id = %i',
                $userId,
                $row['object_id']
            );
            if (DB::count() > 0) {
                try {
                    $value = (string) base64_decode(
                        (string) doDataDecryption(
                            $row['data'],
                            decryptUserObjectKeyWithMigration(
                                $userKey['share_key'],
                                $userPrivateKey,
                                $userPublicKey,
                                (int) $userKey['increment_id'],
                                'sharekeys_fields'
                            )
                        )
                    );
                } catch (Exception $e) {
                    $value = '';
                }
            }
        } else {
            $value = (string) $row['data'];
        }

        if ($value === '') {
            continue;
        }

        $fields[] = [
            'title' => (string) $row['title'],
            'value' => html_entity_decode($value, ENT_QUOTES | ENT_XHTML, 'UTF-8'),
            'masked' => (int) $row['masked'],
        ];
    }

    return $fields;
}
