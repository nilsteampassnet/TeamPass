<?php
/**
 * @file          script.ssh.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

session_start();

include '../includes/config/settings.php';
header("Content-type: text/html; charset=utf-8");
$_SESSION['CPM'] = 1;
require_once "../includes/config/include.php";
require_once "../sources/main.functions.php";

// connect to DB
require_once '../sources/SplClassLoader.php';
require_once '../includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$error_handler = 'db_error_handler';
$link= mysqli_connect($server, $user, $pass, $database, $port);

// ssh libraries
stream_resolve_include_path('../includes/libraries/Authentication/phpseclib/Crypt/RC4.php');
include('../includes/libraries/Authentication/phpseclib/Net/SSH2.php');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// load passwordLib library
$pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
$pwgen->register();
$pwgen = new Encryption\PwGen\pwgen();

//get settings infos
$rows = DB::query("SELECT * FROM ".$pre."misc WHERE type = 'admin'");
foreach ($rows as $record) {
    $settings[$record['intitule']] = $record['valeur'];
}

// loop on server user password to change
if (!empty($settings['enable_server_password_change']) && $settings['enable_server_password_change'] == 1) {
    $log = "Start date ".date("Y-m-d h:i:s a")."\n";

    // loop on items
    $rows = DB::query(
        "SELECT label, login, pw, pw_iv, url, auto_update_pwd_next_date, auto_update_pwd_frequency
        FROM ".prefix_table("items")."
        WHERE auto_update_pwd_next_date > %i",
        time()
    );
    foreach ($rows as $record) {
        $log .= "* Item '".$record['label']."'\n";

        // decrypt password
        $oldPwClear = cryption($record['pw'], "", "decrypt");

        // generate password
        $pwgen->setLength(10);
        $pwgen->setSecure(true);
        $pwgen->setCapitalize(true);
        $pwgen->setNumerals(true);
        $new_pwd = $pwgen->generate();

        // encrypt new password
        $encrypt = cryption(
            $new_pwd,
            "",
            "encrypt"
        );

        $parse = parse_url($record['url']);
        $ssh = new Net_SSH2($parse['host'], $parse['port']);
        if (!$ssh->login($record['login'], $oldPwClear['string'])) {
           $log .= "   ERR - Login failed.\n   Error description:".$_SESSION['sshError']."\n\n";
        }else{
            // send ssh script for user change
            $ret_server = $ssh->exec('echo -e "'.$new_pwd.'\n'.$new_pwd.'" | passwd '.$record['login']);
            if (strpos($ret_server, "updated successfully") !== false) {
                $err = false;
            } else {
                $err = true;
            }
            $log .= "   Answer: ".$ret_server."\n\n";
        }

        if ($err == false) {
            // store new password
            DB::update(
                prefix_table("items"),
                array(
                    'pw' => $encrypt['string'],
                    'pw_iv' => $encrypt['iv'],
                    'auto_update_pwd_next_date' => time() + (2592000 * intval($record['auto_update_pwd_frequency']))
                   ),
                "id = %i",
                $record['id']
            );
            // update log
            logItems($record['id'], $record['label'], "script", 'at_modification', '999998', 'at_pw :'.$record['pw'], $record['pw_iv']);
            //$log .= "   done.\n\n";
        } else {
            $log .= "   An error occured with password change.\n\n";
        }
    }

    $log .= "End of task\n---------------\n\n";

    //save a log
    $handle = fopen('../files/script.ssh.log', 'w+');
    fwrite($handle, $log);
    fclose($handle);
}
