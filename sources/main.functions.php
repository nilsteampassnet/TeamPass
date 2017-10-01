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
define('ITCOUNT', '2072');

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

// Load config
if (file_exists('../includes/config/tp.config.php')) {
    require_once '../includes/config/tp.config.php';
} elseif (file_exists('./includes/config/tp.config.php')) {
    require_once './includes/config/tp.config.php';
} elseif (file_exists('../../includes/config/tp.config.php')) {
    require_once '../../includes/config/tp.config.php';
} else {
    throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
}

// load phpCrypt
if (!isset($SETTINGS['cpassman_dir']) || empty($SETTINGS['cpassman_dir'])) {
    require_once '../includes/libraries/phpcrypt/phpCrypt.php';
    require_once '../includes/config/settings.php';
} else {
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/phpcrypt/phpCrypt.php';
    require_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
}

// Prepare PHPCrypt class calls
use PHP_Crypt\PHP_Crypt as PHP_Crypt;

// Prepare Encryption class calls
use \Defuse\Crypto\Crypto;
use \Defuse\Crypto\Exception as Ex;

//Generate N# of random bits for use as salt
/**
 * @param integer $size
 */
function getBits($size)
{
    $str = '';
    $var_x = $size + 10;
    for ($var_i = 0; $var_i < $var_x; $var_i++) {
        $str .= base_convert(mt_rand(1, 36), 10, 36);
    }
    return substr($str, 0, $size);
}

//generate pbkdf2 compliant hash
function strHashPbkdf2($var_p, $var_s, $var_c, $var_kl, $var_a = 'sha256', $var_st = 0)
{
    $var_kb = $var_st + $var_kl; // Key blocks to compute
    $var_dk = ''; // Derived key

    for ($block = 1; $block <= $var_kb; $block++) { // Create key
        $var_ib = $var_h = hash_hmac($var_a, $var_s.pack('N', $block), $var_p, true); // Initial hash for this block
        for ($var_i = 1; $var_i < $var_c; $var_i++) { // Perform block iterations
            $var_ib ^= ($var_h = hash_hmac($var_a, $var_h, $var_p, true)); // XOR each iterate
        }
        $var_dk .= $var_ib; // Append iterated block
    }
    return substr($var_dk, $var_st, $var_kl); // Return derived key of correct length
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
 * @param string $text
 */
function encryptOld($text, $personalSalt = "")
{
    if (empty($personalSalt) === false) {
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
    }

    // If $personalSalt is not empty
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
    }

    // No personal SK
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

/**
 * encrypt()
 *
 * crypt a string
 * @param string $decrypted
 */
function encrypt($decrypted, $personalSalt = "")
{
    global $SETTINGS;

    if (!isset($SETTINGS['cpassman_dir']) || empty($SETTINGS['cpassman_dir'])) {
        require_once '../includes/libraries/Encryption/PBKDF2/PasswordHash.php';
    } else {
        require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/PBKDF2/PasswordHash.php';
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
    $key = substr(pbkdf2('sha256', $staticSalt, $pbkdf2Salt, ITCOUNT, 16 + 32, true), 32, 16);
    // Build $init_vect and $ivBase64.  We use a block size of 256 bits (AES compliant)
    // and CTR mode.  (Note: ECB mode is inadequate as IV is not used.)
    $init_vect = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, 'ctr'), MCRYPT_RAND);

    //base64 trim
    if (strlen($ivBase64 = rtrim(base64_encode($init_vect), '=')) != 43) {
        return false;
    }
    // Encrypt $decrypted
    $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $decrypted, 'ctr', $init_vect);
    // MAC the encrypted text
    $mac = hash_hmac('sha256', $encrypted, $staticSalt);
    // We're done!
    return base64_encode($ivBase64.$encrypted.$mac.$pbkdf2Salt);
}

/**
 * decrypt()
 *
 * decrypt a crypted string
 */
function decrypt($encrypted, $personalSalt = "")
{
    global $SETTINGS;

    if (!isset($SETTINGS['cpassman_dir']) || empty($SETTINGS['cpassman_dir'])) {
        require_once '../includes/libraries/Encryption/PBKDF2/PasswordHash.php';
    } else {
        require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/PBKDF2/PasswordHash.php';
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
    $key = substr(pbkdf2('sha256', $staticSalt, $pbkdf2Salt, ITCOUNT, 16 + 32, true), 32, 16);
    // Retrieve $init_vect which is the first 22 characters plus ==, base64_decoded.
    $init_vect = base64_decode(substr($encrypted, 0, 43).'==');
    // Remove $init_vect from $encrypted.
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
    $decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $encrypted, 'ctr', $init_vect), "\0\4");
    // Yay!
    return $decrypted;
}


/**
 * genHash()
 *
 * Generate a hash for user login
 * @param string $password
 */
function bCrypt($password, $cost)
{
    $salt = sprintf('$2y$%02d$', $cost);
    if (function_exists('openssl_random_pseudo_bytes')) {
        $salt .= bin2hex(openssl_random_pseudo_bytes(11));
    } else {
        $chars = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i < 22; $i++) {
            $salt .= $chars[mt_rand(0, 63)];
        }
    }
    return crypt($password, $salt);
}

function cryption_before_defuse($message, $saltkey, $init_vect, $type = null, $scope = "public")
{
    if (DEFUSE_ENCRYPTION === true) {
        if ($scope === "perso") {
            return defuse_crypto(
                $message,
                $saltkey,
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
        return cryption_phpCrypt($message, $saltkey, $init_vect, $type);
    }
}

/*
 * cryption() - Encrypt and decrypt string based upon phpCrypt library
 *
 * Using AES_128 and mode CBC
 *
 * $key and $init_vect have to be given in hex format
 */
function cryption_phpCrypt($string, $key, $init_vect, $type)
{
    // manage key origin
    if (null != SALT && $key != SALT) {
        // check key (AES-128 requires a 16 bytes length key)
        if (strlen($key) < 16) {
            for ($inc = strlen($key) + 1; $inc <= 16; $inc++) {
                $key .= chr(0);
            }
        } elseif (strlen($key) > 16) {
            $key = substr($key, 16);
        }
    }

    // load crypt
    $crypt = new PHP_Crypt($key, PHP_Crypt::CIPHER_AES_128, PHP_Crypt::MODE_CBC);

    if ($type == "encrypt") {
        // generate IV and encrypt
        $init_vect = $crypt->createIV();
        $encrypt = $crypt->encrypt($string);
        // return
        return array(
            "string" => bin2hex($encrypt),
            "iv" => bin2hex($init_vect),
            "error" => empty($encrypt) ? "ERR_ENCRYPTION_NOT_CORRECT" : ""
        );
    } elseif ($type == "decrypt") {
        // case if IV is empty
        if (empty($init_vect)) {
                    return array(
                'string' => "",
                'error' => "ERR_ENCRYPTION_NOT_CORRECT"
            );
        }

        // convert
        try {
            $string = testHex2Bin(trim($string));
            $init_vect = testHex2Bin($init_vect);
        } catch (Exception $e) {
            return array(
                'string' => "",
                'error' => "ERR_ENCRYPTION_NOT_CORRECT"
            );
        }

        // load IV
        $crypt->IV($init_vect);
        // decrypt
        $decrypt = $crypt->decrypt($string);
        // return
        return array(
            'string' => str_replace(chr(0), "", $decrypt),
            'error' => ""
        );
    }
}

function testHex2Bin($val)
{
    if (!@hex2bin($val)) {
        throw new Exception("ERROR");
    }
    return hex2bin($val);
}

/**
 * @param string $ascii_key
 * @param string $type
 */
function cryption($message, $ascii_key, $type) //defuse_crypto
{
    global $SETTINGS;

    // load PhpEncryption library
    if (!isset($SETTINGS['cpassman_dir']) || empty($SETTINGS['cpassman_dir'])) {
        $path = '../includes/libraries/Encryption/Encryption/';
    } else {
        $path = $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/';
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
        } elseif ($type === "decrypt") {
            $text = \Defuse\Crypto\Crypto::decrypt($message, $key);
        }
    } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
        $err = "an attack! either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.";
    } catch (Defuse\Crypto\Exception\BadFormatException $ex) {
        $err = $ex;
    } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
        $err = $ex;
    } catch (Defuse\Crypto\Exception\CryptoException $ex) {
        $err = $ex;
    } catch (Defuse\Crypto\Exception\IOException $ex) {
        $err = $ex;
    }

    return array(
        'string' => isset($text) ? $text : "",
        'error' => $err
    );
}

function defuse_generate_key()
{
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

function defuse_generate_personal_key($psk)
{
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

    return $protected_key_encoded; // save this in user table
}

/**
 * @param string $psk
 */
function defuse_validate_personal_key($psk, $protected_key_encoded)
{
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
    } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
        return "Error - Major issue as the encryption is broken.";
    } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
        return "Error - The saltkey is not the correct one.";
    }

    return $user_key_encoded; // store it in session once user has entered his psk
}

/**
 * Decrypt a defuse string if encrypted
 * @param  [type] $value Encrypted string
 * @return [type]        Decrypted string
 */
function defuse_return_decrypted($value)
{
    if (substr($value, 0, 3) === "def") {
        $value = cryption($value, "", "decrypt")['string'];
    }
    return $value;
}

/**
 * trimElement()
 *
 * trim a string depending on a specific string
 * @param string $element
 * @return string
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

function db_error_handler($params)
{
    echo "Error: ".$params['error']."<br>\n";
    echo "Query: ".$params['query']."<br>\n";
    throw new Exception("Error - Query", 1);
}

/**
 * [identifyUserRights description]
 * @param  string $groupesVisiblesUser  [description]
 * @param  string $groupesInterditsUser [description]
 * @param  string $isAdmin              [description]
 * @param  string $idFonctions          [description]
 * @return string                       [description]
 */
function identifyUserRights($groupesVisiblesUser, $groupesInterditsUser, $isAdmin, $idFonctions)
{
    global $server, $user, $pass, $database, $port, $encoding;
    global $SETTINGS;

    //load ClassLoader
    require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    //Connect to DB
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $pass = defuse_return_decrypted($pass);
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    //Build tree
    $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
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
        if (isset($SETTINGS['enable_pf_feature']) && $SETTINGS['enable_pf_feature'] == 1) {
            $where->add('title=%s', $_SESSION['user_id']);
            $where->negateLast();
        }
        // Get ID of personal folder
        $persfld = DB::queryfirstrow(
            "SELECT id FROM ".prefix_table("nested_tree")." WHERE title = %s",
            $_SESSION['user_id']
        );
        if (!empty($persfld['id'])) {
            if (!in_array($persfld['id'], $_SESSION['groupes_visibles'])) {
                array_push($_SESSION['groupes_visibles'], $persfld['id']);
                array_push($_SESSION['personal_visible_groups'], $persfld['id']);
                // get all descendants
                $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');
                $tree->rebuild();
                $tst = $tree->getDescendants($persfld['id']);
                foreach ($tst as $t) {
                    array_push($_SESSION['groupes_visibles'], $t->id);
                    array_push($_SESSION['personal_visible_groups'], $t->id);
                }
            }
        }

        // get complete list of ROLES
        $tmp = explode(";", $idFonctions);
        $rows = DB::query(
            "SELECT * FROM ".prefix_table("roles_title")."
            ORDER BY title ASC"
        );
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
        $_SESSION['fonction_id'] = $idFonctions;
        $groupesInterdits = array();
        if (!is_array($groupesInterditsUser)) {
            $groupesInterditsUser = explode(';', trimElement($groupesInterditsUser, ";"));
        }
        if (!empty($groupesInterditsUser) && count($groupesInterditsUser) > 0) {
            $groupesInterdits = $groupesInterditsUser;
        }
        $_SESSION['is_admin'] = $isAdmin;
        $fonctionsAssociees = explode(';', trimElement($idFonctions, ";"));

        $listAllowedFolders = $listFoldersLimited = $listFoldersEditableByRole = $listRestrictedFoldersForItems = $listReadOnlyFolders = array();

        // rechercher tous les groupes visibles en fonction des roles de l'utilisateur
        foreach ($fonctionsAssociees as $roleId) {
            if (!empty($roleId)) {
                // Get allowed folders for each Role
                $rows = DB::query("SELECT folder_id FROM ".prefix_table("roles_values")." WHERE role_id=%i", $roleId);

                if (DB::count() > 0) {
                    $tmp = DB::queryfirstrow("SELECT allow_pw_change FROM ".prefix_table("roles_title")." WHERE id = %i", $roleId);
                    foreach ($rows as $record) {
                        if (isset($record['folder_id']) && !in_array($record['folder_id'], $listAllowedFolders)) {
                            array_push($listAllowedFolders, $record['folder_id']);
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
                    $inc = 0;
                    foreach ($rows as $record) {
                        if (isset($record['id_tree'])) {
                            $listFoldersLimited[$record['id_tree']][$inc] = $record['item_id'];
                            $inc++;
                        }
                    }
                }
            }
        }

        // Does this user is allowed to see other items
        $inc = 0;
        $rows = DB::query(
            "SELECT id, id_tree FROM ".prefix_table("items")."
            WHERE restricted_to LIKE %ss AND inactif=%s",
            $_SESSION['user_id'].';',
            '0'
        );
        foreach ($rows as $record) {
            $listRestrictedFoldersForItems[$record['id_tree']][$inc] = $record['id'];
            $inc++;
        }
        // => Build final lists
        // Clean arrays
        $listAllowedFolders = array_unique($listAllowedFolders);
        $groupesVisiblesUser = explode(';', trimElement($groupesVisiblesUser, ";"));
        // Add user allowed folders
        $allowedFoldersTmp = array_unique(
            array_merge($listAllowedFolders, $groupesVisiblesUser)
        );
        // Exclude from allowed folders all the specific user forbidden folders
        $allowedFolders = array();
        foreach ($allowedFoldersTmp as $ident) {
            if (!in_array($ident, $groupesInterditsUser) && !empty($ident)) {
                array_push($allowedFolders, $ident);
            }
        }

        // Clean array
        $listAllowedFolders = array_filter(array_unique($allowedFolders));

        // Exclude all PF
        $_SESSION['forbiden_pfs'] = array();

        $where = new WhereClause('and');
        $where->add('personal_folder=%i', 1);
        if (isset($SETTINGS['enable_pf_feature']) &&
            $SETTINGS['enable_pf_feature'] == 1 &&
            isset($_SESSION['personal_folder']) &&
            $_SESSION['personal_folder'] == 1
        ) {
            $where->add('title=%s', $_SESSION['user_id']);
            $where->negateLast();
        }

        $persoFlds = DB::query("SELECT id FROM ".prefix_table("nested_tree")." WHERE %l", $where);
        foreach ($persoFlds as $persoFldId) {
            array_push($_SESSION['forbiden_pfs'], $persoFldId['id']);
        }
        // Get IDs of personal folders
        if (isset($SETTINGS['enable_pf_feature']) &&
            $SETTINGS['enable_pf_feature'] == 1 &&
            isset($_SESSION['personal_folder']) &&
            $_SESSION['personal_folder'] == 1
        ) {
            $persoFld = DB::queryfirstrow("SELECT id FROM ".prefix_table("nested_tree")." WHERE title = %s", $_SESSION['user_id']);
            if (!empty($persoFld['id'])) {
                if (!in_array($persoFld['id'], $listAllowedFolders)) {
                    array_push($_SESSION['personal_folders'], $persoFld['id']);
                    // get all descendants
                    $ids = $tree->getDescendants($persoFld['id'], true, false);
                    foreach ($ids as $ident) {
                        array_push($listAllowedFolders, $ident->id);
                        array_push($_SESSION['personal_visible_groups'], $ident->id);
                        array_push($_SESSION['personal_folders'], $ident->id);
                    }
                }
            }
            // get list of readonly folders when pf is disabled.
            // rule - if one folder is set as W or N in one of the Role, then User has access as W
            foreach ($listAllowedFolders as $folderId) {
                if (!in_array($folderId, array_unique(array_merge($listReadOnlyFolders, $_SESSION['personal_folders'])))) {   //
                    DB::query(
                        "SELECT *
                        FROM ".prefix_table("roles_values")."
                        WHERE folder_id = %i AND role_id IN %li AND type IN %ls",
                        $folderId,
                        $fonctionsAssociees,
                        array("W", "ND", "NE", "NDNE")
                    );
                    if (DB::count() == 0 && in_array($folderId, $groupesVisiblesUser) === false) {
                        array_push($listReadOnlyFolders, $folderId);
                    }
                }
            }
        } else {
            // get list of readonly folders when pf is disabled.
            // rule - if one folder is set as W in one of the Role, then User has access as W
            foreach ($listAllowedFolders as $folderId) {
                if (!in_array($folderId, $listReadOnlyFolders)) {
                    DB::query(
                        "SELECT *
                        FROM ".prefix_table("roles_values")."
                        WHERE folder_id = %i AND role_id IN %li AND type IN %ls",
                        $folderId,
                        $fonctionsAssociees,
                        array("W", "ND", "NE", "NDNE")
                    );
                    if (DB::count() == 0 && !in_array($folderId, $groupesVisiblesUser)) {
                        array_push($listReadOnlyFolders, $folderId);
                    }
                }
            }
        }

        // check if change proposals on User's items
        if (isset($SETTINGS['enable_suggestion']) && $SETTINGS['enable_suggestion'] == 1) {
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
 * @param string $action
 */
function updateCacheTable($action, $ident = "")
{
    global $server, $user, $pass, $database, $port, $encoding;
    global $SETTINGS;

    require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    //Connect to DB
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $pass = defuse_return_decrypted($pass);
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    //Load Tree
    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefix_table("nested_tree"), 'id', 'parent_id', 'title');

    // Rebuild full cache table
    if ($action === "reload") {
        // truncate table
        DB::query("TRUNCATE TABLE ".prefix_table("cache"));

        // reload date
        $rows = DB::query(
            "SELECT *
            FROM ".prefix_table('items')." as i
            INNER JOIN ".prefix_table('log_items')." as l ON (l.id_item = i.id)
            AND l.action = %s
            AND i.inactif = %i",
            'at_creation',
            0
        );
        foreach ($rows as $record) {
            // Get all TAGS
            $tags = "";
            $itemTags = DB::query("SELECT tag FROM ".prefix_table('tags')." WHERE item_id=%i", $record['id']);
            foreach ($itemTags as $itemTag) {
                if (!empty($itemTag['tag'])) {
                    $tags .= $itemTag['tag']." ";
                }
            }
            // Get renewal period
            $resNT = DB::queryfirstrow("SELECT renewal_period FROM ".prefix_table('nested_tree')." WHERE id=%i", $record['id_tree']);

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
                prefix_table('cache'),
                array(
                    'id' => $record['id'],
                    'label' => $record['label'],
                    'description' => isset($record['description']) ? $record['description'] : "",
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
            FROM ".prefix_table('items')."
            WHERE id=%i",
            $ident
        );
        // Get all TAGS
        $tags = "";
        $itemTags = DB::query("SELECT tag FROM ".prefix_table('tags')." WHERE item_id=%i", $ident);
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
            prefix_table('cache'),
            array(
                'label' => $data['label'],
                'description' => $data['description'],
                'tags' => $tags,
                'url' => (isset($data['url']) && !empty($data['url'])) ? $data['url'] : "0",
                'id_tree' => $data['id_tree'],
                'perso' => $data['perso'],
                'restricted_to' => (isset($data['restricted_to']) && !empty($data['restricted_to'])) ? $data['restricted_to'] : "0",
                'login' => isset($data['login']) ? $data['login'] : "",
                'folder' => $folder,
                'author' => $_SESSION['user_id'],
                ),
            "id = %i",
            $ident
        );
        // ADD an item
    } elseif ($action === "add_value") {
        // get new value from db
        $data = DB::queryFirstRow(
            "SELECT i.label, i.description, i.id_tree as id_tree, i.perso, i.restricted_to, i.id, i.login, i.url, l.date
            FROM ".prefix_table('items')." as i
            INNER JOIN ".prefix_table('log_items')." as l ON (l.id_item = i.id)
            WHERE i.id = %i
            AND l.action = %s",
            $ident,
            'at_creation'
        );
        // Get all TAGS
        $tags = "";
        $itemTags = DB::query("SELECT tag FROM ".prefix_table('tags')." WHERE item_id = %i", $ident);
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
            prefix_table('cache'),
            array(
                'id' => $data['id'],
                'label' => $data['label'],
                'description' => $data['description'],
                'tags' => (isset($tags) && !empty($tags)) ? $tags : "None",
                'url' => (isset($data['url']) && !empty($data['url'])) ? $data['url'] : "0",
                'id_tree' => $data['id_tree'],
                'perso' => (isset($data['perso']) && !empty($data['perso']) && $data['perso'] !== "None") ? $data['perso'] : "0",
                'restricted_to' => (isset($data['restricted_to']) && !empty($data['restricted_to'])) ? $data['restricted_to'] : "None",
                'login' => isset($data['login']) ? $data['login'] : "",
                'folder' => $folder,
                'author' => $_SESSION['user_id'],
                'timestamp' => $data['date']
            )
        );

        // DELETE an item
    } elseif ($action === "delete_value") {
        DB::delete(prefix_table('cache'), "id = %i", $ident);
    }
}

/*
*
*/
function getStatisticsData()
{
    global $SETTINGS;

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
    $readOnly = DB::count();

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
        } elseif (!empty($ip['user_ip']) && $ip['user_ip'] !== "none") {
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
        "stat_ro" => $readOnly,
        "stat_kb" => $SETTINGS['enable_kb'],
        "stat_pf" => $SETTINGS['enable_pf_feature'],
        "stat_fav" => $SETTINGS['enable_favourites'],
        "stat_teampassversion" => $SETTINGS['cpassman_version'],
        "stat_ldap" => $SETTINGS['ldap_mode'],
        "stat_agses" => $SETTINGS['agses_authentication_enabled'],
        "stat_duo" => $SETTINGS['duo'],
        "stat_suggestion" => $SETTINGS['enable_suggestion'],
        "stat_api" => $SETTINGS['api'],
        "stat_customfields" => $SETTINGS['item_extra_fields'],
        "stat_syslog" => $SETTINGS['syslog_enable'],
        "stat_2fa" => $SETTINGS['google_authentication'],
        "stat_stricthttps" => $SETTINGS['enable_sts'],
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
    global $SETTINGS;

    // CAse where email not defined
    if ($email === "none") {
        return '"error":"" , "message":"'.$LANG['forgot_my_pw_email_sent'].'"';
    }

    include $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
    //load library
    $user_language = isset($_SESSION['user_language']) ? $_SESSION['user_language'] : "english";
    require_once $SETTINGS['cpassman_dir'].'/includes/language/'.$user_language.'.php';
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Email/Phpmailer/PHPMailerAutoload.php';

    // load PHPMailer
    $mail = new PHPMailer();

    // send to user
    $mail->setLanguage("en", "../includes/libraries/Email/Phpmailer/language/");
    $mail->SMTPDebug = 0; //value 1 can be used to debug
    $mail->Port = $SETTINGS['email_port']; //COULD BE USED
    $mail->CharSet = "utf-8";
    if ($SETTINGS['email_security'] === "tls" || $SETTINGS['email_security'] === "ssl") {
        $mail->SMTPSecure = $SETTINGS['email_security'];
    }
    $mail->isSmtp(); // send via SMTP
    $mail->Host = $SETTINGS['email_smtp_server']; // SMTP servers
    $mail->SMTPAuth = $SETTINGS['email_smtp_auth'] == '1' ? true : false; // turn on SMTP authentication
    $mail->Username = $SETTINGS['email_auth_username']; // SMTP username
    $mail->Password = $SETTINGS['email_auth_pwd']; // SMTP password
    $mail->From = $SETTINGS['email_from'];
    $mail->FromName = $SETTINGS['email_from_name'];
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
    global $SETTINGS;

    $date = date_parse_from_format($SETTINGS['date_format'], $date);
    if ($date['warning_count'] == 0 && $date['error_count'] == 0) {
        return mktime(23, 59, 59, $date['month'], $date['day'], $date['year']);
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
 * @return integer is the string in UTF8 format.
 */

function isUTF8($string)
{
    if (is_array($string) === true) {
        $string = $string['string'];
    }
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
/**
 * @param string $type
 */
function prepareExchangedData($data, $type)
{
    global $SETTINGS;

    //load ClassLoader
    require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
    //Load AES
    $aes = new SplClassLoader('Encryption\Crypt', '../includes/libraries');
    $aes->register();

    if ($type == "encode") {
        if (isset($SETTINGS['encryptClientServer'])
            && $SETTINGS['encryptClientServer'] === "0"
        ) {
            return json_encode(
                $data,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            );
        } else {
            return Encryption\Crypt\aesctr::encrypt(
                json_encode(
                    $data,
                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                ),
                $_SESSION['key'],
                256
            );
        }
    } elseif ($type == "decode") {
        if (isset($SETTINGS['encryptClientServer'])
            && $SETTINGS['encryptClientServer'] === "0"
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

function make_thumb($src, $dest, $desired_width)
{
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
/**
 * @param string $table
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
        return "table_not_exists";
    }
}

/*
 * Creates a KEY using PasswordLib
 */
function GenerateCryptKey($size = "", $secure = false, $numerals = false, $capitalize = false, $ambiguous = false, $symbols = false)
{
    // load library
    $pwgen = new SplClassLoader('Encryption\PwGen', '../includes/libraries');
    $pwgen->register();
    $pwgen = new Encryption\PwGen\pwgen();

    // init
    if (!empty($size)) {
        $pwgen->setLength($size);
    }
    if (!empty($secure)) {
        $pwgen->setSecure($secure);
    }
    if (!empty($numerals)) {
        $pwgen->setNumerals($numerals);
    }
    if (!empty($capitalize)) {
        $pwgen->setCapitalize($capitalize);
    }
    if (!empty($ambiguous)) {
        $pwgen->setAmbiguous($ambiguous);
    }
    if (!empty($symbols)) {
        $pwgen->setSymbols($symbols);
    }

    // generate and send back
    return $pwgen->generate();
}

/*
* Send sysLOG message
* @param string $message
* @param string $host
*/
function send_syslog($message, $host, $port, $component = "teampass")
{
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    $syslog_message = "<123>".date('M d H:i:s ').$component.": ".$message;
    socket_sendto($sock, $syslog_message, strlen($syslog_message), 0, $host, $port);
    socket_close($sock);
}



/**
 * logEvents()
 *
 * permits to log events into DB
 * @param string $type
 * @param string $label
 * @param string $field_1
 */
function logEvents($type, $label, $who, $login = "", $field_1 = null)
{
    global $server, $user, $pass, $database, $port, $encoding;
    global $SETTINGS;

    if (empty($who)) {
        $who = get_client_ip_server();
    }

    // include librairies & connect to DB
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $pass = defuse_return_decrypted($pass);
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    DB::insert(
        prefix_table("log_system"),
        array(
            'type' => $type,
            'date' => time(),
            'label' => $label,
            'qui' => $who,
            'field_1' => $field_1 === null ? "" : $field_1
        )
    );
    if (isset($SETTINGS['syslog_enable']) && $SETTINGS['syslog_enable'] == 1) {
        if ($type == "user_mngt") {
            send_syslog(
                "The User ".$login." performed the action of ".$label." to the user ".$field_1." - ".$type,
                $SETTINGS['syslog_host'],
                $SETTINGS['syslog_port'],
                "teampass"
            );
        } else {
            send_syslog(
                "The User ".$login." performed the action of ".$label." - ".$type,
                $SETTINGS['syslog_host'],
                $SETTINGS['syslog_port'],
                "teampass"
            );
        }
    }
}

/**
 * @param string $item
 * @param string $action
 */
function logItems($ident, $item, $id_user, $action, $login = "", $raison = null, $raison_iv = null, $encryption_type = "")
{
    global $server, $user, $pass, $database, $port, $encoding;
    global $SETTINGS;

    // include librairies & connect to DB
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $pass = defuse_return_decrypted($pass);
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);
    DB::insert(
        prefix_table("log_items"),
        array(
            'id_item' => $ident,
            'date' => time(),
            'id_user' => $id_user,
            'action' => $action,
            'raison' => $raison,
            'raison_iv' => $raison_iv,
            'encryption_type' => $encryption_type
        )
    );
    if (isset($SETTINGS['syslog_enable']) && $SETTINGS['syslog_enable'] == 1) {
        send_syslog(
            "The Item ".$item." was ".$action." by ".$login." ".$raison,
            $SETTINGS['syslog_host'],
            $SETTINGS['syslog_port'],
            "teampass"
        );
    }
}

/*
* Function to get the client ip address
 */
function get_client_ip_server()
{
    if (getenv('HTTP_CLIENT_IP')) {
            $ipaddress = getenv('HTTP_CLIENT_IP');
    } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    } elseif (getenv('HTTP_X_FORWARDED')) {
            $ipaddress = getenv('HTTP_X_FORWARDED');
    } elseif (getenv('HTTP_FORWARDED_FOR')) {
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
    } elseif (getenv('HTTP_FORWARDED')) {
            $ipaddress = getenv('HTTP_FORWARDED');
    } elseif (getenv('REMOTE_ADDR')) {
            $ipaddress = getenv('REMOTE_ADDR');
    } else {
            $ipaddress = 'UNKNOWN';
    }

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
    global $server, $user, $pass, $database, $port, $encoding;
    global $SETTINGS;

    $tp_config_file = "../includes/config/tp.config.php";

    // Load AntiXSS
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    // include librairies & connect to DB
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $pass = defuse_return_decrypted($pass);
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    if (!file_exists($tp_config_file) || $action == "rebuild") {
        // perform a copy
        if (file_exists($tp_config_file)) {
            if (!copy($tp_config_file, $tp_config_file.'.'.date("Y_m_d_His", time()))) {
                return "ERROR: Could not copy file '".$tp_config_file."'";
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
        $data = array_unique($data);
    } elseif ($action == "update" && !empty($field)) {
        $data = file($tp_config_file);
        $inc = 0;
        $bFound = false;
        foreach ($data as $line) {
            if (stristr($line, ");")) {
                break;
            }

            //
            if (stristr($line, "'".$field."' => '")) {
                $data[$inc] = "    '".$field."' => '".$antiXss->xss_clean($value)."',\n";
                $bFound = true;
                break;
            }
            $inc++;
        }
        if ($bFound === false) {
            $data[($inc - 1)] = "    '".$field."' => '".$antiXss->xss_clean($value)."',\n";
        }
    }

    // update file
    file_put_contents($tp_config_file, implode('', isset($data) ? $data : array()));

    return true;
}

/*
** Permits to replace &#92; to permit correct display
*/
/**
 * @param string $input
 */
function handleBackslash($input)
{
    return str_replace("&amp;#92;", "&#92;", $input);
}

/*
** Permits to loas settings
*/
function loadSettings()
{
    global $SETTINGS;

    /* LOAD CPASSMAN SETTINGS */
    if (!isset($SETTINGS['loaded']) || $SETTINGS['loaded'] != 1) {
        $SETTINGS['duplicate_folder'] = 0; //by default, this is set to 0;
        $SETTINGS['duplicate_item'] = 0; //by default, this is set to 0;
        $SETTINGS['number_of_used_pw'] = 5; //by default, this value is set to 5;
        $settings = array();

        $rows = DB::query(
            "SELECT * FROM ".prefix_table("misc")." WHERE type=%s_type OR type=%s_type2",
            array(
                'type' => "admin",
                'type2' => "settings"
            )
        );
        foreach ($rows as $record) {
            if ($record['type'] == 'admin') {
                $SETTINGS[$record['intitule']] = $record['valeur'];
            } else {
                $settings[$record['intitule']] = $record['valeur'];
            }
        }
        $SETTINGS['loaded'] = 1;
        $SETTINGS['default_session_expiration_time'] = 5;
    }
}

/*
** check if folder has custom fields.
** Ensure that target one also has same custom fields
*/
function checkCFconsistency($source_id, $target_id)
{
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
function encrypt_or_decrypt_file($filename_to_rework, $filename_status)
{
    global $server, $user, $pass, $database, $port, $encoding;
    global $SETTINGS;

    // Include librairies & connect to DB
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $pass = defuse_return_decrypted($pass);
    DB::$host = $server;
    DB::$user = $user;
    DB::$password = $pass;
    DB::$dbName = $database;
    DB::$port = $port;
    DB::$encoding = $encoding;
    DB::$error_handler = true;
    $link = mysqli_connect($server, $user, $pass, $database, $port);
    $link->set_charset($encoding);

    // Get file info in DB
    $fileInfo = DB::queryfirstrow(
        "SELECT id FROM ".prefix_table("files")." WHERE file = %s",
        filter_var($filename_to_rework, FILTER_SANITIZE_STRING)
    );
    if (empty($fileInfo['id']) === false) {
        // Load PhpEncryption library
        $path_to_encryption = '/includes/libraries/Encryption/Encryption/';
        require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Crypto.php';
        require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Encoding.php';
        require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'DerivedKeys.php';
        require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Key.php';
        require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'KeyOrPassword.php';
        require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'File.php';
        require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'RuntimeTests.php';
        require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'KeyProtectedByPassword.php';
        require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Core.php';

        // Get KEY
        $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");

        if (isset($SETTINGS['enable_attachment_encryption'])
            && $SETTINGS['enable_attachment_encryption'] === "1" &&
            isset($filename_status)
            && ($filename_status === "clear"
                || $filename_status === "0")
        ) {
            // File needs to be encrypted
            if (file_exists($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework)) {
                // Make a copy of file
                if (!copy(
                    $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                    $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.".copy"
                )) {
                    exit;
                } else {
                    // Do a bck
                    copy(
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.".bck"
                    );
                }

                unlink($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework);

                // Now encrypt the file with saltkey
                $err = '';
                try {
                    \Defuse\Crypto\File::encryptFile(
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.".copy",
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                        \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key)
                    );
                } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                    $err = "An attack! Either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.";
                } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
                    $err = $ex;
                } catch (Defuse\Crypto\Exception\IOException $ex) {
                    $err = $ex;
                }
                if (empty($err) === false) {
                    echo $err;
                }

                unlink($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.".copy");

                // update table
                DB::update(
                    prefix_table('files'),
                    array(
                        'status' => 'encrypted'
                        ),
                    "id = %i",
                    $fileInfo['id']
                );
            }
        } elseif (isset($SETTINGS['enable_attachment_encryption'])
            && $SETTINGS['enable_attachment_encryption'] === "0"
            && isset($filename_status)
            && $filename_status === "encrypted"
        ) {
            // file needs to be decrypted
            if (file_exists($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework)) {
                // make a copy of file
                if (!copy(
                    $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                    $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.".copy"
                )) {
                    exit;
                } else {
                    // do a bck
                    copy(
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.".bck"
                    );
                }

                unlink($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework);

                // Now encrypt the file with saltkey
                $err = '';
                try {
                    \Defuse\Crypto\File::decryptFile(
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.".copy",
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                        \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key)
                    );
                } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                    $err = "An attack! Either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.";
                } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
                    $err = $ex;
                } catch (Defuse\Crypto\Exception\IOException $ex) {
                    $err = $ex;
                }
                if (empty($err) === false) {
                    echo $err;
                }

                unlink($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.".copy");

                // update table
                DB::update(
                    prefix_table('files'),
                    array(
                        'status' => 'clear'
                        ),
                    "id = %i",
                    $fileInfo['id']
                );
            }
        }
    }

    // Exit
    return false;
}

/**
 * Will encrypte/decrypt a fil eusing Defuse
 * @param  string $type        can be either encrypt or decrypt
 * @param  string $source_file path to source file
 * @param  string $target_file path to target file
 * @return string              'true' is success or error message
 */
function prepareFileWithDefuse($type, $source_file, $target_file, $password = '')
{
    global $SETTINGS;

    // Load AntiXSS
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    // Protect against bad inputs
    if (is_array($source_file) ||is_array($target_file)) {
        return 'error_cannot_be_array';
    }

    // Sanitize
    $source_file = $antiXss->xss_clean($source_file);
    $target_file = $antiXss->xss_clean($target_file);

    // load PhpEncryption library
    $path_to_encryption = '/includes/libraries/Encryption/Encryption/';
    require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Crypto.php';
    require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Encoding.php';
    require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'DerivedKeys.php';
    require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Key.php';
    require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'KeyOrPassword.php';
    require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'File.php';
    require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'RuntimeTests.php';
    require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'KeyProtectedByPassword.php';
    require_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Core.php';

    if (empty($password) === true) {
    /*
    File encryption/decryption is done with the SALTKEY
     */

        // get KEY
        $ascii_key = file_get_contents(SECUREPATH."/teampass-seckey.txt");

        // Now perform action on the file
        $err = '';
        if ($type === 'decrypt') {
            try {
                \Defuse\Crypto\File::decryptFile(
                    $source_file,
                    $target_file,
                    \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key)
                );
            } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                $err = "decryption_not_possible";
            } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
                $err = $ex;
            } catch (Defuse\Crypto\Exception\IOException $ex) {
                $err = $ex;
            }
        } elseif ($type === 'encrypt') {
            try {
                \Defuse\Crypto\File::encryptFile(
                    $source_file,
                    $target_file,
                    \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key)
                );
            } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                $err = "encryption_not_possible";
            } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
                $err = $ex;
            } catch (Defuse\Crypto\Exception\IOException $ex) {
                $err = $ex;
            }
        }
    } else {
    /*
    File encryption/decryption is done with special password and not the SALTKEY
     */

        $err = '';
        if ($type === 'decrypt') {
            try {
                \Defuse\Crypto\File::decryptFileWithPassword(
                    $source_file,
                    $target_file,
                    $password
                );
            } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                $err = "wrong_key";
            } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
                $err = $ex;
            } catch (Defuse\Crypto\Exception\IOException $ex) {
                $err = $ex;
            }
        } elseif ($type === 'encrypt') {
            try {
                \Defuse\Crypto\File::encryptFileWithPassword(
                    $source_file,
                    $target_file,
                    $password
                );
            } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                $err = "wrong_key";
            } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
                $err = $ex;
            } catch (Defuse\Crypto\Exception\IOException $ex) {
                $err = $ex;
            }
        }
    }

    // return error
    if (empty($err) === false) {
        return $err;
    } else {
        return true;
    }
}

/*
* NOT TO BE USED
*/
function debugTeampass($text)
{
    $debugFile = fopen('D:/wamp64/www/TeamPass/debug.txt', 'r+');
    fputs($debugFile, $text);
    fclose($debugFile);
}


/**
 * DELETE the file with expected command depending on server type
 * @param  string $file Path to file
 * @return              Nothing
 */
function fileDelete($file)
{
    global $SETTINGS;

    // Load AntiXSS
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    $file = $antiXss->xss_clean($file);
    if (is_file($file)) {
        unlink($file);
    }
}

/*
* Permits to extract the file extension
*/
function getFileExtension($file)
{
    if (strpos($file, '.') === false) {
        return $file;
    }

    return substr($file, strrpos($file, '.') + 1);
}

/**
 * array_map
 * @param  [type] $func [description]
 * @param  [type] $arr  [description]
 * @return [type]       [description]
 */
function array_map_r($func, $arr)
{
    $newArr = array();

    foreach ($arr as $key => $value) {
        $newArr[ $key ] = (is_array($value) ? array_map_r($func, $value) : ( is_array($func) ? call_user_func_array($func, $value) : $func( $value )));
    }

    return $newArr;
}

/**
 * Permits to clean and sanitize text to be displayed
 * @param  string $text text to clean
 * @param  string $type what clean to perform
 * @return string       text cleaned up
 */
function cleanText($string, $type = "")
{
    global $SETTINGS;

    // Load AntiXSS
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    if ($type === "css") {
        // Escape text and quotes in UTF8 format
        return htmlentities($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif ($type === "html" || empty($type)) {
        // Html cleaner
        return $antiXss->xss_clean($string);
    }
}

/**
 * Performs chmod operation on subfolders
 * @param  string  $dir             Parent folder
 * @param  integer $dirPermissions  New permission on folders
 * @param  integer $filePermissions New permission on files
 * @return boolean                  Success/Failure
 */
function chmodRecursive($dir, $dirPermissions, $filePermissions)
{
    $pointer_dir = opendir($dir);
    $res = true;
    while ($file = readdir($pointer_dir)) {
        if (($file == ".") || ($file == "..")) {
            continue;
        }

        $fullPath = $dir."/".$file;

        if (is_dir($fullPath)) {
            if ($res = @chmod($fullPath, $dirPermissions)) {
                $res = @chmodRecursive($fullPath, $dirPermissions, $filePermissions);
            }
        } else {
            $res = chmod($fullPath, $filePermissions);
        }
        if (!$res) {
            closedir($pointer_dir);
            return false;
        }
    }
    closedir($pointer_dir);
    if (is_dir($dir) && $res) {
        $res = @chmod($dir, $dirPermissions);
    }

    return $res;
}

/**
 * Check if user can access to this item
 * @param $item_id
 */
function accessToItemIsGranted($item_id)
{
    // Load item data
    $data = DB::queryFirstRow(
        "SELECT id_tree
        FROM ".prefix_table("items")."
        WHERE id = %i",
        $item_id
    );
//echo in_array($item_id, $_SESSION['list_restricted_folders_for_items'][$data['id_tree']])." - ".$item_id." - ".$data['id_tree']." - ";
print_r($_SESSION['list_folders_editable_by_role']);
    // Check if user can access this folder
    if (!in_array($data['id_tree'], $_SESSION['groupes_visibles'])) {
        // Now check if this folder is restricted to user
        if (isset($_SESSION['list_restricted_folders_for_items'][$data['id_tree']])
            && !in_array($item_id, $_SESSION['list_restricted_folders_for_items'][$data['id_tree']])
        ) {
            return "ERR_FOLDER_NOT_ALLOWED";
        } else {
            return "ERR_FOLDER_NOT_ALLOWED";
        }
    }

    return true;
}
