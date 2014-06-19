<?php
/**
 * @file          utils.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.20
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once('sessions.php');
session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || !isset($_SESSION['key']) || empty($_SESSION['key'])) {
    die('Hacking attempt...');
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
header("Content-type: text/html; charset=utf-8");
include 'main.functions.php';

//Connect to DB
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$error_handler = 'db_error_handler';

// Construction de la requ?te en fonction du type de valeur
switch ($_POST['type']) {
    #CASE export in CSV format
    case "export_to_csv_format":
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

        foreach (explode(';', $_POST['ids']) as $id) {
            if (!in_array($id, $_SESSION['forbiden_pfs']) && in_array($id, $_SESSION['groupes_visibles'])) {
                $rows =DB::query(
                    "SELECT i.id as id, i.restricted_to as restricted_to, i.perso as perso, i.label as label, i.description as description, i.pw as pw, i.login as login,
                    l.date as date,
                    n.renewal_period as renewal_period,
                    k.rand_key
                    FROM ".$pre."items as i
                    INNER JOIN ".$pre."nested_tree as n ON (i.id_tree = n.id)
                    INNER JOIN ".$pre."log_items as l ON (i.id = l.id_item)
                    INNER JOIN ".$pre."keys as k ON (i.id = k.id)
                    WHERE i.inactif = %i
                    AND i.id_tree= %i
                    AND (l.action = %s OR (l.action = %s AND l.raison = %ss))
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
                foreach ($rows as $reccord) {
                    $restricted_users_array = explode(';', $reccord['restricted_to']);
                    //exclude all results except the first one returned by query
                    if (empty($id_managed) || $id_managed != $reccord['id']) {
                        if (
                        (in_array($id, $_SESSION['personal_visible_groups']) && !($reccord['perso'] == 1 && $_SESSION['user_id'] == $reccord['restricted_to']) && !empty($reccord['restricted_to']))
                        ||
                        (!empty($reccord['restricted_to']) && !in_array($_SESSION['user_id'], $restricted_users_array))
                    ) {
                            //exclude this case
                        } else {
                            //encrypt PW
                            if (!empty($_POST['salt_key']) && isset($_POST['salt_key'])) {
                                $pw = decrypt($reccord['pw'], mysql_real_escape_string(stripslashes($_POST['salt_key'])));
                            } else {
                                $pw = decrypt($reccord['pw']);
                            }

                            $full_listing[$i] = array(
                                'id' => $reccord['id'],
                                'label' => $reccord['label'],
                                'description' => htmlentities(str_replace(";", ".", $reccord['description']), ENT_QUOTES, "UTF-8"),
                                'pw' => substr(addslashes($pw), strlen($reccord['rand_key'])),
                                'login' => $reccord['login'],
                                'restricted_to' => $reccord['restricted_to'],
                                'perso' => $reccord['perso']
                            );
                        }
                        $i++;
                    }
                    $id_managed = $reccord['id'];
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
}
