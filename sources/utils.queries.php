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
 * @file      utils.queries.php
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
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] === false || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    include_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    include_once './includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// Do checks
require_once $SETTINGS['cpassman_dir'].'/includes/config/include.php';
require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'items', $SETTINGS) === false) {
    // Not allowed page
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED;
    include $SETTINGS['cpassman_dir'].'/error.php';
    exit();
}

/*
 * Define Timezone
**/
if (isset($SETTINGS['timezone']) === true) {
    date_default_timezone_set($SETTINGS['timezone']);
} else {
    date_default_timezone_set('UTC');
}

require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
require_once 'main.functions.php';

//Connect to DB
include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, $SETTINGS));
}

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_freq = filter_input(INPUT_POST, 'freq', FILTER_SANITIZE_NUMBER_INT);
$post_ids = filter_input(INPUT_POST, 'ids', FILTER_SANITIZE_STRING);
$post_salt_key = filter_input(INPUT_POST, 'salt_key', FILTER_SANITIZE_STRING);
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
                if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {
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
                            if ((in_array($id, $_SESSION['personal_visible_groups'])
                                && !($record['perso'] === '1'
                                    && $_SESSION['user_id'] === $record['restricted_to'])
                                && !empty($record['restricted_to']))
                                ||
                                (!empty($record['restricted_to'])
                                    && !in_array($_SESSION['user_id'], $restricted_users_array)
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

        //CASE start user personal pwd re-encryption
        case 'reencrypt_personal_pwd_start':
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
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

            // check if psk is set
            if (isset($_SESSION['user']['encrypted_psk']) === false
                || empty($_SESSION['user']['encrypted_psk']) === true
            ) {
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => langHdl('error_personal_saltkey_is_not_set'),
                    ),
                    'encode'
                );
                break;
            }

            $currentID = '';
            $pws_list = array();
            $rows = DB::query(
                'SELECT i.id AS id
                FROM  '.prefixTable('nested_tree').' AS n
                LEFT JOIN '.prefixTable('items').' AS i ON i.id_tree = n.id
                WHERE i.perso = %i AND n.title = %i',
                '1',
                $post_user_id
            );
            foreach ($rows as $record) {
                if (empty($currentID)) {
                    $currentID = $record['id'];
                } else {
                    array_push($pws_list, $record['id']);
                }
            }

            echo '[{"error" : "" , "pws_list" : "'.implode(',', $pws_list).'" , "currentId" : "'.$currentID.'" , "nb" : "'.count($pws_list).'"}]';
            break;

        //CASE auto update server password
        case 'server_auto_update_password':
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

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                $post_data,
                'decode'
            );

            // get data about item
            $dataItem = DB::queryfirstrow(
                'SELECT label, login, pw, pw_iv, url
                FROM '.prefixTable('items').'
                WHERE id=%i',
                $dataReceived['currentId']
            );

            // encrypt new password
            $encrypt = cryption(
                $dataReceived['new_pwd'],
                '',
                'encrypt',
                $SETTINGS
            );

            // connect ot server with ssh
            $ret = '';
            require $SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/phpseclib/Net/SSH2.php';
            $parse = parse_url($dataItem['url']);
            if (!isset($parse['host']) || empty($parse['host']) || !isset($parse['port']) || empty($parse['port'])) {
                // error in parsing the url
                echo prepareExchangedData(
                    $SETTINGS['cpassman_dir'],
                    array(
                        'error' => true,
                        'message' => 'Parsing URL failed.<br />Ensure the URL is well written!</i>',
                        'text' => '',
                    ),
                    'encode'
                );
                break;
            } else {
                $ssh = new phpseclib\Net\SSH2($parse['host'], $parse['port']);
                if (!$ssh->login($dataReceived['ssh_root'], $dataReceived['ssh_pwd'])) {
                    echo prepareExchangedData(
                        $SETTINGS['cpassman_dir'],
                        array(
                            'error' => true,
                            'message' => 'Login failed.',
                            'text' => '',
                        ),
                        'encode'
                    );
                    break;
                } else {
                    // send ssh script for user change
                    $ret .= '<br />'.$LANG['ssh_answer_from_server'].':&nbsp;<div style="margin-left:20px;font-style: italic;">';
                    $ret_server = $ssh->exec('echo -e "'.$dataReceived['new_pwd'].'\n'.$dataReceived['new_pwd'].'" | passwd '.$dataItem['login']);
                    if (strpos($ret_server, 'updated successfully') !== false) {
                        $err = false;
                    } else {
                        $err = true;
                    }
                    $ret .= $ret_server.'</div>';
                }
            }

            if ($err === false) {
                // store new password
                DB::update(
                    prefixTable('items'),
                    [
                        'pw' => $encrypt['string'],
                        'pw_iv' => '',
                    ],
                    'id = %i',
                    $dataReceived['currentId']
                );
                // update log
                logItems(
                    $SETTINGS,
                    (int) $dataReceived['currentId'],
                    $dataItem['label'],
                    $_SESSION['user_id'],
                    'at_modification',
                    $_SESSION['login'],
                    'at_pw :'.$dataItem['pw'],
                    'defuse'
                );
                $ret .= '<br />'.$LANG['ssh_action_performed'];
            } else {
                $ret .= "<br /><i class='fa fa-warning'></i>&nbsp;".$LANG['ssh_action_performed_with_error'].'<br />';
            }

            // finished
            echo prepareExchangedData(
                $SETTINGS['cpassman_dir'],
                array(
                    'error' => false,
                    'message' => '',
                    'text' => str_replace(array("\n"), array('<br />'), $ret),
                ),
                'encode'
            );
            break;

        case 'server_auto_update_password_frequency':
            if ($post_key !== $_SESSION['key']
                || null === filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING)
                || null === filter_input(INPUT_POST, 'freq', FILTER_SANITIZE_STRING)
            ) {
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

            // store new frequency
            DB::update(
                prefixTable('items'),
                [
                    'auto_update_pwd_frequency' => $post_freq,
                    'auto_update_pwd_next_date' => time() + (2592000 * $post_freq),
                ],
                'id = %i',
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING)
            );

            echo '[{"error" : ""}]';

            break;
    }
}
