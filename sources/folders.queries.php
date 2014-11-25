<?php
/**
 * @file          folders.queries.php
 * @author        Nils Laumaillé
 * @version       2.1.22
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
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 || 
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) || 
    !isset($_SESSION['key']) || empty($_SESSION['key'])) 
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "folders")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
header("Content-type: text/html; charset==utf-8");
include 'main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to mysql server
require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
DB::$host = $server;
DB::$user = $user;
DB::$password = $pass;
DB::$dbName = $database;
DB::$port = $port;
DB::$error_handler = 'db_error_handler';
$link = mysqli_connect($server, $user, $pass, $database, $port);

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// CASE where title is changed
if (isset($_POST['newtitle'])) {
    $id = explode('_', $_POST['id']);
    //update DB
    DB::update(
        $pre.'nested_tree',
        array(
            'title' => mysqli_escape_string($link, stripslashes(($_POST['newtitle'])))
        ),
        "id=%i",
        $id[1]
    );
    //Show value
    echo ($_POST['newtitle']);

    // CASE where RENEWAL PERIOD is changed
} elseif (isset($_POST['renewal_period']) && !isset($_POST['type'])) {

    //Check if renewal period is an integer
    if (parseInt(intval($_POST['renewal_period']))) {
        $id = explode('_', $_POST['id']);
        //update DB
        DB::update(
            $pre.'nested_tree',
            array(
                'renewal_period' => mysqli_escape_string($link, stripslashes(($_POST['renewal_period'])))
           ),
            "id=%i",
            $id[1]
        );
        //Show value
        echo ($_POST['renewal_period']);
    } else {
        //Show ERROR
        echo ($LANG['error_renawal_period_not_integer']);
    }

    // CASE where the parent is changed
} elseif (isset($_POST['newparent_id'])) {
    $id = explode('_', $_POST['id']);
    //Store in DB
    DB::update(
        $pre.'nested_tree',
        array(
            'parent_id' => $_POST['newparent_id']
       ),
        "id=%i",
        $id[1]
    );
    //Get the title to display it
    $data = DB::queryfirstrow("SELECT title FROM ".$pre."nested_tree WHERE id = %i", $_POST['newparent_id']);
    //show value
    echo ($data['title']);
    //rebuild the tree grid
    $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
    $tree->rebuild();

    // CASE where complexity is changed
} elseif (isset($_POST['changer_complexite'])) {
    /* do checks */
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
    if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_folders")) {
        $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
        include $_SESSION['settings']['cpassman_dir'].'/error.php';
        exit();
    }

    $id = explode('_', $_POST['id']);

    //Check if group exists
    $tmp = DB::query("SELECT * FROM ".$pre."misc WHERE type = %s' AND intitule = %i", "complex", $id[1]);
    $counter = DB::count();
    if ($counter == 0) {
        //Insert into DB
        DB::insert(
            $pre.'misc',
            array(
                'type' => 'complex',
                'intitule' => $id[1],
                'valeur' => $_POST['changer_complexite']
           )
        );
    } else {
        //update DB
        DB::update(
            $pre.'misc',
            array(
                'valeur' => $_POST['changer_complexite']
           ),
            "type=%s AND  intitule = %i",
            "complex",
            $id[1]
        );
    }

    //Get title to display it
    echo $pwComplexity[$_POST['changer_complexite']][1];

    //rebuild the tree grid
    $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
    $tree->rebuild();

    // Several other cases
} elseif (isset($_POST['type'])) {
    switch ($_POST['type']) {
        // CASE where DELETING a group
        case "delete_folder":
            $foldersDeleted = "";
            // this will delete all sub folders and items associated
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

            // Get through each subfolder
            $folders = $tree->getDescendants($_POST['id'], true);
            foreach ($folders as $folder) {
                if (($folder->parent_id > 0 || $folder->parent_id == 0) && $folder->title != $_SESSION['user_id'] ) {
                    //Store the deleted folder (recycled bin)
                    DB::insert(
                        $pre.'misc',
                        array(
                            'type' => 'folder_deleted',
                            'intitule' => "f".$_POST['id'],
                            'valeur' => $folder->id.', '.$folder->parent_id.', '.
                                $folder->title.', '.$folder->nleft.', '.$folder->nright.', '.
                                $folder->nlevel.', 0, 0, 0, 0'
                       )
                    );
                    //delete folder
                    DB::delete($pre."nested_tree", "id = %i", $folder->id);

                    //delete items & logs
                    $items = DB::query("SELECT id FROM ".$pre."items WHERE id_tree=%i", $folder->id);
                    foreach ($items as $item) {
                        DB::update(
                            $pre."items",
                            array(
                                'inactif' => '1',
                            ),
                            "id = %i",
                            $item['id']
                        );
                        //log
                        DB::insert(
                            $pre."log_items",
                            array(
                                'id_item' => $item['id'],
                                'date' => time(),
                                'id_user' => $_SESSION['user_id'],
                                'action' => 'at_delete'
                            )
                        );
                    }

                    //Actualize the variable
                    $_SESSION['nb_folders'] --;
                }
            }

            //rebuild tree
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $tree->rebuild();

            //Update CACHE table
            updateCacheTable("delete_value", $_POST['id']);
            break;
           
          
        // CASE where DELETING multiple groups
        case "delete_multiple_folders":
            //decrypt and retreive data in JSON format
        	$dataReceived = prepareExchangedData($_POST['data'], "decode");
            $error = "";
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            
            foreach (explode(';', $dataReceived['foldersList']) as $folderId) {
                $foldersDeleted = "";
                // Get through each subfolder
                $folders = $tree->getDescendants($folderId, true);
                foreach ($folders as $folder) {
                    if (($folder->parent_id > 0 || $folder->parent_id == 0) && $folder->title != $_SESSION['user_id']) {
                        //Store the deleted folder (recycled bin)
                        DB::insert(
                            $pre.'misc',
                            array(
                                'type' => 'folder_deleted',
                                'intitule' => "f".$folderId,
                                'valeur' => $folder->id.', '.$folder->parent_id.', '.
                                    $folder->title.', '.$folder->nleft.', '.$folder->nright.', '.
                                    $folder->nlevel.', 0, 0, 0, 0'
                            )
                        );
                        //delete folder
                        DB::delete($pre."nested_tree", "id = %i", $folder->id);
                        //delete items & logs
                        $items = DB::query("SELECT id FROM ".$pre."items WHERE id_tree=%i", $folder->id);
                        foreach ($items as $item) {
                            DB::update(
                                $pre."items",
                                array(
                                    'inactif' => '1',
                                ),
                                "id = %i",
                                $item['id']
                            );
                            //log
                            DB::insert(
                                $pre."log_items",
                                array(
                                    'id_item' => $item['id'],
                                    'date' => time(),
                                    'id_user' => $_SESSION['user_id'],
                                    'action' => 'at_delete'
                                )
                            );
                        }
                        //Actualize the variable
                        $_SESSION['nb_folders'] --;
                    }
                }
                //Update CACHE table
                updateCacheTable("delete_value", $folderId);
            }

            //rebuild tree
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $tree->rebuild();
            
            echo '[ { "error" : "'.$error.'" } ]';
            
            break;

        //CASE where ADDING a new group
        case "add_folder":
            $error = "";

            //decrypt and retreive data in JSON format
        	$dataReceived = prepareExchangedData($_POST['data'], "decode");

            //Prepare variables
            $title = htmlspecialchars_decode($dataReceived['title']);
            $complexity = htmlspecialchars_decode($dataReceived['complexity']);
            $parentId = htmlspecialchars_decode($dataReceived['parent_id']);
            $renewalPeriod = htmlspecialchars_decode($dataReceived['renewal_period']);

            //Check if title doesn't contains html codes
            if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $title, $out)) {
                $error = 'error_html_codes';
            }

            //Check if duplicate folders name are allowed
            $createNewFolder = true;
            if (isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 0) {
                DB::query("SELECT * FROM ".$pre."nested_tree WHERE title = %s", $title);
                $counter = DB::count();
                if ($counter != 0) {
                    $error = 'error_group_exist';
                    $createNewFolder = false;
                }
            }

            if ($createNewFolder == true) {
                //check if parent folder is personal
                $data = DB::queryfirstrow("SELECT personal_folder FROM ".$pre."nested_tree WHERE id = %i", $parentId);
                if ($data['personal_folder'] == "1") {
                    $isPersonal = 1;
                } else {
                    $isPersonal = 0;
                }

                if (
                    $isPersonal == 1
                    || $_SESSION['is_admin'] == 1
                    || ($_SESSION['user_manager'] == 1)
                    || (isset($_SESSION['settings']['subfolder_rights_as_parent'])
                    && $_SESSION['settings']['subfolder_rights_as_parent'] == 1)
                ) {
                    //create folder
                    DB::insert(
                        $pre."nested_tree",
                        array(
                            'parent_id' => $parentId,
                            'title' => $title,
                            'personal_folder' => $isPersonal,
                            'renewal_period' => $renewalPeriod,
                            'bloquer_creation' => '0',
                            'bloquer_modification' => '0'
                       )
                    );
                    $newId = DB::insertId();

                    //Add complexity
                    DB::insert(
                        $pre."misc",
                        array(
                            'type' => 'complex',
                            'intitule' => $newId,
                            'valeur' => $complexity
                        )
                    );

                    $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
                    $tree->rebuild();

                    if (
                        $isPersonal != 1
                        && isset($_SESSION['settings']['subfolder_rights_as_parent'])
                        && $_SESSION['settings']['subfolder_rights_as_parent'] == 0
                    ){
                        //Get user's rights
                        @identifyUserRights(
                            $_SESSION['groupes_visibles'].';'.$newId,
                            $_SESSION['groupes_interdits'],
                            $_SESSION['is_admin'],
                            $_SESSION['fonction_id'],
                            true
                        );

                        //add access to this new folder
                        foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                            DB::insert(
                                $pre.'roles_values',
                                array(
                                    'role_id' => $role,
                                    'folder_id' => $newId,
                                    'type' => "W"
                                )
                            );
                        }
                    }

                    //If it is a subfolder, then give access to it for all roles that allows the parent folder
                    $rows = DB::query("SELECT role_id FROM ".$pre."roles_values WHERE folder_id = %i", $parentId);
                    foreach ($rows as $record) {
                        //add access to this subfolder
                        DB::insert(
                            $pre.'roles_values',
                            array(
                                'role_id' => $record['role_id'],
                                'folder_id' => $newId
                           )
                        );
                    }
                }
            }
            echo '[ { "error" : "'.$error.'" } ]';

            break;

        //CASE where UPDATING a new group
        case "update_folder":
            $error = "";

            //decrypt and retreive data in JSON format
        	$dataReceived = prepareExchangedData($_POST['data'], "decode");

            //Prepare variables
            $title = htmlspecialchars_decode($dataReceived['title']);
            $complexity = htmlspecialchars_decode($dataReceived['complexity']);
            $parentId = htmlspecialchars_decode($dataReceived['parent_id']);
            $renewalPeriod = htmlspecialchars_decode($dataReceived['renewal_period']);

            //Check if title doesn't contains html codes
            if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $title, $out)) {
                echo '[ { "error" : "error_html_codes" } ]';
                break;
            }

            //Check if duplicate folders name are allowed
            $createNewFolder = true;
            if (isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 0) {
                $data = DB::queryfirstrow("SELECT id, title FROM ".$pre."nested_tree WHERE title = %s", $title);
                if (!empty($data['id']) && $dataReceived['id'] != $data['id'] && $title != $data['title'] ) {
                    echo '[ { "error" : "error_group_exist" } ]';
                    break;
                }
            }

            DB::update(
                $pre."nested_tree",
                array(
                    'parent_id' => $parentId,
                    'title' => $title,
                    'personal_folder' => 0,
                    'renewal_period' => $renewalPeriod,
                    'bloquer_creation' => '0',
                    'bloquer_modification' => '0'
                ),
                "id=%i", 
                $dataReceived['id']
            );

            //Add complexity
            DB::update(
                $pre."misc",
                array(
                    'valeur' => $complexity
                ),
                "intitule = %s AND type = %s",
                $dataReceived['id'],
                "complex"
            );

            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $tree->rebuild();

            //Get user's rights
            identifyUserRights(implode(";", $_SESSION['groupes_visibles']).';'.$dataReceived['id'], implode(";", $_SESSION['groupes_interdits']), $_SESSION['is_admin'], $_SESSION['fonction_id'], true);

            echo '[ { "error" : "'.$error.'" } ]';
            break;

        //CASE where to update the associated Function
        case "fonction":
            /* do checks */
            require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
            if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_folders")) {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $_SESSION['settings']['cpassman_dir'].'/error.php';
                exit();
            }
            // get values
            $val = explode(';', $_POST['valeur']);
            $valeur = $_POST['valeur'];
            //Check if ID already exists
            $data = DB::queryfirstrow("SELECT authorized FROM ".$pre."rights WHERE tree_id = %i AND fonction_id= %i", $val[0], $val[1]);
            if (empty($data['authorized'])) {
                //Insert into DB
                DB::insert(
                    $pre.'rights',
                    array(
                        'tree_id' => $val[0],
                        'fonction_id' => $val[1],
                        'authorized' => 1
                   )
                );
            } else {
                //Update DB
                if ($data['authorized']==1) {
                    DB::update(
                        $pre.'rights',
                        array(
                            'authorized' => 0
                       ),
                       "id = %i AND fonction_id=%i",
                       $val[0],
                       $val[1]
                    );
                } else {
                    DB::update(
                        $pre.'rights',
                        array(
                            'authorized' => 1
                       ),
                       "id = %i AND fonction_id=%i",
                       $val[0],
                       $val[1]
                    );
                }
            }
            break;

        // CASE where to authorize an ITEM creation without respecting the complexity
        case "modif_droit_autorisation_sans_complexite":
            /* do checks */
            require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
            if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_folders")) {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $_SESSION['settings']['cpassman_dir'].'/error.php';
                exit();
            }
            // send query
            DB::update(
                $pre.'nested_tree',
                array(
                    'bloquer_creation' => $_POST['droit']
                ),
                "id = %i",
                $_POST['id']
            );
            break;

        // CASE where to authorize an ITEM modification without respecting the complexity
        case "modif_droit_modification_sans_complexite":
            /* do checks */
            require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
            if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "manage_folders")) {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $_SESSION['settings']['cpassman_dir'].'/error.php';
                exit();
            }

            // send query
            DB::update(
                $pre.'nested_tree',
                array(
                    'bloquer_modification' => $_POST['droit']
                ),
                "id = %i",
                $_POST['id']
            );
            break;
    }
}
