<?php
/**
 * @author        Nils Laumaillé <nils@teampass.net>
 *
 * @version       2.1.27
 *
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 *
 * @see          https://www.teampass.net
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */
require_once 'SecureHandler.php';
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
if (checkUser($_SESSION['user_id'], $_SESSION['key'], 'folders', $SETTINGS) === false) {
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
require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

// Connect to mysql server
require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
$pass = defuse_return_decrypted($pass);
$link = mysqli_connect(DB_HOST, DB_USER, defuse_return_decrypted(DB_PASSWD), DB_NAME, DB_PORT);
$link->set_charset(DB_ENCODING);

// Build tree
$tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
$tree->register();
$tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

// Prepare POST variables
$post_type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
$post_data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING);
$post_key = filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING);
$post_id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
$post_newtitle = filter_input(INPUT_POST, 'newtitle', FILTER_SANITIZE_STRING);
$post_renewal_period = filter_input(INPUT_POST, 'renewal_period', FILTER_SANITIZE_STRING);
$post_newparent_id = filter_input(INPUT_POST, 'newparent_id', FILTER_SANITIZE_STRING);
$post_changer_complexite = filter_input(INPUT_POST, 'changer_complexite', FILTER_SANITIZE_STRING);

// Ensure Complexity levels are translated
if (defined('TP_PW_COMPLEXITY') === false) {
    define(
        'TP_PW_COMPLEXITY',
        array(
        0 => array(0, langHdl('complex_level0')),
        25 => array(25, langHdl('complex_level1')),
        50 => array(50, langHdl('complex_level2')),
        60 => array(60, langHdl('complex_level3')),
        70 => array(70, langHdl('complex_level4')),
        80 => array(80, langHdl('complex_level5')),
        90 => array(90, langHdl('complex_level6')),
        )
    );
}

// CASE where title is changed
if (null !== $post_newtitle) {
    $id = explode('_', $post_id);
    // Update DB
    DB::update(
        prefixTable('nested_tree'),
        array(
            'title' => mysqli_escape_string($link, stripslashes($post_newtitle)),
        ),
        'id=%i',
        $id[1]
    );
    //Show value
    echo htmlentities($post_newtitle, ENT_QUOTES);

// CASE where RENEWAL PERIOD is changed
} elseif (null !== $post_renewal_period && null === $post_type) {
    // Check if renewal period is an integer
    if (parseInt($post_renewal_period)) {
        $id = explode('_', filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING));
        //update DB
        DB::update(
            prefixTable('nested_tree'),
            array(
                'renewal_period' => mysqli_escape_string($link, stripslashes($post_renewal_period)),
            ),
            'id=%i',
            $id[1]
        );
        //Show value
        echo htmlentities($post_renewal_period, ENT_QUOTES);
    } else {
        //Show ERROR
        echo $LANG['error_renawal_period_not_integer'];
    }

    // CASE where the parent is changed
} elseif (null !== $post_newparent_id) {
    $id = explode('_', filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING));
    //Store in DB
    DB::update(
        prefixTable('nested_tree'),
        array(
            'parent_id' => $post_newparent_id,
        ),
        'id=%i',
        $id[1]
    );

    //Get the title to display it
    $data = DB::queryfirstrow(
        'SELECT title
        FROM '.prefixTable('nested_tree').'
        WHERE id = %i',
        $post_newparent_id
    );

    //show value
    echo $data['title'];

    //rebuild the tree grid
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $tree->rebuild();

// CASE where complexity is changed
} elseif (null !== $post_changer_complexite) {
    /* do checks */
    require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
    if (!checkUser($_SESSION['user_id'], $_SESSION['key'], 'manage_folders', $SETTINGS)) {
        $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
        include $SETTINGS['cpassman_dir'].'/error.php';
        exit();
    }

    $id = explode('_', filter_input(INPUT_POST, 'id', FILTER_SANITIZE_STRING));

    // Check if group exists
    $tmp = DB::query(
        'SELECT * FROM '.prefixTable('misc')."
        WHERE type = %s' AND intitule = %i",
        'complex',
        $id[1]
    );
    $counter = DB::count();
    if ($counter == 0) {
        // Insert into DB
        DB::insert(
            prefixTable('misc'),
            array(
                'type' => 'complex',
                'intitule' => $id[1],
                'valeur' => $post_changer_complexite,
            )
        );
    } else {
        // Update DB
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => $post_changer_complexite,
            ),
            'type=%s AND  intitule = %i',
            'complex',
            $id[1]
        );
    }

    // Get title to display it
    echo $SETTINGS_EXT['pwComplexity'][$post_changer_complexite][1];

    //rebuild the tree grid
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $tree->rebuild();

// Several other cases
} elseif (null !== $post_type) {
    switch ($post_type) {
        // CASE where DELETING a group
        case 'delete_folder':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }
            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // prepare variables
            $post_folder_id = filter_var(
                htmlspecialchars_decode($dataReceived['folder_id'], ENT_QUOTES),
                FILTER_SANITIZE_NUMBER_INT
            );

            // User shall not delete personal folder
            $data = DB::queryfirstrow(
                'SELECT personal_folder
                FROM '.prefixTable('nested_tree').'
                WHERE id = %i',
                $post_folder_id
            );
            if ($data['personal_folder'] === '1') {
                $pf = true;
            } else {
                $pf = false;
            }

            // this will delete all sub folders and items associated
            $foldersDeleted = '';
            $folderForDel = array();
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

            // get parent folder
            $parent = $tree->getPath($post_folder_id);
            $parent = array_pop($parent);
            if ($parent !== null) {
                if ($parent->id === null || $parent->id === 'undefined') {
                    $parent_id = '';
                } else {
                    $parent_id = $parent->id;
                }
            } else {
                $parent_id = 0;
            }

            // Get through each subfolder
            $folders = $tree->getDescendants($post_folder_id, true);

            if (count($folders) > 1) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_cannot_delete_subfolders_exist'),
                    ),
                    'encode'
                );
                break;
            }

            foreach ($folders as $folder) {
                if (($folder->parent_id > 0 || $folder->parent_id == 0) && $folder->title != $_SESSION['user_id']) {
                    //Store the deleted folder (recycled bin)
                    if ($pf === false) {
                        DB::insert(
                            prefixTable('misc'),
                            array(
                                'type' => 'folder_deleted',
                                'intitule' => 'f'.$folder->id,
                                'valeur' => $folder->id.', '.$folder->parent_id.', '.
                                    $folder->title.', '.$folder->nleft.', '.$folder->nright.', '.
                                    $folder->nlevel.', 0, 0, 0, 0',
                            )
                        );

                        //array for delete folder
                        $folderForDel[] = $folder->id;

                        //delete items & logs
                        $items = DB::query(
                            'SELECT id FROM '.prefixTable('items').' WHERE id_tree=%i',
                            $folder->id
                        );
                        foreach ($items as $item) {
                            DB::update(
                                prefixTable('items'),
                                array(
                                    'inactif' => '1',
                                ),
                                'id = %i',
                                $item['id']
                            );
                            //log
                            DB::insert(
                                prefixTable('log_items'),
                                array(
                                    'id_item' => $item['id'],
                                    'date' => time(),
                                    'id_user' => $_SESSION['user_id'],
                                    'action' => 'at_delete',
                                )
                            );

                            //Update CACHE table
                            updateCacheTable('delete_value', $item['id']);
                        }
                    }
                    //array for delete folder
                    $folderForDel[] = $folder->id;

                    //delete items & logs
                    $items = DB::query(
                        'SELECT id FROM '.prefixTable('items').' WHERE id_tree=%i',
                        $folder->id
                    );

                    //Actualize the variable
                    --$_SESSION['nb_folders'];
                }
            }

            // delete folder from SESSION
            if (($key = array_search($post_id, $_SESSION['groupes_visibles'])) !== false) {
                unset($folders[$key]);
            }

            //rebuild tree
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();

            // delete folders
            $folderForDel = array_unique($folderForDel);
            foreach ($folderForDel as $fol) {
                DB::delete(prefixTable('nested_tree'), 'id = %i', $fol);
            }

            echo prepareExchangedData(
                array(
                    'error' => false,
                    'parent_id' => $parent_id,
                ),
                'encode'
            );

            break;

        // CASE where DELETING multiple groups
        case 'delete_multiple_folders':
            // Check KEY and rights
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key'] || $_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(array('error' => 'ERR_KEY_NOT_CORRECT'), 'encode');
                break;
            }
            //decrypt and retreive data in JSON format
            $dataReceived = prepareExchangedData(filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES), 'decode');
            $error = '';
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $folderForDel = array();

            foreach (explode(';', $dataReceived['foldersList']) as $folderId) {
                if (!in_array($folderId, $folderForDel)) {
                    $foldersDeleted = '';
                    // Get through each subfolder
                    $folders = $tree->getDescendants($folderId, true);
                    foreach ($folders as $folder) {
                        if (($folder->parent_id > 0 || $folder->parent_id == 0) && $folder->title != $_SESSION['user_id']) {
                            //Store the deleted folder (recycled bin)
                            DB::insert(
                                prefixTable('misc'),
                                array(
                                    'type' => 'folder_deleted',
                                    'intitule' => 'f'.$folder->id,
                                    'valeur' => $folder->id.', '.$folder->parent_id.', '.
                                        $folder->title.', '.$folder->nleft.', '.$folder->nright.', '.
                                        $folder->nlevel.', 0, 0, 0, 0',
                                )
                            );
                            //array for delete folder
                            $folderForDel[] = $folder->id;

                            //delete items & logs
                            $items = DB::query('SELECT id FROM '.prefixTable('items').' WHERE id_tree=%i', $folder->id);
                            foreach ($items as $item) {
                                DB::update(
                                    prefixTable('items'),
                                    array(
                                        'inactif' => '1',
                                    ),
                                    'id = %i',
                                    $item['id']
                                );
                                //log
                                DB::insert(
                                    prefixTable('log_items'),
                                    array(
                                        'id_item' => $item['id'],
                                        'date' => time(),
                                        'id_user' => $_SESSION['user_id'],
                                        'action' => 'at_delete',
                                    )
                                );

                                // delete folder from SESSION
                                if (($key = array_search($item['id'], $_SESSION['groupes_visibles'])) !== false) {
                                    unset($_SESSION['groupes_visibles'][$item['id']]);
                                }

                                //Update CACHE table
                                updateCacheTable('delete_value', $item['id']);
                            }

                            //Actualize the variable
                            --$_SESSION['nb_folders'];
                        }
                    }

                    // delete folders
                    $folderForDel = array_unique($folderForDel);
                    foreach ($folderForDel as $fol) {
                        DB::delete(prefixTable('nested_tree'), 'id = %i', $fol);
                    }
                }
            }

            //rebuild tree
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();

            echo '[ { "error" : "'.$error.'" } ]';

            break;

        //CASE where ADDING a new group
        case 'add_folder':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // prepare variables
            $post_title = filter_var(htmlspecialchars_decode($dataReceived['label']), FILTER_SANITIZE_STRING);
            $post_parent_id = filter_var(
                htmlspecialchars_decode($dataReceived['parent_id'], ENT_QUOTES),
                FILTER_SANITIZE_NUMBER_INT
            );
            $post_complexicity = filter_var(
                htmlspecialchars_decode($dataReceived['complexicity'], ENT_QUOTES),
                FILTER_SANITIZE_NUMBER_INT
            );

            // Init
            $error = false;
            $newId = $access_level_by_role = $errorMessage = '';
            $renewalPeriod = 0;

            if ($post_parent_id === '0') {
                if (isset($dataReceived['access_level']) === true) {
                    $access_level_by_role = filter_var(
                        htmlspecialchars_decode($dataReceived['access_level']),
                        FILTER_SANITIZE_STRING
                    );
                } else {
                    if ($_SESSION['user_manager'] === '1'
                        || $_SESSION['user_can_manage_all_users'] === '1'
                    ) {
                        $access_level_by_role = 'W';
                    } else {
                        $access_level_by_role = '';
                    }
                }
            }

            // check if title is numeric
            if (is_numeric($post_title) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_only_numbers_in_folder_name'),
                    ),
                    'encode'
                );
                break;
            }

            //Check if duplicate folders name are allowed
            if (isset($SETTINGS['duplicate_folder']) === true
                && $SETTINGS['duplicate_folder'] === '0'
            ) {
                DB::query(
                    'SELECT *
                    FROM '.prefixTable('nested_tree').'
                    WHERE title = %s',
                    $post_title
                );
                $counter = DB::count();
                if ($counter !== 0) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_group_exist'),
                        ),
                        'encode'
                    );
                    break;
                }
            }

            //check if parent folder is personal
            $data = DB::queryfirstrow(
                'SELECT personal_folder, bloquer_creation, bloquer_modification
                FROM '.prefixTable('nested_tree').'
                WHERE id = %i',
                $post_parent_id
            );

            // inherit from parent the specific settings it has
            if (DB::count() > 0) {
                $parentBloquerCreation = $data['bloquer_creation'];
                $parentBloquerModification = $data['bloquer_modification'];
            } else {
                $parentBloquerCreation = 0;
                $parentBloquerModification = 0;
            }

            if ($data['personal_folder'] === '1') {
                $isPersonal = 1;
            } else {
                $isPersonal = 0;

                // check if complexity level is good
                // if manager or admin don't care
                if ($_SESSION['is_admin'] != 1
                    && ($_SESSION['user_manager'] !== '1'
                    || $_SESSION['user_can_manage_all_users'] !== '1')
                ) {
                    // get complexity level for this folder
                    $data = DB::queryfirstrow(
                        'SELECT valeur
                        FROM '.prefixTable('misc').'
                        WHERE intitule = %i AND type = %s',
                        $post_parent_id,
                        'complex'
                    );
                    if (intval($post_complexicity) < intval($data['valeur'])) {
                        echo prepareExchangedData(
                            array(
                                'error' => true,
                                'message' => langHdl('error_folder_complexity_lower_than_top_folder')
                                    .' [<b>'.TP_PW_COMPLEXITY[$data['valeur']][1].'</b>]',
                            ),
                            'encode'
                        );
                        break;
                    }
                }
            }

            if ($isPersonal == 1
                || $_SESSION['is_admin'] == 1
                || ($_SESSION['user_manager'] === '1' || $_SESSION['user_can_manage_all_users'] === '1')
                || (isset($SETTINGS['enable_user_can_create_folders'])
                && $SETTINGS['enable_user_can_create_folders'] == 1)
            ) {
                //create folder
                DB::insert(
                    prefixTable('nested_tree'),
                    array(
                        'parent_id' => $post_parent_id,
                        'title' => $post_title,
                        'personal_folder' => $isPersonal,
                        'renewal_period' => $renewalPeriod,
                        'bloquer_creation' => isset($dataReceived['block_creation']) === true && $dataReceived['block_creation'] === '1' ? '1' : $parentBloquerCreation,
                        'bloquer_modification' => isset($dataReceived['block_modif']) === true && $dataReceived['block_modif'] === '1' ? '1' : $parentBloquerModification,
                    )
                );
                $newId = DB::insertId();

                //Add complexity
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'complex',
                        'intitule' => $newId,
                        'valeur' => $post_complexicity,
                    )
                );

                // add new folder id in SESSION
                array_push($_SESSION['groupes_visibles'], $newId);
                if ($isPersonal == 1) {
                    array_push($_SESSION['personal_folders'], $newId);
                }

                // rebuild tree
                $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
                $tree->rebuild();

                // Add right to see this folder
                if ($_SESSION['is_admin'] === '1'
                    || ($_SESSION['user_manager'] === '1'
                    || $_SESSION['user_can_manage_all_users'] === '1')
                ) {
                    //Get user's rights
                    identifyUserRights(
                        $_SESSION['groupes_visibles'],
                        implode(';', $_SESSION['groupes_interdits']),
                        $_SESSION['is_admin'],
                        is_array($_SESSION['fonction_id']) === true ?
                            implode(';', $_SESSION['fonction_id']) : $_SESSION['fonction_id'],
                        $SETTINGS
                    );
                }

                if ($isPersonal !== 1
                    && $post_parent_id === '0'
                ) {
                    //add access to this new folder
                    foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                        if (empty($role) === false && empty($access_level_by_role) === false) {
                            DB::insert(
                                prefixTable('roles_values'),
                                array(
                                    'role_id' => $role,
                                    'folder_id' => $newId,
                                    'type' => $access_level_by_role,
                                )
                            );
                        }
                    }
                }

                if (isset($SETTINGS['subfolder_rights_as_parent']) === true
                    && $SETTINGS['subfolder_rights_as_parent'] === '1'
                ) {
                    //If it is a subfolder, then give access to it for all roles that allows the parent folder
                    $rows = DB::query('SELECT role_id, type FROM '.prefixTable('roles_values').' WHERE folder_id = %i', $post_parent_id);
                    foreach ($rows as $record) {
                        //add access to this subfolder
                        DB::insert(
                            prefixTable('roles_values'),
                            array(
                                'role_id' => $record['role_id'],
                                'folder_id' => $newId,
                                'type' => $record['type'],
                            )
                        );
                    }
                }

                // if parent folder has Custom Fields Categories then add to this child one too
                $rows = DB::query('SELECT id_category FROM '.prefixTable('categories_folders').' WHERE id_folder = %i', $post_parent_id);
                foreach ($rows as $record) {
                    //add CF Category to this subfolder
                    DB::insert(
                        prefixTable('categories_folders'),
                        array(
                            'id_category' => $record['id_category'],
                            'id_folder' => $newId,
                        )
                    );
                }
            } else {
                $error = true;
                $errorMessage = langHdl('error_not_allowed_to');
            }

            echo prepareExchangedData(
                array(
                    'error' => $error,
                    'message' => $errorMessage,
                    'newId' => $newId,
                ),
                'encode'
            );

            break;

        //CASE where UPDATING a new group
        case 'update_folder':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // prepare variables
            $post_title = filter_var(htmlspecialchars_decode($dataReceived['label']), FILTER_SANITIZE_STRING);
            $post_parent_id = filter_var(
                htmlspecialchars_decode($dataReceived['parent_id'], ENT_QUOTES),
                FILTER_SANITIZE_NUMBER_INT
            );
            $post_complexicity = filter_var(
                htmlspecialchars_decode($dataReceived['complexicity'], ENT_QUOTES),
                FILTER_SANITIZE_NUMBER_INT
            );
            $post_folder_id = filter_var(
                htmlspecialchars_decode($dataReceived['folder_id'], ENT_QUOTES),
                FILTER_SANITIZE_NUMBER_INT
            );

            // Init
            $error = false;
            $errorMessage = '';

            // check if title is numeric
            if (is_numeric($post_title) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_only_numbers_in_folder_name'),
                    ),
                    'encode'
                );
                break;
            }

            // Get info about this folder
            $dataFolder = DB::queryfirstrow(
                'SELECT *
                FROM '.prefixTable('nested_tree').'
                WHERE id = %i',
                $post_folder_id
            );

            // Optional inputs
            if (isset($dataReceived['renewal_period']) === true) {
                $post_renewalPeriod = filter_var(
                    htmlspecialchars_decode($dataReceived['renewal_period']),
                    FILTER_SANITIZE_NUMBER_INT
                );
            } else {
                $post_renewalPeriod = $dataFolder['renewal_period'];
            }
            if (isset($dataReceived['block_creation']) === true) {
                $post_BloquerCreation = filter_var(
                    htmlspecialchars_decode($dataReceived['block_creation']),
                    FILTER_SANITIZE_NUMBER_INT
                );
            } else {
                $post_BloquerCreation = $dataFolder['bloquer_creation'];
            }
            if (isset($dataReceived['block_modif']) === true) {
                $post_BloquerModification = filter_var(
                    htmlspecialchars_decode($dataReceived['block_modif']),
                    FILTER_SANITIZE_NUMBER_INT
                );
            } else {
                $post_BloquerModification = $dataFolder['bloquer_modification'];
            }

            //Check if duplicate folders name are allowed
            if (isset($SETTINGS['duplicate_folder']) === true
                && $SETTINGS['duplicate_folder'] === '0'
            ) {
                if (empty($dataFolder['id']) === false
                    && intval($dataReceived['id']) !== intval($dataFolder['id'])
                    && $post_title !== $dataFolder['title']
                ) {
                    echo prepareExchangedData(
                        array(
                            'error' => true,
                            'message' => langHdl('error_group_exist'),
                        ),
                        'encode'
                    );
                    break;
                }
            }

            //check if parent folder is personal
            $dataParent = DB::queryfirstrow(
                'SELECT personal_folder, bloquer_creation, bloquer_modification
                FROM '.prefixTable('nested_tree').'
                WHERE id = %i',
                $post_parent_id
            );

            // inherit from parent the specific settings it has
            if (DB::count() > 0) {
                $parentBloquerCreation = $dataParent['bloquer_creation'];
                $parentBloquerModification = $dataParent['bloquer_modification'];
            } else {
                $parentBloquerCreation = 0;
                $parentBloquerModification = 0;
            }

            if ($dataParent['personal_folder'] === '1') {
                $isPersonal = 1;
            } else {
                $isPersonal = 0;

                // check if complexity level is good
                // if manager or admin don't care
                if ($_SESSION['is_admin'] != 1
                    && ($_SESSION['user_manager'] !== '1'
                    || $_SESSION['user_can_manage_all_users'] !== '1')
                ) {
                    // get complexity level for this folder
                    $data = DB::queryfirstrow(
                        'SELECT valeur
                        FROM '.prefixTable('misc').'
                        WHERE intitule = %i AND type = %s',
                        $post_parent_id,
                        'complex'
                    );
                    if (intval($post_complexicity) < intval($data['valeur'])) {
                        echo prepareExchangedData(
                            array(
                                'error' => true,
                                'message' => langHdl('error_folder_complexity_lower_than_top_folder')
                                    .' [<b>'.TP_PW_COMPLEXITY[$data['valeur']][1].'</b>]',
                            ),
                            'encode'
                        );
                        break;
                    }
                }
            }

            // Prepare update parameters
            $folderParameters = array(
                'parent_id' => $post_parent_id,
                'title' => $post_title,
                'personal_folder' => 0,
            );
            if ($dataFolder['renewal_period'] !== $post_renewalPeriod) {
                $folderParameters['renewal_period'] = $post_renewalPeriod;
            }
            if ($dataFolder['bloquer_creation'] !== $post_BloquerCreation) {
                $folderParameters['bloquer_creation'] = $post_BloquerCreation;
            }
            if ($dataFolder['bloquer_modification'] !== $post_BloquerModification) {
                $folderParameters['bloquer_modification'] = $post_BloquerModification;
            }

            // Now update
            DB::update(
                prefixTable('nested_tree'),
                $folderParameters,
                'id=%i',
                $dataFolder['id']
            );

            //Add complexity
            DB::update(
                prefixTable('misc'),
                array(
                    'valeur' => $post_complexicity,
                ),
                'intitule = %s AND type = %s',
                $dataFolder['id'],
                'complex'
            );

            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();

            //Get user's rights
            identifyUserRights(
                implode(';', $_SESSION['groupes_visibles']).';'.$dataFolder['id'],
                implode(';', $_SESSION['groupes_interdits']),
                $_SESSION['is_admin'],
                $_SESSION['fonction_id'],
                $SETTINGS
            );

            echo prepareExchangedData(
                array(
                    'error' => $error,
                    'message' => $errorMessage,
                ),
                'encode'
            );

            break;

        //CASE where to update the associated Function
        case 'fonction':
            /* do checks */
            require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
            if (!checkUser($_SESSION['user_id'], $_SESSION['key'], 'manage_folders', $SETTINGS)) {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $SETTINGS['cpassman_dir'].'/error.php';
                exit();
            }
            // get values
            $post_valeur = filter_input(INPUT_POST, 'valeur', FILTER_SANITIZE_STRING);
            $val = explode(';', $post_valeur);
            $valeur = $post_valeur;
            //Check if ID already exists
            $data = DB::queryfirstrow('SELECT authorized FROM '.prefixTable('rights').' WHERE tree_id = %i AND fonction_id= %i', $val[0], $val[1]);
            if (empty($data['authorized'])) {
                //Insert into DB
                DB::insert(
                    prefixTable('rights'),
                    array(
                        'tree_id' => $val[0],
                        'fonction_id' => $val[1],
                        'authorized' => 1,
                    )
                );
            } else {
                //Update DB
                if ($data['authorized'] == 1) {
                    DB::update(
                        prefixTable('rights'),
                        array(
                            'authorized' => 0,
                        ),
                        'id = %i AND fonction_id=%i',
                        $val[0],
                        $val[1]
                    );
                } else {
                    DB::update(
                        prefixTable('rights'),
                        array(
                            'authorized' => 1,
                        ),
                        'id = %i AND fonction_id=%i',
                        $val[0],
                        $val[1]
                    );
                }
            }
            break;

        // CASE where to authorize an ITEM creation without respecting the complexity
        case 'modif_droit_autorisation_sans_complexite':
            /* do checks */
            require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
            if (!checkUser($_SESSION['user_id'], $_SESSION['key'], 'manage_folders', $SETTINGS)) {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $SETTINGS['cpassman_dir'].'/error.php';
                exit();
            }

            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                // error
                exit();
            }

            // send query
            DB::update(
                prefixTable('nested_tree'),
                array(
                    'bloquer_creation' => filter_input(INPUT_POST, 'value', FILTER_SANITIZE_STRING),
                ),
                'id = %i',
                $post_id
            );
            break;

        // CASE where to authorize an ITEM modification without respecting the complexity
        case 'modif_droit_modification_sans_complexite':
            /* do checks */
            require_once $SETTINGS['cpassman_dir'].'/sources/checks.php';
            if (!checkUser($_SESSION['user_id'], $_SESSION['key'], 'manage_folders', $SETTINGS)) {
                $_SESSION['error']['code'] = ERR_NOT_ALLOWED; //not allowed page
                include $SETTINGS['cpassman_dir'].'/error.php';
                exit();
            }

            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                // error
                exit();
            }

            // send query
            DB::update(
                prefixTable('nested_tree'),
                array(
                    'bloquer_modification' => filter_input(INPUT_POST, 'value', FILTER_SANITIZE_STRING),
                ),
                'id = %i',
                $post_id
            );
            break;

        // CASE where selecting/deselecting sub-folders
        case 'select_sub_folders':
            // Check KEY and rights
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key'] || $_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(array('error' => 'ERR_KEY_NOT_CORRECT'), 'encode');
                break;
            }

            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
                // error
                exit();
            }

            // get sub folders
            $subfolders = '';
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

            // Get through each subfolder
            $folders = $tree->getDescendants($post_id, false);
            foreach ($folders as $folder) {
                if ($subfolders === '') {
                    $subfolders = $folder->id;
                } else {
                    $subfolders .= ';'.$folder->id;
                }
            }

            echo '[ { "error" : "" , "subfolders" : "'.$subfolders.'" } ]';

            break;

        case 'get_list_of_folders':
            // Check KEY and rights
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key'] || $_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(array('error' => 'ERR_KEY_NOT_CORRECT'), 'encode');
                break;
            }

            // Check KEY
            if (filter_input(INPUT_POST, 'key', FILTER_SANITIZE_STRING) !== $_SESSION['key']) {
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
                        $ident = '&nbsp;&nbsp;';
                        for ($x = 1; $x < $t->nlevel; ++$x) {
                            $ident .= '&nbsp;&nbsp;';
                        }

                        array_push($arrFolders, '<option value=\"'.$t->id.'\">'.$ident.addslashes($t->title).'</option>');
                        $prevLevel = $t->nlevel;
                    }
                }
            }

            echo '[ { "error" : "" , "list_folders" : "'.implode(';', $arrFolders).'" } ]';

            break;

        case 'copy_folder':
            // Check KEY
            if ($post_key !== $_SESSION['key']) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('key_is_not_correct'),
                    ),
                    'encode'
                );
                break;
            } elseif ($_SESSION['user_read_only'] === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // decrypt and retrieve data in JSON format
            $dataReceived = prepareExchangedData($post_data, 'decode');

            // prepare variables
            $source_folder_id = filter_var(htmlspecialchars_decode($dataReceived['source_folder_id']), FILTER_SANITIZE_NUMBER_INT);
            $target_folder_id = filter_var(htmlspecialchars_decode($dataReceived['target_folder_id']), FILTER_SANITIZE_NUMBER_INT);

            //Load Tree
            $tree = new SplClassLoader('Tree\NestedTree', './includes/libraries');
            $tree->register();
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

            // Test if target folder is Read-only
            // If it is then stop
            if (in_array($target_folder_id, $_SESSION['read_only_folders']) === true) {
                echo prepareExchangedData(
                    array(
                        'error' => true,
                        'message' => langHdl('error_not_allowed_to'),
                    ),
                    'encode'
                );
                break;
            }

            // Get all allowed folders
            $array_all_visible_folders = array_merge(
                $_SESSION['groupes_visibles'],
                $_SESSION['read_only_folders'],
                $_SESSION['personal_visible_groups']
            );

            // get list of all folders
            $nodeDescendants = $tree->getDescendants($source_folder_id, true, false, false);
            $parentId = '';
            $tabNodes = [];
            foreach ($nodeDescendants as $node) {
                // step1 - copy folder

                // Can user access this subfolder?
                if (in_array($node->id, $array_all_visible_folders) === false) {
                    continue;
                }

                // get info about current node
                $nodeInfo = $tree->getNode($node->id);

                // get complexity of current node
                $nodeComplexity = DB::queryfirstrow(
                    'SELECT valeur
                    FROM '.prefixTable('misc').'
                    WHERE intitule = %i AND type= %s',
                    $nodeInfo->id,
                    'complex'
                );

                // prepare parent Id
                if (empty($parentId) === true) {
                    $parentId = $target_folder_id;
                } else {
                    $parentId = $tabNodes[$nodeInfo->parent_id];
                }

                //create folder
                DB::insert(
                    prefixTable('nested_tree'),
                    array(
                        'parent_id' => $parentId,
                        'title' => $nodeInfo->title,
                        'personal_folder' => $nodeInfo->personal_folder,
                        'renewal_period' => $nodeInfo->renewal_period,
                        'bloquer_creation' => $nodeInfo->bloquer_creation,
                        'bloquer_modification' => $nodeInfo->bloquer_modification,
                    )
                );
                $newFolderId = DB::insertId();

                // add to correspondance matrix
                $tabNodes[$nodeInfo->id] = $newFolderId;

                //Add complexity
                DB::insert(
                    prefixTable('misc'),
                    array(
                        'type' => 'complex',
                        'intitule' => $newFolderId,
                        'valeur' => $nodeComplexity['valeur'],
                    )
                );

                // add new folder id in SESSION
                array_push($_SESSION['groupes_visibles'], $newFolderId);
                $_SESSION['groupes_visibles_list'] .= ','.$newFolderId;
                if ($nodeInfo->personal_folder == 1) {
                    array_push($_SESSION['personal_folders'], $newFolderId);
                    array_push($_SESSION['personal_visible_groups'], $newFolderId);
                    $_SESSION['personal_visible_groups_list'] .= ','.$newFolderId;
                } else {
                    array_push($_SESSION['all_non_personal_folders'], $newFolderId);
                }

                //Get user's rights
                identifyUserRights(
                    is_array($_SESSION['groupes_visibles']) ? array_push($_SESSION['groupes_visibles'], $newFolderId) : $_SESSION['groupes_visibles'].';'.$newFolderId,
                    $_SESSION['groupes_interdits'],
                    $_SESSION['is_admin'],
                    $_SESSION['fonction_id'],
                    $SETTINGS
                );

                // If new folder should not heritate of parent rights
                // Then use the creator ones
                if ($nodeInfo->personal_folder !== 1
                    && isset($SETTINGS['subfolder_rights_as_parent']) === true
                    && $SETTINGS['subfolder_rights_as_parent'] === '1'
                    && $_SESSION['is_admin'] !== 0
                ) {
                    //add access to this new folder
                    foreach (explode(';', $_SESSION['fonction_id']) as $role) {
                        if (empty($role) === false) {
                            DB::insert(
                                prefixTable('roles_values'),
                                array(
                                    'role_id' => $role,
                                    'folder_id' => $newFolderId,
                                    'type' => 'W',
                                )
                            );
                        }
                    }
                }

                // If it is a subfolder, then give access to it for all roles that allows the parent folder
                $rows = DB::query(
                    'SELECT role_id, type
                    FROM '.prefixTable('roles_values').'
                    WHERE folder_id = %i',
                    $parentId
                );
                foreach ($rows as $record) {
                    // Add access to this subfolder after checking that it is not already set
                    DB::query(
                        'SELECT *
                        FROM '.prefixTable('roles_values').'
                        WHERE folder_id = %i AND role_id = %i',
                        $newFolderId,
                        $record['role_id']
                    );
                    if (DB::count() === 0) {
                        DB::insert(
                            prefixTable('roles_values'),
                            array(
                                'role_id' => $record['role_id'],
                                'folder_id' => $newFolderId,
                                'type' => $record['type'],
                            )
                        );
                    }
                }

                // if parent folder has Custom Fields Categories then add to this child one too
                $rows = DB::query(
                    'SELECT id_category
                    FROM '.prefixTable('categories_folders').'
                    WHERE id_folder = %i',
                    $nodeInfo->id
                );
                foreach ($rows as $record) {
                    //add CF Category to this subfolder
                    DB::insert(
                        prefixTable('categories_folders'),
                        array(
                            'id_category' => $record['id_category'],
                            'id_folder' => $newFolderId,
                        )
                    );
                }

                // step2 - copy items

                $rows = DB::query(
                    'SELECT *
                    FROM '.prefixTable('items').'
                    WHERE id_tree = %i',
                    $nodeInfo->id
                );
                foreach ($rows as $record) {
                    // check if item is deleted
                    // if it is then don't copy it
                    $item_deleted = DB::queryFirstRow(
                        'SELECT *
                        FROM '.prefixTable('log_items').'
                        WHERE id_item = %i AND action = %s
                        ORDER BY date DESC
                        LIMIT 0, 1',
                        $record['id'],
                        'at_delete'
                    );
                    $dataDeleted = DB::count();

                    $item_restored = DB::queryFirstRow(
                        'SELECT *
                        FROM '.prefixTable('log_items').'
                        WHERE id_item = %i AND action = %s
                        ORDER BY date DESC
                        LIMIT 0, 1',
                        $record['id'],
                        'at_restored'
                    );

                    if ($dataDeleted !== '1' || intval($item_deleted['date']) < intval($item_restored['date'])) {
                        // Decrypt and re-encrypt password
                        $decrypt = cryption(
                            $record['pw'],
                            '',
                            'decrypt'
                        );
                        $originalRecord = cryption(
                            $decrypt['string'],
                            '',
                            'encrypt'
                        );

                        // Insert the new record and get the new auto_increment id
                        DB::insert(
                            prefixTable('items'),
                            array(
                                'label' => 'duplicate',
                                'id_tree' => $newFolderId,
                                'pw' => $originalRecord['string'],
                                'pw_iv' => '',
                                'perso' => 0,
                                'viewed_no' => 0,
                            )
                        );
                        $newItemId = DB::insertId();

                        // Generate the query to update the new record with the previous values
                        $aSet = array();
                        foreach ($record as $key => $value) {
                            if ($key !== 'id' && $key !== 'key' && $key !== 'id_tree'
                                && $key !== 'viewed_no' && $key !== 'pw' && $key !== 'pw_iv'
                                && $key !== 'perso'
                            ) {
                                array_push($aSet, array($key => $value));
                            }
                        }
                        DB::update(
                            prefixTable('items'),
                            $aSet,
                            'id = %i',
                            $newItemId
                        );

                        // Add attached itms
                        $rows2 = DB::query(
                            'SELECT * FROM '.prefixTable('files').' WHERE id_item=%i',
                            $newItemId
                        );
                        foreach ($rows2 as $record2) {
                            DB::insert(
                                prefixTable('files'),
                                array(
                                    'id_item' => $newID,
                                    'name' => $record2['name'],
                                    'size' => $record2['size'],
                                    'extension' => $record2['extension'],
                                    'type' => $record2['type'],
                                    'file' => $record2['file'],
                                    )
                            );
                        }
                        // Add this duplicate in logs
                        logItems(
                            $newItemId,
                            $record['label'],
                            $_SESSION['user_id'],
                            'at_creation',
                            $_SESSION['login']
                        );
                        // Add the fact that item has been copied in logs
                        logItems(
                            $newItemId,
                            $record['label'],
                            $_SESSION['user_id'],
                            'at_copy',
                            $_SESSION['login']
                        );
                    }
                }
            }

            // rebuild tree
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();

            // reload cache table
            require_once $SETTINGS['cpassman_dir'].'/sources/main.functions.php';
            updateCacheTable('reload', '');

            $data = array(
                'error' => false,
            );

            // send data
            echo prepareExchangedData($data, 'encode');

            break;
    }
}
