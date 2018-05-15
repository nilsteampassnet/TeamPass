<?php
/**
 * @package       utils.queries.php
 * @author        Nils Laumaillé <nils@teampass.net>
 * @version       2.1.27
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 * @link          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'SecureHandler.php';
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
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

include $SETTINGS['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
include $SETTINGS['cpassman_dir'].'/includes/config/include.php';
header("Content-type: text/html; charset=utf-8");
require_once 'main.functions.php';

//Connect to DB
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = true;
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$post_freq = filter_input(INPUT_POST, 'freq', FILTER_SANITIZE_NUMBER_INT);
$post_ids = filter_input(INPUT_POST, 'ids', FILTER_SANITIZE_STRING);
$post_salt_key = filter_input(INPUT_POST, 'salt_key', FILTER_SANITIZE_STRING);
$post_current_id = filter_input(INPUT_POST, 'currentId', FILTER_SANITIZE_NUMBER_INT);
$post_data_to_share = filter_input(INPUT_POST, 'data_to_share', FILTER_SANITIZE_STRING);
$post_user_id = filter_input(INPUT_POST, 'user_id', FILTER_SANITIZE_NUMBER_INT);

// Construction de la requ?te en fonction du type de valeur
if (null !== $post_type) {
    switch ($post_type) {
        #CASE export in CSV format
        case "export_to_csv_format":
            // Init
            $full_listing = array();
            $full_listing[0] = array(
                'id' => "id",
                'label' => "label",
                'description' => "description",
                'pw' => "pw",
                'login' => "login",
                'restricted_to' => "restricted_to",
                'perso' => "perso"
            );

            foreach (explode(';', $post_ids) as $id) {
                if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {
                    $rows = DB::query(
                        "SELECT i.id as id, i.restricted_to as restricted_to, i.perso as perso,
                        i.label as label, i.description as description, i.pw as pw, i.login as login, i.pw_iv as pw_iv
                        l.date as date,
                        n.renewal_period as renewal_period
                        FROM ".prefix_table("items")." as i
                        INNER JOIN ".prefix_table("nested_tree")." as n ON (i.id_tree = n.id)
                        INNER JOIN ".prefix_table("log_items")." as l ON (i.id = l.id_item)
                        WHERE i.inactif = %i
                        AND i.id_tree= %i
                        AND (l.action = %s OR (l.action = %s AND l.raison LIKE %ss))
                        ORDER BY i.label ASC, l.date DESC",
                        0,
                        $id,
                        "at_creation",
                        "at_modification",
                        "at_pw :"
                    );

                    $id_managed = '';
                    $i = 1;
                    $items_id_list = array();
                    foreach ($rows as $record) {
                        $restricted_users_array = explode(';', $record['restricted_to']);
                        //exclude all results except the first one returned by query
                        if (empty($id_managed) || $id_managed != $record['id']) {
                            if ((in_array($id, $_SESSION['personal_visible_groups'])
                                && !($record['perso'] === "1"
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
                                        mysqli_escape_string($link, stripslashes($post_salt_key)),
                                        "decrypt"
                                    );
                                } else {
                                    $pw = cryption(
                                        $record['pw'],
                                        "",
                                        "decrypt"
                                    );
                                }

                                $full_listing[$i] = array(
                                    'id' => $record['id'],
                                    'label' => $record['label'],
                                    'description' => htmlentities(str_replace(";", ".", $record['description']), ENT_QUOTES, "UTF-8"),
                                    'pw' => substr(addslashes($pw['string']), strlen($record['rand_key'])),
                                    'login' => $record['login'],
                                    'restricted_to' => $record['restricted_to'],
                                    'perso' => $record['perso']
                                );
                            }
                            $i++;
                        }
                        $id_managed = $record['id'];
                    }
                }
                //save the file
                $handle = fopen($settings['bck_script_path'].'/'.$settings['bck_script_filename'].'-'.time().'.sql', 'w+');
                foreach ($full_listing as $line) {
                    $return = $line['id'].";".$line['label'].";".$line['description'].";".$line['pw'].";".$line['login'].";".$line['restricted_to'].";".$line['perso']."/n";
                    fwrite($handle, $return);
                }
                fclose($handle);
            }
            break;


        #CASE start user personal pwd re-encryption
        case "reencrypt_personal_pwd_start":
            if ($post_key !== $_SESSION['key']) {
                echo '[{"error" : "something_wrong"}]';
                break;
            }

            // check if psk is set
            if (!isset($_SESSION['user_settings']['encrypted_psk']) || empty($_SESSION['user_settings']['encrypted_psk'])) {
                echo '[{"error" : "No personal saltkey given"}]';
                break;
            }


            $currentID = "";
            $pws_list = array();
            $rows = DB::query(
                "SELECT i.id AS id
                FROM  ".prefix_table("nested_tree")." AS n
                LEFT JOIN ".prefix_table("items")." AS i ON i.id_tree = n.id
                WHERE i.perso = %i AND n.title = %i",
                "1",
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


        #CASE user personal pwd re-encryption
        case "reencrypt_personal_pwd":
            if ($post_key !== $_SESSION['key']) {
                echo '[{"error" : "something_wrong"}]';
                break;
            }

            if (null !== $post_data_to_share && empty($post_current_id) === false) {
                // ON DEMAND

                //decrypt and retreive data in JSON format
                $dataReceived = prepareExchangedData(
                    $post_data_to_share,
                    "decode"
                );

                // do a check on old PSK
                if (empty($dataReceived['sk']) || empty($dataReceived['old_sk'])) {
                    echo '[{"error" : "No personnal saltkey provided"}]';
                    break;
                }

                //Prepare variables
                $personal_sk = htmlspecialchars_decode($dataReceived['sk']);
                $oldPersonalSaltkey = htmlspecialchars_decode($dataReceived['old_sk']);

                // get data about pw
                $data = DB::queryfirstrow(
                    "SELECT id, pw, pw_iv, encryption_type
                    FROM ".prefix_table("items")."
                    WHERE id = %i",
                    $post_current_id
                );

                // decrypt with Defuse (assuming default)
                $decrypt = cryption(
                    $data['pw'],
                    $_SESSION['user_settings']['encrypted_oldpsk'],
                    "decrypt"
                );
                if (empty($decrypt['err']) === true) {
                    $pw = $decrypt['string'];
                } else {
                    // check if current encryption protocol #3
                    if (!empty($data['pw_iv']) && !empty($data['pw'])) {
                        // decrypt it
                        $pw = cryption_phpCrypt(
                            $data['pw'],
                            $oldPersonalSaltkey,
                            $data['pw_iv'],
                            "decrypt"
                        );
                        $pw = $pw['string'];
                    } else {
                        // check if pw encrypted with protocol #2
                        $pw = decrypt($data['pw'], $oldPersonalSaltkey);
                        if (empty($pw)) {
                            // used protocol is #1
                            $pw = decryptOld($data['pw'], $oldPersonalSaltkey); // decrypt using protocol #1
                        } else {
                            // used protocol is #2
                            // get key for this pw
                            $dataItem = DB::queryfirstrow(
                                "SELECT rand_key
                                FROM ".prefix_table("keys")."
                                WHERE `sql_table` = %s AND id = %i",
                                "items",
                                $data['id']
                            );
                            if (!empty($dataItem['rand_key'])) {
                                // remove key from pw
                                $pw = substr($pw, strlen($dataTemp['rand_key']));
                            }
                        }
                    }
                }

                // encrypt it
                if (!empty($pw) && isUTF8($pw)) {
                    $encrypt = cryption(
                        $pw,
                        $_SESSION['user_settings']['session_psk'],
                        "encrypt"
                    );
                    if (isUTF8($pw)) {
                        // store Password
                        DB::update(
                            prefix_table('items'),
                            array(
                                'pw' => $encrypt['string'],
                                'pw_iv' => ""
                                ),
                            "id = %i",
                            $data['id']
                        );
                    }
                }
            } else {
                // COMPLETE RE-ENCRYPTION
                // get data about pw
                $data = DB::queryfirstrow(
                    "SELECT id, pw, pw_iv, encryption_type
                    FROM ".prefix_table("items")."
                    WHERE id = %i",
                    $post_current_id
                );
                if (empty($data['pw_iv']) && $data['encryption_type'] === "not_set") {
                // check if pw encrypted with protocol #2
                    $pw = decrypt(
                        $data['pw'],
                        $_SESSION['user_settings']['clear_psk']
                    );
                    if (empty($pw)) {
                        // used protocol is #1
                        $pw = decryptOld($data['pw'], $_SESSION['user_settings']['clear_psk']); // decrypt using protocol #1
                    } else {
                        // used protocol is #2
                        // get key for this pw
                        $dataItem = DB::queryfirstrow(
                            "SELECT rand_key
                            FROM ".prefix_table("keys")."
                            WHERE `sql_table` = %s AND id = %i",
                            "items",
                            $data['id']
                        );
                        if (!empty($dataItem['rand_key'])) {
                            // remove key from pw
                            $pw = substr($pw, strlen($dataTemp['rand_key']));
                        }
                    }

                    // encrypt it
                    $encrypt = cryption(
                        $pw,
                        $_SESSION['user_settings']['session_psk'],
                        "encrypt"
                    );

                    // store Password
                    DB::update(
                        prefix_table('items'),
                        array(
                            'pw' => $encrypt['string'],
                            'pw_iv' => "",
                            "encryption_type" => "defuse"
                            ),
                        "id = %i",
                        $data['id']
                    );
                } elseif ($data['encryption_type'] === "not_set") {
                // to be re-encrypted with defuse

                    // decrypt
                    $pw = cryption_phpCrypt(
                        $data['pw'],
                        $_SESSION['user_settings']['clear_psk'],
                        $data['pw_iv'],
                        "decrypt"
                    );

                    // encrypt
                    $encrypt = cryption(
                        $pw['string'],
                        $_SESSION['user_settings']['session_psk'],
                        "encrypt"
                    );

                    // store Password
                    DB::update(
                        prefix_table('items'),
                        array(
                            'pw' => $encrypt['string'],
                            'pw_iv' => "",
                            "encryption_type" => "defuse"
                            ),
                        "id = %i",
                        $data['id']
                    );
                } else {
                // already re-encrypted
                }
            }


            //
            DB::update(
                prefix_table('users'),
                array(
                    'upgrade_needed' => 0
                    ),
                "id = %i",
                $_SESSION['user_id']
            );
            $_SESSION['user_upgrade_needed'] = 0;


            echo '[{"error" : ""}]';
            break;

            #CASE auto update server password
        case "server_auto_update_password":
            if ($post_key !== $_SESSION['key']) {
                echo '[{"error" : "something_wrong"}]';
                break;
            }

            // decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(
                $post_data,
                "decode"
            );

            // get data about item
            $dataItem = DB::queryfirstrow(
                "SELECT label, login, pw, pw_iv, url
                FROM ".prefix_table("items")."
                WHERE id=%i",
                $dataReceived['currentId']
            );

            // encrypt new password
            $encrypt = cryption(
                $dataReceived['new_pwd'],
                "",
                "encrypt"
            );

            // connect ot server with ssh
            $ret = "";
            require($SETTINGS['cpassman_dir'].'/includes/libraries/Authentication/phpseclib/Net/SSH2.php');
            $parse = parse_url($dataItem['url']);
            if (!isset($parse['host']) || empty($parse['host']) || !isset($parse['port']) || empty($parse['port'])) {
                // error in parsing the url
                echo prepareExchangedData(
                    array(
                        "error" => "Parsing URL failed.<br />Ensure the URL is well written!</i>",
                        "text" => ""
                    ),
                    "encode"
                );
                break;
            } else {
                $ssh = new phpseclib\Net\SSH2($parse['host'], $parse['port']);
                if (!$ssh->login($dataReceived['ssh_root'], $dataReceived['ssh_pwd'])) {
                    echo prepareExchangedData(
                        array(
                            "error" => "Login failed.",
                            "text" => ""
                        ),
                        "encode"
                    );
                    break;
                } else {
                    // send ssh script for user change
                    $ret .= "<br />".$LANG['ssh_answer_from_server'].':&nbsp;<div style="margin-left:20px;font-style: italic;">';
                    $ret_server = $ssh->exec('echo -e "'.$dataReceived['new_pwd'].'\n'.$dataReceived['new_pwd'].'" | passwd '.$dataItem['login']);
                    if (strpos($ret_server, "updated successfully") !== false) {
                        $err = false;
                    } else {
                        $err = true;
                    }
                    $ret .= $ret_server."</div>";
                }
            }

            if ($err === false) {
                // store new password
                DB::update(
                    prefix_table("items"),
                    array(
                        'pw' => $encrypt['string'],
                        'pw_iv' => ""
                        ),
                    "id = %i",
                    $dataReceived['currentId']
                );
                // update log
                logItems(
                    $dataReceived['currentId'],
                    $dataItem['label'],
                    $_SESSION['user_id'],
                    'at_modification',
                    $_SESSION['login'],
                    'at_pw :'.$dataItem['pw'],
                    "defuse"
                );
                $ret .= "<br />".$LANG['ssh_action_performed'];
            } else {
                $ret .= "<br /><i class='fa fa-warning'></i>&nbsp;".$LANG['ssh_action_performed_with_error']."<br />";
            }

            // finished
            echo prepareExchangedData(
                array(
                    "error" => "",
                    "text" => str_replace(array("\n"), array("<br />"), $ret)
                ),
                "encode"
            );
            break;

        case "server_auto_update_password_frequency":
            if ($post_key !== $_SESSION['key']
                || null === filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING)
                || null === filter_input(INPUT_POST, 'freq', FILTER_SANITIZE_STRING)
            ) {
                echo '[{"error" : "something_wrong"}]';
                break;
            }

            // store new frequency
            DB::update(
                prefix_table("items"),
                array(
                    'auto_update_pwd_frequency' => $post_freq,
                    'auto_update_pwd_next_date' => time() + (2592000 * $post_freq)
                    ),
                "id = %i",
                filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING)
            );

            echo '[{"error" : ""}]';

            break;
    }
}
