<?php
/**
 * @author        Nils Laumaillé <nils@teampass.net>
 *
 * @version       2.1.27
 *
 * @copyright     2009-2018 Nils Laumaillé
 * @license       GNU GPL-3.0
 *
 * @see
 */

//define pbkdf2 iteration count
define('ITCOUNT', '2072');

if (!isset($_SESSION['CPM']) || $_SESSION['CPM'] != 1) {
    die('Hacking attempt...');
}

// Load config if $SETTINGS not defined
if (!isset($SETTINGS['cpassman_dir']) || empty($SETTINGS['cpassman_dir'])) {
    if (file_exists('../includes/config/tp.config.php')) {
        include_once '../includes/config/tp.config.php';
    } elseif (file_exists('./includes/config/tp.config.php')) {
        include_once './includes/config/tp.config.php';
    } elseif (file_exists('../../includes/config/tp.config.php')) {
        include_once '../../includes/config/tp.config.php';
    } else {
        throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
    }
}

// load phpCrypt
if (!isset($SETTINGS['cpassman_dir']) || empty($SETTINGS['cpassman_dir'])) {
    include_once '../includes/libraries/phpcrypt/phpCrypt.php';
    include_once '../includes/config/settings.php';
} else {
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/phpcrypt/phpCrypt.php';
    include_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
}

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Prepare PHPCrypt class calls
use PHP_Crypt\PHP_Crypt as PHP_Crypt;
// Prepare Encryption class calls
use Defuse\Crypto\Crypto;

/**
 * Undocumented function.
 *
 * @param [type] $string
 */
function langHdl($string)
{
    if (empty($string) === true || isset($_SESSION['teampass']['lang'][$string]) === false) {
        // Manage error
    } else {
        return str_replace(
            array('"', "'"),
            array('&quot;', '&apos;'),
            $_SESSION['teampass']['lang'][$string]
        );
    }
}

//Generate N# of random bits for use as salt
/**
 * @param int $size
 */
function getBits($size)
{
    $str = '';
    $var_x = $size + 10;
    for ($var_i = 0; $var_i < $var_x; ++$var_i) {
        $str .= base_convert(mt_rand(1, 36), 10, 36);
    }

    return substr($str, 0, $size);
}

//generate pbkdf2 compliant hash
function strHashPbkdf2($var_p, $var_s, $var_c, $var_kl, $var_a = 'sha256', $var_st = 0)
{
    $var_kb = $var_st + $var_kl; // Key blocks to compute
    $var_dk = ''; // Derived key

    for ($block = 1; $block <= $var_kb; ++$block) { // Create key
        $var_ib = $var_h = hash_hmac($var_a, $var_s.pack('N', $block), $var_p, true); // Initial hash for this block
        for ($var_i = 1; $var_i < $var_c; ++$var_i) { // Perform block iterations
            $var_ib ^= ($var_h = hash_hmac($var_a, $var_h, $var_p, true)); // XOR each iterate
        }
        $var_dk .= $var_ib; // Append iterated block
    }

    return substr($var_dk, $var_st, $var_kl); // Return derived key of correct length
}

/**
 * stringUtf8Decode().
 *
 * utf8_decode
 */
function stringUtf8Decode($string)
{
    return str_replace(' ', '+', utf8_decode($string));
}

/**
 * encryptOld().
 *
 * crypt a string
 *
 * @param string $text
 */
function encryptOld($text, $personalSalt = '')
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
 * decryptOld().
 *
 * decrypt a crypted string
 */
function decryptOld($text, $personalSalt = '')
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
 * encrypt().
 *
 * crypt a string
 *
 * @param string $decrypted
 */
function encrypt($decrypted, $personalSalt = '')
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
 * decrypt().
 *
 * decrypt a crypted string
 */
function decrypt($encrypted, $personalSalt = '')
{
    global $SETTINGS;

    if (!isset($SETTINGS['cpassman_dir']) || empty($SETTINGS['cpassman_dir'])) {
        include_once '../includes/libraries/Encryption/PBKDF2/PasswordHash.php';
    } else {
        include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/PBKDF2/PasswordHash.php';
    }

    if (!empty($personalSalt)) {
        $staticSalt = $personalSalt;
    } else {
        $staticSalt = file_get_contents(SECUREPATH.'/teampass-seckey.txt');
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
 * genHash().
 *
 * Generate a hash for user login
 *
 * @param string $password
 */
function bCrypt($password, $cost)
{
    $salt = sprintf('$2y$%02d$', $cost);
    if (function_exists('openssl_random_pseudo_bytes')) {
        $salt .= bin2hex(openssl_random_pseudo_bytes(11));
    } else {
        $chars = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i < 22; ++$i) {
            $salt .= $chars[mt_rand(0, 63)];
        }
    }

    return crypt($password, $salt);
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
            for ($inc = strlen($key) + 1; $inc <= 16; ++$inc) {
                $key .= chr(0);
            }
        } elseif (strlen($key) > 16) {
            $key = substr($key, 16);
        }
    }

    // load crypt
    $crypt = new PHP_Crypt($key, PHP_Crypt::CIPHER_AES_128, PHP_Crypt::MODE_CBC);

    if ($type === 'encrypt') {
        // generate IV and encrypt
        $init_vect = $crypt->createIV();
        $encrypt = $crypt->encrypt($string);
        // return
        return array(
            'string' => bin2hex($encrypt),
            'iv' => bin2hex($init_vect),
            'error' => empty($encrypt) ? 'ERR_ENCRYPTION_NOT_CORRECT' : '',
        );
    } elseif ($type === 'decrypt') {
        // case if IV is empty
        if (empty($init_vect)) {
            return array(
                'string' => '',
                'error' => 'ERR_ENCRYPTION_NOT_CORRECT',
            );
        }

        // convert
        try {
            $string = testHex2Bin(trim($string));
            $init_vect = testHex2Bin($init_vect);
        } catch (Exception $e) {
            return array(
                'string' => '',
                'error' => 'ERR_ENCRYPTION_NOT_CORRECT',
            );
        }

        // load IV
        $crypt->IV($init_vect);
        // decrypt
        $decrypt = $crypt->decrypt($string);
        // return
        return array(
            'string' => str_replace(chr(0), '', $decrypt),
            'error' => '',
        );
    }
}

function testHex2Bin($val)
{
    if (!@hex2bin($val)) {
        throw new Exception('ERROR');
    }

    return hex2bin($val);
}

/**
 * Defuse cryption function.
 *
 * @param string $message   what to de/crypt
 * @param string $ascii_key key to use
 * @param string $type      operation to perform
 * @param array  $SETTINGS  Teampass settings
 *
 * @return array
 */
function cryption($message, $ascii_key, $type, $SETTINGS)
{
    // load PhpEncryption library
    if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir']) === true) {
        $path = '../includes/libraries/Encryption/Encryption/';
    } else {
        $path = $SETTINGS['cpassman_dir'].'/includes/libraries/Encryption/Encryption/';
    }

    include_once $path.'Crypto.php';
    include_once $path.'Encoding.php';
    include_once $path.'DerivedKeys.php';
    include_once $path.'Key.php';
    include_once $path.'KeyOrPassword.php';
    include_once $path.'File.php';
    include_once $path.'RuntimeTests.php';
    include_once $path.'KeyProtectedByPassword.php';
    include_once $path.'Core.php';

    // init
    $err = '';
    if (empty($ascii_key) === true) {
        $ascii_key = file_get_contents(SECUREPATH.'/teampass-seckey.txt');
    }
    
    // convert KEY
    $key = \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key);

    try {
        if ($type === 'encrypt') {
            $text = \Defuse\Crypto\Crypto::encrypt($message, $key);
        } elseif ($type === 'decrypt') {
            $text = \Defuse\Crypto\Crypto::decrypt($message, $key);
        }
    } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
        $err = 'an attack! either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.';
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
        'string' => isset($text) ? $text : '',
        'error' => $err,
    );
}

/**
 * Generating a defuse key.
 *
 * @return string
 */
function defuse_generate_key()
{
    include_once '../includes/libraries/Encryption/Encryption/Crypto.php';
    include_once '../includes/libraries/Encryption/Encryption/Encoding.php';
    include_once '../includes/libraries/Encryption/Encryption/DerivedKeys.php';
    include_once '../includes/libraries/Encryption/Encryption/Key.php';
    include_once '../includes/libraries/Encryption/Encryption/KeyOrPassword.php';
    include_once '../includes/libraries/Encryption/Encryption/File.php';
    include_once '../includes/libraries/Encryption/Encryption/RuntimeTests.php';
    include_once '../includes/libraries/Encryption/Encryption/KeyProtectedByPassword.php';
    include_once '../includes/libraries/Encryption/Encryption/Core.php';

    $key = \Defuse\Crypto\Key::createNewRandomKey();
    $key = $key->saveToAsciiSafeString();

    return $key;
}

/**
 * Generate a Defuse personal key.
 *
 * @param string $psk psk used
 *
 * @return string
 */
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
 * Validate persoanl key with defuse.
 *
 * @param string $psk                   the user's psk
 * @param string $protected_key_encoded special key
 *
 * @return string
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
        return 'Error - Major issue as the encryption is broken.';
    } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
        return 'Error - The saltkey is not the correct one.';
    }

    return $user_key_encoded; // store it in session once user has entered his psk
}

/**
 * Decrypt a defuse string if encrypted.
 *
 * @param string $value Encrypted string
 *
 * @return string Decrypted string
 */
function defuseReturnDecrypted($value, $SETTINGS)
{
    if (substr($value, 0, 3) === 'def') {
        $value = cryption($value, '', 'decrypt', $SETTINGS)['string'];
    }

    return $value;
}

/**
 * trimElement().
 *
 * trim a string depending on a specific string
 *
 * @param string $chaine  what to trim
 * @param string $element trim on what
 *
 * @return string
 */
function trimElement($chaine, $element)
{
    if (!empty($chaine)) {
        if (is_array($chaine) === true) {
            $chaine = implode(';', $chaine);
        }
        $chaine = trim($chaine);
        if (substr($chaine, 0, 1) === $element) {
            $chaine = substr($chaine, 1);
        }
        if (substr($chaine, strlen($chaine) - 1, 1) === $element) {
            $chaine = substr($chaine, 0, strlen($chaine) - 1);
        }
    }

    return $chaine;
}

/**
 * Permits to suppress all "special" characters from string.
 *
 * @param string $string  what to clean
 * @param bool   $special use of special chars?
 *
 * @return string
 */
function cleanString($string, $special = false)
{
    // Create temporary table for special characters escape
    $tabSpecialChar = array();
    for ($i = 0; $i <= 31; ++$i) {
        $tabSpecialChar[] = chr($i);
    }
    array_push($tabSpecialChar, '<br />');
    if ((int) $special === 1) {
        $tabSpecialChar = array_merge($tabSpecialChar, array('</li>', '<ul>', '<ol>'));
    }

    return str_replace($tabSpecialChar, "\n", $string);
}

/**
 * Erro manager for DB.
 *
 * @param array $params output from query
 */
function db_error_handler($params)
{
    echo 'Error: '.$params['error']."<br>\n";
    echo 'Query: '.$params['query']."<br>\n";
    throw new Exception('Error - Query', 1);
}

/**
 * [identifyUserRights description].
 *
 * @param string $groupesVisiblesUser  [description]
 * @param string $groupesInterditsUser [description]
 * @param string $isAdmin              [description]
 * @param string $idFonctions          [description]
 *
 * @return string [description]
 */
function identifyUserRights(
    $groupesVisiblesUser,
    $groupesInterditsUser,
    $isAdmin,
    $idFonctions,
    $SETTINGS
) {
    //load ClassLoader
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    //Connect to DB
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    //Build tree
    $tree = new SplClassLoader('Tree\NestedTree', $SETTINGS['cpassman_dir'].'/includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    // Check if user is ADMINISTRATOR
    if ($isAdmin === '1') {
        identAdmin(
            $idFonctions,
            $SETTINGS,
            $tree
        );
    } else {
        identUser(
            $groupesVisiblesUser,
            $groupesInterditsUser,
            $idFonctions,
            $SETTINGS,
            $tree
        );
    }

    // update user's timestamp
    DB::update(
        prefixTable('users'),
        array(
            'timestamp' => time(),
        ),
        'id=%i',
        $_SESSION['user_id']
    );
}

/**
 * Identify administrator.
 *
 * @param string     $idFonctions Roles of user
 * @param array      $SETTINGS    Teampass settings
 * @param mysqli     $link        DB connection
 * @param NestedTree $tree        Tree of folders
 */
function identAdmin($idFonctions, $SETTINGS, $tree)
{
    $groupesVisibles = array();
    $_SESSION['personal_folders'] = array();
    $_SESSION['groupes_visibles'] = array();
    $_SESSION['groupes_interdits'] = array();
    $_SESSION['personal_visible_groups'] = array();
    $_SESSION['read_only_folders'] = array();
    $_SESSION['list_restricted_folders_for_items'] = array();
    $_SESSION['list_folders_editable_by_role'] = array();
    $_SESSION['list_folders_limited'] = array();
    $_SESSION['no_access_folders'] = array();
    $_SESSION['groupes_visibles_list'] = '';

    // Get list of Folders
    $rows = DB::query('SELECT id FROM '.prefixTable('nested_tree').' WHERE personal_folder = %i', 0);
    foreach ($rows as $record) {
        array_push($groupesVisibles, $record['id']);
    }
    $_SESSION['groupes_visibles'] = $groupesVisibles;
    $_SESSION['all_non_personal_folders'] = $groupesVisibles;
    // Exclude all PF
    $_SESSION['forbiden_pfs'] = array();
    $where = new WhereClause('and'); // create a WHERE statement of pieces joined by ANDs
    $where->add('personal_folder=%i', 1);
    if (isset($SETTINGS['enable_pf_feature']) === true
        && (int) $SETTINGS['enable_pf_feature'] === 1
    ) {
        $where->add('title=%s', $_SESSION['user_id']);
        $where->negateLast();
    }
    // Get ID of personal folder
    $persfld = DB::queryfirstrow(
        'SELECT id FROM '.prefixTable('nested_tree').' WHERE title = %s',
        $_SESSION['user_id']
    );
    if (empty($persfld['id']) === false) {
        if (in_array($persfld['id'], $_SESSION['groupes_visibles']) === false) {
            array_push($_SESSION['groupes_visibles'], $persfld['id']);
            array_push($_SESSION['personal_visible_groups'], $persfld['id']);
            // get all descendants
            $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
            $tree->rebuild();
            $tst = $tree->getDescendants($persfld['id']);
            foreach ($tst as $t) {
                array_push($_SESSION['groupes_visibles'], $t->id);
                array_push($_SESSION['personal_visible_groups'], $t->id);
            }
        }
    }

    // get complete list of ROLES
    $tmp = explode(';', $idFonctions);
    $rows = DB::query(
        'SELECT * FROM '.prefixTable('roles_title').'
        ORDER BY title ASC'
    );
    foreach ($rows as $record) {
        if (!empty($record['id']) && !in_array($record['id'], $tmp)) {
            array_push($tmp, $record['id']);
        }
    }
    $_SESSION['fonction_id'] = implode(';', $tmp);

    $_SESSION['groupes_visibles_list'] = implode(',', $_SESSION['groupes_visibles']);
    $_SESSION['is_admin'] = 1;
    // Check if admin has created Folders and Roles
    DB::query('SELECT * FROM '.prefixTable('nested_tree').'');
    $_SESSION['nb_folders'] = DB::count();
    DB::query('SELECT * FROM '.prefixTable('roles_title'));
    $_SESSION['nb_roles'] = DB::count();
}

/**
 * Undocumented function.
 *
 * @param string     $groupesVisiblesUser  Allowed folders
 * @param string     $groupesInterditsUser Not allowed folders
 * @param string     $idFonctions          Roles of user
 * @param array      $SETTINGS             Teampass settings
 * @param mysqli     $link                 DB connection
 * @param NestedTree $tree                 Tree of folders
 */
function identUser(
    $groupesVisiblesUser,
    $groupesInterditsUser,
    $idFonctions,
    $SETTINGS,
    $tree
) {
    // init
    $_SESSION['groupes_visibles'] = array();
    $_SESSION['personal_folders'] = array();
    $_SESSION['groupes_interdits'] = array();
    $_SESSION['personal_visible_groups'] = array();
    $_SESSION['read_only_folders'] = array();
    $_SESSION['fonction_id'] = $idFonctions;

    $groupesInterdits = array();
    if (is_array($groupesInterditsUser) === false) {
        $groupesInterditsUser = explode(';', trimElement(/* @scrutinizer ignore-type */ $groupesInterditsUser, ';'));
    }
    if (empty($groupesInterditsUser) === false && count($groupesInterditsUser) > 0) {
        $groupesInterdits = $groupesInterditsUser;
    }
    $_SESSION['is_admin'] = '0';
    $fonctionsAssociees = explode(';', trimElement($idFonctions, ';'));

    $listAllowedFolders = $listFoldersLimited = $listFoldersEditableByRole = $listRestrictedFoldersForItems = $listReadOnlyFolders = array();

    // rechercher tous les groupes visibles en fonction des roles de l'utilisateur
    foreach ($fonctionsAssociees as $roleId) {
        if (empty($roleId) === false) {
            // Get allowed folders for each Role
            $rows = DB::query(
                'SELECT folder_id FROM '.prefixTable('roles_values').' WHERE role_id=%i',
                $roleId
            );

            if (DB::count() > 0) {
                $tmp = DB::queryfirstrow(
                    'SELECT allow_pw_change FROM '.prefixTable('roles_title').' WHERE id = %i',
                    $roleId
                );
                foreach ($rows as $record) {
                    if (isset($record['folder_id']) && in_array($record['folder_id'], $listAllowedFolders) === false) {
                        array_push($listAllowedFolders, $record['folder_id']);
                    }
                    // Check if this group is allowed to modify any pw in allowed folders
                    if ((int) $tmp['allow_pw_change'] === 1
                        && in_array($record['folder_id'], $listFoldersEditableByRole) === false
                    ) {
                        array_push($listFoldersEditableByRole, $record['folder_id']);
                    }
                }
                // Check for the users roles if some specific rights exist on items
                $rows = DB::query(
                    'SELECT i.id_tree, r.item_id
                    FROM '.prefixTable('items').' as i
                    INNER JOIN '.prefixTable('restriction_to_roles').' as r ON (r.item_id=i.id)
                    WHERE r.role_id=%i
                    ORDER BY i.id_tree ASC',
                    $roleId
                );
                $inc = 0;
                foreach ($rows as $record) {
                    if (isset($record['id_tree'])) {
                        $listFoldersLimited[$record['id_tree']][$inc] = $record['item_id'];
                        ++$inc;
                    }
                }
            }
        }
    }

    // Clean arrays
    $listAllowedFolders = array_unique($listAllowedFolders);
    $groupesVisiblesUser = explode(';', trimElement($groupesVisiblesUser, ';'));

    // Does this user is allowed to see other items
    $inc = 0;
    $rows = DB::query(
        'SELECT id, id_tree FROM '.prefixTable('items').'
        WHERE restricted_to LIKE %ss AND inactif=%s',
        $_SESSION['user_id'].';',
        '0'
    );
    foreach ($rows as $record) {
        // Exclude restriction on item if folder is fully accessible
        if (in_array($record['id_tree'], $listAllowedFolders) === false) {
            $listRestrictedFoldersForItems[$record['id_tree']][$inc] = $record['id'];
            ++$inc;
        }
    }

    // => Build final lists
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
    if (isset($SETTINGS['enable_pf_feature']) === true && $SETTINGS['enable_pf_feature'] === '1'
        && isset($_SESSION['personal_folder']) === true && $_SESSION['personal_folder'] === '1'
    ) {
        $where->add('title=%s', $_SESSION['user_id']);
        $where->negateLast();
    }

    $persoFlds = DB::query(
        'SELECT id
        FROM '.prefixTable('nested_tree').'
        WHERE %l',
        $where
    );
    foreach ($persoFlds as $persoFldId) {
        array_push($_SESSION['forbiden_pfs'], $persoFldId['id']);
    }
    // Get IDs of personal folders
    if (isset($SETTINGS['enable_pf_feature']) === true && $SETTINGS['enable_pf_feature'] === '1'
        && isset($_SESSION['personal_folder']) === true && $_SESSION['personal_folder'] === '1'
    ) {
        $persoFld = DB::queryfirstrow(
            'SELECT id
            FROM '.prefixTable('nested_tree').'
            WHERE title = %s AND personal_folder = %i',
            $_SESSION['user_id'],
            1
        );
        if (empty($persoFld['id']) === false) {
            if (in_array($persoFld['id'], $listAllowedFolders) === false) {
                array_push($_SESSION['personal_folders'], $persoFld['id']);
                array_push($listAllowedFolders, $persoFld['id']);
                array_push($_SESSION['personal_visible_groups'], $persoFld['id']);
                // get all descendants
                $ids = $tree->getChildren($persoFld['id']);
                foreach ($ids as $ident) {
                    array_push($listAllowedFolders, $ident->id);
                    array_push($_SESSION['personal_visible_groups'], $ident->id);
                    array_push($_SESSION['personal_folders'], $ident->id);
                }
            }
        }
        // get list of readonly folders when pf is disabled.
        $_SESSION['personal_folders'] = array_unique($_SESSION['personal_folders']);
        // rule - if one folder is set as W or N in one of the Role, then User has access as W
        foreach ($listAllowedFolders as $folderId) {
            if (in_array($folderId, array_unique(array_merge($listReadOnlyFolders, $_SESSION['personal_folders']))) === false) {
                DB::query(
                    'SELECT *
                    FROM '.prefixTable('roles_values').'
                    WHERE folder_id = %i AND role_id IN %li AND type IN %ls',
                    $folderId,
                    $fonctionsAssociees,
                    array('W', 'ND', 'NE', 'NDNE')
                );
                if (DB::count() === 0 && in_array($folderId, $groupesVisiblesUser) === false) {
                    array_push($listReadOnlyFolders, $folderId);
                }
            }
        }
    } else {
        // get list of readonly folders when pf is disabled.
        // rule - if one folder is set as W in one of the Role, then User has access as W
        foreach ($listAllowedFolders as $folderId) {
            if (in_array($folderId, $listReadOnlyFolders) === false) {
                DB::query(
                    'SELECT *
                    FROM '.prefixTable('roles_values').'
                    WHERE folder_id = %i AND role_id IN %li AND type IN %ls',
                    $folderId,
                    $fonctionsAssociees,
                    array('W', 'ND', 'NE', 'NDNE')
                );
                if (DB::count() === 0 && in_array($folderId, $groupesVisiblesUser) === false) {
                    array_push($listReadOnlyFolders, $folderId);
                }
            }
        }
    }

    // check if change proposals on User's items
    if (isset($SETTINGS['enable_suggestion']) === true && $SETTINGS['enable_suggestion'] === '1') {
        DB::query(
            'SELECT *
            FROM '.prefixTable('items_change').' AS c
            LEFT JOIN '.prefixTable('log_items').' AS i ON (c.item_id = i.id_item)
            WHERE i.action = %s AND i.id_user = %i',
            'at_creation',
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
    DB::queryfirstrow('SELECT id FROM '.prefixTable('nested_tree').'');
    $_SESSION['nb_folders'] = DB::count();
    DB::queryfirstrow('SELECT id FROM '.prefixTable('roles_title'));
    $_SESSION['nb_roles'] = DB::count();
}

/**
 * Update the CACHE table
 *
 * @param string $action   What to do
 * @param array  $SETTINGS Teampass settings
 * @param string $ident    Ident format
 *
 * @return void
 */
function updateCacheTable($action, $SETTINGS, $ident = null)
{
    if ($action === 'reload') {
        // Rebuild full cache table
        cacheTableRefresh($SETTINGS);
    } elseif ($action === 'update_value' && is_null($ident) === false) {
        // UPDATE an item
        cacheTableUpdate($SETTINGS, $ident);
    } elseif ($action === 'add_value' && is_null($ident) === false) {
        // ADD an item
        cacheTableAdd($SETTINGS, $ident);
    } elseif ($action === 'delete_value' && is_null($ident) === false) {
        // DELETE an item
        DB::delete(prefixTable('cache'), 'id = %i', $ident);
    }
}

/**
 * Cache table - refresh
 *
 * @param array  $SETTINGS Teampass settings
 *
 * @return void
 */
function cacheTableRefresh($SETTINGS)
{
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    //Connect to DB
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;

    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    //Load Tree
    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    // truncate table
    DB::query('TRUNCATE TABLE '.prefixTable('cache'));

    // reload date
    $rows = DB::query(
        'SELECT *
        FROM '.prefixTable('items').' as i
        INNER JOIN '.prefixTable('log_items').' as l ON (l.id_item = i.id)
        AND l.action = %s
        AND i.inactif = %i',
        'at_creation',
        0
    );
    foreach ($rows as $record) {
        if (empty($record['id_tree']) === false) {
            // Get all TAGS
            $tags = '';
            $itemTags = DB::query('SELECT tag FROM '.prefixTable('tags').' WHERE item_id=%i', $record['id']);
            foreach ($itemTags as $itemTag) {
                if (!empty($itemTag['tag'])) {
                    $tags .= $itemTag['tag'].' ';
                }
            }
            // Get renewal period
            $resNT = DB::queryfirstrow('SELECT renewal_period FROM '.prefixTable('nested_tree').' WHERE id=%i', $record['id_tree']);

            // form id_tree to full foldername
            $folder = '';
            $arbo = $tree->getPath($record['id_tree'], true);
            foreach ($arbo as $elem) {
                if ((int) $elem->title === $_SESSION['user_id']
                    && (int) $elem->nlevel === 1
                ) {
                    $elem->title = $_SESSION['login'];
                }
                if (empty($folder)) {
                    $folder = stripslashes($elem->title);
                } else {
                    $folder .= ' » '.stripslashes($elem->title);
                }
            }
            // store data
            DB::insert(
                prefixTable('cache'),
                array(
                    'id' => $record['id'],
                    'label' => $record['label'],
                    'description' => isset($record['description']) ? $record['description'] : '',
                    'url' => (isset($record['url']) && !empty($record['url'])) ? $record['url'] : '0',
                    'tags' => $tags,
                    'id_tree' => $record['id_tree'],
                    'perso' => $record['perso'],
                    'restricted_to' => (isset($record['restricted_to']) && !empty($record['restricted_to'])) ? $record['restricted_to'] : '0',
                    'login' => isset($record['login']) ? $record['login'] : '',
                    'folder' => $folder,
                    'author' => $record['id_user'],
                    'renewal_period' => isset($resNT['renewal_period']) ? $resNT['renewal_period'] : '0',
                    'timestamp' => $record['date'],
                )
            );
        }
    }
}

/**
 * Cache table - update existing value
 *
 * @param array  $SETTINGS Teampass settings
 * @param string $ident    Ident format
 *
 * @return void
 */
function cacheTableUpdate($action, $SETTINGS, $ident = null)
{
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    //Connect to DB
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;

    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    //Load Tree
    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    // get new value from db
    $data = DB::queryfirstrow(
        'SELECT label, description, id_tree, perso, restricted_to, login, url
        FROM '.prefixTable('items').'
        WHERE id=%i',
        $ident
    );
    // Get all TAGS
    $tags = '';
    $itemTags = DB::query('SELECT tag FROM '.prefixTable('tags').' WHERE item_id=%i', $ident);
    foreach ($itemTags as $itemTag) {
        if (!empty($itemTag['tag'])) {
            $tags .= $itemTag['tag'].' ';
        }
    }
    // form id_tree to full foldername
    $folder = '';
    $arbo = $tree->getPath($data['id_tree'], true);
    foreach ($arbo as $elem) {
        if ((int) $elem->title === $_SESSION['user_id'] && (int) $elem->nlevel === 1) {
            $elem->title = $_SESSION['login'];
        }
        if (empty($folder)) {
            $folder = stripslashes($elem->title);
        } else {
            $folder .= ' » '.stripslashes($elem->title);
        }
    }
    // finaly update
    DB::update(
        prefixTable('cache'),
        array(
            'label' => $data['label'],
            'description' => $data['description'],
            'tags' => $tags,
            'url' => (isset($data['url']) && !empty($data['url'])) ? $data['url'] : '0',
            'id_tree' => $data['id_tree'],
            'perso' => $data['perso'],
            'restricted_to' => (isset($data['restricted_to']) && !empty($data['restricted_to'])) ? $data['restricted_to'] : '0',
            'login' => isset($data['login']) ? $data['login'] : '',
            'folder' => $folder,
            'author' => $_SESSION['user_id'],
            ),
        'id = %i',
        $ident
    );
}

/**
 * Cache table - add new value
 *
 * @param array  $SETTINGS Teampass settings
 * @param string $ident    Ident format
 *
 * @return void
 */
function cacheTableAdd($action, $SETTINGS, $ident = null)
{
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    //Connect to DB
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;

    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    //Load Tree
    $tree = new SplClassLoader('Tree\NestedTree', '../includes/libraries');
    $tree->register();
    $tree = new Tree\NestedTree\NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    // get new value from db
    $data = DB::queryFirstRow(
        'SELECT i.label, i.description, i.id_tree as id_tree, i.perso, i.restricted_to, i.id, i.login, i.url, l.date
        FROM '.prefixTable('items').' as i
        INNER JOIN '.prefixTable('log_items').' as l ON (l.id_item = i.id)
        WHERE i.id = %i
        AND l.action = %s',
        $ident,
        'at_creation'
    );
    // Get all TAGS
    $tags = '';
    $itemTags = DB::query('SELECT tag FROM '.prefixTable('tags').' WHERE item_id = %i', $ident);
    foreach ($itemTags as $itemTag) {
        if (!empty($itemTag['tag'])) {
            $tags .= $itemTag['tag'].' ';
        }
    }
    // form id_tree to full foldername
    $folder = '';
    $arbo = $tree->getPath($data['id_tree'], true);
    foreach ($arbo as $elem) {
        if ((int) $elem->title === $_SESSION['user_id'] && (int) $elem->nlevel === 1) {
            $elem->title = $_SESSION['login'];
        }
        if (empty($folder)) {
            $folder = stripslashes($elem->title);
        } else {
            $folder .= ' » '.stripslashes($elem->title);
        }
    }
    // finaly update
    DB::insert(
        prefixTable('cache'),
        array(
            'id' => $data['id'],
            'label' => $data['label'],
            'description' => $data['description'],
            'tags' => (isset($tags) && !empty($tags)) ? $tags : 'None',
            'url' => (isset($data['url']) && !empty($data['url'])) ? $data['url'] : '0',
            'id_tree' => $data['id_tree'],
            'perso' => (isset($data['perso']) && !empty($data['perso']) && $data['perso'] !== 'None') ? $data['perso'] : '0',
            'restricted_to' => (isset($data['restricted_to']) && !empty($data['restricted_to'])) ? $data['restricted_to'] : '0',
            'login' => isset($data['login']) ? $data['login'] : '',
            'folder' => $folder,
            'author' => $_SESSION['user_id'],
            'timestamp' => $data['date'],
        )
    );
}


/**
 * Do statistics
 *
 * @return array
 */
function getStatisticsData()
{
    DB::query(
        'SELECT id FROM '.prefixTable('nested_tree').' WHERE personal_folder = %i',
        0
    );
    $counter_folders = DB::count();

    DB::query(
        'SELECT id FROM '.prefixTable('nested_tree').' WHERE personal_folder = %i',
        1
    );
    $counter_folders_perso = DB::count();

    DB::query(
        'SELECT id FROM '.prefixTable('items').' WHERE perso = %i',
        0
    );
    $counter_items = DB::count();

    DB::query(
        'SELECT id FROM '.prefixTable('items').' WHERE perso = %i',
        1
    );
    $counter_items_perso = DB::count();

    DB::query(
        'SELECT id FROM '.prefixTable('users').''
    );
    $counter_users = DB::count();

    DB::query(
        'SELECT id FROM '.prefixTable('users').' WHERE admin = %i',
        1
    );
    $admins = DB::count();

    DB::query(
        'SELECT id FROM '.prefixTable('users').' WHERE gestionnaire = %i',
        1
    );
    $managers = DB::count();

    DB::query(
        'SELECT id FROM '.prefixTable('users').' WHERE read_only = %i',
        1
    );
    $readOnly = DB::count();

    // list the languages
    $usedLang = [];
    $tp_languages = DB::query(
        'SELECT name FROM '.prefixTable('languages')
    );
    foreach ($tp_languages as $tp_language) {
        DB::query(
            'SELECT * FROM '.prefixTable('users').' WHERE user_language = %s',
            $tp_language['name']
        );
        $usedLang[$tp_language['name']] = round((DB::count() * 100 / $counter_users), 0);
    }

    // get list of ips
    $usedIp = [];
    $tp_ips = DB::query(
        'SELECT user_ip FROM '.prefixTable('users')
    );
    foreach ($tp_ips as $ip) {
        if (array_key_exists($ip['user_ip'], $usedIp)) {
            $usedIp[$ip['user_ip']] = $usedIp[$ip['user_ip']] + 1;
        } elseif (!empty($ip['user_ip']) && $ip['user_ip'] !== 'none') {
            $usedIp[$ip['user_ip']] = 1;
        }
    }

    return array(
        'error' => '',
        'stat_phpversion' => phpversion(),
        'stat_folders' => $counter_folders,
        'stat_folders_shared' => intval($counter_folders) - intval($counter_folders_perso),
        'stat_items' => $counter_items,
        'stat_items_shared' => intval($counter_items) - intval($counter_items_perso),
        'stat_users' => $counter_users,
        'stat_admins' => $admins,
        'stat_managers' => $managers,
        'stat_ro' => $readOnly,
        'stat_kb' => $SETTINGS['enable_kb'],
        'stat_pf' => $SETTINGS['enable_pf_feature'],
        'stat_fav' => $SETTINGS['enable_favourites'],
        'stat_teampassversion' => $SETTINGS['cpassman_version'],
        'stat_ldap' => $SETTINGS['ldap_mode'],
        'stat_agses' => $SETTINGS['agses_authentication_enabled'],
        'stat_duo' => $SETTINGS['duo'],
        'stat_suggestion' => $SETTINGS['enable_suggestion'],
        'stat_api' => $SETTINGS['api'],
        'stat_customfields' => $SETTINGS['item_extra_fields'],
        'stat_syslog' => $SETTINGS['syslog_enable'],
        'stat_2fa' => $SETTINGS['google_authentication'],
        'stat_stricthttps' => $SETTINGS['enable_sts'],
        'stat_mysqlversion' => DB::serverVersion(),
        'stat_languages' => $usedLang,
        'stat_country' => $usedIp,
    );
}

/**
 * Permits to send an email.
 *
 * @param string $subject     email subject
 * @param string $textMail    email message
 * @param string $email       email
 * @param array  $SETTINGS    settings
 * @param string $textMailAlt email message alt
 *
 * @return string some json info
 */
function sendEmail(
    $subject,
    $textMail,
    $email,
    $SETTINGS,
    $textMailAlt = null
) {
    // CAse where email not defined
    if ($email === 'none') {
        return '"error":"" , "message":"'.langHdl('forgot_my_pw_email_sent').'"';
    }

    // Load settings
    include_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';

    // Load superglobal
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    // Get user language
    $session_user_language = $superGlobal->get('user_language', 'SESSION');
    $user_language = isset($session_user_language) ? $session_user_language : 'english';
    include_once $SETTINGS['cpassman_dir'].'/includes/language/'.$user_language.'.php';

    // Load library
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    // load PHPMailer
    $mail = new SplClassLoader('Email\PHPMailer', '../includes/libraries');
    $mail->register();
    $mail = new Email\PHPMailer\PHPMailer(true);

    // send to user
    $mail->setLanguage('en', $SETTINGS['cpassman_dir'].'/includes/libraries/Email/PHPMailer/language/');
    $mail->SMTPDebug = 0; //value 1 can be used to debug - 4 for debuging connections
    $mail->Port = $SETTINGS['email_port']; //COULD BE USED
    $mail->CharSet = 'utf-8';
    $mail->SMTPSecure = ($SETTINGS['email_security'] === 'tls'
    || $SETTINGS['email_security'] === 'ssl') ? $SETTINGS['email_security'] : '';
    $mail->SMTPAutoTLS = ($SETTINGS['email_security'] === 'tls'
        || $SETTINGS['email_security'] === 'ssl') ? true : false;
    $mail->isSmtp(); // send via SMTP
    $mail->Host = $SETTINGS['email_smtp_server']; // SMTP servers
    $mail->SMTPAuth = (int) $SETTINGS['email_smtp_auth'] === 1 ? true : false; // turn on SMTP authentication
    $mail->Username = $SETTINGS['email_auth_username']; // SMTP username
    $mail->Password = $SETTINGS['email_auth_pwd']; // SMTP password
    $mail->From = $SETTINGS['email_from'];
    $mail->FromName = $SETTINGS['email_from_name'];

    // Prepare for each person
    foreach (array_filter(explode(',', $email)) as $dest) {
        $mail->addAddress($dest);
    }

    // Prepare HTML
    $text_html = emailBody($textMail);

    $mail->WordWrap = 80; // set word wrap
    $mail->isHtml(true); // send as HTML
    $mail->Subject = $subject;
    $mail->Body = $text_html;
    $mail->AltBody = (is_null($textMailAlt) === false) ? $textMailAlt : '';

    // send email
    if ($mail->send()) {
        return json_encode(
            array(
                'error' => '',
                'message' => langHdl('forgot_my_pw_email_sent'),
            )
        );
    } else {
        return json_encode(
            array(
                'error' => 'error_mail_not_send',
                'message' => str_replace(array("\n", "\t", "\r"), '', $mail->ErrorInfo),
            )
        );
    }
}

/**
 * Returns the email body
 *
 * @param string $textMail Text for the email
 *
 * @return string
 */
function emailBody($textMail)
{
    return '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.=
    w3.org/TR/html4/loose.dtd"><html>
    <head><title>Email Template</title>
    <style type="text/css">
    body { background-color: #f0f0f0; padding: 10px 0; margin:0 0 10px =0; }
    </style></head>
    <body style="-ms-text-size-adjust: none; size-adjust: none; margin: 0; padding: 10px 0; background-color: #f0f0f0;" bgcolor="#f0f0f0" leftmargin="0" topmargin="0" marginwidth="0" marginheight="0">
    <table border="0" width="100%" height="100%" cellpadding="0" cellspacing="0" bgcolor="#f0f0f0" style="border-spacing: 0;">
    <tr><td style="border-collapse: collapse;"><br>
        <table border="0" width="100%" cellpadding="0" cellspacing="0" bgcolor="#17357c" style="border-spacing: 0; margin-bottom: 25px;">
        <tr><td style="border-collapse: collapse; padding: 11px 20px;">
            <div style="max-width:150px; max-height:34px; color:#f0f0f0; font-weight:bold;">Teampass</div>
        </td></tr></table></td>
    </tr>
    <tr><td align="center" valign="top" bgcolor="#f0f0f0" style="border-collapse: collapse; background-color: #f0f0f0;">
        <table width="600" cellpadding="0" cellspacing="0" border="0" class="container" bgcolor="#ffffff" style="border-spacing: 0; border-bottom: 1px solid #e0e0e0; box-shadow: 0 0 3px #ddd; color: #434343; font-family: Helvetica, Verdana, sans-serif;">
        <tr><td class="container-padding" bgcolor="#ffffff" style="border-collapse: collapse; border-left: 1px solid #e0e0e0; background-color: #ffffff; padding-left: 30px; padding-right: 30px;">
        <br><div style="float:right;">'.
    $textMail.
    '<br><br></td></tr></table>
    </td></tr></table>
    <br></body></html>';
}

/**
 * Generate a Key.
 *
 * @return string
 */
function generateKey()
{
    return substr(md5(rand().rand()), 0, 15);
}

/**
 * Convert date to timestamp.
 *
 * @param string $date     The date
 * @param array  $SETTINGS Teampass settings
 *
 * @return string
 */
function dateToStamp($date, $SETTINGS)
{
    $date = date_parse_from_format($SETTINGS['date_format'], $date);
    if ((int) $date['warning_count'] === 0 && (int) $date['error_count'] === 0) {
        return mktime(23, 59, 59, $date['month'], $date['day'], $date['year']);
    } else {
        return '';
    }
}

/**
 * Is this a date.
 *
 * @param string $date Date
 *
 * @return bool
 */
function isDate($date)
{
    return strtotime($date) !== false;
}

/**
 * isUTF8().
 *
 * @return int is the string in UTF8 format
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

/**
 * Prepare an array to UTF8 format before JSON_encode.
 *
 * @param array $array Array of values
 *
 * @return array
 */
function utf8Converter($array)
{
    array_walk_recursive(
        $array,
        function (&$item, $key) {
            if (mb_detect_encoding($item, 'utf-8', true) === false) {
                $item = utf8_encode($item);
            }
        }
    );

    return $array;
}

/**
 * Permits to prepare data to be exchanged.
 *
 * @param array  $data Text
 * @param string $type Parameter
 *
 * @return string
 */
function prepareExchangedData($data, $type)
{
    if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir'])) {
        if (file_exists('../includes/config/tp.config.php')) {
            include '../includes/config/tp.config.php';
        } elseif (file_exists('./includes/config/tp.config.php')) {
            include './includes/config/tp.config.php';
        } elseif (file_exists('../../includes/config/tp.config.php')) {
            include '../../includes/config/tp.config.php';
        } else {
            throw new Exception("Error file '/includes/config/tp.config.php' not exists", 1);
        }
    }

    //load ClassLoader
    include_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';
    //Load AES
    $aes = new SplClassLoader('Encryption\Crypt', $SETTINGS['cpassman_dir'].'/includes/libraries');
    $aes->register();

    if ($type === 'encode') {
        // Ensure UTF8 format
        $data = utf8Converter($data);
        // Now encode
        if (isset($SETTINGS['encryptClientServer'])
            && $SETTINGS['encryptClientServer'] === '0'
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
    } elseif ($type === 'decode') {
        if (isset($SETTINGS['encryptClientServer'])
            && $SETTINGS['encryptClientServer'] === '0'
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

/**
 * Create a thumbnail.
 *
 * @param string $src           Source
 * @param string $dest          Destination
 * @param int    $desired_width Size of width
 */
function makeThumbnail($src, $dest, $desired_width)
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

/**
 * Check table prefix in SQL query.
 *
 * @param string $table Table name
 *
 * @return string
 */
function prefixTable($table)
{
    $safeTable = htmlspecialchars(DB_PREFIX.$table);
    if (!empty($safeTable)) {
        // sanitize string
        return $safeTable;
    } else {
        // stop error no table
        return 'table_not_exists';
    }
}

/*
 * Creates a KEY using PasswordLib
 */
function GenerateCryptKey($size = null, $secure = false, $numerals = false, $capitalize = false, $symbols = false)
{
    global $SETTINGS;
    require_once $SETTINGS['cpassman_dir'].'/sources/SplClassLoader.php';

    if ($secure === true) {
        $numerals = true;
        $capitalize = true;
        $symbols = true;
    }

    // Load libraries
    $generator = new SplClassLoader('PasswordGenerator\Generator', '../includes/libraries');
    $generator->register();
    $generator = new PasswordGenerator\Generator\ComputerPasswordGenerator();

    // Can we use PHP7 random_int function?
    if (version_compare(phpversion(), '7.0', '>=')) {
        require_once $SETTINGS['cpassman_dir'].'/includes/libraries/PasswordGenerator/RandomGenerator/Php7RandomGenerator.php';
        $generator->setRandomGenerator(new PasswordGenerator\RandomGenerator\Php7RandomGenerator());
    }

    // init
    if (empty($size) === false && is_null($size) === false) {
        $generator->setLength(intval($size));
    }
    if (empty($numerals) === false) {
        $generator->setNumbers($numerals);
    }
    if (empty($capitalize) === false) {
        $generator->setUppercase($capitalize);
    }
    if (empty($symbols) === false) {
        $generator->setSymbols($symbols);
    }

    // generate and send back
    return $generator->generatePassword();
}

/*
* Send sysLOG message
* @param string $message
* @param string $host
*/
function send_syslog($message, $host, $port, $component = 'teampass')
{
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    $syslog_message = '<123>'.date('M d H:i:s ').$component.': '.$message;
    socket_sendto($sock, $syslog_message, strlen($syslog_message), 0, $host, $port);
    socket_close($sock);
}

/**
 * logEvents().
 *
 * permits to log events into DB
 *
 * @param string $type
 * @param string $label
 * @param string $field_1
 */
function logEvents($type, $label, $who, $login = null, $field_1 = null)
{
    global $server, $user, $pass, $database, $port, $encoding;
    global $SETTINGS;

    if (empty($who)) {
        $who = getClientIpServer();
    }

    // include librairies & connect to DB
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    //DB::$errorHandler = true;

    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    DB::insert(
        prefixTable('log_system'),
        array(
            'type' => $type,
            'date' => time(),
            'label' => $label,
            'qui' => $who,
            'field_1' => $field_1 === null ? '' : $field_1,
        )
    );

    // If SYSLOG
    if (isset($SETTINGS['syslog_enable']) === true && (int) $SETTINGS['syslog_enable'] === 1) {
        if ($type === 'user_mngt') {
            send_syslog(
                'action='.str_replace('at_', '', $label).' attribute=user user='.$who.' userid="'.$login.'" change="'.$field_1.'" ',
                $SETTINGS['syslog_host'],
                $SETTINGS['syslog_port'],
                'teampass'
            );
        } else {
            send_syslog(
                'action='.$type.' attribute='.$label.' user='.$who.' userid="'.$login.'" ',
                $SETTINGS['syslog_host'],
                $SETTINGS['syslog_port'],
                'teampass'
            );
        }
    }
}

/**
 * Log events.
 *
 * @param array  $SETTINGS        Teampass settings
 * @param int    $item_id         Item id
 * @param string $item_label      Item label
 * @param int    $id_user         User id
 * @param string $action          Code for reason
 * @param string $login           User login
 * @param string $raison          Code for reason
 * @param string $encryption_type Encryption on
 */
function logItems(
    $SETTINGS,
    $item_id,
    $item_label,
    $id_user,
    $action,
    $login = null,
    $raison = null,
    $encryption_type = null
) {
    $dataItem = '';

    // include librairies & connect to DB
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    // Insert log in DB
    DB::insert(
        prefixTable('log_items'),
        array(
            'id_item' => $item_id,
            'date' => time(),
            'id_user' => $id_user,
            'action' => $action,
            'raison' => $raison,
            'raison_iv' => '',
            'encryption_type' => is_null($encryption_type) === true ? '' : $encryption_type,
        )
    );
    // Timestamp the last change
    if ($action === 'at_creation' || $action === 'at_modifiation' || $action === 'at_delete' || $action === 'at_import') {
        DB::update(
            prefixTable('misc'),
            array(
                'valeur' => time(),
                ),
            'type = %s AND intitule = %s',
            'timestamp',
            'last_item_change'
        );
    }

    // SYSLOG
    if (isset($SETTINGS['syslog_enable']) === true && $SETTINGS['syslog_enable'] === '1') {
        // Extract reason
        $attribute = explode(' : ', $raison);

        // Get item info if not known
        if (empty($item_label) === true) {
            $dataItem = DB::queryfirstrow(
                'SELECT id, id_tree, label
                FROM '.prefixTable('items').'
                WHERE id = %i',
                $item_id
            );

            $item_label = $dataItem['label'];
        }

        send_syslog(
            'action='.str_replace('at_', '', $action).' attribute='.str_replace('at_', '', $attribute[0]).' itemno='.$item_id.' user='.addslashes($login).' itemname="'.addslashes($item_label).'"',
            $SETTINGS['syslog_host'],
            $SETTINGS['syslog_port'],
            'teampass'
        );
    }

    // send notification if enabled
    if (isset($SETTINGS['enable_email_notification_on_item_shown']) === true
        && $SETTINGS['enable_email_notification_on_item_shown'] === '1'
        && $action === 'at_shown'
    ) {
        // Get info about item
        if (empty($dataItem) === true || empty($item_label) === true) {
            $dataItem = DB::queryfirstrow(
                'SELECT id, id_tree, label
                FROM '.prefixTable('items').'
                WHERE id = %i',
                $item_id
            );
            $item_label = $dataItem['label'];
        }

        // send back infos
        DB::insert(
            prefixTable('emails'),
            array(
                'timestamp' => time(),
                'subject' => langHdl('email_on_open_notification_subject'),
                'body' => str_replace(
                    array('#tp_user#', '#tp_item#', '#tp_link#'),
                    array(
                        addslashes($_SESSION['login']),
                        addslashes($item_label),
                        $SETTINGS['cpassman_url'].'/index.php?page=items&group='.$dataItem['id_tree'].'&id='.$dataItem['id'],
                    ),
                    langHdl('email_on_open_notification_mail')
                ),
                'receivers' => $_SESSION['listNotificationEmails'],
                'status' => '',
            )
        );
    }
}

/**
 * Get the client ip address.
 *
 * @return string IP address
 */
function getClientIpServer()
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
 * Escape all HTML, JavaScript, and CSS.
 *
 * @param string $input    The input string
 * @param string $encoding Which character encoding are we using?
 *
 * @return string
 */
function noHTML($input, $encoding = 'UTF-8')
{
    return htmlspecialchars($input, ENT_QUOTES | ENT_XHTML, $encoding, false);
}

/**
 * handleConfigFile().
 *
 * permits to handle the Teampass config file
 * $action accepts "rebuild" and "update"
 */
function handleConfigFile($action, $field = null, $value = null)
{
    global $server, $user, $pass, $database, $port, $encoding;
    global $SETTINGS;

    $tp_config_file = '../includes/config/tp.config.php';

    // include librairies & connect to DB
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    //DB::$errorHandler = true;

    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    if (file_exists($tp_config_file) === false || $action === 'rebuild') {
        // perform a copy
        if (file_exists($tp_config_file)) {
            if (!copy($tp_config_file, $tp_config_file.'.'.date('Y_m_d_His', time()))) {
                return "ERROR: Could not copy file '".$tp_config_file."'";
            }
        }

        // regenerate
        $data = array();
        $data[0] = "<?php\n";
        $data[1] = "global \$SETTINGS;\n";
        $data[2] = "\$SETTINGS = array (\n";
        $rows = DB::query(
            'SELECT * FROM '.prefixTable('misc').' WHERE type=%s',
            'admin'
        );
        foreach ($rows as $record) {
            array_push($data, "    '".$record['intitule']."' => '".$record['valeur']."',\n");
        }
        array_push($data, ");\n");
        $data = array_unique($data);
    } elseif ($action === 'update' && empty($field) === false) {
        $data = file($tp_config_file);
        $inc = 0;
        $bFound = false;
        foreach ($data as $line) {
            if (stristr($line, ');')) {
                break;
            }

            if (stristr($line, "'".$field."' => '")) {
                $data[$inc] = "    '".$field."' => '".filter_var($value, FILTER_SANITIZE_STRING)."',\n";
                $bFound = true;
                break;
            }
            ++$inc;
        }
        if ($bFound === false) {
            $data[($inc)] = "    '".$field."' => '".filter_var($value, FILTER_SANITIZE_STRING)."',\n);\n";
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
    return str_replace('&amp;#92;', '&#92;', $input);
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
            'SELECT * FROM '.prefixTable('misc').' WHERE type=%s_type OR type=%s_type2',
            array(
                'type' => 'admin',
                'type2' => 'settings',
            )
        );
        foreach ($rows as $record) {
            if ($record['type'] === 'admin') {
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
        'SELECT id_category
        FROM '.prefixTable('categories_folders').'
        WHERE id_folder = %i',
        $source_id
    );
    foreach ($rows as $record) {
        array_push($source_cf, $record['id_category']);
    }

    $target_cf = array();
    $rows = DB::QUERY(
        'SELECT id_category
        FROM '.prefixTable('categories_folders').'
        WHERE id_folder = %i',
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

/**
 * Shall we crypt/decrypt.
 *
 * @param string $filename_to_rework File name
 * @param string $filename_status    Its status
 * @param array  $SETTINGS           Settings
 */
function encryptOrDecryptFile(
    $filename_to_rework,
    $filename_status,
    $SETTINGS
) {
    // Include librairies & connect to DB
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    DB::$host = DB_HOST;
    DB::$user = DB_USER;
    DB::$password = defuseReturnDecrypted(DB_PASSWD, $SETTINGS);
    DB::$dbName = DB_NAME;
    DB::$port = DB_PORT;
    DB::$encoding = DB_ENCODING;
    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    // Get file info in DB
    $fileInfo = DB::queryfirstrow(
        'SELECT id FROM '.prefixTable('files').' WHERE file = %s',
        filter_var($filename_to_rework, FILTER_SANITIZE_STRING)
    );
    if (empty($fileInfo['id']) === false) {
        // Load PhpEncryption library
        $path_to_encryption = '/includes/libraries/Encryption/Encryption/';
        include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Crypto.php';
        include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Encoding.php';
        include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'DerivedKeys.php';
        include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Key.php';
        include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'KeyOrPassword.php';
        include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'File.php';
        include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'RuntimeTests.php';
        include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'KeyProtectedByPassword.php';
        include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Core.php';

        // Get KEY
        $ascii_key = file_get_contents(SECUREPATH.'/teampass-seckey.txt');

        if (isset($SETTINGS['enable_attachment_encryption'])
            && $SETTINGS['enable_attachment_encryption'] === '1'
            && isset($filename_status) === true
            && ($filename_status === 'clear' || $filename_status === '0')
        ) {
            // File needs to be encrypted
            if (file_exists($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework) === true) {
                // Make a copy of file
                if (copy(
                    $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                    $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.'.copy'
                )
                    === false
                ) {
                    return;
                } else {
                    // Do a bck
                    copy(
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.'.bck'
                    );
                }

                unlink($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework);

                // Now encrypt the file with saltkey
                $err = '';
                try {
                    \Defuse\Crypto\File::encryptFile(
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.'.copy',
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                        \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key)
                    );
                } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                    $err = 'An attack! Either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.';
                } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
                    $err = $ex;
                } catch (Defuse\Crypto\Exception\IOException $ex) {
                    $err = $ex;
                }
                if (empty($err) === false) {
                    echo $err;
                }

                unlink($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.'.copy');

                // update table
                DB::update(
                    prefixTable('files'),
                    array(
                        'status' => 'encrypted',
                        ),
                    'id = %i',
                    $fileInfo['id']
                );
            }
        } elseif (isset($SETTINGS['enable_attachment_encryption'])
            && $SETTINGS['enable_attachment_encryption'] === '0'
            && isset($filename_status)
            && $filename_status === 'encrypted'
        ) {
            // file needs to be decrypted
            if (file_exists($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework)) {
                // make a copy of file
                if (!copy(
                    $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                    $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.'.copy'
                )) {
                    return;
                } else {
                    // do a bck
                    copy(
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.'.bck'
                    );
                }

                unlink($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework);

                // Now encrypt the file with saltkey
                $err = '';
                try {
                    \Defuse\Crypto\File::decryptFile(
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.'.copy',
                        $SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework,
                        \Defuse\Crypto\Key::loadFromAsciiSafeString($ascii_key)
                    );
                } catch (Defuse\Crypto\Exception\WrongKeyOrModifiedCiphertextException $ex) {
                    $err = 'An attack! Either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.';
                } catch (Defuse\Crypto\Exception\EnvironmentIsBrokenException $ex) {
                    $err = $ex;
                } catch (Defuse\Crypto\Exception\IOException $ex) {
                    $err = $ex;
                }
                if (empty($err) === false) {
                    echo $err;
                }

                unlink($SETTINGS['path_to_upload_folder'].'/'.$filename_to_rework.'.copy');

                // update table
                DB::update(
                    prefixTable('files'),
                    array(
                        'status' => 'clear',
                        ),
                    'id = %i',
                    $fileInfo['id']
                );
            }
        }
    }

    // Exit
    return false;
}

/**
 * Will encrypte/decrypt a fil eusing Defuse.
 *
 * @param string $type        can be either encrypt or decrypt
 * @param string $source_file path to source file
 * @param string $target_file path to target file
 * @param array  $SETTINGS    Settings
 * @param string $password    A password
 *
 * @return string|bool
 */
function prepareFileWithDefuse(
    $type,
    $source_file,
    $target_file,
    $SETTINGS,
    $password = null
) {
    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    // Protect against bad inputs
    if (is_array($source_file) === true || is_array($target_file) === true) {
        return 'error_cannot_be_array';
    }

    // Sanitize
    $source_file = $antiXss->xss_clean($source_file);
    $target_file = $antiXss->xss_clean($target_file);

    // load PhpEncryption library
    $path_to_encryption = '/includes/libraries/Encryption/Encryption/';
    include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Crypto.php';
    include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Encoding.php';
    include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'DerivedKeys.php';
    include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Key.php';
    include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'KeyOrPassword.php';
    include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'File.php';
    include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'RuntimeTests.php';
    include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'KeyProtectedByPassword.php';
    include_once $SETTINGS['cpassman_dir'].$path_to_encryption.'Core.php';

    if (empty($password) === true || is_null($password) === true) {
        /*
        File encryption/decryption is done with the SALTKEY
         */

        // get KEY
        $ascii_key = file_get_contents(SECUREPATH.'/teampass-seckey.txt');

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
                $err = 'decryption_not_possible';
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
                $err = 'encryption_not_possible';
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
                $err = 'wrong_key';
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
                $err = 'wrong_key';
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
 * DELETE the file with expected command depending on server type.
 *
 * @param string $file Path to file
 *
 * @return Nothing
 */
function fileDelete($file)
{
    global $SETTINGS;

    // Load AntiXSS
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    $file = $antiXss->xss_clean($file);
    if (is_file($file)) {
        unlink($file);
    }
}

/**
 * Permits to extract the file extension.
 *
 * @param string $file File name
 *
 * @return string
 */
function getFileExtension($file)
{
    if (strpos($file, '.') === false) {
        return $file;
    }

    return substr($file, strrpos($file, '.') + 1);
}

/**
 * Permits to clean and sanitize text to be displayed.
 *
 * @param string $text Text to clean
 * @param string $type What clean to perform
 *
 * @return string
 */
function cleanText($string, $type = null)
{
    global $SETTINGS;

    // Load AntiXSS
    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/AntiXSS/AntiXSS.php';
    $antiXss = new protect\AntiXSS\AntiXSS();

    if ($type === 'css') {
        // Escape text and quotes in UTF8 format
        return htmlentities($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif (empty($type) === true || is_null($type) === true || $type === 'html') {
        // Html cleaner
        return $antiXss->xss_clean($string);
    }
}

/**
 * Performs chmod operation on subfolders.
 *
 * @param string $dir             Parent folder
 * @param int    $dirPermissions  New permission on folders
 * @param int    $filePermissions New permission on files
 *
 * @return bool
 */
function chmodRecursive($dir, $dirPermissions, $filePermissions)
{
    $pointer_dir = opendir($dir);
    $res = true;
    while ($file = readdir($pointer_dir)) {
        if (($file === '.') || ($file === '..')) {
            continue;
        }

        $fullPath = $dir.'/'.$file;

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
 * Check if user can access to this item.
 *
 * @param int $item_id ID of item
 */
function accessToItemIsGranted($item_id)
{
    global $SETTINGS;

    require_once $SETTINGS['cpassman_dir'].'/includes/libraries/protect/SuperGlobal/SuperGlobal.php';
    $superGlobal = new protect\SuperGlobal\SuperGlobal();

    // Prepare superGlobal variables
    $session_groupes_visibles = $superGlobal->get('groupes_visibles', 'SESSION');
    $session_list_restricted_folders_for_items = $superGlobal->get('list_restricted_folders_for_items', 'SESSION');

    // Load item data
    $data = DB::queryFirstRow(
        'SELECT id_tree
        FROM '.prefixTable('items').'
        WHERE id = %i',
        $item_id
    );

    // Check if user can access this folder
    if (in_array($data['id_tree'], $session_groupes_visibles) === false) {
        // Now check if this folder is restricted to user
        if (isset($session_list_restricted_folders_for_items[$data['id_tree']])
            && !in_array($item_id, $session_list_restricted_folders_for_items[$data['id_tree']])
        ) {
            return 'ERR_FOLDER_NOT_ALLOWED';
        } else {
            return 'ERR_FOLDER_NOT_ALLOWED';
        }
    }

    return true;
}

/**
 * Creates a unique key.
 *
 * @lenght  integer $lenght Key lenght
 *
 * @return string
 */
function uniqidReal($lenght = 13)
{
    // uniqid gives 13 chars, but you could adjust it to your needs.
    if (function_exists('random_bytes')) {
        $bytes = random_bytes(ceil($lenght / 2));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes(ceil($lenght / 2));
    } else {
        throw new Exception('no cryptographically secure random function available');
    }

    return substr(bin2hex($bytes), 0, $lenght);
}

/**
 * Obfuscate an email address.
 *
 * @email {string}  email address
 */
function obfuscate_email($email)
{
    $prop = 2;
    $start = '';
    $end = '';
    $domain = substr(strrchr($email, '@'), 1);
    $mailname = str_replace($domain, '', $email);
    $name_l = strlen($mailname);
    $domain_l = strlen($domain);
    for ($i = 0; $i <= $name_l / $prop - 1; ++$i) {
        $start .= 'x';
    }

    for ($i = 0; $i <= $domain_l / $prop - 1; ++$i) {
        $end .= 'x';
    }

    return substr_replace($mailname, $start, 2, $name_l / $prop)
        .substr_replace($domain, $end, 2, $domain_l / $prop);
}

/**
 * Permits to get LDAP information about a user.
 *
 * @param string $username User name
 * @param string $password User password
 *
 * @return string
 */
function connectLDAP($username, $password, $SETTINGS)
{
    $user_email = '';
    $user_found = false;
    $user_lastname = '';
    $user_name = '';
    $ldapConnection = false;

    // Prepare LDAP connection if set up
    //Multiple Domain Names
    if (strpos(html_entity_decode($username), '\\') === true) {
        $ldap_suffix = '@'.substr(html_entity_decode($username), 0, strpos(html_entity_decode($username), '\\'));
        $username = substr(html_entity_decode($username), strpos(html_entity_decode($username), '\\') + 1);
    }
    if ($SETTINGS['ldap_type'] === 'posix-search') {
        $ldapURIs = '';
        foreach (explode(',', $SETTINGS['ldap_domain_controler']) as $domainControler) {
            if ((int) $SETTINGS['ldap_ssl'] === 1) {
                $ldapURIs .= 'ldaps://'.$domainControler.':'.$SETTINGS['ldap_port'].' ';
            } else {
                $ldapURIs .= 'ldap://'.$domainControler.':'.$SETTINGS['ldap_port'].' ';
            }
        }
        $ldapconn = ldap_connect($ldapURIs);

        if ($SETTINGS['ldap_tls']) {
            ldap_start_tls($ldapconn);
        }
        ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldapconn, LDAP_OPT_REFERRALS, 0);

        // Is LDAP connection ready?
        if ($ldapconn !== false) {
            // Should we bind the connection?
            if ($SETTINGS['ldap_bind_dn'] !== '' && $SETTINGS['ldap_bind_passwd'] !== '') {
                $ldapbind = ldap_bind($ldapconn, $SETTINGS['ldap_bind_dn'], $SETTINGS['ldap_bind_passwd']);
            } else {
                $ldapbind = false;
            }
            if (($SETTINGS['ldap_bind_dn'] === '' && $SETTINGS['ldap_bind_passwd'] === '') || $ldapbind === true) {
                $filter = '(&('.$SETTINGS['ldap_user_attribute'].'='.$username.')(objectClass='.$SETTINGS['ldap_object_class'].'))';
                $result = ldap_search(
                    $ldapconn,
                    $SETTINGS['ldap_search_base'],
                    $filter,
                    array('dn', 'mail', 'givenname', 'sn', 'samaccountname')
                );

                // Check if user was found in AD
                if (ldap_count_entries($ldapconn, $result) > 0) {
                    // Get user's info and especially the DN
                    $result = ldap_get_entries($ldapconn, $result);
                    $user_dn = $result[0]['dn'];
                    $user_email = $result[0]['mail'][0];
                    $user_lastname = $result[0]['sn'][0];
                    $user_name = isset($result[0]['givenname'][0]) === true ? $result[0]['givenname'][0] : '';
                    $user_found = true;

                    // Should we restrain the search in specified user groups
                    $GroupRestrictionEnabled = false;
                    if (isset($SETTINGS['ldap_usergroup']) === true && empty($SETTINGS['ldap_usergroup']) === false) {
                        // New way to check User's group membership
                        $filter_group = 'memberUid='.$username;
                        $result_group = ldap_search(
                            $ldapconn,
                            $SETTINGS['ldap_search_base'],
                            $filter_group,
                            array('dn', 'samaccountname')
                        );

                        if ($result_group) {
                            $entries = ldap_get_entries($ldapconn, $result_group);

                            if ($entries['count'] > 0) {
                                // Now check if group fits
                                for ($i = 0; $i < $entries['count']; ++$i) {
                                    $parsr = ldap_explode_dn($entries[$i]['dn'], 0);
                                    if (str_replace(array('CN=', 'cn='), '', $parsr[0]) === $SETTINGS['ldap_usergroup']) {
                                        $GroupRestrictionEnabled = true;
                                        break;
                                    }
                                }
                            }
                        }
                    }

                    // Is user in the LDAP?
                    if ($GroupRestrictionEnabled === true
                        || (
                            $GroupRestrictionEnabled === false
                            && (
                                isset($SETTINGS['ldap_usergroup']) === false
                                || (isset($SETTINGS['ldap_usergroup']) === true && empty($SETTINGS['ldap_usergroup']) === true)
                            )
                        )
                    ) {
                        // Try to auth inside LDAP
                        $ldapbind = ldap_bind($ldapconn, $user_dn, $password);
                        if ($ldapbind === true) {
                            $ldapConnection = true;
                        } else {
                            $ldapConnection = false;
                        }
                    }
                } else {
                    $ldapConnection = false;
                }
            } else {
                $ldapConnection = false;
            }
        } else {
            $ldapConnection = false;
        }
    } else {
        $adldap = new SplClassLoader('adLDAP', '../includes/libraries/LDAP');
        $adldap->register();

        // Posix style LDAP handles user searches a bit differently
        if ($SETTINGS['ldap_type'] === 'posix') {
            $ldap_suffix = ','.$SETTINGS['ldap_suffix'].','.$SETTINGS['ldap_domain_dn'];
        } else {
            // case where $SETTINGS['ldap_type'] equals 'windows'
            //Multiple Domain Names
            $ldap_suffix = $SETTINGS['ldap_suffix'];
        }

        // Ensure no double commas exist in ldap_suffix
        $ldap_suffix = str_replace(',,', ',', $ldap_suffix);

        // Create LDAP connection
        $adldap = new adLDAP\adLDAP(
            array(
                'base_dn' => $SETTINGS['ldap_domain_dn'],
                'account_suffix' => $ldap_suffix,
                'domain_controllers' => explode(',', $SETTINGS['ldap_domain_controler']),
                'ad_port' => $SETTINGS['ldap_port'],
                'use_ssl' => $SETTINGS['ldap_ssl'],
                'use_tls' => $SETTINGS['ldap_tls'],
            )
        );

        // OpenLDAP expects an attribute=value pair
        if ($SETTINGS['ldap_type'] === 'posix') {
            $auth_username = $SETTINGS['ldap_user_attribute'].'='.$username;
        } else {
            $auth_username = $username;
        }

        // Authenticate the user
        if ($adldap->authenticate($auth_username, html_entity_decode($password))) {
            // Get user info
            $result = $adldap->user()->info($auth_username, array('mail', 'givenname', 'sn'));
            $user_email = $result[0]['mail'][0];
            $user_lastname = $result[0]['sn'][0];
            $user_name = $result[0]['givenname'][0];
            $user_found = true;

            // Is user in allowed group
            if (isset($SETTINGS['ldap_allowed_usergroup']) === true
                && empty($SETTINGS['ldap_allowed_usergroup']) === false
            ) {
                if ($adldap->user()->inGroup($auth_username, $SETTINGS['ldap_allowed_usergroup']) === true) {
                    $ldapConnection = true;
                } else {
                    $ldapConnection = false;
                }
            } else {
                $ldapConnection = true;
            }
        } else {
            $ldapConnection = false;
        }
    }

    return json_encode(
        array(
            'lastname' => $user_lastname,
            'name' => $user_name,
            'email' => $user_email,
            'auth_success' => $ldapConnection,
            'user_found' => $user_found,
        )
    );
}

//--------------------------------

/**
 * Perform a Query.
 *
 * @param array  $SETTINGS Teamapss settings
 * @param string $fields   Fields to use
 * @param string $table    Table to use
 *
 * @return array
 */
function performDBQuery($SETTINGS, $fields, $table)
{
    // include librairies & connect to DB
    include_once $SETTINGS['cpassman_dir'].'/includes/config/settings.php';
    include_once $SETTINGS['cpassman_dir'].'/includes/libraries/Database/Meekrodb/db.class.php';
    $link = mysqli_connect(DB_HOST, DB_USER, defuseReturnDecrypted(DB_PASSWD, $SETTINGS), DB_NAME, DB_PORT);
    $link->set_charset(DB_ENCODING);

    // Insert log in DB
    return DB::query(
        'SELECT '.$fields.'
        FROM '.prefixTable($table)
    );
}
