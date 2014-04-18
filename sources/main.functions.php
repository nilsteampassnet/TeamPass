<?php
/**
 *
 * @file          main.functions.php
 * @author        Nils Laumaillé
 * @version       2.1.19
 * @copyright     (c) 2009-2014 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link
 */
// session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

//define pbkdf2 iteration count
@define('ITCOUNT', '2072');

//Generate N# of random bits for use as salt
function getBits($n)
{
    $str = '';
    $x = $n + 10;
    for ($i=0; $i<$x; $i++) {
        $str .= base_convert(mt_rand(1, 36), 10, 36);
    }
    return substr($str, 0, $n);
}

//generate pbkdf2 compliant hash
function strHashPbkdf2($p, $s, $c, $kl, $a = 'sha256', $st = 0)
{
    $kb = $st+$kl;  // Key blocks to compute
    $dk = '';    // Derived key

    for ($block=1; $block<=$kb; $block++) { // Create key
        $ib = $h = hash_hmac($a, $s . pack('N', $block), $p, true); // Initial hash for this block
        for ($i=1; $i<$c; $i++) { // Perform block iterations
            $ib ^= ($h = hash_hmac($a, $h, $p, true));  // XOR each iterate
        }
        $dk .= $ib; // Append iterated block
    }
    return substr($dk, $st, $kl); // Return derived key of correct length
}

/**
 * stringUtf8Decode()
 *
 * utf8_decode
 */
function stringUtf8Decode($string)
{
    return str_replace(" ", "+", utf8_decode($string));
}

/**
 * encryptOld()
 *
 * crypt a string
 */
function encryptOld($text, $personalSalt = "")
{
    if (!empty($personalSalt)) {
        return trim(
            base64_encode(
                mcrypt_encrypt(
                    MCRYPT_RIJNDAEL_256,
                    $personalSalt,
                    $text,
                    MCRYPT_MODE_ECB,
                    mcrypt_create_iv(
                        mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB),
                        MCRYPT_RAND
                    )
                )
            )
        );
    } else {
        return trim(
            base64_encode(
                mcrypt_encrypt(
                    MCRYPT_RIJNDAEL_256,
                    SALT,
                    $text,
                    MCRYPT_MODE_ECB,
                    mcrypt_create_iv(
                        mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB),
                        MCRYPT_RAND
                    )
                )
            )
        );
    }
}

/**
 * decryptOld()
 *
 * decrypt a crypted string
 */
function decryptOld($text, $personalSalt = "")
{
    if (!empty($personalSalt)) {
        return trim(
            mcrypt_decrypt(
                MCRYPT_RIJNDAEL_256,
                $personalSalt,
                base64_decode($text),
                MCRYPT_MODE_ECB,
                mcrypt_create_iv(
                    mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB),
                    MCRYPT_RAND
                )
            )
        );
    } else {
        return trim(
            mcrypt_decrypt(
                MCRYPT_RIJNDAEL_256,
                SALT,
                base64_decode($text),
                MCRYPT_MODE_ECB,
                mcrypt_create_iv(
                    mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB),
                    MCRYPT_RAND
                )
            )
        );
    }
}

/**
 * encrypt()
 *
 * crypt a string
 */
function encrypt($decrypted, $personalSalt = "")
{
    if (!empty($personalSalt)) {
 	    $staticSalt = $personalSalt;
    } else {
 	    $staticSalt = SALT;
    }
    //set our salt to a variable
    // Get 64 random bits for the salt for pbkdf2
    $pbkdf2Salt = getBits(64);
    // generate a pbkdf2 key to use for the encryption.
    $key = strHashPbkdf2($staticSalt, $pbkdf2Salt, ITCOUNT, 16, 'sha256', 32);
    // Build $iv and $ivBase64.  We use a block size of 256 bits (AES compliant)
    // and CTR mode.  (Note: ECB mode is inadequate as IV is not used.)
    $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, 'ctr'), MCRYPT_RAND);
    //base64 trim
    if (strlen($ivBase64 = rtrim(base64_encode($iv), '=')) != 43) {
        return false;
    }
    // Encrypt $decrypted
    $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $decrypted, 'ctr', $iv);
    // MAC the encrypted text
    $mac = hash_hmac('sha256', $encrypted, $staticSalt);
    // We're done!
    return base64_encode($ivBase64 . $encrypted . $mac . $pbkdf2Salt);
}

/**
 * decrypt()
 *
 * decrypt a crypted string
 */
function decrypt($encrypted, $personalSalt = "")
{
    if (!empty($personalSalt)) {
	    $staticSalt = $personalSalt;
    } else {
	    $staticSalt = SALT;
    }
    //base64 decode the entire payload
    $encrypted = base64_decode($encrypted);
    // get the salt
    $pbkdf2Salt = substr($encrypted, -64);
    //remove the salt from the string
    $encrypted = substr($encrypted, 0, -64);
    $key = strHashPbkdf2($staticSalt, $pbkdf2Salt, ITCOUNT, 16, 'sha256', 32);
    // Retrieve $iv which is the first 22 characters plus ==, base64_decoded.
    $iv = base64_decode(substr($encrypted, 0, 43) . '==');
    // Remove $iv from $encrypted.
    $encrypted = substr($encrypted, 43);
    // Retrieve $mac which is the last 64 characters of $encrypted.
    $mac = substr($encrypted, -64);
    // Remove the last 64 chars from encrypted (remove MAC)
    $encrypted = substr($encrypted, 0, -64);
    //verify the sha256hmac from the encrypted data before even trying to decrypt it
    if (hash_hmac('sha256', $encrypted, $staticSalt) != $mac) {
        return false;
    }
    // Decrypt the data.
    $decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $encrypted, 'ctr', $iv), "\0\4");
    // Yay!
    return $decrypted;
}


/**
 * genHash()
 *
 * Generate a hash for user login
 */
function bCrypt($password, $cost)
{
    $salt = sprintf('$2y$%02d$', $cost);
    if (function_exists('openssl_random_pseudo_bytes')) {
        $salt .= bin2hex(openssl_random_pseudo_bytes(11));
    } else {
        $chars='./ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        for ($i=0; $i<22; $i++) {
            $salt.=$chars[mt_rand(0, 63)];
        }
    }
    return crypt($password, $salt);
}


/**
 * trimElement()
 *
 * trim a string depending on a specific string
 */
function trimElement($chaine, $element)
{
    if (!empty($chaine)) {
        $chaine = trim($chaine);
        if (substr($chaine, 0, 1) == $element) {
            $chaine = substr($chaine, 1);
        }
        if (substr($chaine, strlen($chaine) - 1, 1) == $element) {
            $chaine = substr($chaine, 0, strlen($chaine) - 1);
        }

        return $chaine;
    }
}

/**
 * cleanString()
 *
 * permits to suppress all "special" characters from string
 */
function cleanString($string)
{
    // Create temporary table for special characters escape
    $tabSpecialChar = array();
    for ($i = 0; $i <= 31; $i++) {
        $tabSpecialChar[] = chr($i);
    }
    array_push($tabSpecialChar, "<br />");

    return str_replace($tabSpecialChar, "", $string);
}

/**
 * identifyUserRights()
 *
 * @return
 */
function identifyUserRights($groupesVisiblesUser, $groupesInterditsUser, $isAdmin, $idFonctions, $refresh)
{
    global $server, $user, $pass, $database, $pre;

    //load ClassLoader
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    //Connect to DB
    $db = new SplClassLoader('Database\Core', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
    $db->register();
    $db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
    $db->connect();

    //Build tree
    $tree = new SplClassLoader('Tree\NestedTree', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

    // Check if user is ADMINISTRATOR
    if ($isAdmin == 1) {
        $groupesVisibles = array();
        $_SESSION['groupes_visibles'] = array();
        $_SESSION['groupes_interdits'] = array();
        $_SESSION['personal_visible_groups'] = array();
        $_SESSION['list_restricted_folders_for_items'] = array();
        $_SESSION['groupes_visibles_list'] = "";
        $rows = $db->fetchAllArray("SELECT id FROM ".$pre."nested_tree WHERE personal_folder = '0'");
        foreach ($rows as $record) {
            array_push($groupesVisibles, $record['id']);
        }
        $_SESSION['groupes_visibles'] = $groupesVisibles;
        $_SESSION['all_non_personal_folders'] = $groupesVisibles;
        // Exclude all PF
        $_SESSION['forbiden_pfs'] = array();
        $sql = "SELECT id FROM ".$pre."nested_tree WHERE personal_folder = 1";
        if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1) {
            $sql .= " AND title != '".$_SESSION['user_id']."'";
        }
        // Get ID of personal folder
        // $pf = $db->fetchRow("SELECT id FROM ".$pre."nested_tree WHERE title = '".$_SESSION['user_id']."'");
        $pf = $db->queryGetRow(
            "nested_tree",
            array(
                "id"
            ),
            array(
                "title" => $_SESSION['user_id']
            )
        );
        if (!empty($pf[0])) {
            if (!in_array($pf[0], $_SESSION['groupes_visibles'])) {
                array_push($_SESSION['groupes_visibles'], $pf[0]);
                array_push($_SESSION['personal_visible_groups'], $pf[0]);
                // get all descendants
                $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title', 'personal_folder');
                $tree->rebuild();
                $tst = $tree->getDescendants($pf[0]);
                foreach ($tst as $t) {
                    array_push($_SESSION['groupes_visibles'], $t->id);
                    array_push($_SESSION['personal_visible_groups'], $t->id);
                }
            }
        }

        $_SESSION['groupes_visibles_list'] = implode(',', $_SESSION['groupes_visibles']);
        $_SESSION['is_admin'] = $isAdmin;
        // Check if admin has created Folders and Roles
        $ret = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."nested_tree");
        $_SESSION['nb_folders'] = $ret[0];
        $ret = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."roles_title");
        $_SESSION['nb_roles'] = $ret[0];
    } else {
        // init
        $_SESSION['groupes_visibles'] = array();
        $_SESSION['groupes_interdits'] = array();
        $_SESSION['personal_visible_groups'] = array();
        $groupesVisibles = array();
        $groupesInterdits = array();
        $groupesInterditsUser = explode(';', trimElement($groupesInterditsUser, ";"));
        if (!empty($groupesInterditsUser) && count($groupesInterditsUser) > 0) {
            $groupesInterdits = $groupesInterditsUser;
        }
        $_SESSION['is_admin'] = $isAdmin;
        $fonctionsAssociees = explode(';', trimElement($idFonctions, ";"));
        $newListeGpVisibles = array();
        $listeGpInterdits = array();

        $listAllowedFolders = $listForbidenFolders = $listFoldersLimited = $listFoldersEditableByRole = $listRestrictedFoldersForItems = array();

        // rechercher tous les groupes visibles en fonction des roles de l'utilisateur
        foreach ($fonctionsAssociees as $roleId) {
            if (!empty($roleId)) {
                // Get allowed folders for each Role
                $rows = $db->fetchAllArray(
                    "SELECT folder_id
                    FROM ".$pre."roles_values
                    WHERE role_id=".$roleId
                );
                if (count($rows) > 0) {
                    foreach ($rows as $reccord) {
                        if (isset($reccord['folder_id']) && !in_array($reccord['folder_id'], $listAllowedFolders)) {
                            array_push($listAllowedFolders, $reccord['folder_id']);
                        }
                        // Check if this group is allowed to modify any pw in allowed folders
                        $tmp = $db->queryFirst(
                            "SELECT allow_pw_change
                            FROM ".$pre."roles_title
                            WHERE id = ".$roleId
                        );
                        if ($tmp['allow_pw_change'] == 1 && !in_array($reccord['folder_id'], $listFoldersEditableByRole)) {
                            array_push($listFoldersEditableByRole, $reccord['folder_id']);
                        }
                    }
                    // Check for the users roles if some specific rights exist on items
                    $rows = $db->fetchAllArray(
                        "SELECT i.id_tree, r.item_id
                        FROM ".$pre."items as i
                        INNER JOIN ".$pre."restriction_to_roles as r ON (r.item_id=i.id)
                        WHERE r.role_id=".$roleId."
                        ORDER BY i.id_tree ASC"
                    );
                    $x = 0;
                    foreach ($rows as $reccord) {
                        if (isset($reccord['id_tree'])) {
                            $listFoldersLimited[$reccord['id_tree']][$x] = $reccord['item_id'];
                            $x++;
                        }
                    }
                }
            }
        }
        // Does this user is allowed to see other items
        $x = 0;
        $rows = $db->fetchAllArray(
            "SELECT id,id_tree FROM ".$pre."items
            WHERE restricted_to LIKE '%".$_SESSION['user_id'].";%' AND inactif='0'"
        );
        foreach ($rows as $reccord) {
            $listRestrictedFoldersForItems[$reccord['id_tree']][$x] = $reccord['id'];
            $x++;
            // array_push($listRestrictedFoldersForItems, $reccord['id_tree']);
        }
        // => Build final lists
        // Clean arrays
        $allowedFoldersTmp = array();
        $listAllowedFolders = array_unique($listAllowedFolders);
        // Add user allowed folders
        $allowedFoldersTmp = array_unique(
            array_merge($listAllowedFolders, explode(';', trimElement($groupesVisiblesUser, ";")))
        );
        // Exclude from allowed folders all the specific user forbidden folders
        $allowedFolders = array();
        foreach ($allowedFoldersTmp as $id) {
            if (!in_array($id, $groupesInterditsUser) && !empty($id)) {
                array_push($allowedFolders, $id);
            }
        }
        // Clean array
        $listAllowedFolders = array_filter(array_unique($allowedFolders));
        // Exclude all PF
        $_SESSION['forbiden_pfs'] = array();
        $sql = "SELECT id FROM ".$pre."nested_tree WHERE personal_folder = 1";
        if (
            isset($_SESSION['settings']['enable_pf_feature']) &&
            $_SESSION['settings']['enable_pf_feature'] == 1 &&
            isset($_SESSION['personal_folder']) &&
            $_SESSION['personal_folder'] == 1
        ) {
            $sql .= " AND title != '".$_SESSION['user_id']."'";
        }

        $pfs = $db->fetchAllArray($sql);
        foreach ($pfs as $pfId) {
            array_push($_SESSION['forbiden_pfs'], $pfId['id']);
        }
        // Get ID of personal folder
        if (
            isset($_SESSION['settings']['enable_pf_feature']) &&
            $_SESSION['settings']['enable_pf_feature'] == 1 &&
            isset($_SESSION['personal_folder']) &&
            $_SESSION['personal_folder'] == 1
        ) {
        // $pf = $db->fetchRow("SELECT id FROM ".$pre."nested_tree WHERE title = '".$_SESSION['user_id']."'");
        $pf = $db->queryGetRow(
            "nested_tree",
            array(
                "id"
            ),
            array(
                "title" => intval($_SESSION['user_id'])
            )
        );
            if (!empty($pf[0])) {
                if (!in_array($pf[0], $listAllowedFolders)) {
                    // get all descendants
                    $ids = $tree->getDescendants($pf[0], true);
                    foreach ($ids as $id) {
                        array_push($listAllowedFolders, $id->id);
                        array_push($_SESSION['personal_visible_groups'], $id->id);
                    }
                }
            }
        }

        $_SESSION['all_non_personal_folders'] = $listAllowedFolders;
        $_SESSION['groupes_visibles'] = $listAllowedFolders;
        $_SESSION['groupes_visibles_list'] = implode(',', $listAllowedFolders);

        $_SESSION['list_folders_limited'] = $listFoldersLimited;
        $_SESSION['list_folders_editable_by_role'] = $listFoldersEditableByRole;
        $_SESSION['list_restricted_folders_for_items'] = $listRestrictedFoldersForItems;
        // Folders and Roles numbers
        $ret = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."nested_tree");
        $_SESSION['nb_folders'] = $ret[0];
        $ret = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."roles_title");
        $_SESSION['nb_roles'] = $ret[0];
    }
}

/**
 * logEvents()
 *
 * permits to log events into DB
 */
function logEvents($type, $label, $who)
{
    global $server, $user, $pass, $database, $pre;
    // include librairies & connect to DB
    $db = new SplClassLoader('Database\Core', '../includes/libraries');
    $db->register();
    $db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
    $db->connect();

    $db->queryInsert(
        "log_system",
        array(
            'type' => $type,
            'date' => time(),
            'label' => $label,
            'qui' => $who
           )
    );
}

/**
 * updateCacheTable()
 *
 * Update the CACHE table
 */
function updateCacheTable($action, $id = "")
{
    global $db, $server, $user, $pass, $database, $pre;
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    //Connect to DB
    $db = new SplClassLoader('Database\Core', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
    $db->register();
    $db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
    $db->connect();

    //Load Tree
    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree($pre.'nested_tree', 'id', 'parent_id', 'title');

    // Rebuild full cache table
    if ($action == "reload") {
        // truncate table
        $db->query("TRUNCATE TABLE ".$pre."cache");
        // reload date
        $sql = "SELECT *
                FROM ".$pre."items as i
                INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
                AND l.action = 'at_creation'
                AND i.inactif=0";
        $rows = $db->fetchAllArray($sql);
        foreach ($rows as $reccord) {
            // Get all TAGS
            $tags = "";
            $itemTags = $db->fetchAllArray("SELECT tag FROM ".$pre."tags WHERE item_id=".$reccord['id']);
            foreach ($itemTags as $itemTag) {
                if (!empty($itemTag['tag'])) {
                    $tags .= $itemTag['tag']." ";
                }
            }
            // form id_tree to full foldername
            $folder = "";
            $arbo = $tree->getPath($reccord['id_tree'], true);
            foreach ($arbo as $elem) {
                if ($elem->title == $_SESSION['user_id'] && $elem->nlevel == 1) {
                    $elem->title = $_SESSION['login'];
                }
                if (empty($folder)) {
                    $folder = stripslashes($elem->title);
                } else {
                    $folder .= " » ".stripslashes($elem->title);
                }
            }
            // store data
            $db->queryInsert(
                "cache",
                array(
                    'id' => $reccord['id'],
                    'label' => $reccord['label'],
                    'description' => $reccord['description'],
                    'tags' => $tags,
                    'id_tree' => $reccord['id_tree'],
                    'perso' => $reccord['perso'],
                    'restricted_to' => $reccord['restricted_to'],
                    'login' => $reccord['login'],
                    'folder' => $folder,
                    'author' => $reccord['id_user'],
                   )
            );
        }
        // UPDATE an item
    } elseif ($action == "update_value") {
        // get new value from db
        /*$sql = "SELECT label, description, id_tree, perso, restricted_to, login
                FROM ".$pre."items
                WHERE id=".$id;
        $row = $db->query($sql);
        $data = $db->fetchArray($row);*/
        $ret = $db->queryGetArray(
            "items",
            array(
                "label",
                "description",
                "id_tree",
                "perso",
                "restricted_to",
                "login"
            ),
            array(
                "id" => intval($id)
            )
        );
        // Get all TAGS
        $tags = "";
        $itemTags = $db->fetchAllArray("SELECT tag FROM ".$pre."tags WHERE item_id=".intval($id));
        foreach ($itemTags as $itemTag) {
            if (!empty($itemTag['tag'])) {
                $tags .= $itemTag['tag']." ";
            }
        }
        // form id_tree to full foldername
        $folder = "";
        $arbo = $tree->getPath($data['id_tree'], true);
        foreach ($arbo as $elem) {
            if ($elem->title == $_SESSION['user_id'] && $elem->nlevel == 1) {
                $elem->title = $_SESSION['login'];
            }
            if (empty($folder)) {
                $folder = stripslashes($elem->title);
            } else {
                $folder .= " » ".stripslashes($elem->title);
            }
        }
        // finaly update
        $db->queryUpdate(
            "cache",
            array(
                'label' => $data['label'],
                'description' => $data['description'],
                'tags' => $tags,
                'id_tree' => $data['id_tree'],
                'perso' => $data['perso'],
                'restricted_to' => $data['restricted_to'],
                'login' => $data['login'],
                'folder' => $folder,
                'author' => $_SESSION['user_id'],
               ),
            "id='".intval($id)."'"
        );
        // ADD an item
    } elseif ($action == "add_value") {
        // get new value from db
        /*$sql = "SELECT i.label, i.description, i.id_tree as id_tree, i.perso, i.restricted_to, i.id, i.login
                FROM ".$pre."items as i
                INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
                WHERE i.id=".$id."
                AND l.action = 'at_creation'";
        $row = $db->query($sql);
        $data = $db->fetchArray($row);*/
        $data = $db->queryGetArray(
            array(
                "items" => "i"
            ),
            array(
                "i.label" => "label",
                "i.description" => "description",
                "i.id_tree" => id_tree,
                "i.perso" => "perso",
                "i.restricted_to" => "restricted_to",
                "i.login" => "login",
                "i.id" => "id"
            ),
            array(
                "i.id" => intval($id),
                "l.action" => "at_creation"
            ),
            "",
            array(
                "log_items as l" => "(l.id_item = i.id)"
            )
        );
        // Get all TAGS
        $tags = "";
        $itemTags = $db->fetchAllArray("SELECT tag FROM ".$pre."tags WHERE item_id=".$id);
        foreach ($itemTags as $itemTag) {
            if (!empty($itemTag['tag'])) {
                $tags .= $itemTag['tag']." ";
            }
        }
        // form id_tree to full foldername
        $folder = "";
        $arbo = $tree->getPath($data['id_tree'], true);
        foreach ($arbo as $elem) {
            if ($elem->title == $_SESSION['user_id'] && $elem->nlevel == 1) {
                $elem->title = $_SESSION['login'];
            }
            if (empty($folder)) {
                $folder = stripslashes($elem->title);
            } else {
                $folder .= " » ".stripslashes($elem->title);
            }
        }
        // finaly update
        $db->queryInsert(
            "cache",
            array(
                'id' => $data['id'],
                'label' => $data['label'],
                'description' => $data['description'],
                'tags' => $tags,
                'id_tree' => $data['id_tree'],
                'perso' => $data['perso'],
                'restricted_to' => $data['restricted_to'],
                'login' => $data['login'],
                'folder' => $folder,
                'author' => $_SESSION['user_id'],
               )
        );
        // DELETE an item
    } elseif ($action == "delete_value") {
        mysql_query("DELETE FROM ".$pre."cache WHERE id = ".$id);
    }
}

/**
 * send statistics about your usage of cPassMan.
 * This helps the creator to evaluate the usage you have of the tool.
 */
function teampassStats()
{
    global $server, $user, $pass, $database, $pre;

    require_once $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    // connect to the server
    $db = new SplClassLoader('Database\Core', '../includes/libraries');
    $db->register();
    $db = new Database\Core\DbCore($server, $user, $pass, $database, $pre);
    $db->connect();

    // Prepare stats to be sent
    // Count no FOLDERS
    $dataFolders = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."nested_tree");
    // Count no USERS
    $dataUsers = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."users");
    // Count no ITEMS
    $dataItems = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."items");
    // Get info about installation
    $dataSystem = array();
    $rows = $db->fetchAllArray(
        "SELECT valeur,intitule FROM ".$pre."misc
        WHERE type = 'admin'
        AND intitule IN ('enable_pf_feature','log_connections','cpassman_version')"
    );
    foreach ($rows as $reccord) {
        if ($reccord['intitule'] == 'enable_pf_feature') {
            $dataSystem['enable_pf_feature'] = $reccord['valeur'];
        } elseif ($reccord['intitule'] == 'cpassman_version') {
            $dataSystem['cpassman_version'] = $reccord['valeur'];
        } elseif ($reccord['intitule'] == 'log_connections') {
            $dataSystem['log_connections'] = $reccord['valeur'];
        }
    }
    // Get the actual stats.
    $statsToSend = array(
        'uid' => md5(SALT),
        'time_added' => time(),
        'users' => $dataUsers[0],
        'folders' => $dataFolders[0],
        'items' => $dataItems[0],
        'cpm_version' => $dataSystem['cpassman_version'],
        'enable_pf_feature' => $dataSystem['enable_pf_feature'],
        'log_connections' => $dataSystem['log_connections'],
       );
    // Encode all the data, for security.
    foreach ($statsToSend as $k => $v) {
        $statsToSend[$k] = urlencode($k).'='.urlencode($v);
    }
    // Turn this into the query string!
    $statsToSend = implode('&', $statsToSend);

    fopen("http://www.teampass.net/files/cpm_stats/collect_stats.php?".$statsToSend, 'r');
    // update the actual time
    $db->queryUpdate(
        "misc",
        array(
            'valeur' => time()
           ),
        "type='admin' AND intitule = 'send_stats_time'"
    );
}

/**
 * sendEmail()
 *
 * @return
 */
function sendEmail($subject, $textMail, $email, $textMailAlt = "")
{
    include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';

    //load ClassLoader
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    //load library
    $mail = new SplClassLoader('Email\Phpmailer', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
    $mail->register();
    $mail = new Email\Phpmailer\PhpMailer();

    // send to user
    $mail->setLanguage("en", "../includes/libraries/Email/Phpmailer/language/");
    $mail->SMTPDebug = 0; //value 1 can be used to debug
    $mail->Port = $_SESSION['settings']['email_port']; //COULD BE USED
    // $mail->SMTPSecure = 'ssl';     //COULD BE USED
    $mail->isSmtp(); // send via SMTP
    $mail->Host = $_SESSION['settings']['email_smtp_server']; // SMTP servers
    $mail->SMTPAuth = $_SESSION['settings']['email_smtp_auth']; // turn on SMTP authentication
    $mail->Username = $_SESSION['settings']['email_auth_username']; // SMTP username
    $mail->Password = $_SESSION['settings']['email_auth_pwd']; // SMTP password
    $mail->From = $_SESSION['settings']['email_from'];
    $mail->FromName = $_SESSION['settings']['email_from_name'];
    $mail->addAddress($email); //Destinataire
    $mail->WordWrap = 80; // set word wrap
    $mail->isHtml(true); // send as HTML
    $mail->Subject = $subject;
    $mail->Body = $textMail;
    $mail->AltBody = $textMailAlt;
    // send email
    if (!$mail->send()) {
        return '"error":"error_mail_not_send" , "message":"'.$mail->ErrorInfo.'"';
    } else {
        return '"error":"" , "message":"'.$txt['forgot_my_pw_email_sent'].'"';
    }
}

/**
 * generateKey()
 *
 * @return
 */
function generateKey()
{
    return substr(md5(rand().rand()), 0, 15);
}

/**
 * dateToStamp()
 *
 * @return
 */
function dateToStamp($date)
{
    $date = date_parse_from_format($_SESSION['settings']['date_format'], $date);
    if ($date['warning_count'] == 0 && $date['error_count'] == 0) {
        return mktime(0, 0, 0, $date['month'], $date['day'], $date['year']);
    } else {
        return false;
    }
}

function isDate($date)
{
    return (strtotime($date) !== false);
}

/**
 * isUTF8()
 *
 * @return is the string in UTF8 format.
 */

function isUTF8($string)
{
    return preg_match(
        '%^(?:
        [\x09\x0A\x0D\x20-\x7E] # ASCII
        | [\xC2-\xDF][\x80-\xBF] # non-overlong 2-byte
        | \xE0[\xA0-\xBF][\x80-\xBF] # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2} # straight 3-byte
        | \xED[\x80-\x9F][\x80-\xBF] # excluding surrogates
        | \xF0[\x90-\xBF][\x80-\xBF]{2} # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3} # planes 4-15
        | \xF4[\x80-\x8F][\x80-\xBF]{2} # plane 16
        )*$%xs',
        $string
    );
}

/*
* FUNCTION
* permits to prepare data to be exchanged
*/
function prepareExchangedData($data, $type)
{
    if ($type == "encode") {
        if (
            isset($_SESSION['settings']['encryptClientServer'])
            && $_SESSION['settings']['encryptClientServer'] == 0
        ) {
            return json_encode(
                $data,
                JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP
            );
        } else {
            return Encryption\Crypt\aesctr::encrypt(
                json_encode(
                    $data,
                    JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP
                ),
                $_SESSION['key'],
                256
            );
        }
    } elseif  ($type == "decode") {
        if (
            isset($_SESSION['settings']['encryptClientServer'])
            && $_SESSION['settings']['encryptClientServer'] == 0
        ) {
            return json_decode(
                $data,
                true
            );
        } else {
            return json_decode(
                Encryption\Crypt\aesctr::decrypt($data, $_SESSION['key'], 256),
                true
            );
        }
    }
}
