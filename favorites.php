<?php
/**
 * @file        favorites.php
 * @author      Nils Laumaillé
 * @version       2.1.27
 * @copyright   (c) 2009-2017 Nils Laumaillé
 * @licensing   GNU AFFERO GPL 3.0
 * @link          http://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

require_once 'sources/SecureHandler.php';
session_start();

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}


require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset==utf-8");

// connect to DB
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$encoding = $encoding;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);
$link->set_charset($encoding);

echo '
<div class="page-header">
    <h1>
        '.$LANG['my_favourites'].'
    </h1>
</div>

<div style="height:100%;overflow:auto;">';
if (empty($_SESSION['favourites'])) {
    echo '
    ';
} else {
    echo '
    <table id="t_items" style="empty-cells:show;width:100%;" class="table table-striped table-hover">
        <thead class=""><tr>
            <th style="width:55px;"></th>
            <th style="min-width:15%;">'.$LANG['label'].'</th>
            <th style="min-width:50%;">'.$LANG['description'].'</th>
            <th style="min-width:20%;">'.$LANG['group'].'</th>
        </tr></thead>
        <tbody class="">';
    //Get favourites
    $cpt= 0 ;
    foreach ($_SESSION['favourites'] as $fav) {
        if (!empty($fav)) {
            $data = DB::queryFirstRow(
                "SELECT i.label, i.description, i.id, i.id_tree, t.title
                FROM ".prefix_table("items")." as i
                INNER JOIN ".prefix_table("nested_tree")." as t ON (t.id = i.id_tree)
                WHERE i.id = %i",
                $fav
            );
            if (!empty($data['label'])) {
                echo '
                    <tr id="row-'.$data['id'].'">
                        <td>
                            <i class="fa fa-external-link" onClick="javascript:window.location.href = \'index.php?page=items&amp;group='.$data['id_tree'].'&amp;id='.$data['id'].'\';" style="cursor:pointer; font-size:18px;"></i>
                            &nbsp;
                            <i class="fa fa-trash mi-red tip" onClick="prepare_delete_fav(\''.$data['id'].'\');" style="cursor:pointer; font-size:18px;" title="'.$LANG['item_menu_del_from_fav'].'"></i>
                        </td>
                        <td align="left">'.stripslashes($data['label']).'</td>
                        <td align="center">'.stripslashes($data['description']).'</td>
                        <td align="center">',$data['title'] == $_SESSION['user_id']?$_SESSION['login']:$data['title'],'</td>
                    </tr>';
                $cpt++;
            }
        }
    }
    echo '
        </tbody>
    </table>';
}
    echo '
</div>';

// DIV FOR FAVOURITES DELETION
echo '
<div id="div_delete_fav" style="display:none;">
    '.$LANG['confirm_del_from_fav'].'
    <input type="hidden" id="detele_fav_id" />
</div>';
