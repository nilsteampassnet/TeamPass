<?php
/**
 *
 * @file          main.functions.php
 * @author        Nils Laumaillé
 * @version       2.1.27
 * @copyright     (c) 2009-2017 Nils Laumaillé
 * @licensing     GNU AFFERO GPL 3.0
 * @link
 */

//define pbkdf2 iteration count
@define('ITCOUNT', '2072');

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

// load phpCrypt
if (!isset($_SESSION['settings']['cpassman_dir']) || empty($_SESSION['settings']['cpassman_dir'])) {
    require_once '../includes/libraries/phpcrypt/phpCrypt.php';
    require_once '../includes/config/settings.php';
} else {
    require_once $_SESSION['settings']['cpassman_dir'] . '/includes/libraries/phpcrypt/phpCrypt.php';
    require_once $_SESSION['settings']['cpassman_dir'] . '/includes/config/settings.php';
}
use PHP_Crypt\PHP_Crypt as PHP_Crypt;
use PHP_Crypt\Cipher as Cipher;


// prepare Encryption class calls
use \Defuse\Crypto\Crypto;
use \Defuse\Crypto\Exception as Ex;

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
    if (!isset($_SESSION['settings']['cpassman_dir']) || empty($_SESSION['settings']['cpassman_dir'])) {
        require_once '../includes/libraries/Encryption/PBKDF2/PasswordHash.php';
    } else {
        require_once $_SESSION['settings']['cpassman_dir'] . '/includes/libraries/Encryption/PBKDF2/PasswordHash.php';
    }

    if (!empty($personalSalt)) {
         $staticSalt = $personalSalt;
    } else {
         $staticSalt = SALT;
    }

    //set our salt to a variable
    // Get 64 random bits for the salt for pbkdf2
    $pbkdf2Salt = getBits(64);
    // generate a pbkdf2 key to use for the encryption.
    //$key = strHashPbkdf2($staticSalt, $pbkdf2Salt, ITCOUNT, 16, 'sha256', 32);
    $key = substr(pbkdf2('sha256', $staticSalt, $pbkdf2Salt, ITCOUNT, 16+32, true), 32, 16);
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
    if (!isset($_SESSION['settings']['cpassman_dir']) || empty($_SESSION['settings']['cpassman_dir'])) {
        require_once '../includes/libraries/Encryption/PBKDF2/PasswordHash.php';
    } else {
        require_once $_SESSION['settings']['cpassman_dir'] . '/includes/libraries/Encryption/PBKDF2/PasswordHash.php';
    }

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
    //$key = strHashPbkdf2($staticSalt, $pbkdf2Salt, ITCOUNT, 16, 'sha256', 32);
    $key = substr(pbkdf2('sha256', $staticSalt, $pbkdf2Salt, ITCOUNT, 16+32, true), 32, 16);
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

function cryption_before_defuse($message, $sk, $iv, $type = null, $scope = "public")
{
    if (DEFUSE_ENCRYPTION === TRUE) {
        if ($scope === "perso") {
            return defuse_crypto(
                $message,
                $sk,
                $type
            );
        } else {
            return defuse_crypto(
                $message,
                file_get_contents(SECUREPATH."/teampass-seckey.txt"),
                $type
            );
        }
    } else {
        return cryption_phpCrypt($message, $sk, $iv, $type);
    }
}

/*
 * cryption() - Encrypt and decrypt string based upon phpCrypt library
 *
 * Using AES_128 and mode CBC
 *
 * $key and $iv have to be given in hex format
 */
function cryption_phpCrypt($string, $key, $iv, $type)
{
    // manage key origin
    define('SALT', 'LEfzTjADMTzV6qHC');

    if ($key != SALT) {
        // check key (AES-128 requires a 16 bytes length key)
        if (strlen($key) < 16) {
            for ($x = strlen($key) + 1; $x <= 16; $x++) {
                $key .= chr(0);
            }
        } else if (strlen($key) > 16) {
            $key = substr($key, 16);
        }
    }

    // load crypt
    $crypt = new PHP_Crypt($key, PHP_Crypt::CIPHER_AES_128, PHP_Crypt::MODE_CBC);

    if ($type == "encrypt") {
        // generate IV and encrypt
        $iv = $crypt->createIV();
        $encrypt = $crypt->encrypt($string);
        // return
        return array(
            "string" => bin2hex($encrypt),
            "iv" => bin2hex($iv),
            "error" => empty($encrypt) ? "ERR_ENCRYPTION_NOT_CORRECT" : ""
        );
    } else if ($type == "decrypt") {
        // case if IV is empty
        if (empty($iv))
            return array(
                'string' => "",
                'error' => "ERR_ENCRYPTION_NOT_CORRECT"
            );

        // convert
        try {
            $string = testHex2Bin(trim($string));
            $iv = testHex2Bin($iv);
        }
        catch (Exception $e) {
            // error - $e->getMessage();
            return array(
                'string' => "",
                'error' => "ERR_ENCRYPTION_NOT_CORRECT"
            );
        }

        // load IV
        $crypt->IV($iv);
        // decrypt
        $decrypt = $crypt->decrypt($string);
        // return
        //return str_replace(chr(0), "", $decrypt);
        return array(
            'string' => str_replace(chr(0), "", $decrypt),
            'error' => ""
        );
    }
}

function testHex2Bin ($val)
{
    if (!@hex2bin($val)) {
        throw new Exception("ERROR");
    }
    return hex2bin($val);
}

function cryption($message, $ascii_key, $type) //defuse_crypto
{
    // load PhpEncryption library
    if (!isset($_SESSION['settings']['cpassman_dir']) || empty($_SESSION['settings']['cpassman_dir'])) {
        $path = '../includes/libraries/Encryption/Encryption/';
    } else {
        $path = $_SESSION['settings']['cpassman_dir'] . '/includes/libraries/Encryption/Encryption/';
    }

    require_once $path.'Crypto.php';
    require_once $path.'Encoding.php';
    require_once $path.'DerivedKeys.php';
    require_once $path.'Key.php';
    require_once $path.'KeyOrPassword.php';
    require_once $path.'File.php';
    require_once $path.'RuntimeTests.php';
    require_once $path.'KeyProtectedByPassword.php';
    require_once $path.'Core.php';

    // init
    $err = '';
    if (empty($ascii_key)) {
        $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");
    }

    // convert KEY
    $key = \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key);

    try {
        if ($type === "encrypt") {
            $text = \Defuse\Crypto\Crypto::encrypt($message, $key);
        } else if ($type === "decrypt") {
            $text = \Defuse\Crypto\Crypto::decrypt($message, $key);
        }
    }
    catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
        $err = "An attack! Either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.";
    }
    catch (Defuse\Crypto\Exception\BadFormatException $ex) {
        $err = $ex;
    }
    catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
        $err = $ex;
    }
    catch (Defuse\Crypto\Exception\CryptoException $ex) {
        $err = $ex;
    }
    catch (Defuse\Crypto\Exception\IOException $ex) {
        $err = $ex;
    }

    return array(
        'string' => isset($text) ? $text : "",
        'error' => $err
    );
}

function defuse_generate_key() {
    require_once '../includes/libraries/Encryption/Encryption/Crypto.php';
    require_once '../includes/libraries/Encryption/Encryption/Encoding.php';
    require_once '../includes/libraries/Encryption/Encryption/DerivedKeys.php';
    require_once '../includes/libraries/Encryption/Encryption/Key.php';
    require_once '../includes/libraries/Encryption/Encryption/KeyOrPassword.php';
    require_once '../includes/libraries/Encryption/Encryption/File.php';
    require_once '../includes/libraries/Encryption/Encryption/RuntimeTests.php';
    require_once '../includes/libraries/Encryption/Encryption/KeyProtectedByPassword.php';
    require_once '../includes/libraries/Encryption/Encryption/Core.php';

    $key = \Defuse\Crypto\Key::createNewRandomKey();
    $key = $key->saveToAsciiSafeString();
    return $key;
}

function defuse_generate_personal_key($psk) {
    require_once '../includes/libraries/Encryption/Encryption/Crypto.php';
    require_once '../includes/libraries/Encryption/Encryption/Encoding.php';
    require_once '../includes/libraries/Encryption/Encryption/DerivedKeys.php';
    require_once '../includes/libraries/Encryption/Encryption/Key.php';
    require_once '../includes/libraries/Encryption/Encryption/KeyOrPassword.php';
    require_once '../includes/libraries/Encryption/Encryption/File.php';
    require_once '../includes/libraries/Encryption/Encryption/RuntimeTests.php';
    require_once '../includes/libraries/Encryption/Encryption/KeyProtectedByPassword.php';
    require_once '../includes/libraries/Encryption/Encryption/Core.php';

    $protected_key = \Defuse\Crypto\KeyProtectedByPassword::createRandomPasswordProtectedKey($psk);
    $protected_key_encoded = $protected_key->saveToAsciiSafeString();

    return $protected_key_encoded;  // save this in user table
}

function defuse_validate_personal_key($psk, $protected_key_encoded) {
    require_once '../includes/libraries/Encryption/Encryption/Crypto.php';
    require_once '../includes/libraries/Encryption/Encryption/Encoding.php';
    require_once '../includes/libraries/Encryption/Encryption/DerivedKeys.php';
    require_once '../includes/libraries/Encryption/Encryption/Key.php';
    require_once '../includes/libraries/Encryption/Encryption/KeyOrPassword.php';
    require_once '../includes/libraries/Encryption/Encryption/File.php';
    require_once '../includes/libraries/Encryption/Encryption/RuntimeTests.php';
    require_once '../includes/libraries/Encryption/Encryption/KeyProtectedByPassword.php';
    require_once '../includes/libraries/Encryption/Encryption/Core.php';

    try {
        $protected_key = \Defuse\Crypto\KeyProtectedByPassword::loadFromAsciiSafeString($protected_key_encoded);
        $user_key = $protected_key->unlockKey($psk);
        $user_key_encoded = $user_key->saveToAsciiSafeString();
    }
    catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
        return "Error - Major issue as the encryption is broken.";
    }
    catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
        return "Error - The saltkey is not the correct one.";
    }

    return $user_key_encoded;   // store it in session once user has entered his psk
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
    }
    return $chaine;
}

/**
 * cleanString()
 *
 * permits to suppress all "special" characters from string
 */
function cleanString($string, $special = false)
{
    // Create temporary table for special characters escape
    $tabSpecialChar = array();
    for ($i = 0; $i <= 31; $i++) {
        $tabSpecialChar[] = chr($i);
    }
    array_push($tabSpecialChar, "<br />");
    if ($special == "1") {
        $tabSpecialChar = array_merge($tabSpecialChar, array("</li>", "<ul>", "<ol>"));
    }

    return str_replace($tabSpecialChar, "\n", $string);
}

function db_error_handler($params) {
    echo "Error: " . $params['error'] . "<br>\n";
    echo "Query: " . $params['query'] . "<br>\n";
    die; // don't want to keep going if a query broke
}

/**
 * identifyUserRights()
 *
 * @return
 */
function identifyUserRights($groupesVisiblesUser, $groupesInterditsUser, $isAdmin, $idFonctions, $refresh)
{
    global $server, $user, $pass, $database, $pre, $port, $encoding;

    //load ClassLoader
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    //Connect to DB
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

    // Check if user is ADMINISTRATOR
    if ($isAdmin == 1) {
        $groupesVisibles = array();
        $_SESSION['personal_folders'] = array();
        $_SESSION['groupes_visibles'] = array();
        $_SESSION['groupes_interdits'] = array();
        $_SESSION['personal_visible_groups'] = array();
        $_SESSION['read_only_folders'] = array();
        $_SESSION['list_restricted_folders_for_items'] = array();
        $_SESSION['list_folders_editable_by_role'] = array();
        $_SESSION['list_folders_limited'] = array();
        $_SESSION['groupes_visibles_list'] = "";
        $_SESSION['list_folders_limited'] = "";
        $rows = DB::query("SELECT id FROM ".prefix_table("nested_tree")." WHERE personal_folder = %i", 0);
        foreach ($rows as $record) {
            array_push($groupesVisibles, $record['id']);
        }
        $_SESSION['groupes_visibles'] = $groupesVisibles;
        $_SESSION['all_non_personal_folders'] = $groupesVisibles;
        // Exclude all PF
        $_SESSION['forbiden_pfs'] = array();
        $where = new WhereClause('and'); // create a WHERE statement of pieces joined by ANDs
        $where->add('personal_folder=%i', 1);
        if (isset($_SESSION['settings']['enable_pf_feature']) && $_SESSION['settings']['enable_pf_feature'] == 1) {
            $where->add('title=%s', $_SESSION['user_id']);
            $where->negateLast();
        }
        // Get ID of personal folder
        $pf = DB::queryfirstrow(
            "SELECT id FROM ".prefix_table("nested_tree")." WHERE title = %s",
            $_SESSION['user_id']
        );
        if (!empty($pf['id'])) {
            if (!in_array($pf['id'], $_SESSION['groupes_visibles'])) {
                array_push($_SESSION['groupes_visibles'], $pf['id']);
                array_push($_SESSION['personal_visible_groups'], $pf['id']);
                // get all descendants
                $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title', 'personal_folder');
                $tree->rebuild();
                $tst = $tree->getDescendants($pf['id']);
                foreach ($tst as $t) {
                    array_push($_SESSION['groupes_visibles'], $t->id);
                    array_push($_SESSION['personal_visible_groups'], $t->id);
                }
            }
        }

        // get complete list of ROLES
        $tmp = explode(";", $_SESSION['fonction_id']);
        $rows = DB::query(
            "SELECT * FROM ".prefix_table("roles_title")."
            ORDER BY title ASC");
        foreach ($rows as $record) {
            if (!empty($record['id']) && !in_array($record['id'], $tmp)) {
                array_push($tmp, $record['id']);
            }
        }
        $_SESSION['fonction_id'] = implode(";", $tmp);

        $_SESSION['groupes_visibles_list'] = implode(',', $_SESSION['groupes_visibles']);
        $_SESSION['is_admin'] = $isAdmin;
        // Check if admin has created Folders and Roles
        DB::query("SELECT * FROM ".prefix_table("nested_tree")."");
        $_SESSION['nb_folders'] = DB::count();
        DB::query("SELECT * FROM ".prefix_table("roles_title"));
        $_SESSION['nb_roles'] = DB::count();
    } else {
        // init
        $_SESSION['groupes_visibles'] = array();
        $_SESSION['personal_folders'] = array();
        $_SESSION['groupes_interdits'] = array();
        $_SESSION['personal_visible_groups'] = array();
        $_SESSION['read_only_folders'] = array();
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

        $listAllowedFolders = $listForbidenFolders = $listFoldersLimited = $listFoldersEditableByRole = $listRestrictedFoldersForItems = $listReadOnlyFolders = $listNoAccessFolders = array();

        // rechercher tous les groupes visibles en fonction des roles de l'utilisateur
        foreach ($fonctionsAssociees as $roleId) {
            if (!empty($roleId)) {
                // Get allowed folders for each Role
                $rows = DB::query("SELECT folder_id FROM ".prefix_table("roles_values")." WHERE role_id=%i", $roleId);

                if (DB::count() > 0) {
                    $tmp = DB::queryfirstrow("SELECT allow_pw_change FROM ".prefix_table("roles_title")." WHERE id = %i", $roleId);
                    foreach ($rows as $record) {
                        if (isset($record['folder_id']) && !in_array($record['folder_id'], $listAllowedFolders)) {
                            array_push($listAllowedFolders, $record['folder_id']);//echo $record['folder_id'].";";
                        }
                        // Check if this group is allowed to modify any pw in allowed folders
                        if ($tmp['allow_pw_change'] == 1 && !in_array($record['folder_id'], $listFoldersEditableByRole)) {
                            array_push($listFoldersEditableByRole, $record['folder_id']);
                        }
                    }
                    // Check for the users roles if some specific rights exist on items
                    $rows = DB::query(
                        "SELECT i.id_tree, r.item_id
                        FROM ".prefix_table("items")." as i
                        INNER JOIN ".prefix_table("restriction_to_roles")." as r ON (r.item_id=i.id)
                        WHERE r.role_id=%i
                        ORDER BY i.id_tree ASC",
                        $roleId
                    );
                    $x = 0;
                    foreach ($rows as $record) {
                        if (isset($record['id_tree'])) {
                            $listFoldersLimited[$record['id_tree']][$x] = $record['item_id'];
                            $x++;
                        }
                    }
                }
            }
        }

        // Does this user is allowed to see other items
        $x = 0;
        $rows = DB::query(
            "SELECT id, id_tree FROM ".prefix_table("items")."
            WHERE restricted_to LIKE %ss AND inactif=%s",
            $_SESSION['user_id'].';',
            '0'
        );
        foreach ($rows as $record) {
            $listRestrictedFoldersForItems[$record['id_tree']][$x] = $record['id'];
            $x++;
        }
        // => Build final lists
        // Clean arrays
        $allowedFoldersTmp = array();
        $listAllowedFolders = array_unique($listAllowedFolders);
        $groupesVisiblesUser = explode(';', trimElement($groupesVisiblesUser, ";"));
        // Add user allowed folders
        $allowedFoldersTmp = array_unique(
            array_merge($listAllowedFolders, $groupesVisiblesUser)
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

        $where = new WhereClause('and');
        $where->add('personal_folder=%i', 1);
        if (
            isset($_SESSION['settings']['enable_pf_feature']) &&
            $_SESSION['settings']['enable_pf_feature'] == 1 &&
            isset($_SESSION['personal_folder']) &&
            $_SESSION['personal_folder'] == 1
        ) {
            $where->add('title=%s', $_SESSION['user_id']);
            $where->negateLast();
        }

        $pfs = DB::query("SELECT id FROM ".prefix_table("nested_tree")." WHERE %l", $where);
        foreach ($pfs as $pfId) {
            array_push($_SESSION['forbiden_pfs'], $pfId['id']);
        }
        // Get IDs of personal folders
        if (
            isset($_SESSION['settings']['enable_pf_feature']) &&
            $_SESSION['settings']['enable_pf_feature'] == 1 &&
            isset($_SESSION['personal_folder']) &&
            $_SESSION['personal_folder'] == 1
        ) {
            $pf = DB::queryfirstrow("SELECT id FROM ".prefix_table("nested_tree")." WHERE title = %s", $_SESSION['user_id']);
            if (!empty($pf['id'])) {
                if (!in_array($pf['id'], $listAllowedFolders)) {
                    array_push($_SESSION['personal_folders'], $pf['id']);
                    // get all descendants
                    $ids = $tree->getDescendants($pf['id'], true, false);
                    foreach ($ids as $id) {
                        array_push($listAllowedFolders, $id->id);
                        array_push($_SESSION['personal_visible_groups'], $id->id);
                        array_push($_SESSION['personal_folders'], $id->id);
                    }
                }
            }
            // get list of readonly folders when pf is disabled.
            // rule - if one folder is set as W or N in one of the Role, then User has access as W
            foreach ($listAllowedFolders as $folderId) {
                if (!in_array($folderId, array_unique (array_merge ($listReadOnlyFolders, $_SESSION['personal_folders'])))) {   //
                    DB::query(
                        "SELECT *
                        FROM ".prefix_table("roles_values")."
                        WHERE folder_id = %i AND role_id IN %li AND type IN %ls",
                        $folderId,
                        $fonctionsAssociees,
                        array("W","ND","NE","NDNE")

                    );
                    if (DB::count() == 0 && !in_array($folderId, $groupesVisiblesUser)) {
                        array_push($listReadOnlyFolders, $folderId);
                    }
                }
            }
        } else {
            // get list of readonly folders when pf is disabled.
            // rule - if one folder is set as W in one of the Role, then User has access as W
            foreach ($listAllowedFolders as $folderId) {
                if (!in_array($folderId, $listReadOnlyFolders)) {   // || (isset($pf) && $folderId != $pf['id'])
                    DB::query(
                        "SELECT *
                        FROM ".prefix_table("roles_values")."
                        WHERE folder_id = %i AND role_id IN %li AND type IN %ls",
                        $folderId,
                        $fonctionsAssociees,
                        array("W","ND","NE","NDNE")
                    );
                    if (DB::count() == 0 && !in_array($folderId, $groupesVisiblesUser)) {
                        array_push($listReadOnlyFolders, $folderId);
                    }
                }
            }
        }

        // check if change proposals on User's items
        if (isset($_SESSION['settings']['enable_suggestion']) && $_SESSION['settings']['enable_suggestion'] == 1) {
            DB::query(
                "SELECT *
                FROM ".prefix_table("items_change")." AS c
                LEFT JOIN ".prefix_table("log_items")." AS i ON (c.item_id = i.id_item)
                WHERE i.action = %s AND i.id_user = %i",
                "at_creation",
                $_SESSION['user_id']
            );
            $_SESSION['nb_item_change_proposals'] = DB::count();
        } else {
            $_SESSION['nb_item_change_proposals'] = 0;
        }

        $_SESSION['all_non_personal_folders'] = $listAllowedFolders;
        $_SESSION['groupes_visibles'] = $listAllowedFolders;
        $_SESSION['groupes_visibles_list'] = implode(',', $listAllowedFolders);
        $_SESSION['personal_visible_groups_list'] = implode(',', $_SESSION['personal_visible_groups']);
        $_SESSION['read_only_folders'] = $listReadOnlyFolders;
        $_SESSION['no_access_folders'] = $groupesInterdits;

        $_SESSION['list_folders_limited'] = $listFoldersLimited;
        $_SESSION['list_folders_editable_by_role'] = $listFoldersEditableByRole;
        $_SESSION['list_restricted_folders_for_items'] = $listRestrictedFoldersForItems;
        // Folders and Roles numbers
        DB::queryfirstrow("SELECT id FROM ".prefix_table("nested_tree")."");
        $_SESSION['nb_folders'] = DB::count();
        DB::queryfirstrow("SELECT id FROM ".prefix_table("roles_title"));
        $_SESSION['nb_roles'] = DB::count();
    }

    // update user's timestamp
    DB::update(
        prefix_table('users'),
        array(
            'timestamp' => time()
        ),
        "id=%i",
        $_SESSION['user_id']
    );
}

/**
 * updateCacheTable()
 *
 * Update the CACHE table
 */
function updateCacheTable($action, $id = "")
{
    global $db, $server, $user, $pass, $database, $pre, $port, $encoding;
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';

    //Connect to DB
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

    //Load Tree
    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

    // Rebuild full cache table
    if ($action === "reload") {
        // truncate table
        DB::query("TRUNCATE TABLE ".$pre."cache");

        // reload date
        $rows = DB::query(
            "SELECT *
            FROM ".$pre."items as i
            INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
            AND l.action = %s
            AND i.inactif = %i",
            'at_creation',
            0
        );
        foreach ($rows as $record) {
            // Get all TAGS
            $tags = "";
            $itemTags = DB::query("SELECT tag FROM ".$pre."tags WHERE item_id=%i", $record['id']);
            foreach ($itemTags as $itemTag) {
                if (!empty($itemTag['tag'])) {
                    $tags .= $itemTag['tag']." ";
                }
            }
            // Get renewal period
            $resNT = DB::queryfirstrow("SELECT renewal_period FROM ".$pre."nested_tree WHERE id=%i", $record['id_tree']);

            // form id_tree to full foldername
            $folder = "";
            $arbo = $tree->getPath($record['id_tree'], true);
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
            DB::insert(
                $pre."cache",
                array(
                    'id' => $record['id'],
                    'label' => $record['label'],
                    'description' => $record['description'],
                    'url' => (isset($record['url']) && !empty($record['url'])) ? $record['url'] : "0",
                    'tags' => $tags,
                    'id_tree' => $record['id_tree'],
                    'perso' => $record['perso'],
                    'restricted_to' => (isset($record['restricted_to']) && !empty($record['restricted_to'])) ? $record['restricted_to'] : "0",
                    'login' => isset($record['login']) ? $record['login'] : "",
                    'folder' => $folder,
                    'author' => $record['id_user'],
                    'renewal_period' => isset($resNT['renewal_period']) ? $resNT['renewal_period'] : "0",
                    'timestamp' => $record['date']
                   )
            );
        }
        // UPDATE an item
    } elseif ($action === "update_value") {
        // get new value from db
        $data = DB::queryfirstrow(
            "SELECT label, description, id_tree, perso, restricted_to, login, url
            FROM ".$pre."items
            WHERE id=%i", $id);
        // Get all TAGS
        $tags = "";
        $itemTags = DB::query("SELECT tag FROM ".$pre."tags WHERE item_id=%i", $id);
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
        DB::update(
            $pre."cache",
            array(
                'label' => $data['label'],
                'description' => $data['description'],
                'tags' => $tags,
                'url' => (isset($data['url']) && !empty($data['url'])) ? $data['url'] : "0",
                'id_tree' => $data['id_tree'],
                'perso' => $data['perso'],
                'restricted_to' => $data['restricted_to'],
                'login' => isset($data['login']) ? $data['login'] : "",
                'folder' => $folder,
                'author' => $_SESSION['user_id'],
               ),
            "id = %i",
            $id
        );
        // ADD an item
    } elseif ($action === "add_value") {
        // get new value from db
        $data = DB::queryFirstRow(
            "SELECT i.label, i.description, i.id_tree as id_tree, i.perso, i.restricted_to, i.id, i.login, i.url, l.date
            FROM ".$pre."items as i
            INNER JOIN ".$pre."log_items as l ON (l.id_item = i.id)
            WHERE i.id = %i
            AND l.action = %s",
            $id, 'at_creation'
        );
        // Get all TAGS
        $tags = "";
        $itemTags = DB::query("SELECT tag FROM ".$pre."tags WHERE item_id = %i", $id);
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
        DB::insert(
            $pre."cache",
            array(
                'id' => $data['id'],
                'label' => $data['label'],
                'description' => $data['description'],
                'tags' => $tags,
                'url' => (isset($data['url']) && !empty($data['url'])) ? $data['url'] : "0",
                'url' => $data['url'],
                'id_tree' => $data['id_tree'],
                'perso' => $data['perso'],
                'restricted_to' => $data['restricted_to'],
                'login' => isset($data['login']) ? $data['login'] : "",
                'folder' => $folder,
                'author' => $_SESSION['user_id'],
                'timestamp' => $data['date']
               )
        );
        // DELETE an item
    } elseif ($action === "delete_value") {
        DB::delete($pre."cache", "id = %i", $id);
    }
}

/*
*
*/
function getStatisticsData() {
     DB::query(
        "SELECT id FROM ".prefix_table("nested_tree")." WHERE personal_folder = %i",
        0
    );
    $counter_folders = DB::count();

    DB::query(
        "SELECT id FROM ".prefix_table("nested_tree")." WHERE personal_folder = %i",
        1
    );
    $counter_folders_perso = DB::count();

    DB::query(
        "SELECT id FROM ".prefix_table("items")." WHERE perso = %i",
        0
    );
    $counter_items = DB::count();

    DB::query(
        "SELECT id FROM ".prefix_table("items")." WHERE perso = %i",
        1
    );
    $counter_items_perso = DB::count();

    DB::query(
        "SELECT id FROM ".prefix_table("users").""
    );
    $counter_users = DB::count();

    DB::query(
        "SELECT id FROM ".prefix_table("users")." WHERE admin = %i",
        1
    );
    $admins = DB::count();

    DB::query(
        "SELECT id FROM ".prefix_table("users")." WHERE gestionnaire = %i",
        1
    );
    $managers = DB::count();

    DB::query(
        "SELECT id FROM ".prefix_table("users")." WHERE read_only = %i",
        1
    );
    $ro = DB::count();

    // list the languages
    $usedLang = [];
    $tp_languages = DB::query(
        "SELECT name FROM ".prefix_table("languages")
    );
    foreach ($tp_languages as $tp_language) {
        DB::query(
            "SELECT * FROM ".prefix_table("users")." WHERE user_language = %s",
            $tp_language['name']
        );
        $usedLang[$tp_language['name']] = round((DB::count() * 100 / $counter_users), 0);
    }

    // get list of ips
    $usedIp = [];
    $tp_ips = DB::query(
        "SELECT user_ip FROM ".prefix_table("users")
    );
    foreach ($tp_ips as $ip) {
        if (array_key_exists($ip['user_ip'], $usedIp)) {
            $usedIp[$ip['user_ip']] = $usedIp[$ip['user_ip']] + 1;
        } else if (!empty($ip['user_ip']) && $ip['user_ip'] !== "none") {
            $usedIp[$ip['user_ip']] = 1;
        }
    }

    return array(
        "error" => "",
        "stat_phpversion" => phpversion(),
        "stat_folders" => $counter_folders,
        "stat_folders_shared" => intval($counter_folders) - intval($counter_folders_perso),
        "stat_items" => $counter_items,
        "stat_items_shared" => intval($counter_items) - intval($counter_items_perso),
        "stat_users" => $counter_users,
        "stat_admins" => $admins,
        "stat_managers" => $managers,
        "stat_ro" => $ro,
        "stat_kb" => $_SESSION['settings']['enable_kb'],
        "stat_pf" => $_SESSION['settings']['enable_pf_feature'],
        "stat_fav" => $_SESSION['settings']['enable_favourites'],
        "stat_teampassversion" => $_SESSION['settings']['cpassman_version'],
        "stat_ldap" => $_SESSION['settings']['ldap_mode'],
        "stat_agses" => $_SESSION['settings']['agses_authentication_enabled'],
        "stat_duo" => $_SESSION['settings']['duo'],
        "stat_suggestion" => $_SESSION['settings']['enable_suggestion'],
        "stat_api" => $_SESSION['settings']['api'],
        "stat_customfields" => $_SESSION['settings']['item_extra_fields'],
        "stat_syslog" => $_SESSION['settings']['syslog_enable'],
        "stat_2fa" => $_SESSION['settings']['google_authentication'],
        "stat_stricthttps" => $_SESSION['settings']['enable_sts'],
        "stat_mysqlversion" => DB::serverVersion(),
        "stat_languages" => $usedLang,
        "stat_country" => $usedIp
    );
}

/**
 * sendEmail()
 *
 * @return
 */
function sendEmail($subject, $textMail, $email, $textMailAlt = "")
{
    global $LANG;
    include $_SESSION['settings']['cpassman_dir'].'/includes/config/settings.php';
    //load library
    $user_language = isset($_SESSION['user_language']) ?$_SESSION['user_language'] : "english";
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/language/'.$user_language.'.php';
    require_once $_SESSION['settings']['cpassman_dir'].'/includes/libraries/Email/Phpmailer/PHPMailerAutoload.php';

    // load PHPMailer
    if (!isset($mail)) $mail = new PHPMailer();
    // send to user
    $mail->setLanguage("en", "../includes/libraries/Email/Phpmailer/language/");
    $mail->SMTPDebug = 0; //value 1 can be used to debug
    $mail->Port = $_SESSION['settings']['email_port']; //COULD BE USED
    $mail->CharSet = "utf-8";
    $smtp_security = $_SESSION['settings']['email_security'];
    if ($smtp_security == "tls" || $smtp_security == "ssl") {
        $mail->SMTPSecure = $smtp_security;
    }
    $mail->isSmtp(); // send via SMTP
    $mail->Host = $_SESSION['settings']['email_smtp_server']; // SMTP servers
    $mail->SMTPAuth = $_SESSION['settings']['email_smtp_auth'] == '1' ? true : false; // turn on SMTP authentication
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
        return '"error":"error_mail_not_send" , "message":"'.str_replace(array("\n", "\t", "\r"), '', $mail->ErrorInfo).'"';
    } else {
        return '"error":"" , "message":"'.$LANG['forgot_my_pw_email_sent'].'"';
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
 * @return string is the string in UTF8 format.
 */

function isUTF8($string)
{
    if (is_array($string) === true) $string = $string['string'];
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
    //load ClassLoader
    require_once $_SESSION['settings']['cpassman_dir'].'/sources/SplClassLoader.php';
    //Load AES
    $aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
    $aes->register();

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
                Encryption\Crypt\aesctr::decrypt(
                    $data,
                    $_SESSION['key'],
                    256
                ),
                true
            );
        }
    }
}

function make_thumb($src, $dest, $desired_width) {

    /* read the source image */
    $source_image = imagecreatefrompng($src);
    $width = imagesx($source_image);
    $height = imagesy($source_image);

    /* find the "desired height" of this thumbnail, relative to the desired width  */
    $desired_height = floor($height * ($desired_width / $width));

    /* create a new, "virtual" image */
    $virtual_image = imagecreatetruecolor($desired_width, $desired_height);

    /* copy source image at a resized size */
    imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);

    /* create the physical thumbnail image to its destination */
    imagejpeg($virtual_image, $dest);
}

/*
** check table prefix in SQL query
*/
function prefix_table($table)
{
    global $pre;
    $safeTable = htmlspecialchars($pre.$table);
    if (!empty($safeTable)) {
        // sanitize string
        return $safeTable;
    } else {
        // stop error no table
        return false;
    }
}

/*
 * Creates a KEY using PasswordLib
 */
function GenerateCryptKey($size="", $secure="", $numerals="", $capitalize="", $ambiguous="", $symbols="")
{
    // load library
    $pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
    $pwgen->register();
    $pwgen = new Encryption\PwGen\pwgen();

    // init
    if(!empty($size)) $pwgen->setLength($size);
    if(!empty($secure)) $pwgen->setSecure($secure);
    if(!empty($numerals)) $pwgen->setNumerals($numerals);
    if(!empty($capitalize)) $pwgen->setCapitalize($capitalize);
    if(!empty($ambiguous)) $pwgen->setAmbiguous($ambiguous);
    if(!empty($symbols)) $pwgen->setSymbols($symbols);

    // generate and send back
    return $pwgen->generate();
}

/*
* Send sysLOG message
*/
function send_syslog($message, $component = "teampass", $program = "php", $host , $port)
{
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        //$syslog_message = "<123>" . date('M d H:i:s ') . " " .$host . " " . $component . ": " . $message;
    $syslog_message = "<123>" . date('M d H:i:s ') . $component . ": " . $message;
        socket_sendto($sock, $syslog_message, strlen($syslog_message), 0, $host, $port);
    socket_close($sock);
}



/**
 * logEvents()
 *
 * permits to log events into DB
 */
function logEvents($type, $label, $who, $login="", $field_1 = NULL)
{
    global $server, $user, $pass, $database, $pre, $port, $encoding;

    if (empty($who)) $who = get_client_ip_server();

    // include librairies & connect to DB
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

    DB::insert(
        prefix_table("log_system"),
        array(
            'type' => $type,
            'date' => time(),
            'label' => $label,
            'qui' => $who,
            'field_1' => $field_1 == null ? "" : $field_1
        )
    );
    if (isset($_SESSION['settings']['syslog_enable']) && $_SESSION['settings']['syslog_enable'] == 1) {
        if ($type == "user_mngt"){
            send_syslog("The User " .$login. " perform the acction off " .$label. " to the user " .$field_1. " - " .$type,"teampass","php",$_SESSION['settings']['syslog_host'],$_SESSION['settings']['syslog_port']);
        } else {
            send_syslog("The User " .$login. " perform the acction off " .$label. " - " .$type,"teampass","php",$_SESSION['settings']['syslog_host'],$_SESSION['settings']['syslog_port']);
        }
    }
}

function logItems($id, $item, $id_user, $action, $login = "", $raison = NULL, $raison_iv = NULL)
{
    global $server, $user, $pass, $database, $pre, $port, $encoding;
    // include librairies & connect to DB
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
    DB::insert(
        prefix_table(
            "log_items"),
            array(
                'id_item' => $id,
                'date' => time(),
                'id_user' => $id_user,
                'action' => $action,
                'raison' => $raison,
                'raison_iv' => $raison_iv
            )
        );
        if (isset($_SESSION['settings']['syslog_enable']) && $_SESSION['settings']['syslog_enable'] == 1) {
                send_syslog("The Item ".$item." was ".$action." by ".$login." ".$raison,"teampass","php",$_SESSION['settings']['syslog_host'],$_SESSION['settings']['syslog_port']);
        }
}

/*
* Function to get the client ip address
 */
function get_client_ip_server() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
        $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';

    return $ipaddress;
}

/**
 * Escape all HTML, JavaScript, and CSS
 *
 * @param string $input The input string
 * @param string $encoding Which character encoding are we using?
 * @return string
 */
function noHTML($input, $encoding = 'UTF-8')
{
    return htmlspecialchars($input, ENT_QUOTES | ENT_XHTML, $encoding, false);
}

/**
 * handleConfigFile()
 *
 * permits to handle the Teampass config file
 * $action accepts "rebuild" and "update"
 */
function handleConfigFile($action, $field = null, $value = null)
{
    global $server, $user, $pass, $database, $pre, $port, $encoding;
    $tp_config_file = "../includes/config/tp.config.php";

    // include librairies & connect to DB
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

    if (!file_exists($tp_config_file) || $action == "rebuild") {
        // perform a copy
        if (file_exists($tp_config_file)) {
            if (!copy($tp_config_file, $tp_config_file.'.'.date("Y_m_d_His", time()))) {
                return "ERROR: Could not copy file '" . $tp_config_file . "'";
            }
        }

        // regenerate
        $data = array();
        $data[0] = "<?php\n";
        $data[1] = "global \$SETTINGS;\n";
        $data[2] = "\$SETTINGS = array (\n";
        $rows = DB::query(
            "SELECT * FROM ".prefix_table("misc")." WHERE type=%s",
            "admin"
        );
        foreach ($rows as $record) {
            array_push($data, "    '".$record['intitule']."' => '".$record['valeur']."',\n");
        }
        array_push($data, ");");
        $dat = array_unique($data);
    } else if ($action == "update" && !empty($field)) {
        $data = file($tp_config_file);
        $x = 0;
        $bFound = false;
        foreach($data as $line) {
            if (stristr($line, ");")) break;
            if (stristr($line, "'".$field."' => '")) {
                $data[$x] = "    '".$field."' => '".$value."',\n";
                $bFound = true;
                break;
            }
            $x++;
        }
        if ($bFound === false) {
            $data[($x-1)] = "    '".$field."' => '".$value."',\n";
        }
    } else {
        // ERROR
    }

    // update file
    file_put_contents($tp_config_file, implode('', $data));

    return true;
}

/*
** Permits to replace &#92; to permit correct display
*/
function handleBackslash ($input)
{
    return str_replace("&amp;#92;", "&#92;", $input);
}

/*
** Permits to loas settings
*/
function loadSettings ()
{
    /* LOAD CPASSMAN SETTINGS */
    if (!isset($_SESSION['settings']['loaded']) || $_SESSION['settings']['loaded'] != 1) {
        $_SESSION['settings']['duplicate_folder'] = 0;  //by default, this is false;
        $_SESSION['settings']['duplicate_item'] = 0;  //by default, this is false;
        $_SESSION['settings']['number_of_used_pw'] = 5; //by default, this value is 5;

        $rows = DB::query("SELECT * FROM " . prefix_table("misc") . " WHERE type=%s_type OR type=%s_type2",
            array(
                'type' => "admin",
                'type2' => "settings"
            )
        );
        foreach ($rows as $record) {
            if ($record['type'] == 'admin') {
                $_SESSION['settings'][$record['intitule']] = $record['valeur'];
            } else {
                $settings[$record['intitule']] = $record['valeur'];
            }
        }
        $_SESSION['settings']['loaded'] = 1;
        $_SESSION['settings']['default_session_expiration_time'] = 5;
    }
}

/*
** check if folder has custom fields.
** Ensure that target one also has same custom fields
*/
function checkCFconsistency($source_id, $target_id) {
    $source_cf = array();
    $rows = DB::QUERY(
        "SELECT id_category
        FROM ".prefix_table("categories_folders")."
        WHERE id_folder = %i",
        $source_id
    );
    foreach ($rows as $record) {
        array_push($source_cf, $record['id_category']);
    }

    $target_cf = array();
    $rows = DB::QUERY(
        "SELECT id_category
        FROM ".prefix_table("categories_folders")."
        WHERE id_folder = %i",
        $target_id
    );
    foreach ($rows as $record) {
        array_push($target_cf, $record['id_category']);
    }

    $cf_diff = array_diff($source_cf, $target_cf);
    if (count($cf_diff) > 0) {
       return false;
    }

    return true;
}

/*
*
*/
function encrypt_or_decrypt_file($image_code, $image_status, $opts) {
    global $server, $user, $pass, $database, $pre, $port, $encoding;
    $tp_config_file = "../includes/config/tp.config.php";

    // include librairies & connect to DB
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

    if (isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] === "1" && isset($image_status) && $image_status === "clear") {
        // file needs to be encrypted
        if (file_exists($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code)) {
            // make a copy of file
            if (!copy(
                    $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code,
                    $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".copy"
            )) {
                $error = "Copy not possible";
                exit;
            } else {
                // do a bck
                copy(
                    $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code,
                    $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".bck"
                );
            }

            // Open the file
            unlink($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code);
            $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".copy", "rb");
            $out = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code, 'wb');

            // ecnrypt
            stream_filter_append($out, 'mcrypt.tripledes', STREAM_FILTER_WRITE, $opts);

            // read file and create new one
            while (($line = fgets($fp)) !== false) {
                fputs($out, $line);
            }
            fclose($fp);
            fclose($out);

            // update table
            DB::update(
                prefix_table('files'),
                array(
                    'status' => 'encrypted'
                   ),
                "id=%i",
                substr($_POST['uri'], 1)
            );
        }
    } elseif (isset($_SESSION['settings']['enable_attachment_encryption']) && $_SESSION['settings']['enable_attachment_encryption'] === "0" && isset($image_status) && $image_status === "encrypted") {
        // file needs to be encrypted
        if (file_exists($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code)) {
            // make a copy of file
            if (!copy(
                    $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code,
                    $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".copy"
            )) {
                $error = "Copy not possible";
                exit;
            } else {
                // do a bck
                copy(
                    $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code,
                    $_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".bck"
                );
            }

            // Open the file
            unlink($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code);
            $fp = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code.".copy", "rb");
            $out = fopen($_SESSION['settings']['path_to_upload_folder'].'/'.$image_code, 'wb');

            // ecnrypt
            stream_filter_append($fp, 'mdecrypt.tripledes', STREAM_FILTER_READ, $opts);

            // read file and create new one
            while (($line = fgets($fp)) !== false) {
                fputs($out, $line);
            }
            fclose($fp);
            fclose($out);

            // update table
            DB::update(
                prefix_table('files'),
                array(
                    'status' => 'clear'
                   ),
                "id=%i",
                substr($_POST['uri'], 1)
            );
        }
    }
}

/*
* NOT TO BE USED
*/
function debugTeampass($text) {
    $debugFile = fopen('D:/wamp64/www/TeamPass/debug.txt', 'r+');
    fputs($debugFile, $text);
    fclose($debugFile);
}