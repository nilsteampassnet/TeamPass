<?php
/**
 * @file          folders.queries.php
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

require_once 'SecureHandler.php';
session_start();
if (
    !isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1 ||
    !isset($_SESSION['user_id']) || empty($_SESSION['user_id']) ||
    !isset($_SESSION['key']) || empty($_SESSION['key']))
{
    die('Hacking attempt...');
}

/* do checks */
require_once $_SESSION['settings']['cpassman_dir'].'/includes/config/include.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/checks.php';
if (!checkUser($_SESSION['user_id'], $_SESSION['key'], "folders")) {
    $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
    include $_SESSION['settings']['cpassman_dir'].'/error.php';
    exit();
}

include $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$_SESSION['user_language'].'.php';
include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
header("Content-type: text/html; charset==utf-8");
require_once 'main.functions.php';
require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

//Connect to mysql server
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

//Build tree
$tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

//Load AES
$aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
$aes->register();

// CASE where title is changed
if (isset($_POST['newtitle'])) {
    $id = explode('_', $_POST['id']);
    //update DB
    DB::update(
        prefix_table("nested_tree"),
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
            prefix_table("nested_tree"),
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
        prefix_table("nested_tree"),
        array(
            'parent_id' => $_POST['newparent_id']
       ),
        "id=%i",
        $id[1]
    );
    //Get the title to display it
    $data = DB::queryfirstrow("SELECT title FROM ".prefix_table("nested_tree")." WHERE id = %i", $_POST['newparent_id']);
    //show value
    echo ($data['title']);
    //rebuild the tree grid
    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
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
    $tmp = DB::query("SELECT * FROM ".prefix_table("misc")." WHERE type = %s' AND intitule = %i", "complex", $id[1]);
    $counter = DB::count();
    if ($counter == 0) {
        //Insert into DB
        DB::insert(
            prefix_table("misc"),
            array(
                'type' => 'complex',
                'intitule' => $id[1],
                'valeur' => $_POST['changer_complexite']
           )
        );
    } else {
        //update DB
        DB::update(
            prefix_table("misc"),
            array(
                'valeur' => $_POST['changer_complexite']
           ),
            "type=%s AND  intitule = %i",
            "complex",
            $id[1]
        );
    }

    //Get title to display it
    echo $_SESSION['settings']['pwComplexity'][$_POST['changer_complexite']][1];

    //rebuild the tree grid
    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
    $tree->rebuild();

    // Several other cases
} elseif (isset($_POST['type'])) {
    switch ($_POST['type']) {
        // CASE where DELETING a group
        case "delete_folder":
            // Check KEY and rights
            if ($_POST['key'] !== $_SESSION['key'] || $_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            $error = "";

            // user shall not delete personal folder
            $data = DB::queryfirstrow(
                "SELECT personal_folder
                FROM ".prefix_table("nested_tree")."
                WHERE id = %i",
                $_POST['id']
            );
            if ($data['personal_folder'] === "1") {
                $pf = true;
            } else {
                $pf = false;
            }

            // this will delete all sub folders and items associated
            $foldersDeleted = "";
            $folderForDel = array();
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

            // get parent folder
            $parent = $tree->getPath($_POST['id']);
            $parent = array_pop($parent);
            if ($parent->id === null || $parent->id === "undefined") {
                $parent_id = "";
            } else {
                $parent_id = $parent->id;
            }

            // Get through each subfolder
            $folders = $tree->getDescendants($_POST['id'], true);

            if (count($folders) > 1) {
                echo prepareExchangedData(array("error" => "ERR_SUB_FOLDERS_EXIST"), "encode");
                break;
            }

            foreach ($folders as $folder) {
                if (($folder->parent_id > 0 || $folder->parent_id == 0) && $folder->title != $_SESSION['user_id'] ) {
                    //Store the deleted folder (recycled bin)
                    if ($pf === false) {
                        DB::insert(
                            prefix_table("misc"),
                            array(
                                'type' => 'folder_deleted',
                                'intitule' => "f".$folder->id,
                                'valeur' => $folder->id.', '.$folder->parent_id.', '.
                                    $folder->title.', '.$folder->nleft.', '.$folder->nright.', '.
                                    $folder->nlevel.', 0, 0, 0, 0'
                           )
                        );

                        //array for delete folder
                        $folderForDel[] = $folder->id;

                        //delete items & logs
                        $items = DB::query(
                            "SELECT id FROM ".prefix_table("items")." WHERE id_tree=%i",
                            $folder->id
                        );
                        foreach ($items as $item) {
                            DB::update(
                                prefix_table("items"),
                                array(
                                    'inactif' => '1',
                                ),
                                "id = %i",
                                $item['id']
                            );
                            //log
                            DB::insert(
                                prefix_table("log_items"),
                                array(
                                    'id_item' => $item['id'],
                                    'date' => time(),
                                    'id_user' => $_SESSION['user_id'],
                                    'action' => 'at_delete'
                                )
                            );

                            //Update CACHE table
                            updateCacheTable("delete_value", $item['id']);
                        }
                    }
                    //array for delete folder
                    $folderForDel[] = $folder->id;

                    //delete items & logs
                    $items = DB::query(
                        "SELECT id FROM ".prefix_table("items")." WHERE id_tree=%i",
                        $folder->id
                    );

                    //Actualize the variable
                    $_SESSION['nb_folders'] --;
                }
            }

            // delete folder from SESSION
            if(($key = array_search($_POST['id'], $_SESSION['groupes_visibles'])) !== false) {
                unset($folders[$key]);
            }

            //rebuild tree
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
            $tree->rebuild();

            // delete folders
            $folderForDel = array_unique($folderForDel);
            foreach ($folderForDel as $fol){
                DB::delete(prefix_table("nested_tree"), "id = %i", $fol);
            }

            echo prepareExchangedData(array("error" => "", "parent_id" => $parent_id), "encode");

            break;


        // CASE where DELETING multiple groups
        case "delete_multiple_folders":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }
            //decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");
            $error = "";
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
            $folderForDel = array();

            foreach (explode(';', $dataReceived['foldersList']) as $folderId) {
                if (!in_array($folderId, $folderForDel)) {
                    $foldersDeleted = "";
                    // Get through each subfolder
                    $folders = $tree->getDescendants($folderId, true);
                    foreach ($folders as $folder) {
                        if (($folder->parent_id > 0 || $folder->parent_id == 0) && $folder->title != $_SESSION['user_id']) {
                            //Store the deleted folder (recycled bin)
                            DB::insert(
                                prefix_table("misc"),
                                array(
                                    'type' => 'folder_deleted',
                                    'intitule' => "f".$folder->id,
                                    'valeur' => $folder->id.', '.$folder->parent_id.', '.
                                        $folder->title.', '.$folder->nleft.', '.$folder->nright.', '.
                                        $folder->nlevel.', 0, 0, 0, 0'
                                )
                            );
                            //array for delete folder
                            $folderForDel[] = $folder->id;

                            //delete items & logs
                            $items = DB::query("SELECT id FROM ".prefix_table("items")." WHERE id_tree=%i", $folder->id);
                            foreach ($items as $item) {
                                DB::update(
                                    prefix_table("items"),
                                    array(
                                        'inactif' => '1',
                                    ),
                                    "id = %i",
                                    $item['id']
                                );
                                //log
                                DB::insert(
                                    prefix_table("log_items"),
                                    array(
                                        'id_item' => $item['id'],
                                        'date' => time(),
                                        'id_user' => $_SESSION['user_id'],
                                        'action' => 'at_delete'
                                    )
                                );

                                // delete folder from SESSION
                                if(($key = array_search($item['id'], $_SESSION['groupes_visibles'])) !== false) {
                                    unset($_SESSION['groupes_visibles'][$item['id']]);
                                }

                                //Update CACHE table
                                updateCacheTable("delete_value", $item['id']);
                            }

                            //Actualize the variable
                            $_SESSION['nb_folders'] --;
                        }
                    }

                    // delete folders
                    $folderForDel=array_unique($folderForDel);
                    foreach ($folderForDel as $fol){
                        DB::delete(prefix_table("nested_tree"), "id = %i", $fol);
                    }
                }
            }

            //rebuild tree
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
            $tree->rebuild();

            echo '[ { "error" : "'.$error.'" } ]';

            break;

        //CASE where ADDING a new group
        case "add_folder":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            $error = $newId = $droplist = "";

            //decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

            //Prepare variables
            $title = htmlspecialchars_decode($dataReceived['title']);
            $complexity = htmlspecialchars_decode($dataReceived['complexity']);
            $parentId = htmlspecialchars_decode($dataReceived['parent_id']);
            $renewalPeriod = htmlspecialchars_decode($dataReceived['renewal_period']);

            //Check if title doesn't contains html codes
            if (preg_match_all("|<[^>]+>(.*)</[^>]+>|U", $title, $out)) {
                echo '[ { "error" : "error_html_codes"} ]';
                break;
            }

            // check if title is numeric
            if (is_numeric($title) === true) {
                echo '[ { "error" : "error_title_only_with_numbers"} ]';
                break;
            }

            //Check if duplicate folders name are allowed
            $createNewFolder = true;
            if (isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 0) {
                DB::query("SELECT * FROM ".prefix_table("nested_tree")." WHERE title = %s", $title);
                $counter = DB::count();
                if ($counter != 0) {
                    $error = 'error_group_exist';
                    $createNewFolder = false;
                }
            }

            if ($createNewFolder == true) {
                //check if parent folder is personal
                $data = DB::queryfirstrow(
                    "SELECT personal_folder, bloquer_creation, bloquer_modification
                    FROM ".prefix_table("nested_tree")."
                    WHERE id = %i",
                    $parentId
                );

                // inherit from parent the specific settings it has
                if (DB::count() > 0) {
                    $parentBloquerCreation = $data['bloquer_creation'];
                    $parentBloquerModification = $data['bloquer_modification'];
                } else {
                    $parentBloquerCreation = 0;
                    $parentBloquerModification = 0;
                }

                if ($data['personal_folder'] === "1") {
                    $isPersonal = 1;
                } else {
                    $isPersonal = 0;

                    // check if complexity level is good
                    // if manager or admin don't care
                    if ($_SESSION['is_admin'] != 1 && $_SESSION['user_manager'] != 1) {
                        // get complexity level for this folder
                        $data = DB::queryfirstrow(
                            "SELECT valeur
                            FROM ".prefix_table("misc")."
                            WHERE intitule = %i AND type = %s",
                            $parentId,
                            "complex"
                        );
                        if (intval($complexity) < intval($data['valeur'])) {
                            echo '[ { "error" : "'.addslashes($LANG['error_folder_complexity_lower_than_top_folder']." [<b>".$_SESSION['settings']['pwComplexity'][$data['valeur']][1]).'</b>]"} ]';
                            break;
                        }
                    }
                }


                if (
                    $isPersonal == 1
                    || $_SESSION['is_admin'] == 1
                    || ($_SESSION['user_manager'] == 1)
                    || (isset($_SESSION['settings']['enable_user_can_create_folders'])
                    && $_SESSION['settings']['enable_user_can_create_folders'] == 1)
                ) {
                    //create folder
                    DB::insert(
                        prefix_table("nested_tree"),
                        array(
                            'parent_id' => $parentId,
                            'title' => $title,
                            'personal_folder' => $isPersonal,
                            'renewal_period' => $renewalPeriod,
                            'bloquer_creation' => isset($dataReceived['block_creation']) && $dataReceived['block_creation'] == 1 ? '1' : $parentBloquerCreation,
                            'bloquer_modification' => isset($dataReceived['block_modif']) && $dataReceived['block_modif'] == 1 ? '1' : $parentBloquerModification
                       )
                    );
                    $newId = DB::insertId();

                    //Add complexity
                    DB::insert(
                        prefix_table("misc"),
                        array(
                            'type' => 'complex',
                            'intitule' => $newId,
                            'valeur' => $complexity
                        )
                    );

                    // add new folder id in SESSION
                    array_push($_SESSION['groupes_visibles'], $newId);
                    if ($isPersonal == 1) {
                        array_push($_SESSION['personal_folders'], $newId);
                    }

                    // rebuild tree
                    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
                    $tree->rebuild();

                    if (
                        $isPersonal != 1
                        && isset($_SESSION['settings']['subfolder_rights_as_parent'])
                        && $_SESSION['settings']['subfolder_rights_as_parent'] == 1
                        && $_SESSION['is_admin'] !== 0
                        || ($isPersonal != 1 && $parentId === "0")
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
                            if (!empty($role)) {
                                DB::insert(
                                    prefix_table("roles_values"),
                                    array(
                                        'role_id' => $role,
                                        'folder_id' => $newId,
                                        'type' => "W"
                                    )
                                );
                            }
                        }
                    }

                    //If it is a subfolder, then give access to it for all roles that allows the parent folder
                    $rows = DB::query("SELECT role_id, type FROM ".prefix_table("roles_values")." WHERE folder_id = %i", $parentId);
                    foreach ($rows as $record) {
                        //add access to this subfolder
                        DB::insert(
                            prefix_table("roles_values"),
                            array(
                                'role_id' => $record['role_id'],
                                'folder_id' => $newId,
                                'type' => $record['type']
                            )
                        );
                    }

                    // if parent folder has Custom Fields Categories then add to this child one too
                    $rows = DB::query("SELECT id_category FROM ".prefix_table("categories_folders")." WHERE id_folder = %i", $parentId);
                    foreach ($rows as $record) {
                        //add CF Category to this subfolder
                        DB::insert(
                            prefix_table("categories_folders"),
                            array(
                                'id_category' => $record['id_category'],
                                'id_folder' => $newId
                            )
                        );
                    }

                    // rebuild the droplist
                    $prev_level = 0;
                    $tst = $tree->getDescendants();
                    $droplist .= '<option value=\"na\">---'.addslashes($LANG['select']).'---</option>';
                    if ($_SESSION['is_admin'] == 1 || $_SESSION['can_create_root_folder'] == 1) {
                        $droplist .= '<option value=\"0\">'.addslashes($LANG['root']).'</option>';
                    }
                    foreach ($tst as $t) {
                        if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups'])) {
                            $ident = "";
                            for ($x = 1; $x < $t->nlevel; $x++) {
                                $ident .= "&nbsp;&nbsp;";
                            }
                            if ($prev_level < $t->nlevel) {
                                $droplist .= '<option value=\"'.$t->id.'\">'.$ident.addslashes(str_replace("'", "&lsquo;", $t->title)).'</option>';
                            } elseif ($prev_level == $t->nlevel) {
                                $droplist .= '<option value=\"'.$t->id.'\">'.$ident.addslashes(str_replace("'", "&lsquo;", $t->title)).'</option>';
                            } else {
                                $droplist .= '<option value=\"'.$t->id.'\">'.$ident.addslashes(str_replace("'", "&lsquo;", $t->title)).'</option>';
                            }
                            $prev_level = $t->nlevel;
                        }
                    }
                }
                else{
                    $error = $LANG['error_not_allowed_to'];
                }
            }
            echo '[ { "error" : "'.addslashes($error).'" , "newid" : "'.$newId.'" , "droplist" : "'.$droplist.'" } ]';

            break;

        //CASE where UPDATING a new group
        case "update_folder":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }
            $error = $droplist = "";

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

            // check if title is numeric
            if (is_numeric($title) === true) {
                echo '[ { "error" : "error_title_only_with_numbers"} ]';
                break;
            }

            //Check if duplicate folders name are allowed
            $createNewFolder = true;
            if (isset($_SESSION['settings']['duplicate_folder']) && $_SESSION['settings']['duplicate_folder'] == 0) {
                $data = DB::queryfirstrow(
                    "SELECT id, title FROM ".prefix_table("nested_tree")." WHERE title = %s", $title);
                if (!empty($data['id']) && $dataReceived['id'] != $data['id'] && $title != $data['title'] ) {
                    echo '[ { "error" : "error_group_exist" } ]';
                    break;
                }
            }

            // check if complexity level is good
            // if manager or admin don't care
            if ($_SESSION['is_admin'] != 1 && ($_SESSION['user_manager'] != 1)) {
                $data = DB::queryfirstrow(
                    "SELECT valeur
                    FROM ".prefix_table("misc")."
                    WHERE intitule = %i AND type = %s",
                    $parentId,
                    "complex"
                );
                if (intval($complexity) < intval($data['valeur'])) {
                    echo '[ { "error" : "'.addslashes($LANG['error_folder_complexity_lower_than_top_folder']." [<b>".$_SESSION['settings']['pwComplexity'][$data['valeur']][1]).'</b>]"} ]';
                    break;
                }
            }

            DB::update(
                prefix_table("nested_tree"),
                array(
                    'parent_id' => $parentId,
                    'title' => $title,
                    'personal_folder' => 0,
                    'renewal_period' => $renewalPeriod,
                    'bloquer_creation' => $dataReceived['block_creation'] == 1 ? '1' : '0',
                    'bloquer_modification' => $dataReceived['block_modif'] == 1 ? '1' : '0'
                ),
                "id=%i",
                $dataReceived['id']
            );

            //Add complexity
            DB::update(
                prefix_table("misc"),
                array(
                    'valeur' => $complexity
                ),
                "intitule = %s AND type = %s",
                $dataReceived['id'],
                "complex"
            );

            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
            $tree->rebuild();

            //Get user's rights
            identifyUserRights(implode(";", $_SESSION['groupes_visibles']).';'.$dataReceived['id'], implode(";", $_SESSION['groupes_interdits']), $_SESSION['is_admin'], $_SESSION['fonction_id'], true);


            // rebuild the droplist
            $prev_level = 0;
            $tst = $tree->getDescendants();
            $droplist .= '<option value=\"na\">---'.addslashes($LANG['select']).'---</option>';
            if ($_SESSION['is_admin'] == 1 || $_SESSION['can_create_root_folder'] == 1) {
                $droplist .= '<option value=\"0\">'.addslashes($LANG['root']).'</option>';
            }
            foreach ($tst as $t) {
                if (in_array($t->id, $_SESSION['groupes_visibles']) && !in_array($t->id, $_SESSION['personal_visible_groups']) && $t->personal_folder == 0) {
                    $ident = "";
                    for ($x = 1; $x < $t->nlevel; $x++) {
                        $ident .= "&nbsp;&nbsp;";
                    }
                    if ($prev_level < $t->nlevel) {
                        $droplist .= '<option value=\"'.$t->id.'\">'.$ident.addslashes(str_replace("'", "&lsquo;", $t->title)).'</option>';
                    } elseif ($prev_level == $t->nlevel) {
                        $droplist .= '<option value=\"'.$t->id.'\">'.$ident.addslashes(str_replace("'", "&lsquo;", $t->title)).'</option>';
                    } else {
                        $droplist .= '<option value=\"'.$t->id.'\">'.$ident.addslashes(str_replace("'", "&lsquo;", $t->title)).'</option>';
                    }
                    $prev_level = $t->nlevel;
                }
            }

            echo '[ { "error" : "'.$error.'" , "droplist" : "'.$droplist.'" } ]';
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
            $data = DB::queryfirstrow("SELECT authorized FROM ".prefix_table("rights")." WHERE tree_id = %i AND fonction_id= %i", $val[0], $val[1]);
            if (empty($data['authorized'])) {
                //Insert into DB
                DB::insert(
                    prefix_table("rights"),
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
                        prefix_table("rights"),
                        array(
                            'authorized' => 0
                       ),
                       "id = %i AND fonction_id=%i",
                       $val[0],
                       $val[1]
                    );
                } else {
                    DB::update(
                        prefix_table("rights"),
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

            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            // send query
            DB::update(
                prefix_table("nested_tree"),
                array(
                    'bloquer_creation' => $_POST['value']
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

            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            // send query
            DB::update(
                prefix_table("nested_tree"),
                array(
                    'bloquer_modification' => $_POST['value']
                ),
                "id = %i",
                $_POST['id']
            );
            break;


        // CASE where selecting/deselecting sub-folders
        case "select_sub_folders":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            // get sub folders
            $subfolders = "";
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

            // Get through each subfolder
            $folders = $tree->getDescendants($_POST['id'], false);
            foreach ($folders as $folder) {
                if($subfolders === "") {
                    $subfolders = $folder->id;
                } else {
                    $subfolders .= ";" . $folder->id;
                }
            }

            echo '[ { "error" : "" , "subfolders" : "'.$subfolders.'" } ]';

            break;

        case "get_list_of_folders":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] == true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            //Load Tree
            $tree = new SplClassLoader('Tree\NestedTree', './includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');
            $folders = $tree->getDescendants();
            $prevLevel = 0;

            // show list of all folders
            $arrFolders = [];
            foreach ($folders as $t) {
                if (in_array($t->id, $_SESSION['groupes_visibles'])) {
                    if (!is_numeric($t->title)) {
                        $ident = "&nbsp;&nbsp;";
                        for ($x=1; $x<$t->nlevel; $x++) {
                            $ident .= "&nbsp;&nbsp;";
                        }

                        array_push($arrFolders, '<option value=\"'.$t->id.'\">'.$ident.addslashes($t->title).'</option>');
                        $prevLevel = $t->nlevel;
                    }
                }
            }

            echo '[ { "error" : "" , "list_folders" : "'.implode(";", $arrFolders).'" } ]';

            break;

        case "copy_folder":
            // Check KEY and rights
            if ($_POST['key'] != $_SESSION['key'] || $_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(array("error" => "ERR_KEY_NOT_CORRECT"), "encode");
                break;
            }

            // Check KEY
            if ($_POST['key'] != $_SESSION['key']) {
                // error
                exit();
            }

            //decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData($_POST['data'], "decode");

            //Prepare variables
            $source_folder_id = htmlspecialchars_decode($dataReceived['source_folder_id']);
            $target_folder_id = htmlspecialchars_decode($dataReceived['target_folder_id']);

            //Load Tree
            $tree = new SplClassLoader('Tree\NestedTree', './includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

            // get info about target folder
            $nodeTarget = $tree->getNode($target_folder_id);

            // get list of all folders
            $nodeDescendants = $tree->getDescendants($source_folder_id, true, false, false);
            $parentId = "";
            $tabNodes = [];
            foreach ($nodeDescendants as $node) {
                // step1 - copy folder

                // get info about current node
                $nodeInfo = $tree->getNode($node->id);

                // get complexity of current node
                $nodeComplexity = DB::queryfirstrow("SELECT valeur FROM ".prefix_table("misc")." WHERE intitule = %i AND type= %s", $nodeInfo->id, 'complex');

                // prepare parent Id
                if (empty($parentId)) {
                    $parentId = $target_folder_id;
                } else {
                    $parentId = $tabNodes[$nodeInfo->parent_id];
                }

                //create folder
                DB::insert(
                    prefix_table("nested_tree"),
                    array(
                        'parent_id' => $parentId,
                        'title' => $nodeInfo->title,
                        'personal_folder' => $nodeInfo->personal_folder,
                        'renewal_period' => $nodeInfo->renewal_period,
                        'bloquer_creation' => $nodeInfo->bloquer_creation,
                        'bloquer_modification' => $nodeInfo->bloquer_modification
                   )
                );
                $newFolderId = DB::insertId();

                // add to correspondance matrix
                $tabNodes[$nodeInfo->id] = $newFolderId;

                //Add complexity
                DB::insert(
                    prefix_table("misc"),
                    array(
                        'type' => 'complex',
                        'intitule' => $newFolderId,
                        'valeur' => $nodeComplexity['valeur']
                    )
                );

                // add new folder id in SESSION
                array_push($_SESSION['groupes_visibles'], $newFolderId);
                if ($nodeInfo->personal_folder == 1) {
                    array_push($_SESSION['personal_folders'], $newFolderId);
                }


                if (
                    $nodeInfo->personal_folder != 1
                    && isset($_SESSION['settings']['subfolder_rights_as_parent'])
                    && $_SESSION['settings']['subfolder_rights_as_parent'] == 1
                    && $_SESSION['is_admin'] !== 0
                ){
                    //Get user's rights
                    @identifyUserRights(
                        $_SESSION['groupes_visibles'].';'.$newFolderId,
                        $_SESSION['groupes_interdits'],
                        $_SESSION['is_admin'],
                        $_SESSION['fonction_id'],
                        true
                    );

                    //add access to this new folder
                    foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                        if (!empty($role)) {
                            DB::insert(
                                prefix_table("roles_values"),
                                array(
                                    'role_id' => $role,
                                    'folder_id' => $newFolderId,
                                    'type' => "W"
                                )
                            );
                        }
                    }
                }

                //If it is a subfolder, then give access to it for all roles that allows the parent folder
                $rows = DB::query(
                    "SELECT role_id, type
                    FROM ".prefix_table("roles_values")."
                    WHERE folder_id = %i",
                    $nodeInfo->id
                );
                foreach ($rows as $record) {
                    //add access to this subfolder
                    DB::insert(
                        prefix_table("roles_values"),
                        array(
                            'role_id' => $record['role_id'],
                            'folder_id' => $newFolderId,
                            'type' => $record['type']
                        )
                    );
                }

                // if parent folder has Custom Fields Categories then add to this child one too
                $rows = DB::query("SELECT id_category FROM ".prefix_table("categories_folders")." WHERE id_folder = %i", $nodeInfo->id);
                foreach ($rows as $record) {
                    //add CF Category to this subfolder
                    DB::insert(
                        prefix_table("categories_folders"),
                        array(
                            'id_category' => $record['id_category'],
                            'id_folder' => $newFolderId
                        )
                    );
                }


                // step2 - copy items

                $rows = DB::query(
                    "SELECT *
                    FROM ".prefix_table("items")."
                    WHERE id_tree = %i",
                    $nodeInfo->id
                );
                foreach ($rows as $record) {
                    // check if item is deleted
                    // if it is then don't copy it
                    $item_deleted = DB::queryFirstRow(
                        "SELECT *
                        FROM ".prefix_table("log_items")."
                        WHERE id_item = %i AND action = %s
                        ORDER BY date DESC
                        LIMIT 0, 1",
                        $record['id'],
                        "at_delete"
                    );
                    $dataDeleted = DB::count();

                    $item_restored = DB::queryFirstRow(
                        "SELECT *
                        FROM ".prefix_table("log_items")."
                        WHERE id_item = %i AND action = %s
                        ORDER BY date DESC
                        LIMIT 0, 1",
                        $record['id'],
                        "at_restored"
                    );

                    if ($dataDeleted === "0" || intval($item_deleted['date']) < intval($item_restored['date'])) {

                        // decrypt and re-encrypt password
                        $decrypt = cryption(
                            $record['pw'],
                            "",
                            "decrypt"
                        );
                        $originalRecord = cryption(
                            $decrypt['string'],
                            "",
                            "encrypt"
                        );

                        // insert the new record and get the new auto_increment id
                        DB::insert(
                            prefix_table("items"),
                            array(
                                'label' => "duplicate",
                                'id_tree' => $newFolderId,
                                'pw' => $originalRecord['string'],
                                'pw_iv' => '',
                                'perso' => 0,
                                'viewed_no' => 0
                            )
                        );
                        $newItemId = DB::insertId();

                        // generate the query to update the new record with the previous values
                        $aSet = array();
                        foreach ($record as $key => $value) {
                            if (
                                $key !== "id" && $key !== "key" && $key !== "id_tree"
                                && $key !== "viewed_no" && $key !== "pw" && $key !== "pw_iv"
                                && $key !== "perso"
                            ) {
                                array_push($aSet, array($key => $value));
                            }
                        }
                        DB::update(
                            prefix_table("items"),
                            $aSet,
                            "id = %i",
                            $newItemId
                        );

                        // Add attached itms
                        $rows = DB::query(
                            "SELECT * FROM ".prefix_table("files")." WHERE id_item=%i",
                            $newItemId
                        );
                        foreach ($rows as $record) {
                            DB::insert(
                                prefix_table('files'),
                                array(
                                    'id_item' => $newID,
                                    'name' => $record['name'],
                                    'size' => $record['size'],
                                    'extension' => $record['extension'],
                                    'type' => $record['type'],
                                    'file' => $record['file']
                                   )
                            );
                        }
                        // Add this duplicate in logs
                        logItems($newItemId, $record['label'], $_SESSION['user_id'], 'at_creation', $_SESSION['login']);
                        // Add the fact that item has been copied in logs
                        logItems($newItemId, $record['label'], $_SESSION['user_id'], 'at_copy', $_SESSION['login']);
                    }
                }
            }


            // rebuild tree
            $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
            $tree->rebuild();

            // reload cache table
            require_once $_SESSION['settings']['cpassman_dir'].'/sources/main.functions.php';
            updateCacheTable("reload", "");

            echo '[ { "error" : "" } ]';

            break;
    }
}
