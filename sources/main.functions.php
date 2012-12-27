<?php
/**
 *
 * @file          main.functions.php
 * @author        Nils Laumaillé
 * @version       2.1.13
 * @copyright     (c) 2009-2012 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link
 */
// session_start();
if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
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
 * encrypt()
 *
 * crypt a string
 */
function encrypt($text, $personal_salt = "")
{
    if (!empty($personal_salt)) {
        return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $personal_salt, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
    } else {
        return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, SALT, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
    }
}

/**
 * decrypt()
 *
 * decrypt a crypted string
 */
function decrypt($text, $personal_salt = "")
{
    if (!empty($personal_salt)) {
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $personal_salt, base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
    } else {
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, SALT, base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
    }
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
    $tab_special_car = array();
    for ($i = 0; $i <= 31; $i++) {
        $tab_special_car[] = chr($i);
    }
    array_push($tab_special_car, "<br />");

    return str_replace($tab_special_car, "", $string);
}

/**
 * identifyUserRights()
 *
 * @return
 */
function identifyUserRights($groupes_visibles_user, $groupes_interdits_user, $is_admin, $id_fonctions, $refresh)
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
    if ($is_admin == 1) {
        $groupes_visibles = array();
        $_SESSION['groupes_visibles'] = array();
        $_SESSION['groupes_interdits'] = array();
        $_SESSION['personal_visible_groups'] = array();
        $_SESSION['list_restricted_folders_for_items'] = array();
        $_SESSION['groupes_visibles_list'] = "";
        $rows = $db->fetchAllArray("SELECT id FROM ".$pre."nested_tree WHERE personal_folder = '0'");
        foreach ($rows as $record) {
            array_push($groupes_visibles, $record['id']);
        }
        $_SESSION['groupes_visibles'] = $groupes_visibles;
        $_SESSION['all_non_personal_folders'] = $groupes_visibles;
        // Exclude all PF
        $_SESSION['forbiden_pfs'] = array();
        $sql = "SELECT id FROM ".$pre."nested_tree WHERE personal_folder = 1";
        if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1) {
            $sql .= " AND title != '".$_SESSION['user_id']."'";
        }
        // Get ID of personal folder
        $pf = $db->fetchRow("SELECT id FROM ".$pre."nested_tree WHERE title = '".$_SESSION['user_id']."'");
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
        $_SESSION['is_admin'] = $is_admin;
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
        $groupes_visibles = array();
        $groupes_interdits = array();
        $groupes_interdits_user = explode(';', trimElement($groupes_interdits_user, ";"));
        if (!empty($groupes_interdits_user) && count($groupes_interdits_user) > 0) {
            $groupes_interdits = $groupes_interdits_user;
        }
        $_SESSION['is_admin'] = $is_admin;
        $fonctions_associees = explode(';', trimElement($id_fonctions, ";"));
        $new_liste_gp_visibles = array();
        $liste_gp_interdits = array();

        $list_allowed_folders = $list_forbiden_folders = $list_folders_limited = $list_folders_editable_by_role = $list_restricted_folders_for_items = array();

        // rechercher tous les groupes visibles en fonction des roles de l'utilisateur
        foreach ($fonctions_associees as $role_id) {
            if (!empty($role_id)) {
                // Get allowed folders for each Role
                $rows = $db->fetchAllArray(
                    "SELECT folder_id
                    FROM ".$pre."roles_values
                    WHERE role_id=".$role_id
                );
                if (count($rows) > 0) {
                    foreach ($rows as $reccord) {
                        if (isset($reccord['folder_id']) && !in_array($reccord['folder_id'], $list_allowed_folders)) {
                            array_push($list_allowed_folders, $reccord['folder_id']);
                        }
                        // Check if this group is allowed to modify any pw in allowed folders
                        $tmp = $db->queryFirst(
                            "SELECT allow_pw_change
                            FROM ".$pre."roles_title
                            WHERE id = ".$role_id
                        );
                        if ($tmp['allow_pw_change'] == 1 && !in_array($reccord['folder_id'], $list_folders_editable_by_role)) {
                            array_push($list_folders_editable_by_role, $reccord['folder_id']);
                        }
                    }
                    // Check for the users roles if some specific rights exist on items
                    $rows = $db->fetchAllArray(
                        "SELECT i.id_tree, r.item_id
                        FROM ".$pre."items as i
                        INNER JOIN ".$pre."restriction_to_roles as r ON (r.item_id=i.id)
                        WHERE r.role_id=".$role_id."
                        ORDER BY i.id_tree ASC"
                    );
                    $x = 0;
                    foreach ($rows as $reccord) {
                        if (isset($reccord['id_tree'])) {
                            $list_folders_limited[$reccord['id_tree']][$x] = $reccord['item_id'];
                            $x++;
                        }
                    }
                }
            }
        }
        // Does this user is allowed to see other items
        $x = 0;
        $rows = $db->fetchAllArray("SELECT id,id_tree FROM ".$pre."items WHERE restricted_to LIKE '%".$_SESSION['user_id'].";%' AND inactif='0'");
        foreach ($rows as $reccord) {
            $list_restricted_folders_for_items[$reccord['id_tree']][$x] = $reccord['id'];
            $x++;
            // array_push($list_restricted_folders_for_items, $reccord['id_tree']);
        }
        // => Build final lists
        // Clean arrays
        $allowed_folders_tmp = array();
        $list_allowed_folders = array_unique($list_allowed_folders);
        // Add user allowed folders
        $allowed_folders_tmp = array_unique(array_merge($list_allowed_folders, explode(';', trimElement($groupes_visibles_user, ";"))));
        // Exclude from allowed folders all the specific user forbidden folders
        $allowed_folders = array();
        foreach ($allowed_folders_tmp as $id) {
            if (!in_array($id, $groupes_interdits_user) && !empty($id)) {
                array_push($allowed_folders, $id);
            }
        }
        // Clean array
        $list_allowed_folders = array_filter(array_unique($allowed_folders));
        // Exclude all PF
        $_SESSION['forbiden_pfs'] = array();
        $sql = "SELECT id FROM ".$pre."nested_tree WHERE personal_folder = 1";
        if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 && isset($_SESSION['personal_folder']) && $_SESSION['personal_folder'] == 1) {
            $sql .= " AND title != '".$_SESSION['user_id']."'";
        }

        $pfs = $db->fetchAllArray($sql);
        foreach ($pfs as $pf_id) {
            array_push($_SESSION['forbiden_pfs'], $pf_id['id']);
        }
        // Get ID of personal folder
        if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1 && isset($_SESSION['personal_folder']) && $_SESSION['personal_folder'] == 1) {
            $pf = $db->fetchRow("SELECT id FROM ".$pre."nested_tree WHERE title = '".$_SESSION['user_id']."'");
            if (!empty($pf[0])) {
                if (!in_array($pf[0], $list_allowed_folders)) {
                    // get all descendants
                    $ids = $tree->getDescendants($pf[0], true);
                    foreach ($ids as $id) {
                        array_push($list_allowed_folders, $id->id);
                        array_push($_SESSION['personal_visible_groups'], $id->id);
                    }
                }
            }
        }

        $_SESSION['all_non_personal_folders'] = $list_allowed_folders;
        $_SESSION['groupes_visibles'] = $list_allowed_folders;
        $_SESSION['groupes_visibles_list'] = implode(',', $list_allowed_folders);

        $_SESSION['list_folders_limited'] = $list_folders_limited;
        $_SESSION['list_folders_editable_by_role'] = $list_folders_editable_by_role;
        $_SESSION['list_restricted_folders_for_items'] = $list_restricted_folders_for_items;
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
            $item_tags = $db->fetchAllArray("SELECT tag FROM ".$pre."tags WHERE item_id=".$reccord['id']);
            foreach ($item_tags as $item_tag) {
                if (!empty($item_tag['tag'])) {
                    $tags .= $item_tag['tag']." ";
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
        $sql = "SELECT label, description, id_tree, perso, restricted_to, login
                FROM ".$pre."items
                WHERE id=".$id;
        $row = $db->query($sql);
        $data = $db->fetchArray($row);
        // Get all TAGS
        $tags = "";
        $item_tags = $db->fetchAllArray("SELECT tag FROM ".$pre."tags WHERE item_id=".$id);
        foreach ($item_tags as $item_tag) {
            if (!empty($item_tag['tag'])) {
                $tags .= $item_tag['tag']." ";
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
            "id='".$id."'"
        );
        // ADD an item
    } elseif ($action == "add_value") {
        // get new value from db
        $sql = "SELECT i.label, i.description, i.id_tree as id_tree, i.perso, i.restricted_to, i.id, i.login
                FROM ".$pre."items as i
                INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
                WHERE i.id=".$id."
                AND l.action = 'at_creation'";
        $row = $db->query($sql);
        $data = $db->fetchArray($row);
        // Get all TAGS
        $tags = "";
        $item_tags = $db->fetchAllArray("SELECT tag FROM ".$pre."tags WHERE item_id=".$id);
        foreach ($item_tags as $item_tag) {
            if (!empty($item_tag['tag'])) {
                $tags .= $item_tag['tag']." ";
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
    $data_folders = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."nested_tree");
    // Count no USERS
    $data_users = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."users");
    // Count no ITEMS
    $data_items = $db->fetchRow("SELECT COUNT(*) FROM ".$pre."items");
    // Get info about installation
    $data_system = array();
    $rows = $db->fetchAllArray("SELECT valeur,intitule FROM ".$pre."misc WHERE type = 'admin' AND intitule IN ('enable_pf_feature','log_connections','cpassman_version')");
    foreach ($rows as $reccord) {
        if ($reccord['intitule'] == 'enable_pf_feature') {
            $data_system['enable_pf_feature'] = $reccord['valeur'];
        } elseif ($reccord['intitule'] == 'cpassman_version') {
            $data_system['cpassman_version'] = $reccord['valeur'];
        } elseif ($reccord['intitule'] == 'log_connections') {
            $data_system['log_connections'] = $reccord['valeur'];
        }
    }
    // Get the actual stats.
    $stats_to_send = array(
        'uid' => md5(SALT),
        'time_added' => time(),
        'users' => $data_users[0],
        'folders' => $data_folders[0],
        'items' => $data_items[0],
        'cpm_version' => $data_system['cpassman_version'],
        'enable_pf_feature' => $data_system['enable_pf_feature'],
        'log_connections' => $data_system['log_connections'],
       );
    // Encode all the data, for security.
    foreach ($stats_to_send as $k => $v) {
        $stats_to_send[$k] = urlencode($k).'='.urlencode($v);
    }
    // Turn this into the query string!
    $stats_to_send = implode('&', $stats_to_send);

    fopen("http://www.teampass.net/files/cpm_stats/collect_stats.php?".$stats_to_send, 'r');
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
function sendEmail($subject, $text_mail, $email, $text_mail_alt = "")
{
    include $_SESSION['settings']['cpassman_dir'].'/includes/settings.php';

    //load ClassLoader
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    //load library
    $mail = new SplClassLoader('Email\Phpmailer', $_SESSION['settings']['cpassman_dir'].'/includes/libraries');
    $mail->register();
    $mail = new Email\Phpmailer\PHPMailer();

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
    $mail->Body = $text_mail;
    $mail->AltBody = $text_mail_alt;
    // send email
    if (!$mail->send()) {
        return '"error":"error_mail_not_send" , "message":"'.$mail->ErrorInfo.'"';
    } else {
        return '"error":"" , "message":""';
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
