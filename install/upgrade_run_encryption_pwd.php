<?php
/**
 * @file          upgrade_run_encryption_pwd.php
 * @author        Nils Laumaillé
 * @version       2.1.26
 * @copyright     (c) 2009-2016 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */


require_once('../sources/sessions.php');
session_start();
error_reporting(E_ERROR | E_PARSE);
$_SESSION['db_encoding'] = "utf8";
$_SESSION['CPM'] = 1;

require_once '../includes/language/english.php';
require_once '../includes/config/include.php';
if (!file_exists("../includes/config/settings.php")) {
    echo 'document.getElementById("res_step1_error").innerHTML = "";';
    echo 'document.getElementById("res_step1_error").innerHTML = '.
        '"File settings.php does not exist in folder includes/! '.
        'If it is an upgrade, it should be there, otherwise select install!";';
    echo 'document.getElementById("loader").style.display = "none";';
    exit;
}
require_once '../includes/config/settings.php';
require_once '../sources/main.functions.php';

$_SESSION['settings']['loaded'] = "";

$dbgDuo = fopen("upgrade.log", "a");
$finish = false;
$next = ($_POST['nb']+$_POST['start']);

$dbTmp = mysqli_connect(
    $_SESSION['db_host'],
    $_SESSION['db_login'],
    $_SESSION['db_pw'],
    $_SESSION['db_bdd'],
    $_SESSION['db_port']
);

fputs($dbgDuo, "\n\nSELECT id, pw, pw_iv FROM ".$_SESSION['tbl_prefix']."items
    WHERE perso = '0' LIMIT ".$_POST['start'].", ".$_POST['nb']."\n");

// get total items
$rows = mysqli_query($dbTmp,
    "SELECT id, pw, pw_iv FROM ".$_SESSION['tbl_prefix']."items
    WHERE perso = '0'"
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}

$total = mysqli_num_rows($rows);

// loop on items
$rows = mysqli_query($dbTmp,
    "SELECT id, pw, pw_iv FROM ".$_SESSION['tbl_prefix']."items
    WHERE perso = '0' LIMIT ".$_POST['start'].", ".$_POST['nb']
);
if (!$rows) {
    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
    exit();
}

while ($data = mysqli_fetch_array($rows)) {
    fputs($dbgDuo, "\n\n-----\nItem : ".$data['id']);
    // check if pw encrypted with protocol #3
    if (!empty($data['pw_iv'])) {
        $pw = cryption($data['pw'], SALT, $data['pw_iv'], "decrypt");
        // nothing to do - last encryption protocol (#3) used
        fputs($dbgDuo, "\nItem is correctly encrypted");
    } else {
        if (!empty($data['pw_iv'])) {
            // check if pw encrypted with protocol #2
            $pw = decrypt($data['pw']);
            if (empty($pw)) {
                // used protocol is #1
                $pw = decryptOld($data['pw']);  // decrypt using protocol #1
            }

            // get key for this pw
            $resData = mysqli_query($dbTmp,
                "SELECT rand_key FROM ".$_SESSION['tbl_prefix']."keys
                WHERE `sql_table` = 'items' AND id = ".$data['id']
            );
            if (!$resData) {
                echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
                exit();
            }

            $resData = mysqli_query($dbTmp,
                "SELECT rand_key FROM ".$_SESSION['tbl_prefix']."keys
                WHERE `sql_table` = 'items' AND id = ".$data['id']
            );
            if (!$resData) {
                echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
                exit();
            }

            $dataTemp = mysqli_fetch_row($resData);
            if (!empty($dataTemp[0])) {
                // remove key from pw
                $pw = substr($pw, strlen($dataTemp[0]));
            }

            // crypt pw with new protocol #3
            // encrypt pw
            $encrypt = cryption($pw, SALT, "", "encrypt");

            // store Password
            mysqli_query($dbTmp,
                "UPDATE ".$_SESSION['tbl_prefix']."items
                SET pw = '".$encrypt['string']."',pw_iv = '".$encrypt['iv']."'
                WHERE id=".$data['id']
            );

            fputs($dbgDuo, "\nItem has been re-encrypted");
        } else {
            // item has no pwd
            fputs($dbgDuo, "\nItem has no password.");
        }
    }

    // does tables KEYS exists
    if(mysqli_num_rows(mysqli_query("SHOW TABLES LIKE '".$_SESSION['tbl_prefix']."keys'")) == 1) {
        $table_keys_exists = 1;
    } else {
        $table_keys_exists = 0;
        fputs($dbgDuo, "\nNo re-encryption needed as passwords already using latest encryption protocol.\n");
    }

    // change log and category fields
    if ($table_keys_exists == 1) {
        $resData = mysqli_query($dbTmp,
            "SELECT l.id_item AS id_item, k.rand_key AS rndKey, l.raison AS raison, l.raison_iv AS raison_iv, l.date AS mDate, l.id_user AS id_user, l.action AS action
            FROM ".$_SESSION['tbl_prefix']."log_items AS l
            LEFT JOIN ".$_SESSION['tbl_prefix']."keys AS k ON (l.id_item = k.id)
            WHERE l.id_item = ".$data['id']." AND l.raison LIKE 'at_pw :%' AND k.sql_table='items'"
        );
        fputs($dbgDuo, "\nNb of entries in log: ".mysqli_num_rows($resData));
        while ($record = mysqli_fetch_array($resData)) {
            fputs($dbgDuo, "\n> ".$record['raison']);
            if (!empty($record['raison_iv']) && $record['raison_iv'] != NULL) {
                // nothing to do
                fputs($dbgDuo, "Item log correct");
            } else {
                // only at_modif and at_pw
                $reason = explode(' : ', $record['raison']);
                if (trim($reason[0]) == "at_pw") {

                    // check if pw encrypted with protocol #2
                    $pw = decrypt(trim($reason[1]));
                    fputs($dbgDuo, "\n/ step1 : ".$pw);
                    if (empty($pw)) {
                        // used protocol is #1
                        $pw = decryptOld(trim($reason[1]));  // decrypt using protocol #1
                        fputs($dbgDuo, " / step2 : ".$pw);
                    }

                    // get key for this pw
                    $resData_tmp = mysqli_query($dbTmp,
                        "SELECT rand_key FROM ".$_SESSION['tbl_prefix']."keys
                        WHERE `sql_table` = 'items' AND id = ".$data['id']
                    );
                    if (!$resData_tmp) {
                        echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
                        exit();
                    }

                    $dataTemp = mysqli_fetch_row($resData_tmp);
                    if (!empty($dataTemp[0])) {
                        // remove key from pw
                        $pw = substr($pw, strlen($dataTemp[0]));
                    }
                    fputs($dbgDuo, " / step3 : ".$pw);

                    // store new encryption
                    if (isUTF8($pw) && !empty($pw)) {
                        $encrypt = cryption($pw , SALT, "", "encrypt");
                        fputs($dbgDuo, " / Final : ".$encrypt['string']);
                        mysqli_query($dbTmp,
                            "UPDATE ".$_SESSION['tbl_prefix']."log_items
                            SET raison = 'at_pw : ".$encrypt['string']."', raison_iv = '".$encrypt['iv']."'
                            WHERE id_item =".$data['id']." AND date='".$record['mDate']."'
                            AND id_user=".$record['id_user']." AND action ='".$record['action']."'"
                        );
                    } else {
                        //data is lost ... unknown encryption
                    }
                    fputs($dbgDuo, " / Done.");
                }
            }
        }

        fputs($dbgDuo, "\nLog treatment done.");

        // change category fields encryption
        $resData = mysqli_query($dbTmp,
            "SELECT i.data AS data, k.rand_key AS rndKey
            FROM ".$_SESSION['tbl_prefix']."categories_items AS i
            LEFT JOIN ".$_SESSION['tbl_prefix']."keys AS k ON (k.id = i.item_id)
            WHERE i.item_id = ".$data['id']." AND k.sql_table='items'"
        );
        if (!$resData) {
            echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
            exit();
        }

        while ($record = mysqli_fetch_array($resData)) {
            $tmpData = substr(decrypt($record['data']), strlen($record['rndKey']));
            if (isUTF8($tmpData ) && !empty($tmpData )) {
                $encrypt = cryption($tmpData , SALT, "", "encrypt");

                // store Password
                $resData_tmp2 = mysqli_query($dbTmp,
                    "UPDATE ".$_SESSION['tbl_prefix']."categories_items
                        SET data = '".$encrypt['string']."', data_iv = '".$encrypt['iv']."'
                        WHERE item_id =".$data['id']
                );
                if (!$resData_tmp2) {
                    echo '[{"finish":"1" , "error":"'.mysqli_error($dbTmp).'"}]';
                    exit();
                }

            } else {
                //data is lost ... unknown encryption
            }
        }
        fputs($dbgDuo, "\nCategory treatment done.");
    }

}
if ($next >= $total) {
    $finish = 1;
}


fputs($dbgDuo, "\n\nAll finished.\n");

echo '[{"finish":"'.$finish.'" , "next":"'.$next.'", "error":""}]';