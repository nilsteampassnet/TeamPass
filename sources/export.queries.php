<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 * @project   Teampass
 * @file      export.queries.php
 * ---
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2022 Teampass.net
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 * @see       https://www.teampass.net
 */


require_once 'SecureHandler.php';
session_name('teampass_session');
session_start();
if (
    isset($_SESSION['CPM']) === false
    || $_SESSION['CPM'] != 1
    || isset($_SESSION['user_id']) === false || empty($_SESSION['user_id'])
    || isset($_SESSION['key']) === false || empty($_SESSION['key'])
) {
    die('Hacking attempt...');
}


// reference the Dompdf namespace
//use Dompdf\Dompdf;

// Load config if $SETTINGS not defined
if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir'])) {
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } elseif (file_exists('../../includes/config/tp.config.php')) {
        include_once '../../includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
}

// Do checks
require_once $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'items', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'] . '/error.php';
    exit();
}

// No time limit
set_time_limit(0);

require_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
error_reporting(E_ERROR);
require_once $SETTINGS['cpassman_dir'] . '/sources/main.functions.php';
require_once $SETTINGS['cpassman_dir'] . '/sources/SplClassLoader.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}
DB::$host = DB_HOST;
DB::$user = DB_USER;
DB::$password = DB_PASSWD_CLEAR;
DB::$dbName = DB_NAME;
DB::$port = DB_PORT;
DB::$encoding = DB_ENCODING;

// Build tree
$tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'] . '/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre . 'nested_tree', 'id', 'parent_id', 'title');

// User's language loading
require_once $SETTINGS['cpassman_dir'] . '/includes/language/' . $_SESSION['user_language'] . '.php';

// Prepare POST variables
$id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_idTree = filter_input(INPUT_POST, 'idTree', FILTER_SANITIZE_NUMBER_INT);
$post_idsList = filter_input(INPUT_POST, 'idsList', FILTER_SANITIZE_STRING);
$post_file = filter_input(INPUT_POST, 'file', FILTER_SANITIZE_STRING);
$post_pdf_password = filter_input(INPUT_POST, 'pdf_password', FILTER_SANITIZE_STRING);
$post_number = filter_input(INPUT_POST, 'number', FILTER_SANITIZE_STRING);
$post_cpt = filter_input(INPUT_POST, 'cpt', FILTER_SANITIZE_STRING);
$post_file_link = filter_input(INPUT_POST, 'file_link', FILTER_SANITIZE_STRING);
$post_ids = filter_input(INPUT_POST, 'ids', FILTER_SANITIZE_STRING);

$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING);

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
            );

            $id_managed = '';
            $i = 1;
            $items_id_list = array();

            foreach (json_decode(html_entity_decode($post_ids)) as $id) {
                if (
                    in_array($id, $_SESSION['forbiden_pfs']) === false
                    && in_array($id, $_SESSION['groupes_visibles']) === true
                ) {
                    $rows = DB::query(
                        'SELECT i.id as id, i.id_tree as id_tree, i.restricted_to as restricted_to, i.perso as perso,
                            i.label as label, i.description as description, i.pw as pw, i.login as login, i.url as url,
                            i.email as email,l.date as date, i.pw_iv as pw_iv,n.renewal_period as renewal_period
                        FROM ' . prefixTable('items') . ' as i
                        INNER JOIN ' . prefixTable('nested_tree') . ' as n ON (i.id_tree = n.id)
                        INNER JOIN ' . prefixTable('log_items') . ' as l ON (i.id = l.id_item)
                        WHERE i.inactif = %i
                        AND i.id_tree= %i
                        AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s))
                        ORDER BY i.label ASC, l.date DESC',
                        '0',
                        intval($id),
                        'at_creation',
                        'at_modification',
                        'at_pw :%'
                    );
                    foreach ($rows as $record) {
                        $restricted_users_array = explode(';', $record['restricted_to']);
                        //exclude all results except the first one returned by query
                        if (empty($id_managed) === true || (int) $id_managed !== (int) $record['id']) {
                            if ((in_array($record['id_tree'], $_SESSION['personal_visible_groups']) === true)
                                || (in_array($record['id_tree'], $_SESSION['groupes_visibles']) === true
                                    && (empty($record['restricted_to']) === true
                                        || (in_array($_SESSION['user_id'], explode(';', $record['restricted_to'])) === true)))
                            ) {
                                // Run query
                                $dataItem = DB::queryfirstrow(
                                    'SELECT i.pw AS pw, s.share_key AS share_key
                                    FROM ' . prefixTable('items') . ' AS i
                                    INNER JOIN ' . prefixTable('sharekeys_items') . ' AS s ON (s.object_id = i.id)
                                    WHERE user_id = %i AND i.id = %i',
                                    $_SESSION['user_id'],
                                    $record['id']
                                );

                                // Uncrypt PW
                                if (DB::count() === 0) {
                                    // No share key found
                                    $pw = '';
                                } else {
                                    $pw = base64_decode(doDataDecryption(
                                        $dataItem['pw'],
                                        decryptUserObjectKey(
                                            $dataItem['share_key'],
                                            $_SESSION['user']['private_key']
                                        )
                                    ));
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

                                $full_listing[$i] = array(
                                    'id' => $record['id'],
                                    'label' => strip_tags(cleanString(html_entity_decode($record['label'], ENT_QUOTES | ENT_XHTML, 'UTF-8'), true)),
                                    'description' => htmlspecialchars_decode(addslashes(str_replace(array(';', '<br />'), array('|', "\n\r"), mysqli_escape_string($link, stripslashes(utf8_decode($record['description'])))))),
                                    'pw' => html_entity_decode($pw, ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                                    'login' => strip_tags(cleanString(html_entity_decode($record['login'], ENT_QUOTES | ENT_XHTML, 'UTF-8'), true)),
                                    'restricted_to' => isset($record['restricted_to']) ? $record['restricted_to'] : '',
                                    'perso' => $record['perso'] === '0' ? 'False' : 'True',
                                    'url' => $record['url'] !== 'none' ? htmlspecialchars_decode($record['url']) : '',
                                    'email' => $record['email'] !== 'none' ? htmlspecialchars_decode($record['email']) : '',
                                    'kbs' => implode(' | ', $arr_kbs),
                                    'tags' => implode(' ', $arr_tags),
                                );
                                ++$i;

                                // log
                                logItems(
                                    $SETTINGS,
                                    (int) $record['id'],
                                    $record['label'],
                                    $_SESSION['user_id'],
                                    'at_export',
                                    $_SESSION['login'],
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
                $value = array_map('utf8_decode', $value);
                $tmp .= array2csv($value);
            }

            echo '[{"content":"' . base64_encode($tmp) . '"}]';
            break;

            /*
         * PDF - step 1 - Prepare database
         */
        case 'initialize_export_table':
            DB::query('TRUNCATE TABLE ' . prefixTable('export'));
            break;

            /*
         * PDF - step 2 - Export the items inside database
         */
        case 'export_to_pdf_format':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                $post_data,
                'decode'
            );

            // Prepare variables
            $post_id = filter_var($dataReceived['id'], FILTER_SANITIZE_NUMBER_INT);
            $post_ids = filter_var_array($dataReceived['ids'], FILTER_SANITIZE_NUMBER_INT);

            if (
                in_array($post_id, $_SESSION['forbiden_pfs']) === false
                && in_array($post_id, $_SESSION['groupes_visibles']) === true
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
                        l.date as date, i.pw_iv as pw_iv,
                        n.renewal_period as renewal_period
                        FROM ' . prefixTable('items') . ' as i
                        INNER JOIN ' . prefixTable('nested_tree') . ' as n ON (i.id_tree = n.id)
                        INNER JOIN ' . prefixTable('log_items') . ' as l ON (i.id = l.id_item)
                        WHERE i.inactif = %i
                        AND i.id_tree= %i
                        AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s))
                        ORDER BY i.label ASC, l.date DESC',
                    '0',
                    $post_id,
                    'at_creation',
                    'at_modification',
                    'at_pw :%'
                );

                $id_managed = '';
                $items_id_list = array();
                foreach ($rows as $record) {
                    $restricted_users_array = explode(';', $record['restricted_to']);
                    //exclude all results except the first one returned by query
                    if (empty($id_managed) || $id_managed != $record['id']) {
                        if ((in_array($post_id, $_SESSION['personal_visible_groups']) && !($record['perso'] == 1 && $_SESSION['user_id'] == $record['restricted_to']) && !empty($record['restricted_to']))
                            || (!empty($record['restricted_to']) && !in_array($_SESSION['user_id'], $restricted_users_array))
                        ) {
                            //exclude this case
                        } else {
                            // Run query
                            $dataItem = DB::queryfirstrow(
                                'SELECT i.pw AS pw, s.share_key AS share_key
                                FROM ' . prefixTable('items') . ' AS i
                                INNER JOIN ' . prefixTable('sharekeys_items') . ' AS s ON (s.object_id = i.id)
                                WHERE user_id = %i AND i.id = %i',
                                $_SESSION['user_id'],
                                $record['id']
                            );

                            // Uncrypt PW
                            if (DB::count() === 0) {
                                // No share key found
                                $pw = '';
                            } else {
                                $pw = base64_decode(doDataDecryption(
                                    $dataItem['pw'],
                                    decryptUserObjectKey(
                                        $dataItem['share_key'],
                                        $_SESSION['user']['private_key']
                                    )
                                ));
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
                                    'id' => $record['id'],
                                    'description' => strip_tags(cleanString(html_entity_decode($record['description'], ENT_QUOTES | ENT_XHTML, 'UTF-8'), true)),
                                    'label' => cleanString(html_entity_decode($record['label'], ENT_QUOTES | ENT_XHTML, 'UTF-8'), true),
                                    'pw' => html_entity_decode($pw, ENT_QUOTES | ENT_XHTML, 'UTF-8'),
                                    'login' => strip_tags(cleanString(html_entity_decode($record['login'], ENT_QUOTES | ENT_XHTML, 'UTF-8'), true)),
                                    'path' => $path,
                                    'url' => strip_tags(cleanString(html_entity_decode($record['url'], ENT_QUOTES | ENT_XHTML, 'UTF-8'), true)),
                                    'email' => strip_tags(cleanString(html_entity_decode($record['email'], ENT_QUOTES | ENT_XHTML, 'UTF-8'), true)),
                                    'kbs' => $arr_kbs,
                                    'tags' => $arr_tags,
                                )
                            );

                            // log
                            logItems(
                                $SETTINGS,
                                (int) $record['id'],
                                $record['label'],
                                $_SESSION['user_id'],
                                'at_export',
                                $_SESSION['login'],
                                'pdf'
                            );
                        }
                    }
                    $id_managed = $record['id'];
                }
            }

            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                ),
                'encode'
            );
            break;

        

        case 'finalize_export_pdf':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                $post_data,
                'decode'
            );

            // Adapt header to pdf
            header('Content-type: application/pdf');

            // query
            $rows = DB::query('SELECT * FROM ' . prefixTable('export'));
            $counter = DB::count();
            if ($counter > 0) {
                // print
                //Some variables
                $prev_path = '';

                //Prepare the PDF file
                require_once($SETTINGS['cpassman_dir'] . '/includes/libraries/Pdf/tcpdf/config/tcpdf_config.php');
                include $SETTINGS['cpassman_dir'] . '/includes/libraries/Pdf/tcpdf/tcpdf.php';

                $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
                $pdf->SetProtection(array('print'), $dataReceived['pdf_password'], null);

                // set document information
                $pdf->SetCreator(PDF_CREATOR);
                $pdf->SetAuthor($_SESSION['lastname']." ".$_SESSION['name']);
                $pdf->SetTitle('Teampass export');

                // set default header data
                $pdf->SetHeaderData(
                    $SETTINGS['cpassman_dir'] . '/includes/images/teampass-logo2-home.png',
                    PDF_HEADER_LOGO_WIDTH,
                    'Teampass export',
                    $_SESSION['lastname']." ".$_SESSION['name'].' @ '.date($SETTINGS['date_format']." ".$SETTINGS['time_format'], (int) time())
                );

                // set header and footer fonts
                $pdf->setHeaderFont(Array('helvetica', '', PDF_FONT_SIZE_MAIN));
                $pdf->setFooterFont(Array('helvetica', '', PDF_FONT_SIZE_DATA));

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
                            <th>Description</th>
                            <th>Email</th>
                            <th>URL</th>
                        </tr>
                    </thead>
                    <tbody>';

                foreach ($rows as $record) {
                    // Manage path
                    if ($prev_path !== $record['path']) {
                        $html_table .= '
                            <tr>
                                <td colspan="6">'.$record['path'].'</td>
                            </tr>';
                    }
                    $prev_path = $record['path'];

                    // build
                    $html_table .= '
                    <tr>
                        <td>'.$record['label'].'</td>
                        <td>'.$record['login'].'</td>
                        <td>'.$record['pw'].'</td>
                        <td>'.$record['description'].'</td>
                        <td>'.$record['email'].'</td>
                        <td>'.$record['url'].'</td>
                    </tr>';
                }
                $html_table .= '
                    </tbody>
                </table>
                </body></html>';

                $pdf->writeHTML($html_table, true, false, false, false, '');

                //log
                logEvents($SETTINGS, 'pdf_export', '', (string) $_SESSION['user_id'], $_SESSION['login']);

                //clean table
                DB::query('TRUNCATE TABLE ' . prefixTable('export'));

                // Send back the file in Blob
                echo $pdf->Output(null, 'I');
            }
            break;

            //CASE export in HTML format
        case 'export_to_html_format':
            // step 1:
            // - prepare export file
            // - get full list of objects id to export
            include $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
            include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/GibberishAES/GibberishAES.php';
            $idsList = array();

            foreach (explode(';', $post_ids) as $id) {
                if (
                    in_array($id, $_SESSION['forbiden_pfs']) === false
                    && in_array($id, $_SESSION['groupes_visibles']) === true
                    && (in_array($id, $_SESSION['no_access_folders']) === false)
                ) {
                    // count elements to display
                    $result = DB::query(
                        'SELECT i.id AS id, i.label AS label, i.restricted_to AS restricted_to, i.perso AS perso
                    FROM ' . prefixTable('items') . ' as i
                    INNER JOIN ' . prefixTable('nested_tree') . ' as n ON (i.id_tree = n.id)
                    INNER JOIN ' . prefixTable('log_items') . ' as l ON (i.id = l.id_item)
                    WHERE i.inactif = %i
                    AND i.id_tree= %i
                    AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s))
                    ORDER BY i.label ASC, l.date DESC',
                        '0',
                        $id,
                        'at_creation',
                        'at_modification',
                        'at_pw :%'
                    );
                    foreach ($result as $record) {
                        $restricted_users_array = explode(';', $record['restricted_to']);
                        if (((in_array($id, $_SESSION['personal_visible_groups']) === true
                                && !($record['perso'] == 1 && $_SESSION['user_id'] == $record['restricted_to'])
                                && empty($record['restricted_to']) === false)
                                || (empty($record['restricted_to']) === false
                                    && in_array($_SESSION['user_id'], $restricted_users_array) === false)
                                || (in_array($id, $_SESSION['groupes_visibles']))) && (in_array($record['id'], $idsList) === false)
                        ) {
                            array_push($idsList, $record['id']);
                        }
                    }
                }
            }

            // prepare export file
            //save the file
            $html_file = '/teampass_export_' . time() . '_' . generateKey() . '.html';
            //print_r($full_listing);
            $outstream = fopen($SETTINGS['path_to_files_folder'] . $html_file, 'w');
            if ($outstream === false) {
                echo '[{"error":"true"}]';
                break;
            }
            fwrite(
                $outstream,
                '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
    <head>
    <meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
    <title>TeamPass Off-line mode</title>
    <style>
    body{font-family:sans-serif; font-size:11pt; background:#DCE0E8;}
    thead th{font-size:13px; font-weight:bold; background:#344151; padding:4px 10px 4px 10px; font-family:arial; color:#FFFFFF;}
    tr.line0 td {background-color:#FFFFFF; border-bottom:1px solid #CCCCCC; font-family:arial; font-size:11px;}
    tr.line1 td {background-color:#F0F0F0; border-bottom:1px solid #CCCCCC; font-family:arial; font-size:11px;}
    tr.path td {background-color:#C0C0C0; font-family:arial; font-size:11px; font-weight:bold;}
    #footer{width: 980px; height: 20px; line-height: 16px; margin: 10px auto 0 auto; padding: 10px; font-family: sans-serif; font-size: 10px; color:#000000;}
    #header{padding:10px; font-size:18px; background:#344151; color:#FFFFFF; border:2px solid #222E3D;}
    #itemsTable{width:100%;}
    #information{margin:10px 0 10px 0; background:#344151; color:#FFFFFF; border:2px solid #222E3D;}
    </style>
    </head>
    <body>
    <input type="hidden" id="generation_date" value="' . GibberishAES::enc(/** @scrutinizer ignore-type */ (string) time(), $post_pdf_password) . '" />
    <div id="header">
    ' . TP_TOOL_NAME . ' - Off Line mode
    </div>
    <div style="margin:10px; font-size:9px;">
    <i>This page was generated by <b>' . $_SESSION['name'] . ' ' . $_SESSION['lastname'] . '</b>, the ' . date('Y/m/d H:i:s') . '.</i>
    <span id="info_page" style="margin-left:20px; font-weight:bold; font-size: 14px; color:red;"></span>
    </div>
    <div id="information"></div>
    <div style="margin:10px;">
    Enter the decryption key : <input type="password" id="saltkey" onchange="uncryptTable()" />
    &nbsp;<button onclic="uncryptTable()">Refresh</button>
    </div>
    <div>
    <table id="itemsTable">
        <thead><tr>
            <th style="width:15%;">' . $LANG['label'] . '</th>
            <th style="width:10%;">' . $LANG['pw'] . '</th>
            <th style="width:30%;">' . $LANG['description'] . '</th>
            <th style="width:5%;">' . $LANG['user_login'] . '</th>
            <th style="width:20%;">' . $LANG['url'] . '</th>
        </tr></thead>
        <tbody id="itemsTable_tbody">'
            );

            fclose($outstream);

            // send back and continue
            //echo '[{"loop":"true", "number":"'.$objNumber.'", "file":"'.$SETTINGS['path_to_files_folder'].$html_file.'" , "file_link":"'.$SETTINGS['url_to_files_folder'].$html_file.'"}]';
            break;

            //CASE export in HTML format - Iteration loop
        case 'export_to_html_format_loop':
            // do checks ... if fails, return an error
            if (null === $post_idTree || null === $post_idsList) {
                echo '[{"error":"true"}]';
                break;
            }

            // exclude this folder if not allowed
            if (
                in_array($post_idTree, $_SESSION['forbiden_pfs']) === true
                || in_array($post_idTree, $_SESSION['groupes_visibles']) === false
                || (in_array($post_idTree, $_SESSION['no_access_folders']) === true)
            ) {
                echo '[{"loop":"true", "number":"' . $post_number . '", "cpt":"' . $post_cpt . '", "file":"' . $post_file . '", "idsList":"' . $post_idsList . '" , "file_link":"' . $post_file_link . '"}]';
                break;
            }

            $full_listing = array();
            $items_id_list = array();
            include $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
            include_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/GibberishAES/GibberishAES.php';

            $rows = DB::query(
                'SELECT i.id as id, i.url as url, i.perso as perso, i.label as label, i.description as description, i.pw as pw, i.login as login, i.id_tree as id_tree,
                l.date as date, i.pw_iv as pw_iv,
                n.renewal_period as renewal_period
            FROM ' . prefixTable('items') . ' as i
            INNER JOIN ' . prefixTable('nested_tree') . ' as n ON (i.id_tree = n.id)
            INNER JOIN ' . prefixTable('log_items') . ' as l ON (i.id = l.id_item)
            WHERE i.inactif = %i
            AND i.id_tree= %i
            AND (l.action = %s OR (l.action = %s AND l.raison LIKE %s))
            ORDER BY i.label ASC, l.date DESC',
                '0',
                $post_idTree,
                'at_creation',
                'at_modification',
                'at_pw :%'
            );

            foreach ($rows as $record) {
                //exclude all results except the first one returned by query
                if (empty($id_managed) || $id_managed != $record['id']) {
                    // decrypt PW
                    if (empty($post_salt_key) === false && null !== $post_salt_key) {
                        $pw = cryption(
                            $record['pw'],
                            mysqli_escape_string($link, stripslashes($post_salt_key)),
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
                    array_push(
                        $full_listing,
                        array(
                            'id_tree' => $record['id_tree'],
                            'id' => $record['id'],
                            'label' => $record['label'],
                            'description' => addslashes(str_replace(array(';', '<br />'), array('|', "\n\r"), mysqli_escape_string($link, stripslashes(utf8_decode($record['description']))))),
                            'pw' => $pw['string'],
                            'login' => $record['login'],
                            'url' => $record['url'],
                            'perso' => $record['perso'],
                        )
                    );
                    array_push($items_id_list, $record['id']);

                    // log
                    /*logItems(
                        $record['id'],
                        $record['l SeekableIteratorabel'],
                        $_SESSION['user_id'],
                        'at_export',
                        $_SESSION['login'],
                        'html'
                    );*/
                }
                $id_managed = $record['id'];
            }

            //save in export file
            $outstream = fopen($post_file . '.txt', 'a');
            if ($outstream === false) {
                echo '[{"error":"true"}]';
                break;
            }

            $lineType = 'line1';
            $idTree = '';
            foreach ($full_listing as $elem) {
                if ($lineType == 'line0') {
                    $lineType = 'line1';
                } else {
                    $lineType = 'line0';
                }
                if (empty($elem['description'])) {
                    $desc = '&nbsp;';
                } else {
                    $desc = addslashes($elem['description']);
                }
                if (empty($elem['login'])) {
                    $login = '&nbsp;';
                } else {
                    $login = addslashes($elem['login']);
                }
                if (empty($elem['url'])) {
                    $url = '&nbsp;';
                } else {
                    $url = addslashes($elem['url']);
                }

                // Prepare tree
                if ($idTree != $elem['id_tree']) {
                    $arbo = $tree->getPath($elem['id_tree'], true);
                    foreach ($arbo as $folder) {
                        $arboHtml_tmp = htmlspecialchars(stripslashes($folder->title), ENT_QUOTES);
                        if (empty($arboHtml)) {
                            $arboHtml = $arboHtml_tmp;
                        } else {
                            $arboHtml .= ' » ' . $arboHtml_tmp;
                        }
                    }
                    fputs(
                        $outstream,
                        '
        <tr class="path"><td colspan="5">' . $arboHtml . '</td></tr>'
                    );
                    $idTree = $elem['id_tree'];
                }

                $encPw = GibberishAES::enc($elem['pw'], $post_pdf_password);
                fputs(
                    $outstream,
                    '
        <tr class="' . $lineType . '">
            <td>' . addslashes($elem['label']) . '</td>
            <td align="center"><span class="span_pw" id="span_' . $elem['id'] . '"><a href="#" onclick="decryptme(' . $elem['id'] . ', \'' . $encPw . '\');return false;">Decrypt </a></span><input type="hidden" id="hide_' . $elem['id'] . '" value="' . $encPw . '" /></td>
            <td>' . $desc . '</td>
            <td align="center">' . $login . '</td>
            <td align="center">' . $url . '</td>
            </tr>'
                );
            }

            fclose($outstream);

            // send back and continue
            echo '[{"loop":"true", "number":"' . $post_number . '", "cpt":"' . $post_cpt . '", "file":"' . $post_file . '", "idsList":"' . $post_idsList . '" , "file_link":"' . $post_file_link . '"}]';
            break;

            //CASE export in HTML format - Iteration loop
        case 'export_to_html_format_finalize':
            // Load includes
            include $SETTINGS['cpassman_dir'] . '/includes/config/include.php';
            require_once $SETTINGS['cpassman_dir'] . '/includes/libraries/Encryption/GibberishAES/GibberishAES.php';

            // read the content of the temporary file
            $handle = fopen($post_file . '.txt', 'r');
            if ($handle === false) {
                echo '[{"error":"true"}]';
                break;
            }
            $contents = fread($handle, filesize($post_file . '.txt'));
            if ($contents === false) {
                echo '[{"error":"true"}]';
                break;
            }
            fclose($handle);
            if (is_file($post_file . '.txt')) {
                unlink($post_file . '.txt');
            }

            // Encrypt its content
            //$contents = GibberishAES::enc($contents, $post_pdf_password);
            $encrypted_text = '';
            $chunks = explode('|#|#|', chunk_split($contents, 10000, '|#|#|'));
            foreach ($chunks as $chunk) {
                if (empty($encrypted_text) === true) {
                    $encrypted_text = GibberishAES::enc(/** @scrutinizer ignore-type */ $chunk, $post_pdf_password);
                } else {
                    $encrypted_text .= '|#|#|' . GibberishAES::enc(/** @scrutinizer ignore-type */ $chunk, $post_pdf_password);
                }
            }

            // open file
            $outstream = fopen($post_file, 'a');
            if ($outstream === false) {
                echo '[{"error":"true"}]';
                break;
            }

            fputs(
                $outstream,
                '</tbody>
        </table></div>
        <input type="button" value="Hide all" onclick="hideAll()" />
        <div id="footer" style="text-align:center;">
            <a href="https://teampass.net/about/" target="_blank" style="">' . TP_TOOL_NAME . '&nbsp;' . TP_VERSION_FULL . '&nbsp;' . TP_COPYRIGHT . '</a>
        </div>
        <div id="enc_html" style="display:none;">' . $encrypted_text . '</div>
        </body>
    </html>
    <script type="text/javascript">
        function uncryptTable()
        {
            // uncrypt file generation date
            try {
                var file_date = decryptedTable = GibberishAES.dec(
                    document.getElementById("generation_date").value,
                    document.getElementById("saltkey").value
                );
            }
            catch(e) {
                console.info("Key not correct");
                document.getElementById("itemsTable_tbody").innerHTML = "";
                document.getElementById("itemsTable").style.display  = "none";
                document.getElementById("info_page").innerHTML = "ERROR - " + e;
                return false;
            }

            // Check date
            if (~~(Date.now()/ 1000) - parseInt(file_date) < 604800) {
                console.info("File is valid");
                document.getElementById("info_page").innerHTML = "";

                // Uncrypt the table
                try {
                    var encodedString = document.getElementById("enc_html").innerHTML;
                    var splitedString = encodedString.split("|#|#|");
                    var decryptedTable = "";
                    var i;
                    for (i = 0; i < splitedString.length; i++) {
                        decryptedTable += GibberishAES.dec(
                            splitedString[i],
                            document.getElementById("saltkey").value
                        );
                    }
                }
                catch(e) {
                    console.info("Key not correct");
                    document.getElementById("itemsTable_tbody").innerHTML = "";
                    document.getElementById("itemsTable").style.display  = "none";
                    document.getElementById("info_page").innerHTML = "ERROR - " + e;
                    return false;
                }

                document.getElementById("itemsTable_tbody").innerHTML = decryptedTable;
                document.getElementById("itemsTable").style.display  = "block";
            } else {
                document.getElementById("info_page").innerHTML = "This file is too old. It cannot be shown anymore!";
                console.info("File is NOT valid any more!");
                document.getElementById("itemsTable").style.display  = "none";
            }
        }
        function decryptme(id, string)
        {
            if (document.getElementById("saltkey").value != "") {
                var decryptedPw;

                try {
                    decryptedPw = GibberishAES.dec(string, document.getElementById("saltkey").value)
                }
                catch(e) {
                    alert (e);
                    return decryptedPw;
                }

                document.getElementById("span_"+id).innerHTML = "<input type=\"text\" value=\"" +  decryptedPw + "\" id=\"pass_input_" + id + "\">" +
                    "&nbsp;<a href=\"#\" onclick=\"encryptme("+id+");return false;\"><span style=\"font-size:7px;\">[Hide]</span></a>";
                document.getElementById("pass_input_"+id).select();
            } else {
                alert("Decryption Key is empty!");
            }
        }
        function encryptme(id)
        {
            document.getElementById("span_"+id).innerHTML = "<a href=\"#\" onclick=\"decryptme("+id+", \'"+document.getElementById("hide_"+id).value+"\')\">Decrypt</a>";
        }
        function hideAll()
        {
            var elements = document.getElementsByClassName("span_pw");
            for (var i=0, im=elements.length; im>i; i++) {
                var dataPw = elements[i].id.split("_");
                elements[i].innerHTML = "<a href=\"#\" onclick=\"decryptme("+dataPw[1]+", \'"+document.getElementById("hide_"+dataPw[1]).value+"\')\">Decrypt</a>";
            }
        }
    		function prepareString(string) {
    			try {
    				 result = decodeURIComponent(string);
    			}
    			catch (e) {
    				 result =  unescape(string);
    			}
    			return result;
    		}
        (function(e,r){"object"==typeof exports?module.exports=r():"function"==typeof define&&define.amd?define(r):e.GibberishAES=r()})(this,function(){"use strict";var e=14,r=8,n=!1,f=function(e){try{return unescape(encodeURIComponent(e))}catch(r){throw"Error on UTF-8 encode"}},c=function(e){try{return prepareString(escape(e))}catch(r){throw"Bad Key"}},t=function(e){var r,n,f=[];for(16>e.length&&(r=16-e.length,f=[r,r,r,r,r,r,r,r,r,r,r,r,r,r,r,r]),n=0;e.length>n;n++)f[n]=e[n];return f},a=function(e,r){var n,f,c="";if(r){if(n=e[15],n>16)throw"Decryption error: Maybe bad key";if(16===n)return"";for(f=0;16-n>f;f++)c+=String.fromCharCode(e[f])}else for(f=0;16>f;f++)c+=String.fromCharCode(e[f]);return c},o=function(e){var r,n="";for(r=0;e.length>r;r++)n+=(16>e[r]?"0":"")+e[r].toString(16);return n},d=function(e){var r=[];return e.replace(/(..)/g,function(e){r.push(parseInt(e,16))}),r},u=function(e,r){var n,c=[];for(r||(e=f(e)),n=0;e.length>n;n++)c[n]=e.charCodeAt(n);return c},i=function(n){switch(n){case 128:e=10,r=4;break;case 192:e=12,r=6;break;case 256:e=14,r=8;break;default:throw"Invalid Key Size Specified:"+n}},b=function(e){var r,n=[];for(r=0;e>r;r++)n=n.concat(Math.floor(256*Math.random()));return n},h=function(n,f){var c,t=e>=12?3:2,a=[],o=[],d=[],u=[],i=n.concat(f);for(d[0]=L(i),u=d[0],c=1;t>c;c++)d[c]=L(d[c-1].concat(i)),u=u.concat(d[c]);return a=u.slice(0,4*r),o=u.slice(4*r,4*r+16),{key:a,iv:o}},l=function(e,r,n){r=S(r);var f,c=Math.ceil(e.length/16),a=[],o=[];for(f=0;c>f;f++)a[f]=t(e.slice(16*f,16*f+16));for(0===e.length%16&&(a.push([16,16,16,16,16,16,16,16,16,16,16,16,16,16,16,16]),c++),f=0;a.length>f;f++)a[f]=0===f?x(a[f],n):x(a[f],o[f-1]),o[f]=s(a[f],r);return o},v=function(e,r,n,f){r=S(r);var t,o=e.length/16,d=[],u=[],i="";for(t=0;o>t;t++)d.push(e.slice(16*t,16*(t+1)));for(t=d.length-1;t>=0;t--)u[t]=p(d[t],r),u[t]=0===t?x(u[t],n):x(u[t],d[t-1]);for(t=0;o-1>t;t++)i+=a(u[t]);return i+=a(u[t],!0),f?i:c(i)},s=function(r,f){n=!1;var c,t=M(r,f,0);for(c=1;e+1>c;c++)t=g(t),t=y(t),e>c&&(t=k(t)),t=M(t,f,c);return t},p=function(r,f){n=!0;var c,t=M(r,f,e);for(c=e-1;c>-1;c--)t=y(t),t=g(t),t=M(t,f,c),c>0&&(t=k(t));return t},g=function(e){var r,f=n?D:B,c=[];for(r=0;16>r;r++)c[r]=f[e[r]];return c},y=function(e){var r,f=[],c=n?[0,13,10,7,4,1,14,11,8,5,2,15,12,9,6,3]:[0,5,10,15,4,9,14,3,8,13,2,7,12,1,6,11];for(r=0;16>r;r++)f[r]=e[c[r]];return f},k=function(e){var r,f=[];if(n)for(r=0;4>r;r++)f[4*r]=F[e[4*r]]^R[e[1+4*r]]^j[e[2+4*r]]^z[e[3+4*r]],f[1+4*r]=z[e[4*r]]^F[e[1+4*r]]^R[e[2+4*r]]^j[e[3+4*r]],f[2+4*r]=j[e[4*r]]^z[e[1+4*r]]^F[e[2+4*r]]^R[e[3+4*r]],f[3+4*r]=R[e[4*r]]^j[e[1+4*r]]^z[e[2+4*r]]^F[e[3+4*r]];else for(r=0;4>r;r++)f[4*r]=E[e[4*r]]^U[e[1+4*r]]^e[2+4*r]^e[3+4*r],f[1+4*r]=e[4*r]^E[e[1+4*r]]^U[e[2+4*r]]^e[3+4*r],f[2+4*r]=e[4*r]^e[1+4*r]^E[e[2+4*r]]^U[e[3+4*r]],f[3+4*r]=U[e[4*r]]^e[1+4*r]^e[2+4*r]^E[e[3+4*r]];return f},M=function(e,r,n){var f,c=[];for(f=0;16>f;f++)c[f]=e[f]^r[n][f];return c},x=function(e,r){var n,f=[];for(n=0;16>n;n++)f[n]=e[n]^r[n];return f},S=function(n){var f,c,t,a,o=[],d=[],u=[];for(f=0;r>f;f++)c=[n[4*f],n[4*f+1],n[4*f+2],n[4*f+3]],o[f]=c;for(f=r;4*(e+1)>f;f++){for(o[f]=[],t=0;4>t;t++)d[t]=o[f-1][t];for(0===f%r?(d=m(w(d)),d[0]^=K[f/r-1]):r>6&&4===f%r&&(d=m(d)),t=0;4>t;t++)o[f][t]=o[f-r][t]^d[t]}for(f=0;e+1>f;f++)for(u[f]=[],a=0;4>a;a++)u[f].push(o[4*f+a][0],o[4*f+a][1],o[4*f+a][2],o[4*f+a][3]);return u},m=function(e){for(var r=0;4>r;r++)e[r]=B[e[r]];return e},w=function(e){var r,n=e[0];for(r=0;4>r;r++)e[r]=e[r+1];return e[3]=n,e},A=function(e,r){var n,f=[];for(n=0;e.length>n;n+=r)f[n/r]=parseInt(e.substr(n,r),16);return f},C=function(e){var r,n=[];for(r=0;e.length>r;r++)n[e[r]]=r;return n},I=function(e,r){var n,f;for(f=0,n=0;8>n;n++)f=1===(1&r)?f^e:f,e=e>127?283^e<<1:e<<1,r>>>=1;return f},O=function(e){var r,n=[];for(r=0;256>r;r++)n[r]=I(e,r);return n},B=A("637c777bf26b6fc53001672bfed7ab76ca82c97dfa5947f0add4a2af9ca472c0b7fd9326363ff7cc34a5e5f171d8311504c723c31896059a071280e2eb27b27509832c1a1b6e5aa0523bd6b329e32f8453d100ed20fcb15b6acbbe394a4c58cfd0efaafb434d338545f9027f503c9fa851a3408f929d38f5bcb6da2110fff3d2cd0c13ec5f974417c4a77e3d645d197360814fdc222a908846eeb814de5e0bdbe0323a0a4906245cc2d3ac629195e479e7c8376d8dd54ea96c56f4ea657aae08ba78252e1ca6b4c6e8dd741f4bbd8b8a703eb5664803f60e613557b986c11d9ee1f8981169d98e949b1e87e9ce5528df8ca1890dbfe6426841992d0fb054bb16",2),D=C(B),K=A("01020408102040801b366cd8ab4d9a2f5ebc63c697356ad4b37dfaefc591",2),E=O(2),U=O(3),z=O(9),R=O(11),j=O(13),F=O(14),G=function(e,r,n){var f,c=b(8),t=h(u(r,n),c),a=t.key,o=t.iv,d=[[83,97,108,116,101,100,95,95].concat(c)];return e=u(e,n),f=l(e,a,o),f=d.concat(f),T.encode(f)},H=function(e,r,n){var f=T.decode(e),c=f.slice(8,16),t=h(u(r,n),c),a=t.key,o=t.iv;return f=f.slice(16,f.length),e=v(f,a,o,n)},L=function(e){function r(e,r){return e<<r|e>>>32-r}function n(e,r){var n,f,c,t,a;return c=2147483648&e,t=2147483648&r,n=1073741824&e,f=1073741824&r,a=(1073741823&e)+(1073741823&r),n&f?2147483648^a^c^t:n|f?1073741824&a?3221225472^a^c^t:1073741824^a^c^t:a^c^t}function f(e,r,n){return e&r|~e&n}function c(e,r,n){return e&n|r&~n}function t(e,r,n){return e^r^n}function a(e,r,n){return r^(e|~n)}function o(e,c,t,a,o,d,u){return e=n(e,n(n(f(c,t,a),o),u)),n(r(e,d),c)}function d(e,f,t,a,o,d,u){return e=n(e,n(n(c(f,t,a),o),u)),n(r(e,d),f)}function u(e,f,c,a,o,d,u){return e=n(e,n(n(t(f,c,a),o),u)),n(r(e,d),f)}function i(e,f,c,t,o,d,u){return e=n(e,n(n(a(f,c,t),o),u)),n(r(e,d),f)}function b(e){for(var r,n=e.length,f=n+8,c=(f-f%64)/64,t=16*(c+1),a=[],o=0,d=0;n>d;)r=(d-d%4)/4,o=8*(d%4),a[r]=a[r]|e[d]<<o,d++;return r=(d-d%4)/4,o=8*(d%4),a[r]=a[r]|128<<o,a[t-2]=n<<3,a[t-1]=n>>>29,a}function h(e){var r,n,f=[];for(n=0;3>=n;n++)r=255&e>>>8*n,f=f.concat(r);return f}var l,v,s,p,g,y,k,M,x,S=[],m=A("67452301efcdab8998badcfe10325476d76aa478e8c7b756242070dbc1bdceeef57c0faf4787c62aa8304613fd469501698098d88b44f7afffff5bb1895cd7be6b901122fd987193a679438e49b40821f61e2562c040b340265e5a51e9b6c7aad62f105d02441453d8a1e681e7d3fbc821e1cde6c33707d6f4d50d87455a14eda9e3e905fcefa3f8676f02d98d2a4c8afffa39428771f6816d9d6122fde5380ca4beea444bdecfa9f6bb4b60bebfbc70289b7ec6eaa127fad4ef308504881d05d9d4d039e6db99e51fa27cf8c4ac5665f4292244432aff97ab9423a7fc93a039655b59c38f0ccc92ffeff47d85845dd16fa87e4ffe2ce6e0a30143144e0811a1f7537e82bd3af2352ad7d2bbeb86d391",8);for(S=b(e),y=m[0],k=m[1],M=m[2],x=m[3],l=0;S.length>l;l+=16)v=y,s=k,p=M,g=x,y=o(y,k,M,x,S[l+0],7,m[4]),x=o(x,y,k,M,S[l+1],12,m[5]),M=o(M,x,y,k,S[l+2],17,m[6]),k=o(k,M,x,y,S[l+3],22,m[7]),y=o(y,k,M,x,S[l+4],7,m[8]),x=o(x,y,k,M,S[l+5],12,m[9]),M=o(M,x,y,k,S[l+6],17,m[10]),k=o(k,M,x,y,S[l+7],22,m[11]),y=o(y,k,M,x,S[l+8],7,m[12]),x=o(x,y,k,M,S[l+9],12,m[13]),M=o(M,x,y,k,S[l+10],17,m[14]),k=o(k,M,x,y,S[l+11],22,m[15]),y=o(y,k,M,x,S[l+12],7,m[16]),x=o(x,y,k,M,S[l+13],12,m[17]),M=o(M,x,y,k,S[l+14],17,m[18]),k=o(k,M,x,y,S[l+15],22,m[19]),y=d(y,k,M,x,S[l+1],5,m[20]),x=d(x,y,k,M,S[l+6],9,m[21]),M=d(M,x,y,k,S[l+11],14,m[22]),k=d(k,M,x,y,S[l+0],20,m[23]),y=d(y,k,M,x,S[l+5],5,m[24]),x=d(x,y,k,M,S[l+10],9,m[25]),M=d(M,x,y,k,S[l+15],14,m[26]),k=d(k,M,x,y,S[l+4],20,m[27]),y=d(y,k,M,x,S[l+9],5,m[28]),x=d(x,y,k,M,S[l+14],9,m[29]),M=d(M,x,y,k,S[l+3],14,m[30]),k=d(k,M,x,y,S[l+8],20,m[31]),y=d(y,k,M,x,S[l+13],5,m[32]),x=d(x,y,k,M,S[l+2],9,m[33]),M=d(M,x,y,k,S[l+7],14,m[34]),k=d(k,M,x,y,S[l+12],20,m[35]),y=u(y,k,M,x,S[l+5],4,m[36]),x=u(x,y,k,M,S[l+8],11,m[37]),M=u(M,x,y,k,S[l+11],16,m[38]),k=u(k,M,x,y,S[l+14],23,m[39]),y=u(y,k,M,x,S[l+1],4,m[40]),x=u(x,y,k,M,S[l+4],11,m[41]),M=u(M,x,y,k,S[l+7],16,m[42]),k=u(k,M,x,y,S[l+10],23,m[43]),y=u(y,k,M,x,S[l+13],4,m[44]),x=u(x,y,k,M,S[l+0],11,m[45]),M=u(M,x,y,k,S[l+3],16,m[46]),k=u(k,M,x,y,S[l+6],23,m[47]),y=u(y,k,M,x,S[l+9],4,m[48]),x=u(x,y,k,M,S[l+12],11,m[49]),M=u(M,x,y,k,S[l+15],16,m[50]),k=u(k,M,x,y,S[l+2],23,m[51]),y=i(y,k,M,x,S[l+0],6,m[52]),x=i(x,y,k,M,S[l+7],10,m[53]),M=i(M,x,y,k,S[l+14],15,m[54]),k=i(k,M,x,y,S[l+5],21,m[55]),y=i(y,k,M,x,S[l+12],6,m[56]),x=i(x,y,k,M,S[l+3],10,m[57]),M=i(M,x,y,k,S[l+10],15,m[58]),k=i(k,M,x,y,S[l+1],21,m[59]),y=i(y,k,M,x,S[l+8],6,m[60]),x=i(x,y,k,M,S[l+15],10,m[61]),M=i(M,x,y,k,S[l+6],15,m[62]),k=i(k,M,x,y,S[l+13],21,m[63]),y=i(y,k,M,x,S[l+4],6,m[64]),x=i(x,y,k,M,S[l+11],10,m[65]),M=i(M,x,y,k,S[l+2],15,m[66]),k=i(k,M,x,y,S[l+9],21,m[67]),y=n(y,v),k=n(k,s),M=n(M,p),x=n(x,g);return h(y).concat(h(k),h(M),h(x))},T=function(){var e="ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",r=e.split(""),n=function(e){var n,f,c=[],t="";for(Math.floor(16*e.length/3),n=0;16*e.length>n;n++)c.push(e[Math.floor(n/16)][n%16]);for(n=0;c.length>n;n+=3)t+=r[c[n]>>2],t+=r[(3&c[n])<<4|c[n+1]>>4],t+=void 0!==c[n+1]?r[(15&c[n+1])<<2|c[n+2]>>6]:"=",t+=void 0!==c[n+2]?r[63&c[n+2]]:"=";for(f=t.slice(0,64)+"\n",n=1;Math.ceil(t.length/64)>n;n++)f+=t.slice(64*n,64*n+64)+(Math.ceil(t.length/64)===n+1?"":"\n");return f},f=function(r){r=r.replace(/\n/g,"");var n,f=[],c=[],t=[];for(n=0;r.length>n;n+=4)c[0]=e.indexOf(r.charAt(n)),c[1]=e.indexOf(r.charAt(n+1)),c[2]=e.indexOf(r.charAt(n+2)),c[3]=e.indexOf(r.charAt(n+3)),t[0]=c[0]<<2|c[1]>>4,t[1]=(15&c[1])<<4|c[2]>>2,t[2]=(3&c[2])<<6|c[3],f.push(t[0],t[1],t[2]);return f=f.slice(0,f.length-f.length%16)};return"function"==typeof Array.indexOf&&(e=r),{encode:n,decode:f}}();return{size:i,h2a:d,expandKey:S,encryptBlock:s,decryptBlock:p,Decrypt:n,s2a:u,rawEncrypt:l,rawDecrypt:v,dec:H,openSSLKey:h,a2h:o,enc:G,Hash:{MD5:L},Base64:T}});

        $("a").click(function(event){
            event.preventDefault();
        });
    </script>'
            );

            fclose($outstream);

            echo '[{"text":"<a href=\'' .
                $post_file_link .
                '\' target=\'_blank\'>' . $LANG['pdf_download'] . '</a>"}]';
            break;
    }
}

//SPECIFIC FUNCTIONS FOR FPDF

/**
 * Should we incloude  apage break in pdf.
 *
 * @param int $height Height of cell to add
 */
function checkPageBreak($height)
{
    global $pdf;
    //Continue on a new page if needed
    if ($pdf->GetY() + $height > $pdf->PageBreakTrigger) {
        $pdf->addPage($pdf->CurOrientation);
    }
}


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
function array2csv($fields, $delimiter = ';', $enclosure = '"', $escape_char = '\\')
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
