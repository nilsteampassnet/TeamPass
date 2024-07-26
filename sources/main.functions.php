<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * 
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * 
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * 
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      main.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Connection;
use ForceUTF8\Encoding;
use Elegant\Sanitizer\Sanitizer;
use voku\helper\AntiXSS;
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\RandomGenerator\Php7RandomGenerator;
use TeampassClasses\SessionManager\SessionManager;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TeampassClasses\Language\Language;
use TeampassClasses\NestedTree\NestedTree;
use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\KeyProtectedByPassword;
use Defuse\Crypto\File as CryptoFile;
use Defuse\Crypto\Exception as CryptoException;
use Elegant\Sanitizer\Filters\Uppercase;
use PHPMailer\PHPMailer\PHPMailer;
use TeampassClasses\PasswordManager\PasswordManager;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use TeampassClasses\Encryption\Encryption;
use TeampassClasses\ConfigManager\ConfigManager;

header('Content-type: text/html; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

loadClasses('DB');
$session = SessionManager::getSession();

// Load config if $SETTINGS not defined
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();
/*if (isset($SETTINGS['cpassman_dir']) === false || empty($SETTINGS['cpassman_dir']) === true) {
    include_once __DIR__ . '/../includes/config/tp.config.php';
}
*/

/**
 * genHash().
 *
 * Generate a hash for user login
 *
 * @param string $password What password
 * @param string $cost     What cost
 *
 * @return string|void
 */
function bCrypt(
    string $password,
    string $cost
): ?string
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

/**
 * Checks if a string is hex encoded
 *
 * @param string $str
 * @return boolean
 */
function isHex(string $str): bool
{
    if (str_starts_with(strtolower($str), '0x')) {
        $str = substr($str, 2);
    }

    return ctype_xdigit($str);
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
function cryption(string $message, string $ascii_key, string $type, ?array $SETTINGS = []): array
{
    $ascii_key = empty($ascii_key) === true ? file_get_contents(SECUREPATH.'/'.SECUREFILE) : $ascii_key;
    $err = false;
    
    // convert KEY
    $key = Key::loadFromAsciiSafeString($ascii_key);
    try {
        if ($type === 'encrypt') {
            $text = Crypto::encrypt($message, $key);
        } elseif ($type === 'decrypt') {
            $text = Crypto::decrypt($message, $key);
        }
    } catch (CryptoException\WrongKeyOrModifiedCiphertextException $ex) {
        $err = 'an attack! either the wrong key was loaded, or the ciphertext has changed since it was created either corrupted in the database or intentionally modified by someone trying to carry out an attack.';
    } catch (CryptoException\BadFormatException $ex) {
        $err = $ex;
    } catch (CryptoException\EnvironmentIsBrokenException $ex) {
        $err = $ex;
    } catch (CryptoException\CryptoException $ex) {
        $err = $ex;
    } catch (CryptoException\IOException $ex) {
        $err = $ex;
    }

    return [
        'string' => $text ?? '',
        'error' => $err,
    ];
}

/**
 * Generating a defuse key.
 *
 * @return string
 */
function defuse_generate_key()
{
    $key = Key::createNewRandomKey();
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
function defuse_generate_personal_key(string $psk): string
{
    $protected_key = KeyProtectedByPassword::createRandomPasswordProtectedKey($psk);
    return $protected_key->saveToAsciiSafeString(); // save this in user table
}

/**
 * Validate persoanl key with defuse.
 *
 * @param string $psk                   the user's psk
 * @param string $protected_key_encoded special key
 *
 * @return string
 */
function defuse_validate_personal_key(string $psk, string $protected_key_encoded): string
{
    try {
        $protected_key_encoded = KeyProtectedByPassword::loadFromAsciiSafeString($protected_key_encoded);
        $user_key = $protected_key_encoded->unlockKey($psk);
        $user_key_encoded = $user_key->saveToAsciiSafeString();
    } catch (CryptoException\EnvironmentIsBrokenException $ex) {
        return 'Error - Major issue as the encryption is broken.';
    } catch (CryptoException\WrongKeyOrModifiedCiphertextException $ex) {
        return 'Error - The saltkey is not the correct one.';
    }

    return $user_key_encoded;
    // store it in session once user has entered his psk
}

/**
 * Decrypt a defuse string if encrypted.
 *
 * @param string $value Encrypted string
 *
 * @return string Decrypted string
 */
function defuseReturnDecrypted(string $value, $SETTINGS): string
{
    if (substr($value, 0, 3) === 'def') {
        $value = cryption($value, '', 'decrypt', $SETTINGS)['string'];
    }

    return $value;
}

/**
 * Trims a string depending on a specific string.
 *
 * @param string|array $chaine  what to trim
 * @param string       $element trim on what
 *
 * @return string
 */
function trimElement($chaine, string $element): string
{
    if (! empty($chaine)) {
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
function cleanString(string $string, bool $special = false): string
{
    // Create temporary table for special characters escape
    $tabSpecialChar = [];
    for ($i = 0; $i <= 31; ++$i) {
        $tabSpecialChar[] = chr($i);
    }
    array_push($tabSpecialChar, '<br />');
    if ((int) $special === 1) {
        $tabSpecialChar = array_merge($tabSpecialChar, ['</li>', '<ul>', '<ol>']);
    }

    return str_replace($tabSpecialChar, "\n", $string);
}

/**
 * Erro manager for DB.
 *
 * @param array $params output from query
 *
 * @return void
 */
function db_error_handler(array $params): void
{
    echo 'Error: ' . $params['error'] . "<br>\n";
    echo 'Query: ' . $params['query'] . "<br>\n";
    throw new Exception('Error - Query', 1);
}

/**
 * Identify user's rights
 *
 * @param string|array $groupesVisiblesUser  [description]
 * @param string|array $groupesInterditsUser [description]
 * @param string       $isAdmin              [description]
 * @param string       $idFonctions          [description]
 *
 * @return bool
 */
function identifyUserRights(
    $groupesVisiblesUser,
    $groupesInterditsUser,
    $isAdmin,
    $idFonctions,
    $SETTINGS
) {
    $session = SessionManager::getSession();
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');

    // Check if user is ADMINISTRATOR    
    (int) $isAdmin === 1 ?
        identAdmin(
            $idFonctions,
            $SETTINGS, /** @scrutinizer ignore-type */
            $tree
        )
        :
        identUser(
            $groupesVisiblesUser,
            $groupesInterditsUser,
            $idFonctions,
            $SETTINGS, /** @scrutinizer ignore-type */
            $tree
        );

    // update user's timestamp
    DB::update(
        prefixTable('users'),
        [
            'timestamp' => time(),
        ],
        'id=%i',
        $session->get('user-id')
    );

    return true;
}

/**
 * Identify administrator.
 *
 * @param string $idFonctions Roles of user
 * @param array  $SETTINGS    Teampass settings
 * @param object $tree        Tree of folders
 *
 * @return bool
 */
function identAdmin($idFonctions, $SETTINGS, $tree)
{
    
    $session = SessionManager::getSession();
    $groupesVisibles = [];
    $session->set('user-personal_folders', []);
    $session->set('user-accessible_folders', []);
    $session->set('user-no_access_folders', []);
    $session->set('user-personal_visible_folders', []);
    $session->set('user-read_only_folders', []);
    $session->set('system-list_restricted_folders_for_items', []);
    $session->set('system-list_folders_editable_by_role', []);
    $session->set('user-list_folders_limited', []);
    $session->set('user-forbiden_personal_folders', []);
    $globalsUserId = $session->get('user-id');
    $globalsVisibleFolders = $session->get('user-accessible_folders');
    $globalsPersonalVisibleFolders = $session->get('user-personal_visible_folders');
    // Get list of Folders
    $rows = DB::query('SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE personal_folder = %i', 0);
    foreach ($rows as $record) {
        array_push($groupesVisibles, $record['id']);
    }
    $session->set('user-accessible_folders', $groupesVisibles);
    $session->set('user-all_non_personal_folders', $groupesVisibles);
    // Exclude all PF
    $where = new WhereClause('and');
    // create a WHERE statement of pieces joined by ANDs
    $where->add('personal_folder=%i', 1);
    if (
        isset($SETTINGS['enable_pf_feature']) === true
        && (int) $SETTINGS['enable_pf_feature'] === 1
    ) {
        $where->add('title=%s', $globalsUserId);
        $where->negateLast();
    }
    // Get ID of personal folder
    $persfld = DB::queryfirstrow(
        'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE title = %s',
        $globalsUserId
    );
    if (empty($persfld['id']) === false) {
        if (in_array($persfld['id'], $globalsVisibleFolders) === false) {
            array_push($globalsVisibleFolders, $persfld['id']);
            array_push($globalsPersonalVisibleFolders, $persfld['id']);
            // get all descendants
            $tree->rebuild();
            $tst = $tree->getDescendants($persfld['id']);
            foreach ($tst as $t) {
                array_push($globalsVisibleFolders, $t->id);
                array_push($globalsPersonalVisibleFolders, $t->id);
            }
        }
    }

    // get complete list of ROLES
    $tmp = explode(';', $idFonctions);
    $rows = DB::query(
        'SELECT * FROM ' . prefixTable('roles_title') . '
        ORDER BY title ASC'
    );
    foreach ($rows as $record) {
        if (! empty($record['id']) && ! in_array($record['id'], $tmp)) {
            array_push($tmp, $record['id']);
        }
    }
    $session->set('user-roles', implode(';', $tmp));
    $session->set('user-admin', 1);
    // Check if admin has created Folders and Roles
    DB::query('SELECT * FROM ' . prefixTable('nested_tree') . '');
    $session->set('user-nb_folders', DB::count());
    DB::query('SELECT * FROM ' . prefixTable('roles_title'));
    $session->set('user-nb_roles', DB::count());

    return true;
}

/**
 * Permits to convert an element to array.
 *
 * @param string|array $element Any value to be returned as array
 *
 * @return array
 */
function convertToArray($element): array
{
    if (is_string($element) === true) {
        if (empty($element) === true) {
            return [];
        }
        return explode(
            ';',
            trimElement($element, ';')
        );
    }
    return $element;
}

/**
 * Defines the rights the user has.
 *
 * @param string|array $allowedFolders  Allowed folders
 * @param string|array $noAccessFolders Not allowed folders
 * @param string|array $userRoles       Roles of user
 * @param array        $SETTINGS        Teampass settings
 * @param object       $tree            Tree of folders
 * 
 * @return bool
 */
function identUser(
    $allowedFolders,
    $noAccessFolders,
    $userRoles,
    array $SETTINGS,
    object $tree
) {
    
    $session = SessionManager::getSession();
    // Init
    $session->set('user-accessible_folders', []);
    $session->set('user-personal_folders', []);
    $session->set('user-no_access_folders', []);
    $session->set('user-personal_visible_folders', []);
    $session->set('user-read_only_folders', []);
    $session->set('user-user-roles', $userRoles);
    $session->set('user-admin', 0);
    // init
    $personalFolders = [];
    $readOnlyFolders = [];
    $noAccessPersonalFolders = [];
    $restrictedFoldersForItems = [];
    $foldersLimited = [];
    $foldersLimitedFull = [];
    $allowedFoldersByRoles = [];
    $globalsUserId = $session->get('user-id');
    $globalsPersonalFolders = $session->get('user-personal_folder_enabled');
    // Ensure consistency in array format
    $noAccessFolders = convertToArray($noAccessFolders);
    $userRoles = convertToArray($userRoles);
    $allowedFolders = convertToArray($allowedFolders);
    
    // Get list of folders depending on Roles
    $arrays = identUserGetFoldersFromRoles(
        $userRoles,
        $allowedFoldersByRoles,
        $readOnlyFolders,
        $allowedFolders
    );
    $allowedFoldersByRoles = $arrays['allowedFoldersByRoles'];
    $readOnlyFolders = $arrays['readOnlyFolders'];

    // Does this user is allowed to see other items
    $inc = 0;
    $rows = DB::query(
        'SELECT id, id_tree FROM ' . prefixTable('items') . '
            WHERE restricted_to LIKE %ss AND inactif = %s'.
            (count($allowedFolders) > 0 ? ' AND id_tree NOT IN ('.implode(',', $allowedFolders).')' : ''),
        $globalsUserId,
        '0'
    );
    foreach ($rows as $record) {
        // Exclude restriction on item if folder is fully accessible
        //if (in_array($record['id_tree'], $allowedFolders) === false) {
            $restrictedFoldersForItems[$record['id_tree']][$inc] = $record['id'];
            ++$inc;
        //}
    }

    // Check for the users roles if some specific rights exist on items
    $rows = DB::query(
        'SELECT i.id_tree, r.item_id
        FROM ' . prefixTable('items') . ' as i
        INNER JOIN ' . prefixTable('restriction_to_roles') . ' as r ON (r.item_id=i.id)
        WHERE i.id_tree <> "" '.
        (count($userRoles) > 0 ? 'AND r.role_id IN %li ' : '').
        'ORDER BY i.id_tree ASC',
        $userRoles
    );
    $inc = 0;
    foreach ($rows as $record) {
        //if (isset($record['id_tree'])) {
            $foldersLimited[$record['id_tree']][$inc] = $record['item_id'];
            array_push($foldersLimitedFull, $record['id_tree']);
            ++$inc;
        //}
    }

    // Get list of Personal Folders
    $arrays = identUserGetPFList(
        $globalsPersonalFolders,
        $allowedFolders,
        $globalsUserId,
        $personalFolders,
        $noAccessPersonalFolders,
        $foldersLimitedFull,
        $allowedFoldersByRoles,
        array_keys($restrictedFoldersForItems),
        $readOnlyFolders,
        $noAccessFolders,
        isset($SETTINGS['enable_pf_feature']) === true ? $SETTINGS['enable_pf_feature'] : 0,
        $tree
    );
    $allowedFolders = $arrays['allowedFolders'];
    $personalFolders = $arrays['personalFolders'];
    $noAccessPersonalFolders = $arrays['noAccessPersonalFolders'];

    // Return data
    $session->set('user-all_non_personal_folders', $allowedFolders);
    $session->set('user-accessible_folders', array_unique(array_merge($allowedFolders, $personalFolders), SORT_NUMERIC));
    $session->set('user-read_only_folders', $readOnlyFolders);
    $session->set('user-no_access_folders', $noAccessFolders);
    $session->set('user-personal_folders', $personalFolders);
    $session->set('user-list_folders_limited', $foldersLimited);
    $session->set('system-list_folders_editable_by_role', $allowedFoldersByRoles, 'SESSION');
    $session->set('system-list_restricted_folders_for_items', $restrictedFoldersForItems);
    $session->set('user-forbiden_personal_folders', $noAccessPersonalFolders);
    $session->set(
        'all_folders_including_no_access',
        array_unique(array_merge(
            $allowedFolders,
            $personalFolders,
            $noAccessFolders,
            $readOnlyFolders
        ), SORT_NUMERIC)
    );
    // Folders and Roles numbers
    DB::queryfirstrow('SELECT id FROM ' . prefixTable('nested_tree') . '');
    $session->set('user-nb_folders', DB::count());
    DB::queryfirstrow('SELECT id FROM ' . prefixTable('roles_title'));
    $session->set('user-nb_roles', DB::count());
    // check if change proposals on User's items
    if (isset($SETTINGS['enable_suggestion']) === true && (int) $SETTINGS['enable_suggestion'] === 1) {
        $countNewItems = DB::query(
            'SELECT COUNT(*)
            FROM ' . prefixTable('items_change') . ' AS c
            LEFT JOIN ' . prefixTable('log_items') . ' AS i ON (c.item_id = i.id_item)
            WHERE i.action = %s AND i.id_user = %i',
            'at_creation',
            $globalsUserId
        );
        $session->set('user-nb_item_change_proposals', $countNewItems);
    } else {
        $session->set('user-nb_item_change_proposals', 0);
    }

    return true;
}

/**
 * Get list of folders depending on Roles
 * 
 * @param array $userRoles
 * @param array $allowedFoldersByRoles
 * @param array $readOnlyFolders
 * @param array $allowedFolders
 * 
 * @return array
 */
function identUserGetFoldersFromRoles($userRoles, $allowedFoldersByRoles, $readOnlyFolders, $allowedFolders) : array
{
    $rows = DB::query(
        'SELECT *
        FROM ' . prefixTable('roles_values') . '
        WHERE type IN %ls'.(count($userRoles) > 0 ? ' AND role_id IN %li' : ''),
        ['W', 'ND', 'NE', 'NDNE', 'R'],
        $userRoles,
    );
    foreach ($rows as $record) {
        if ($record['type'] === 'R') {
            array_push($readOnlyFolders, $record['folder_id']);
        } elseif (in_array($record['folder_id'], $allowedFolders) === false) {
            array_push($allowedFoldersByRoles, $record['folder_id']);
        }
    }
    $allowedFoldersByRoles = array_unique($allowedFoldersByRoles);
    $readOnlyFolders = array_unique($readOnlyFolders);
    
    // Clean arrays
    foreach ($allowedFoldersByRoles as $value) {
        $key = array_search($value, $readOnlyFolders);
        if ($key !== false) {
            unset($readOnlyFolders[$key]);
        }
    }
    return [
        'readOnlyFolders' => $readOnlyFolders,
        'allowedFoldersByRoles' => $allowedFoldersByRoles
    ];
}

/**
 * Get list of Personal Folders
 * 
 * @param int $globalsPersonalFolders
 * @param array $allowedFolders
 * @param int $globalsUserId
 * @param array $personalFolders
 * @param array $noAccessPersonalFolders
 * @param array $foldersLimitedFull
 * @param array $allowedFoldersByRoles
 * @param array $restrictedFoldersForItems
 * @param array $readOnlyFolders
 * @param array $noAccessFolders
 * @param int $enablePfFeature
 * @param object $tree
 * 
 * @return array
 */
function identUserGetPFList(
    $globalsPersonalFolders,
    $allowedFolders,
    $globalsUserId,
    $personalFolders,
    $noAccessPersonalFolders,
    $foldersLimitedFull,
    $allowedFoldersByRoles,
    $restrictedFoldersForItems,
    $readOnlyFolders,
    $noAccessFolders,
    $enablePfFeature,
    $tree
)
{
    if (
        (int) $enablePfFeature === 1
        && (int) $globalsPersonalFolders === 1
    ) {
        $persoFld = DB::queryfirstrow(
            'SELECT id
            FROM ' . prefixTable('nested_tree') . '
            WHERE title = %s AND personal_folder = %i'.
            (count($allowedFolders) > 0 ? ' AND id NOT IN ('.implode(',', $allowedFolders).')' : ''),
            $globalsUserId,
            1
        );
        if (empty($persoFld['id']) === false) {
            array_push($personalFolders, $persoFld['id']);
            array_push($allowedFolders, $persoFld['id']);
            // get all descendants
            $ids = $tree->getDescendants($persoFld['id'], false, false, true);
            foreach ($ids as $id) {
                //array_push($allowedFolders, $id);
                array_push($personalFolders, $id);
            }
        }
    }
    
    // Exclude all other PF
    $where = new WhereClause('and');
    $where->add('personal_folder=%i', 1);
    if (count($personalFolders) > 0) {
        $where->add('id NOT IN ('.implode(',', $personalFolders).')');
    }
    if (
        (int) $enablePfFeature === 1
        && (int) $globalsPersonalFolders === 1
    ) {
        $where->add('title=%s', $globalsUserId);
        $where->negateLast();
    }
    $persoFlds = DB::query(
        'SELECT id
        FROM ' . prefixTable('nested_tree') . '
        WHERE %l',
        $where
    );
    foreach ($persoFlds as $persoFldId) {
        array_push($noAccessPersonalFolders, $persoFldId['id']);
    }

    // All folders visibles
    $allowedFolders = array_unique(array_merge(
        $allowedFolders,
        $foldersLimitedFull,
        $allowedFoldersByRoles,
        $restrictedFoldersForItems,
        $readOnlyFolders
    ), SORT_NUMERIC);
    // Exclude from allowed folders all the specific user forbidden folders
    if (count($noAccessFolders) > 0) {
        $allowedFolders = array_diff($allowedFolders, $noAccessFolders);
    }

    return [
        'allowedFolders' => array_diff(array_diff($allowedFolders, $noAccessPersonalFolders), $personalFolders),
        'personalFolders' => $personalFolders,
        'noAccessPersonalFolders' => $noAccessPersonalFolders
    ];
}


/**
 * Update the CACHE table.
 *
 * @param string $action   What to do
 * @param array  $SETTINGS Teampass settings
 * @param int    $ident    Ident format
 * 
 * @return void
 */
function updateCacheTable(string $action, ?int $ident = null): void
{
    if ($action === 'reload') {
        // Rebuild full cache table
        cacheTableRefresh();
    } elseif ($action === 'update_value' && is_null($ident) === false) {
        // UPDATE an item
        cacheTableUpdate($ident);
    } elseif ($action === 'add_value' && is_null($ident) === false) {
        // ADD an item
        cacheTableAdd($ident);
    } elseif ($action === 'delete_value' && is_null($ident) === false) {
        // DELETE an item
        DB::delete(prefixTable('cache'), 'id = %i', $ident);
    }
}

/**
 * Cache table - refresh.
 *
 * @return void
 */
function cacheTableRefresh(): void
{
    // Load class DB
    loadClasses('DB');

    //Load Tree
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    // truncate table
    DB::query('TRUNCATE TABLE ' . prefixTable('cache'));
    // reload date
    $rows = DB::query(
        'SELECT *
        FROM ' . prefixTable('items') . ' as i
        INNER JOIN ' . prefixTable('log_items') . ' as l ON (l.id_item = i.id)
        AND l.action = %s
        AND i.inactif = %i',
        'at_creation',
        0
    );
    foreach ($rows as $record) {
        if (empty($record['id_tree']) === false) {
            // Get all TAGS
            $tags = '';
            $itemTags = DB::query(
                'SELECT tag
                FROM ' . prefixTable('tags') . '
                WHERE item_id = %i AND tag != ""',
                $record['id']
            );
            foreach ($itemTags as $itemTag) {
                $tags .= $itemTag['tag'] . ' ';
            }

            // Get renewal period
            $resNT = DB::queryfirstrow(
                'SELECT renewal_period
                FROM ' . prefixTable('nested_tree') . '
                WHERE id = %i',
                $record['id_tree']
            );
            // form id_tree to full foldername
            $folder = [];
            $arbo = $tree->getPath($record['id_tree'], true);
            foreach ($arbo as $elem) {
                // Check if title is the ID of a user
                if (is_numeric($elem->title) === true) {
                    // Is this a User id?
                    $user = DB::queryfirstrow(
                        'SELECT id, login
                        FROM ' . prefixTable('users') . '
                        WHERE id = %i',
                        $elem->title
                    );
                    if (count($user) > 0) {
                        $elem->title = $user['login'];
                    }
                }
                // Build path
                array_push($folder, stripslashes($elem->title));
            }
            // store data
            DB::insert(
                prefixTable('cache'),
                [
                    'id' => $record['id'],
                    'label' => $record['label'],
                    'description' => $record['description'] ?? '',
                    'url' => isset($record['url']) && ! empty($record['url']) ? $record['url'] : '0',
                    'tags' => $tags,
                    'id_tree' => $record['id_tree'],
                    'perso' => $record['perso'],
                    'restricted_to' => isset($record['restricted_to']) && ! empty($record['restricted_to']) ? $record['restricted_to'] : '0',
                    'login' => $record['login'] ?? '',
                    'folder' => implode(' > ', $folder),
                    'author' => $record['id_user'],
                    'renewal_period' => $resNT['renewal_period'] ?? '0',
                    'timestamp' => $record['date'],
                ]
            );
        }
    }
}

/**
 * Cache table - update existing value.
 *
 * @param int    $ident    Ident format
 * 
 * @return void
 */
function cacheTableUpdate(?int $ident = null): void
{
    $session = SessionManager::getSession();
    loadClasses('DB');

    //Load Tree
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    // get new value from db
    $data = DB::queryfirstrow(
        'SELECT label, description, id_tree, perso, restricted_to, login, url
        FROM ' . prefixTable('items') . '
        WHERE id=%i',
        $ident
    );
    // Get all TAGS
    $tags = '';
    $itemTags = DB::query(
        'SELECT tag
            FROM ' . prefixTable('tags') . '
            WHERE item_id = %i AND tag != ""',
        $ident
    );
    foreach ($itemTags as $itemTag) {
        $tags .= $itemTag['tag'] . ' ';
    }
    // form id_tree to full foldername
    $folder = [];
    $arbo = $tree->getPath($data['id_tree'], true);
    foreach ($arbo as $elem) {
        // Check if title is the ID of a user
        if (is_numeric($elem->title) === true) {
            // Is this a User id?
            $user = DB::queryfirstrow(
                'SELECT id, login
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $elem->title
            );
            if (count($user) > 0) {
                $elem->title = $user['login'];
            }
        }
        // Build path
        array_push($folder, stripslashes($elem->title));
    }
    // finaly update
    DB::update(
        prefixTable('cache'),
        [
            'label' => $data['label'],
            'description' => $data['description'],
            'tags' => $tags,
            'url' => isset($data['url']) && ! empty($data['url']) ? $data['url'] : '0',
            'id_tree' => $data['id_tree'],
            'perso' => $data['perso'],
            'restricted_to' => isset($data['restricted_to']) && ! empty($data['restricted_to']) ? $data['restricted_to'] : '0',
            'login' => $data['login'] ?? '',
            'folder' => implode(' » ', $folder),
            'author' => $session->get('user-id'),
        ],
        'id = %i',
        $ident
    );
}

/**
 * Cache table - add new value.
 *
 * @param int    $ident    Ident format
 * 
 * @return void
 */
function cacheTableAdd(?int $ident = null): void
{
    $session = SessionManager::getSession();
    $globalsUserId = $session->get('user-id');

    // Load class DB
    loadClasses('DB');

    //Load Tree
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    // get new value from db
    $data = DB::queryFirstRow(
        'SELECT i.label, i.description, i.id_tree as id_tree, i.perso, i.restricted_to, i.id, i.login, i.url, l.date
        FROM ' . prefixTable('items') . ' as i
        INNER JOIN ' . prefixTable('log_items') . ' as l ON (l.id_item = i.id)
        WHERE i.id = %i
        AND l.action = %s',
        $ident,
        'at_creation'
    );
    // Get all TAGS
    $tags = '';
    $itemTags = DB::query(
        'SELECT tag
            FROM ' . prefixTable('tags') . '
            WHERE item_id = %i AND tag != ""',
        $ident
    );
    foreach ($itemTags as $itemTag) {
        $tags .= $itemTag['tag'] . ' ';
    }
    // form id_tree to full foldername
    $folder = [];
    $arbo = $tree->getPath($data['id_tree'], true);
    foreach ($arbo as $elem) {
        // Check if title is the ID of a user
        if (is_numeric($elem->title) === true) {
            // Is this a User id?
            $user = DB::queryfirstrow(
                'SELECT id, login
                FROM ' . prefixTable('users') . '
                WHERE id = %i',
                $elem->title
            );
            if (count($user) > 0) {
                $elem->title = $user['login'];
            }
        }
        // Build path
        array_push($folder, stripslashes($elem->title));
    }
    // finaly update
    DB::insert(
        prefixTable('cache'),
        [
            'id' => $data['id'],
            'label' => $data['label'],
            'description' => $data['description'],
            'tags' => isset($tags) && empty($tags) === false ? $tags : 'None',
            'url' => isset($data['url']) && ! empty($data['url']) ? $data['url'] : '0',
            'id_tree' => $data['id_tree'],
            'perso' => isset($data['perso']) && empty($data['perso']) === false && $data['perso'] !== 'None' ? $data['perso'] : '0',
            'restricted_to' => isset($data['restricted_to']) && empty($data['restricted_to']) === false ? $data['restricted_to'] : '0',
            'login' => $data['login'] ?? '',
            'folder' => implode(' » ', $folder),
            'author' => $globalsUserId,
            'timestamp' => $data['date'],
        ]
    );
}

/**
 * Do statistics.
 *
 * @param array $SETTINGS Teampass settings
 *
 * @return array
 */
function getStatisticsData(array $SETTINGS): array
{
    DB::query(
        'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE personal_folder = %i',
        0
    );
    $counter_folders = DB::count();
    DB::query(
        'SELECT id FROM ' . prefixTable('nested_tree') . ' WHERE personal_folder = %i',
        1
    );
    $counter_folders_perso = DB::count();
    DB::query(
        'SELECT id FROM ' . prefixTable('items') . ' WHERE perso = %i',
        0
    );
    $counter_items = DB::count();
        DB::query(
        'SELECT id FROM ' . prefixTable('items') . ' WHERE perso = %i',
        1
    );
    $counter_items_perso = DB::count();
        DB::query(
        'SELECT id FROM ' . prefixTable('users') . ''
    );
    $counter_users = DB::count();
        DB::query(
        'SELECT id FROM ' . prefixTable('users') . ' WHERE admin = %i',
        1
    );
    $admins = DB::count();
    DB::query(
        'SELECT id FROM ' . prefixTable('users') . ' WHERE gestionnaire = %i',
        1
    );
    $managers = DB::count();
    DB::query(
        'SELECT id FROM ' . prefixTable('users') . ' WHERE read_only = %i',
        1
    );
    $readOnly = DB::count();
    // list the languages
    $usedLang = [];
    $tp_languages = DB::query(
        'SELECT name FROM ' . prefixTable('languages')
    );
    foreach ($tp_languages as $tp_language) {
        DB::query(
            'SELECT * FROM ' . prefixTable('users') . ' WHERE user_language = %s',
            $tp_language['name']
        );
        $usedLang[$tp_language['name']] = round((DB::count() * 100 / $counter_users), 0);
    }

    // get list of ips
    $usedIp = [];
    $tp_ips = DB::query(
        'SELECT user_ip FROM ' . prefixTable('users')
    );
    foreach ($tp_ips as $ip) {
        if (array_key_exists($ip['user_ip'], $usedIp)) {
            $usedIp[$ip['user_ip']] += $usedIp[$ip['user_ip']];
        } elseif (! empty($ip['user_ip']) && $ip['user_ip'] !== 'none') {
            $usedIp[$ip['user_ip']] = 1;
        }
    }

    return [
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
        'stat_teampassversion' => TP_VERSION,
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
    ];
}

/**
 * Permits to prepare the way to send the email
 * 
 * @param string $subject       email subject
 * @param string $body          email message
 * @param string $email         email
 * @param string $receiverName  Receiver name
 * @param array  $SETTINGS      settings
 *
 * @return void
 */
function prepareSendingEmail(
    $subject,
    $body,
    $email,
    $receiverName = ''
): void 
{
    DB::insert(
        prefixTable('background_tasks'),
        array(
            'created_at' => time(),
            'process_type' => 'send_email',
            'arguments' => json_encode([
                'subject' => $subject,
                'receivers' => $email,
                'body' => $body,
                'receiver_name' => $receiverName,
            ], JSON_HEX_QUOT | JSON_HEX_TAG),
        )
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
 * @param bool   $silent      no errors
 *
 * @return string some json info
 */
function sendEmail(
    $subject,
    $textMail,
    $email,
    $SETTINGS,
    $textMailAlt = null,
    $silent = true,
    $cron = false
) {
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    // CAse where email not defined
    if ($email === 'none' || empty($email) === true) {
        return json_encode(
            [
                'error' => true,
                'message' => $lang->get('forgot_my_pw_email_sent'),
            ]
        );
    }

    // Build and send email
    $email = buildEmail(
        $subject,
        $textMail,
        $email,
        $SETTINGS,
        $textMailAlt = null,
        $silent = true,
        $cron
    );

    if ($silent === false) {
        return json_encode(
            [
                'error' => false,
                'message' => $lang->get('forgot_my_pw_email_sent'),
            ]
        );
    }
    // Debug purpose
    if ((int) $SETTINGS['email_debug_level'] !== 0 && $cron === false) {
        return json_encode(
            [
                'error' => true,
                'message' => isset($email['ErrorInfo']) === true ? $email['ErrorInfo'] : '',
            ]
        );
    }
    return json_encode(
        [
            'error' => false,
            'message' => $lang->get('share_sent_ok'),
        ]
    );
}


function buildEmail(
    $subject,
    $textMail,
    $email,
    $SETTINGS,
    $textMailAlt = null,
    $silent = true,
    $cron = false
)
{
    // Load PHPMailer
    $mail = new PHPMailer(true);
    $languageDir = $SETTINGS['cpassman_dir'] . '/vendor/phpmailer/phpmailer/language/';

    try {
        // Set language and SMTPDebug
        $mail->setLanguage('en', $languageDir);
        $mail->SMTPDebug = ($cron || $silent) ? 0 : $SETTINGS['email_debug_level'];
        
        /*
        // Define custom Debug output function
        $mail->Debugoutput = function($str, $level) {
            // Path to your log file
            $logFilePath = '/var/log/phpmailer.log';
            file_put_contents($logFilePath, gmdate('Y-m-d H:i:s'). "\t$level\t$str\n", FILE_APPEND | LOCK_EX);
        };
        */

        // Configure SMTP
        $mail->isSMTP();
        $mail->Host = $SETTINGS['email_smtp_server'];
        $mail->SMTPAuth = (int) $SETTINGS['email_smtp_auth'] === 1;
        $mail->Username = $SETTINGS['email_auth_username'];
        $mail->Password = $SETTINGS['email_auth_pwd'];
        $mail->Port = (int) $SETTINGS['email_port'];
        $mail->SMTPSecure = $SETTINGS['email_security'] !== 'none' ? $SETTINGS['email_security'] : '';
        $mail->SMTPAutoTLS = $SETTINGS['email_security'] !== 'none';
        $mail->CharSet = 'utf-8';   //#4143
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];

        // Set From and FromName
        $mail->From = $SETTINGS['email_from'];
        $mail->FromName = $SETTINGS['email_from_name'];

        // Prepare recipients
        foreach (array_filter(explode(',', $email)) as $dest) {
            $mail->addAddress($dest);
        }
        
        // Prepare HTML and AltBody
        $text_html = emailBody($textMail);
        $mail->WordWrap = 80;
        $mail->isHtml(true);
        $mail->Subject = $subject;
        $mail->Body = $text_html;
        $mail->AltBody = is_null($textMailAlt) ? '' : $textMailAlt;
        
        // Send email
        $mail->send();
        $mail->smtpClose();
        
        return '';
    } catch (Exception $e) {
        error_log('Error sending email: ' . $e->getMessage());
        if (!$silent || (int) $SETTINGS['email_debug_level'] !== 0) {
            return json_encode([
                'error' => true,
                'errorInfo' => str_replace(["\n", "\t", "\r"], '', $mail->ErrorInfo),
            ]);
        }
        return '';
    }
}

/**
 * Returns the email body.
 *
 * @param string $textMail Text for the email
 */
function emailBody(string $textMail): string
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
        <br><div style="float:right;">' .
        $textMail .
        '<br><br></td></tr></table>
    </td></tr></table>
    <br></body></html>';
}

/**
 * Convert date to timestamp.
 *
 * @param string $date        The date
 * @param string $date_format Date format
 *
 * @return int
 */
function dateToStamp(string $date, string $date_format): int
{
    $date = date_parse_from_format($date_format, $date);
    if ((int) $date['warning_count'] === 0 && (int) $date['error_count'] === 0) {
        return mktime(
            empty($date['hour']) === false ? $date['hour'] : 23,
            empty($date['minute']) === false ? $date['minute'] : 59,
            empty($date['second']) === false ? $date['second'] : 59,
            $date['month'],
            $date['day'],
            $date['year']
        );
    }
    return 0;
}

/**
 * Is this a date.
 *
 * @param string $date Date
 *
 * @return bool
 */
function isDate(string $date): bool
{
    return strtotime($date) !== false;
}

/**
 * Check if isUTF8().
 *
 * @param string|array $string Is the string
 *
 * @return int is the string in UTF8 format
 */
function isUTF8($string): int
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
function utf8Converter(array $array): array
{
    array_walk_recursive(
        $array,
        static function (&$item): void {
            if (mb_detect_encoding((string) $item, 'utf-8', true) === false) {
                $item = mb_convert_encoding($item, 'ISO-8859-1', 'UTF-8');
            }
        }
    );
    return $array;
}

/**
 * Permits to prepare data to be exchanged.
 *
 * @param array|string $data Text
 * @param string       $type Parameter
 * @param string       $key  Optional key
 *
 * @return string|array
 */
function prepareExchangedData($data, string $type, ?string $key = null)
{
    $session = SessionManager::getSession();
    
    // Perform
    if ($type === 'encode' && is_array($data) === true) {
        // Now encode
        return Encryption::encrypt(
            json_encode(
                $data,
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
            ),
            $session->get('key')
        );
    }
    if ($type === 'decode' && is_array($data) === false) {
        // check if key exists
        return json_decode(
            (string) Encryption::decrypt(
                (string) $data,
                $session->get('key')
            ),
            true
        );
    }
    return '';
}


/**
 * Create a thumbnail.
 *
 * @param string  $src           Source
 * @param string  $dest          Destination
 * @param int $desired_width Size of width
 * 
 * @return void|string|bool
 */
function makeThumbnail(string $src, string $dest, int $desired_width)
{
    /* read the source image */
    if (is_file($src) === true && mime_content_type($src) === 'image/png') {
        $source_image = imagecreatefrompng($src);
        if ($source_image === false) {
            return "Error: Not a valid PNG file! It's type is ".mime_content_type($src);
        }
    } else {
        return "Error: Not a valid PNG file! It's type is ".mime_content_type($src);
    }

    // Get height and width
    $width = imagesx($source_image);
    $height = imagesy($source_image);
    /* find the "desired height" of this thumbnail, relative to the desired width  */
    $desired_height = (int) floor($height * $desired_width / $width);
    /* create a new, "virtual" image */
    $virtual_image = imagecreatetruecolor($desired_width, $desired_height);
    if ($virtual_image === false) {
        return false;
    }
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
function prefixTable(string $table): string
{
    $safeTable = htmlspecialchars(DB_PREFIX . $table);
    if (empty($safeTable) === false) {
        // sanitize string
        return $safeTable;
    }
    // stop error no table
    return 'table_not_exists';
}

/**
 * GenerateCryptKey
 *
 * @param int     $size      Length
 * @param bool $secure Secure
 * @param bool $numerals Numerics
 * @param bool $uppercase Uppercase letters
 * @param bool $symbols Symbols
 * @param bool $lowercase Lowercase
 * @param array   $SETTINGS  SETTINGS
 * 
 * @return string
 */
function GenerateCryptKey(
    int $size = 20,
    bool $secure = false,
    bool $numerals = false,
    bool $uppercase = false,
    bool $symbols = false,
    bool $lowercase = false,
    array $SETTINGS = []
): string {
    $generator = new ComputerPasswordGenerator();
    $generator->setRandomGenerator(new Php7RandomGenerator());
    
    // Manage size
    $generator->setLength((int) $size);
    if ($secure === true) {
        $generator->setSymbols(true);
        $generator->setLowercase(true);
        $generator->setUppercase(true);
        $generator->setNumbers(true);
    } else {
        $generator->setLowercase($lowercase);
        $generator->setUppercase($uppercase);
        $generator->setNumbers($numerals);
        $generator->setSymbols($symbols);
    }

    return $generator->generatePasswords()[0];
}

/**
 * Send sysLOG message
 *
 * @param string    $message
 * @param string    $host
 * @param int       $port
 * @param string    $component
 * 
 * @return void
*/
function send_syslog($message, $host, $port, $component = 'teampass'): void
{
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    $syslog_message = '<123>' . date('M d H:i:s ') . $component . ': ' . $message;
    socket_sendto($sock, (string) $syslog_message, strlen($syslog_message), 0, (string) $host, (int) $port);
    socket_close($sock);
}

/**
 * Permits to log events into DB
 *
 * @param array  $SETTINGS Teampass settings
 * @param string $type     Type
 * @param string $label    Label
 * @param string $who      Who
 * @param string $login    Login
 * @param string|int $field_1  Field
 * 
 * @return void
 */
function logEvents(
    array $SETTINGS, 
    string $type, 
    string $label, 
    string $who, 
    ?string $login = null, 
    $field_1 = null
): void
{
    if (empty($who)) {
        $who = getClientIpServer();
    }

    // Load class DB
    loadClasses('DB');

    DB::insert(
        prefixTable('log_system'),
        [
            'type' => $type,
            'date' => time(),
            'label' => $label,
            'qui' => $who,
            'field_1' => $field_1 === null ? '' : $field_1,
        ]
    );
    // If SYSLOG
    if (isset($SETTINGS['syslog_enable']) === true && (int) $SETTINGS['syslog_enable'] === 1) {
        if ($type === 'user_mngt') {
            send_syslog(
                'action=' . str_replace('at_', '', $label) . ' attribute=user user=' . $who . ' userid="' . $login . '" change="' . $field_1 . '" ',
                $SETTINGS['syslog_host'],
                $SETTINGS['syslog_port'],
                'teampass'
            );
        } else {
            send_syslog(
                'action=' . $type . ' attribute=' . $label . ' user=' . $who . ' userid="' . $login . '" ',
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
 * @param string $time Encryption Time
 * @param string $old_value       Old value
 * 
 * @return void
 */
function logItems(
    array $SETTINGS,
    int $item_id,
    string $item_label,
    int $id_user,
    string $action,
    ?string $login = null,
    ?string $raison = null,
    ?string $encryption_type = null,
    ?string $time = null,
    ?string $old_value = null
): void {
    // Load class DB
    loadClasses('DB');

    // Insert log in DB
    DB::insert(
        prefixTable('log_items'),
        [
            'id_item' => $item_id,
            'date' => is_null($time) === true ? time() : $time,
            'id_user' => $id_user,
            'action' => $action,
            'raison' => $raison,
            'old_value' => $old_value,
            'encryption_type' => is_null($encryption_type) === true ? TP_ENCRYPTION_NAME : $encryption_type,
        ]
    );
    // Timestamp the last change
    if ($action === 'at_creation' || $action === 'at_modifiation' || $action === 'at_delete' || $action === 'at_import') {
        DB::update(
            prefixTable('misc'),
            [
                'valeur' => time(),
                'updated_at' => time(),
            ],
            'type = %s AND intitule = %s',
            'timestamp',
            'last_item_change'
        );
    }

    // SYSLOG
    if (isset($SETTINGS['syslog_enable']) === true && $SETTINGS['syslog_enable'] === '1') {
        // Extract reason
        $attribute = is_null($raison) === true ? Array('') : explode(' : ', $raison);
        // Get item info if not known
        if (empty($item_label) === true) {
            $dataItem = DB::queryfirstrow(
                'SELECT id, id_tree, label
                FROM ' . prefixTable('items') . '
                WHERE id = %i',
                $item_id
            );
            $item_label = $dataItem['label'];
        }

        send_syslog(
            'action=' . str_replace('at_', '', $action) .
                ' attribute=' . str_replace('at_', '', $attribute[0]) .
                ' itemno=' . $item_id .
                ' user=' . (is_null($login) === true ? '' : addslashes((string) $login)) .
                ' itemname="' . addslashes($item_label) . '"',
            $SETTINGS['syslog_host'],
            $SETTINGS['syslog_port'],
            'teampass'
        );
    }

    // send notification if enabled
    //notifyOnChange($item_id, $action, $SETTINGS);
}

/**
 * Prepare notification email to subscribers.
 *
 * @param int    $item_id  Item id
 * @param string $label    Item label
 * @param array  $changes  List of changes
 * @param array  $SETTINGS Teampass settings
 * 
 * @return void
 */
function notifyChangesToSubscribers(int $item_id, string $label, array $changes, array $SETTINGS): void
{
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    $globalsUserId = $session->get('user-id');
    $globalsLastname = $session->get('user-lastname');
    $globalsName = $session->get('user-name');
    // send email to user that what to be notified
    $notification = DB::queryOneColumn(
        'email',
        'SELECT *
        FROM ' . prefixTable('notification') . ' AS n
        INNER JOIN ' . prefixTable('users') . ' AS u ON (n.user_id = u.id)
        WHERE n.item_id = %i AND n.user_id != %i',
        $item_id,
        $globalsUserId
    );
    if (DB::count() > 0) {
        // Prepare path
        $path = geItemReadablePath($item_id, '', $SETTINGS);
        // Get list of changes
        $htmlChanges = '<ul>';
        foreach ($changes as $change) {
            $htmlChanges .= '<li>' . $change . '</li>';
        }
        $htmlChanges .= '</ul>';
        // send email
        DB::insert(
            prefixTable('emails'),
            [
                'timestamp' => time(),
                'subject' => $lang->get('email_subject_item_updated'),
                'body' => str_replace(
                    ['#item_label#', '#folder_name#', '#item_id#', '#url#', '#name#', '#lastname#', '#changes#'],
                    [$label, $path, $item_id, $SETTINGS['cpassman_url'], $globalsName, $globalsLastname, $htmlChanges],
                    $lang->get('email_body_item_updated')
                ),
                'receivers' => implode(',', $notification),
                'status' => '',
            ]
        );
    }
}

/**
 * Returns the Item + path.
 *
 * @param int    $id_tree  Node id
 * @param string $label    Label
 * @param array  $SETTINGS TP settings
 * 
 * @return string
 */
function geItemReadablePath(int $id_tree, string $label, array $SETTINGS): string
{
    $tree = new NestedTree(prefixTable('nested_tree'), 'id', 'parent_id', 'title');
    $arbo = $tree->getPath($id_tree, true);
    $path = '';
    foreach ($arbo as $elem) {
        if (empty($path) === true) {
            $path = htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES) . ' ';
        } else {
            $path .= '&#8594; ' . htmlspecialchars(stripslashes(htmlspecialchars_decode($elem->title, ENT_QUOTES)), ENT_QUOTES);
        }
    }

    // Build text to show user
    if (empty($label) === false) {
        return empty($path) === true ? addslashes($label) : addslashes($label) . ' (' . $path . ')';
    }
    return empty($path) === true ? '' : $path;
}

/**
 * Get the client ip address.
 *
 * @return string IP address
 */
function getClientIpServer(): string
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
function noHTML(string $input, string $encoding = 'UTF-8'): string
{
    return htmlspecialchars($input, ENT_QUOTES | ENT_XHTML, $encoding, false);
}

/**
 * Permits to handle the Teampass config file
 * $action accepts "rebuild" and "update"
 *
 * @param string $action   Action to perform
 * @param array  $SETTINGS Teampass settings
 * @param string $field    Field to refresh
 * @param string $value    Value to set
 *
 * @return string|bool
 */
function handleConfigFile($action, $SETTINGS, $field = null, $value = null)
{
    $tp_config_file = $SETTINGS['cpassman_dir'] . '/includes/config/tp.config.php';

    // Load class DB
    loadClasses('DB');

    if (file_exists($tp_config_file) === false || $action === 'rebuild') {
        // perform a copy
        if (file_exists($tp_config_file)) {
            if (! copy($tp_config_file, $tp_config_file . '.' . date('Y_m_d_His', time()))) {
                return "ERROR: Could not copy file '" . $tp_config_file . "'";
            }
        }

        // regenerate
        $data = [];
        $data[0] = "<?php\n";
        $data[1] = "global \$SETTINGS;\n";
        $data[2] = "\$SETTINGS = array (\n";
        $rows = DB::query(
            'SELECT * FROM ' . prefixTable('misc') . ' WHERE type=%s',
            'admin'
        );
        foreach ($rows as $record) {
            array_push($data, "    '" . $record['intitule'] . "' => '" . htmlspecialchars_decode($record['valeur'], ENT_COMPAT) . "',\n");
        }
        array_push($data, ");\n");
        $data = array_unique($data);
    // ---
    } elseif ($action === 'update' && empty($field) === false) {
        $data = file($tp_config_file);
        $inc = 0;
        $bFound = false;
        foreach ($data as $line) {
            if (stristr($line, ');')) {
                break;
            }

            if (stristr($line, "'" . $field . "' => '")) {
                $data[$inc] = "    '" . $field . "' => '" . htmlspecialchars_decode($value ?? '', ENT_COMPAT) . "',\n";
                $bFound = true;
                break;
            }
            ++$inc;
        }
        if ($bFound === false) {
            $data[$inc] = "    '" . $field . "' => '" . htmlspecialchars_decode($value ?? '', ENT_COMPAT). "',\n);\n";
        }
    }

    // update file
    file_put_contents($tp_config_file, implode('', $data ?? []));
    return true;
}

/**
 * Permits to replace &#92; to permit correct display
 *
 * @param string $input Some text
 * 
 * @return string
 */
function handleBackslash(string $input): string
{
    return str_replace('&amp;#92;', '&#92;', $input);
}

/**
 * Permits to load settings
 * 
 * @return void
*/
function loadSettings(): void
{
    global $SETTINGS;
    /* LOAD CPASSMAN SETTINGS */
    if (! isset($SETTINGS['loaded']) || $SETTINGS['loaded'] !== 1) {
        $SETTINGS = [];
        $SETTINGS['duplicate_folder'] = 0;
        //by default, this is set to 0;
        $SETTINGS['duplicate_item'] = 0;
        //by default, this is set to 0;
        $SETTINGS['number_of_used_pw'] = 5;
        //by default, this value is set to 5;
        $settings = [];
        $rows = DB::query(
            'SELECT * FROM ' . prefixTable('misc') . ' WHERE type=%s_type OR type=%s_type2',
            [
                'type' => 'admin',
                'type2' => 'settings',
            ]
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

/**
 * check if folder has custom fields.
 * Ensure that target one also has same custom fields
 * 
 * @param int $source_id
 * @param int $target_id 
 * 
 * @return bool
*/
function checkCFconsistency(int $source_id, int $target_id): bool
{
    $source_cf = [];
    $rows = DB::QUERY(
        'SELECT id_category
            FROM ' . prefixTable('categories_folders') . '
            WHERE id_folder = %i',
        $source_id
    );
    foreach ($rows as $record) {
        array_push($source_cf, $record['id_category']);
    }

    $target_cf = [];
    $rows = DB::QUERY(
        'SELECT id_category
            FROM ' . prefixTable('categories_folders') . '
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
    string $type,
    string $source_file,
    string $target_file,
    array $SETTINGS,
    string $password = null
) {
    // Load AntiXSS
    $antiXss = new AntiXSS();
    // Protect against bad inputs
    if (is_array($source_file) === true || is_array($target_file) === true) {
        return 'error_cannot_be_array';
    }

    // Sanitize
    $source_file = $antiXss->xss_clean($source_file);
    $target_file = $antiXss->xss_clean($target_file);
    if (empty($password) === true || is_null($password) === true) {
        // get KEY to define password
        $ascii_key = file_get_contents(SECUREPATH.'/'.SECUREFILE);
        $password = Key::loadFromAsciiSafeString($ascii_key);
    }

    $err = '';
    if ($type === 'decrypt') {
        // Decrypt file
        $err = defuseFileDecrypt(
            $source_file,
            $target_file,
            $SETTINGS, /** @scrutinizer ignore-type */
            $password
        );
    } elseif ($type === 'encrypt') {
        // Encrypt file
        $err = defuseFileEncrypt(
            $source_file,
            $target_file,
            $SETTINGS, /** @scrutinizer ignore-type */
            $password
        );
    }

    // return error
    return $err === true ? $err : '';
}

/**
 * Encrypt a file with Defuse.
 *
 * @param string $source_file path to source file
 * @param string $target_file path to target file
 * @param array  $SETTINGS    Settings
 * @param string $password    A password
 *
 * @return string|bool
 */
function defuseFileEncrypt(
    string $source_file,
    string $target_file,
    array $SETTINGS,
    string $password = null
) {
    try {
        CryptoFile::encryptFileWithPassword(
            $source_file,
            $target_file,
            $password
        );
    } catch (CryptoException\WrongKeyOrModifiedCiphertextException $ex) {
        $err = 'wrong_key';
    } catch (CryptoException\EnvironmentIsBrokenException $ex) {
        $err = $ex;
    } catch (CryptoException\IOException $ex) {
        $err = $ex;
    }

    // return error
    return empty($err) === false ? $err : true;
}

/**
 * Decrypt a file with Defuse.
 *
 * @param string $source_file path to source file
 * @param string $target_file path to target file
 * @param array  $SETTINGS    Settings
 * @param string $password    A password
 *
 * @return string|bool
 */
function defuseFileDecrypt(
    string $source_file,
    string $target_file,
    array $SETTINGS,
    string $password = null
) {
    try {
        CryptoFile::decryptFileWithPassword(
            $source_file,
            $target_file,
            $password
        );
    } catch (CryptoException\WrongKeyOrModifiedCiphertextException $ex) {
        $err = 'wrong_key';
    } catch (CryptoException\EnvironmentIsBrokenException $ex) {
        $err = $ex;
    } catch (CryptoException\IOException $ex) {
        $err = $ex;
    }

    // return error
    return empty($err) === false ? $err : true;
}

/*
* NOT TO BE USED
*/
/**
 * Undocumented function.
 *
 * @param string $text Text to debug
 */
function debugTeampass(string $text): void
{
    $debugFile = fopen('D:/wamp64/www/TeamPass/debug.txt', 'r+');
    if ($debugFile !== false) {
        fputs($debugFile, $text);
        fclose($debugFile);
    }
}

/**
 * DELETE the file with expected command depending on server type.
 *
 * @param string $file     Path to file
 * @param array  $SETTINGS Teampass settings
 *
 * @return void
 */
function fileDelete(string $file, array $SETTINGS): void
{
    // Load AntiXSS
    $antiXss = new AntiXSS();
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
function getFileExtension(string $file): string
{
    if (strpos($file, '.') === false) {
        return $file;
    }

    return substr($file, strrpos($file, '.') + 1);
}

/**
 * Chmods files and folders with different permissions.
 *
 * This is an all-PHP alternative to using: \n
 * <tt>exec("find ".$path." -type f -exec chmod 644 {} \;");</tt> \n
 * <tt>exec("find ".$path." -type d -exec chmod 755 {} \;");</tt>
 *
 * @author Jeppe Toustrup (tenzer at tenzer dot dk)
  *
 * @param string $path      An either relative or absolute path to a file or directory which should be processed.
 * @param int    $filePerm The permissions any found files should get.
 * @param int    $dirPerm  The permissions any found folder should get.
 *
 * @return bool Returns TRUE if the path if found and FALSE if not.
 *
 * @warning The permission levels has to be entered in octal format, which
 * normally means adding a zero ("0") in front of the permission level. \n
 * More info at: http://php.net/chmod.
*/

function recursiveChmod(
    string $path,
    int $filePerm = 0644,
    int  $dirPerm = 0755
) {
    // Check if the path exists
    $path = basename($path);
    if (! file_exists($path)) {
        return false;
    }

    // See whether this is a file
    if (is_file($path)) {
        // Chmod the file with our given filepermissions
        try {
            chmod($path, $filePerm);
        } catch (Exception $e) {
            return false;
        }
    // If this is a directory...
    } elseif (is_dir($path)) {
        // Then get an array of the contents
        $foldersAndFiles = scandir($path);
        // Remove "." and ".." from the list
        $entries = array_slice($foldersAndFiles, 2);
        // Parse every result...
        foreach ($entries as $entry) {
            // And call this function again recursively, with the same permissions
            recursiveChmod($path.'/'.$entry, $filePerm, $dirPerm);
        }

        // When we are done with the contents of the directory, we chmod the directory itself
        try {
            chmod($path, $filePerm);
        } catch (Exception $e) {
            return false;
        }
    }

    // Everything seemed to work out well, return true
    return true;
}

/**
 * Check if user can access to this item.
 *
 * @param int   $item_id ID of item
 * @param array $SETTINGS
 *
 * @return bool|string
 */
function accessToItemIsGranted(int $item_id, array $SETTINGS)
{
    
    $session = SessionManager::getSession();
    $session_groupes_visibles = $session->get('user-accessible_folders');
    $session_list_restricted_folders_for_items = $session->get('system-list_restricted_folders_for_items');
    // Load item data
    $data = DB::queryFirstRow(
        'SELECT id_tree
        FROM ' . prefixTable('items') . '
        WHERE id = %i',
        $item_id
    );
    // Check if user can access this folder
    if (in_array($data['id_tree'], $session_groupes_visibles) === false) {
        // Now check if this folder is restricted to user
        if (isset($session_list_restricted_folders_for_items[$data['id_tree']]) === true
            && in_array($item_id, $session_list_restricted_folders_for_items[$data['id_tree']]) === false
        ) {
            return 'ERR_FOLDER_NOT_ALLOWED';
        }
    }

    return true;
}

/**
 * Creates a unique key.
 *
 * @param int $lenght Key lenght
 *
 * @return string
 */
function uniqidReal(int $lenght = 13): string
{
    if (function_exists('random_bytes')) {
        $bytes = random_bytes(intval(ceil($lenght / 2)));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $bytes = openssl_random_pseudo_bytes(intval(ceil($lenght / 2)));
    } else {
        throw new Exception('no cryptographically secure random function available');
    }

    return substr(bin2hex($bytes), 0, $lenght);
}

/**
 * Obfuscate an email.
 *
 * @param string $email Email address
 *
 * @return string
 */
function obfuscateEmail(string $email): string
{
    $email = explode("@", $email);
    $name = $email[0];
    if (strlen($name) > 3) {
        $name = substr($name, 0, 2);
        for ($i = 0; $i < strlen($email[0]) - 3; $i++) {
            $name .= "*";
        }
        $name .= substr($email[0], -1, 1);
    }
    $host = explode(".", $email[1])[0];
    if (strlen($host) > 3) {
        $host = substr($host, 0, 1);
        for ($i = 0; $i < strlen(explode(".", $email[1])[0]) - 2; $i++) {
            $host .= "*";
        }
        $host .= substr(explode(".", $email[1])[0], -1, 1);
    }
    $email = $name . "@" . $host . "." . explode(".", $email[1])[1];
    return $email;
}

/**
 * Perform a Query.
 *
 * @param array  $SETTINGS Teamapss settings
 * @param string $fields   Fields to use
 * @param string $table    Table to use
 *
 * @return array
 */
function performDBQuery(array $SETTINGS, string $fields, string $table): array
{
    // include librairies & connect to DB
    //include_once $SETTINGS['cpassman_dir'] . '/includes/config/settings.php';

    // Load class DB
    loadClasses('DB');
    
    // Insert log in DB
    return DB::query(
        'SELECT ' . $fields . '
        FROM ' . prefixTable($table)
    );
}

/**
 * Undocumented function.
 *
 * @param int $bytes Size of file
 *
 * @return string
 */
function formatSizeUnits(int $bytes): string
{
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes .= ' bytes';
    } elseif ($bytes === 1) {
        $bytes .= ' byte';
    } else {
        $bytes = '0 bytes';
    }

    return $bytes;
}

/**
 * Generate user pair of keys.
 *
 * @param string $userPwd User password
 *
 * @return array
 */
function generateUserKeys(string $userPwd): array
{
    // Sanitize
    $antiXss = new AntiXSS();
    $userPwd = $antiXss->xss_clean($userPwd);
    // Load classes
    $rsa = new Crypt_RSA();
    $cipher = new Crypt_AES();
    // Create the private and public key
    $res = $rsa->createKey(4096);
    // Encrypt the privatekey
    $cipher->setPassword($userPwd);
    $privatekey = $cipher->encrypt($res['privatekey']);
    return [
        'private_key' => base64_encode($privatekey),
        'public_key' => base64_encode($res['publickey']),
        'private_key_clear' => base64_encode($res['privatekey']),
    ];
}

/**
 * Permits to decrypt the user's privatekey.
 *
 * @param string $userPwd        User password
 * @param string $userPrivateKey User private key
 *
 * @return string|object
 */
function decryptPrivateKey(string $userPwd, string $userPrivateKey)
{
    // Sanitize
    $antiXss = new AntiXSS();
    $userPwd = $antiXss->xss_clean($userPwd);
    $userPrivateKey = $antiXss->xss_clean($userPrivateKey);

    if (empty($userPwd) === false) {
        // Load classes
        $cipher = new Crypt_AES();
        // Encrypt the privatekey
        $cipher->setPassword($userPwd);
        try {
            return base64_encode((string) $cipher->decrypt(base64_decode($userPrivateKey)));
        } catch (Exception $e) {
            return $e;
        }
    }
    return '';
}

/**
 * Permits to encrypt the user's privatekey.
 *
 * @param string $userPwd        User password
 * @param string $userPrivateKey User private key
 *
 * @return string
 */
function encryptPrivateKey(string $userPwd, string $userPrivateKey): string
{
    // Sanitize
    $antiXss = new AntiXSS();
    $userPwd = $antiXss->xss_clean($userPwd);
    $userPrivateKey = $antiXss->xss_clean($userPrivateKey);

    if (empty($userPwd) === false) {
        // Load classes
        $cipher = new Crypt_AES();
        // Encrypt the privatekey
        $cipher->setPassword($userPwd);        
        try {
            return base64_encode($cipher->encrypt(base64_decode($userPrivateKey)));
        } catch (Exception $e) {
            return $e;
        }
    }
    return '';
}

/**
 * Encrypts a string using AES.
 *
 * @param string $data String to encrypt
 * @param string $key
 *
 * @return array
 */
function doDataEncryption(string $data, string $key = NULL): array
{
    // Sanitize
    $antiXss = new AntiXSS();
    $data = $antiXss->xss_clean($data);
    
    // Load classes
    $cipher = new Crypt_AES(CRYPT_AES_MODE_CBC);
    // Generate an object key
    $objectKey = is_null($key) === true ? uniqidReal(KEY_LENGTH) : $antiXss->xss_clean($key);
    // Set it as password
    $cipher->setPassword($objectKey);
    return [
        'encrypted' => base64_encode($cipher->encrypt($data)),
        'objectKey' => base64_encode($objectKey),
    ];
}

/**
 * Decrypts a string using AES.
 *
 * @param string $data Encrypted data
 * @param string $key  Key to uncrypt
 *
 * @return string
 */
function doDataDecryption(string $data, string $key): string
{
    // Sanitize
    $antiXss = new AntiXSS();
    $data = $antiXss->xss_clean($data);
    $key = $antiXss->xss_clean($key);

    // Load classes
    $cipher = new Crypt_AES();
    // Set the object key
    $cipher->setPassword(base64_decode($key));
    return base64_encode((string) $cipher->decrypt(base64_decode($data)));
}

/**
 * Encrypts using RSA a string using a public key.
 *
 * @param string $key       Key to be encrypted
 * @param string $publicKey User public key
 *
 * @return string
 */
function encryptUserObjectKey(string $key, string $publicKey): string
{
    // Sanitize
    $antiXss = new AntiXSS();
    $publicKey = $antiXss->xss_clean($publicKey);
    // Load classes
    $rsa = new Crypt_RSA();
    // Load the public key
    $decodedPublicKey = base64_decode($publicKey, true);
    if ($decodedPublicKey === false) {
        throw new InvalidArgumentException("Error while decoding key.");
    }
    $rsa->loadKey($decodedPublicKey);
    // Encrypt
    $encrypted = $rsa->encrypt(base64_decode($key));
    if ($encrypted === false) {
        throw new RuntimeException("Error while encrypting key.");
    }
    // Return
    return base64_encode($encrypted);
}

/**
 * Decrypts using RSA an encrypted string using a private key.
 *
 * @param string $key        Encrypted key
 * @param string $privateKey User private key
 *
 * @return string
 */
function decryptUserObjectKey(string $key, string $privateKey): string
{
    // Sanitize
    $antiXss = new AntiXSS();
    $privateKey = $antiXss->xss_clean($privateKey);

    // Load classes
    $rsa = new Crypt_RSA();
    // Load the private key
    $decodedPrivateKey = base64_decode($privateKey, true);
    if ($decodedPrivateKey === false) {
        throw new InvalidArgumentException("Error while decoding private key.");
    }

    $rsa->loadKey($decodedPrivateKey);

    // Decrypt
    try {
        $decodedKey = base64_decode($key, true);
        if ($decodedKey === false) {
            throw new InvalidArgumentException("Error while decoding key.");
        }

        $tmpValue = $rsa->decrypt($decodedKey);
        if ($tmpValue !== false) {
            return base64_encode($tmpValue);
        } else {
            return '';
        }
    } catch (Exception $e) {
        error_log('TEAMPASS Error - ldap - '.$e->getMessage());
        return 'Exception: could not decrypt object';
    }
}

/**
 * Encrypts a file.
 *
 * @param string $fileInName File name
 * @param string $fileInPath Path to file
 *
 * @return array
 */
function encryptFile(string $fileInName, string $fileInPath): array
{
    if (defined('FILE_BUFFER_SIZE') === false) {
        define('FILE_BUFFER_SIZE', 128 * 1024);
    }

    // Load classes
    $cipher = new Crypt_AES();

    // Generate an object key
    $objectKey = uniqidReal(32);
    // Set it as password
    $cipher->setPassword($objectKey);
    // Prevent against out of memory
    $cipher->enableContinuousBuffer();

    // Encrypt the file content
    $filePath = filter_var($fileInPath . '/' . $fileInName, FILTER_SANITIZE_URL);
    $fileContent = file_get_contents($filePath);
    $plaintext = $fileContent;
    $ciphertext = $cipher->encrypt($plaintext);

    // Save new file
    // deepcode ignore InsecureHash: is simply used to get a unique name
    $hash = md5($plaintext);
    $fileOut = $fileInPath . '/' . TP_FILE_PREFIX . $hash;
    file_put_contents($fileOut, $ciphertext);
    unlink($fileInPath . '/' . $fileInName);
    return [
        'fileHash' => base64_encode($hash),
        'objectKey' => base64_encode($objectKey),
    ];
}

/**
 * Decrypt a file.
 *
 * @param string $fileName File name
 * @param string $filePath Path to file
 * @param string $key      Key to use
 *
 * @return string
 */
function decryptFile(string $fileName, string $filePath, string $key): string
{
    if (! defined('FILE_BUFFER_SIZE')) {
        define('FILE_BUFFER_SIZE', 128 * 1024);
    }
    
    // Load classes
    $cipher = new Crypt_AES();
    $antiXSS = new AntiXSS();
    
    // Get file name
    $safeFileName = $antiXSS->xss_clean(base64_decode($fileName));

    // Set the object key
    $cipher->setPassword(base64_decode($key));
    // Prevent against out of memory
    $cipher->enableContinuousBuffer();
    $cipher->disablePadding();
    // Get file content
    $safeFilePath = realpath($filePath . '/' . TP_FILE_PREFIX . $safeFileName);
    $ciphertext = file_get_contents(filter_var($safeFilePath, FILTER_SANITIZE_URL));

    if (WIP) error_log('DEBUG: File image url -> '.filter_var($safeFilePath, FILTER_SANITIZE_URL));

    // Decrypt file content and return
    return base64_encode($cipher->decrypt($ciphertext));
}

/**
 * Generate a simple password
 *
 * @param int $length Length of string
 * @param bool $symbolsincluded Allow symbols
 *
 * @return string
 */
function generateQuickPassword(int $length = 16, bool $symbolsincluded = true): string
{
    // Generate new user password
    $small_letters = range('a', 'z');
    $big_letters = range('A', 'Z');
    $digits = range(0, 9);
    $symbols = $symbolsincluded === true ?
        ['#', '_', '-', '@', '$', '+', '!'] : [];
    $res = array_merge($small_letters, $big_letters, $digits, $symbols);
    $count = count($res);
    // first variant

    $random_string = '';
    for ($i = 0; $i < $length; ++$i) {
        $random_string .= $res[random_int(0, $count - 1)];
    }

    return $random_string;
}

/**
 * Permit to store the sharekey of an object for users.
 *
 * @param string $object_name             Type for table selection
 * @param int    $post_folder_is_personal Personal
 * @param int    $post_folder_id          Folder
 * @param int    $post_object_id          Object
 * @param string $objectKey               Object key
 * @param array  $SETTINGS                Teampass settings
 * @param int    $user_id                 User ID if needed
 * @param bool   $onlyForUser                 User ID if needed
 * @param bool   $deleteAll                 User ID if needed
 * @param array  $objectKeyArray                 User ID if needed
 * @param int    $all_users_except_id                 User ID if needed
 * @param int   $apiUserId                 User ID if needed
 *
 * @return void
 */
function storeUsersShareKey(
    string $object_name,
    int $post_folder_is_personal,
    int $post_folder_id,
    int $post_object_id,
    string $objectKey,
    bool $onlyForUser = false,
    bool $deleteAll = true,
    array $objectKeyArray = [],
    int $all_users_except_id = -1,
    int $apiUserId = -1
): void {
    
    $session = SessionManager::getSession();
    loadClasses('DB');

    // Delete existing entries for this object
    if ($deleteAll === true) {
        DB::delete(
            $object_name,
            'object_id = %i',
            $post_object_id
        );
    }
    
    if (
        $onlyForUser === true || (int) $post_folder_is_personal === 1
    ) {
        // Only create the sharekey for a user
        $user = DB::queryFirstRow(
            'SELECT public_key
            FROM ' . prefixTable('users') . '
            WHERE id = ' . ($apiUserId === -1 ? (int) $session->get('user-id') : $apiUserId) . '
            AND public_key != ""'
        );

        if (empty($objectKey) === false) {
            DB::insert(
                $object_name,
                [
                    'object_id' => (int) $post_object_id,
                    'user_id' => (int) ($apiUserId === -1 ? (int) $session->get('user-id') : $apiUserId),
                    'share_key' => encryptUserObjectKey(
                        $objectKey,
                        $user['public_key']
                    ),
                ]
            );
        } else if (count($objectKeyArray) > 0) {
            foreach ($objectKeyArray as $object) {
                DB::insert(
                    $object_name,
                    [
                        'object_id' => (int) $object['objectId'],
                        'user_id' => (int) ($apiUserId === -1 ? (int) $session->get('user-id') : $apiUserId),
                        'share_key' => encryptUserObjectKey(
                            $object['objectKey'],
                            $user['public_key']
                        ),
                    ]
                );
            }
        }
    } else {
        // Create sharekey for each user
        //error_log('Building QUERY - all_users_except_id: '. $all_users_except_id);
        //DB::debugmode(true);
        $users = DB::query(
            'SELECT id, public_key
            FROM ' . prefixTable('users') . '
            WHERE ' . ($onlyForUser === true ? 
                'id IN ("' . TP_USER_ID . '","' . ($apiUserId === -1 ? (int) $session->get('user-id') : $apiUserId) . '") ' : 
                'id NOT IN ("' . OTV_USER_ID . '","' . SSH_USER_ID . '","' . API_USER_ID . '"'.($all_users_except_id === -1 ? '' : ', "'.$all_users_except_id.'"').') ') . '
            AND public_key != ""'
        );
        //DB::debugmode(false);
        foreach ($users as $user) {
            // Insert in DB the new object key for this item by user
            if (count($objectKeyArray) === 0) {
                if (WIP === true) error_log('TEAMPASS Debug - storeUsersShareKey case1 - ' . $object_name . ' - ' . $post_object_id . ' - ' . $user['id'] . ' - ' . $objectKey);
                DB::insert(
                    $object_name,
                    [
                        'object_id' => $post_object_id,
                        'user_id' => (int) $user['id'],
                        'share_key' => encryptUserObjectKey(
                            $objectKey,
                            $user['public_key']
                        ),
                    ]
                );
            } else {
                foreach ($objectKeyArray as $object) {
                    if (WIP === true) error_log('TEAMPASS Debug - storeUsersShareKey case2 - ' . $object_name . ' - ' . $object['objectId'] . ' - ' . $user['id'] . ' - ' . $object['objectKey']);
                    DB::insert(
                        $object_name,
                        [
                            'object_id' => (int) $object['objectId'],
                            'user_id' => (int) $user['id'],
                            'share_key' => encryptUserObjectKey(
                                $object['objectKey'],
                                $user['public_key']
                            ),
                        ]
                    );
                }
            }
        }
    }
}

/**
 * Is this string base64 encoded?
 *
 * @param string $str Encoded string?
 *
 * @return bool
 */
function isBase64(string $str): bool
{
    $str = (string) trim($str);
    if (! isset($str[0])) {
        return false;
    }

    $base64String = (string) base64_decode($str, true);
    if ($base64String && base64_encode($base64String) === $str) {
        return true;
    }

    return false;
}

/**
 * Undocumented function
 *
 * @param string $field Parameter
 *
 * @return array|bool|resource|string
 */
function filterString(string $field)
{
    // Sanitize string
    $field = filter_var(trim($field), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    if (empty($field) === false) {
        // Load AntiXSS
        $antiXss = new AntiXSS();
        // Return
        return $antiXss->xss_clean($field);
    }

    return false;
}

/**
 * CHeck if provided credentials are allowed on server
 *
 * @param string $login    User Login
 * @param string $password User Pwd
 * @param array  $SETTINGS Teampass settings
 *
 * @return bool
 */
function ldapCheckUserPassword(string $login, string $password, array $SETTINGS): bool
{
    // Build ldap configuration array
    $config = [
        // Mandatory Configuration Options
        'hosts' => [$SETTINGS['ldap_hosts']],
        'base_dn' => $SETTINGS['ldap_bdn'],
        'username' => $SETTINGS['ldap_username'],
        'password' => $SETTINGS['ldap_password'],

        // Optional Configuration Options
        'port' => $SETTINGS['ldap_port'],
        'use_ssl' => (int) $SETTINGS['ldap_ssl'] === 1 ? true : false,
        'use_tls' => (int) $SETTINGS['ldap_tls'] === 1 ? true : false,
        'version' => 3,
        'timeout' => 5,
        'follow_referrals' => false,

        // Custom LDAP Options
        'options' => [
            // See: http://php.net/ldap_set_option
            LDAP_OPT_X_TLS_REQUIRE_CERT => (isset($SETTINGS['ldap_tls_certiface_check']) ? $SETTINGS['ldap_tls_certiface_check'] : LDAP_OPT_X_TLS_HARD),
        ],
    ];
    
    $connection = new Connection($config);
    // Connect to LDAP
    try {
        $connection->connect();
    } catch (\LdapRecord\Auth\BindException $e) {
        $error = $e->getDetailedError();
        error_log('TEAMPASS Error - Auth - '.$error->getErrorCode()." - ".$error->getErrorMessage(). " - ".$error->getDiagnosticMessage());
        // deepcode ignore ServerLeak: No important data is sent
        echo 'An error occurred.';
        return false;
    }

    // Authenticate user
    try {
        if ($SETTINGS['ldap_type'] === 'ActiveDirectory') {
            $connection->auth()->attempt($login, $password, $stayAuthenticated = true);
        } else {
            $connection->auth()->attempt($SETTINGS['ldap_user_attribute'].'='.$login.','.(isset($SETTINGS['ldap_dn_additional_user_dn']) && !empty($SETTINGS['ldap_dn_additional_user_dn']) ? $SETTINGS['ldap_dn_additional_user_dn'].',' : '').$SETTINGS['ldap_bdn'], $password, $stayAuthenticated = true);
        }
    } catch (\LdapRecord\Auth\BindException $e) {
        $error = $e->getDetailedError();
        error_log('TEAMPASS Error - Auth - '.$error->getErrorCode()." - ".$error->getErrorMessage(). " - ".$error->getDiagnosticMessage());
        // deepcode ignore ServerLeak: No important data is sent
        echo 'An error occurred.';
        return false;
    }

    return true;
}

/**
 * Removes from DB all sharekeys of this user
 *
 * @param int $userId User's id
 * @param array   $SETTINGS Teampass settings
 *
 * @return bool
 */
function deleteUserObjetsKeys(int $userId, array $SETTINGS = []): bool
{
    // Load class DB
    loadClasses('DB');

    // Remove all item sharekeys items
    // expect if personal item
    DB::delete(
        prefixTable('sharekeys_items'),
        'user_id = %i AND object_id NOT IN (SELECT i.id FROM ' . prefixTable('items') . ' AS i WHERE i.perso = 1)',
        $userId
    );
    // Remove all item sharekeys files
    DB::delete(
        prefixTable('sharekeys_files'),
        'user_id = %i AND object_id NOT IN (
            SELECT f.id 
            FROM ' . prefixTable('items') . ' AS i 
            INNER JOIN ' . prefixTable('files') . ' AS f ON f.id_item = i.id
            WHERE i.perso = 1
        )',
        $userId
    );
    // Remove all item sharekeys fields
    DB::delete(
        prefixTable('sharekeys_fields'),
        'user_id = %i AND object_id NOT IN (
            SELECT c.id 
            FROM ' . prefixTable('items') . ' AS i 
            INNER JOIN ' . prefixTable('categories_items') . ' AS c ON c.item_id = i.id
            WHERE i.perso = 1
        )',
        $userId
    );
    // Remove all item sharekeys logs
    DB::delete(
        prefixTable('sharekeys_logs'),
        'user_id = %i AND object_id NOT IN (SELECT i.id FROM ' . prefixTable('items') . ' AS i WHERE i.perso = 1)',
        $userId
    );
    // Remove all item sharekeys suggestions
    DB::delete(
        prefixTable('sharekeys_suggestions'),
        'user_id = %i AND object_id NOT IN (SELECT i.id FROM ' . prefixTable('items') . ' AS i WHERE i.perso = 1)',
        $userId
    );
    return false;
}

/**
 * Manage list of timezones   $SETTINGS Teampass settings
 *
 * @return array
 */
function timezone_list()
{
    static $timezones = null;
    if ($timezones === null) {
        $timezones = [];
        $offsets = [];
        $now = new DateTime('now', new DateTimeZone('UTC'));
        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            $now->setTimezone(new DateTimeZone($timezone));
            $offsets[] = $offset = $now->getOffset();
            $timezones[$timezone] = '(' . format_GMT_offset($offset) . ') ' . format_timezone_name($timezone);
        }

        array_multisort($offsets, $timezones);
    }

    return $timezones;
}

/**
 * Provide timezone offset
 *
 * @param int $offset Timezone offset
 *
 * @return string
 */
function format_GMT_offset($offset): string
{
    $hours = intval($offset / 3600);
    $minutes = abs(intval($offset % 3600 / 60));
    return 'GMT' . ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
}

/**
 * Provides timezone name
 *
 * @param string $name Timezone name
 *
 * @return string
 */
function format_timezone_name($name): string
{
    $name = str_replace('/', ', ', $name);
    $name = str_replace('_', ' ', $name);

    return str_replace('St ', 'St. ', $name);
}

/**
 * Provides info if user should use MFA based on roles
 *
 * @param string $userRolesIds  User roles ids
 * @param string $mfaRoles      Roles for which MFA is requested
 *
 * @return bool
 */
function mfa_auth_requested_roles(string $userRolesIds, string $mfaRoles): bool
{
    if (empty($mfaRoles) === true) {
        return true;
    }

    $mfaRoles = array_values(json_decode($mfaRoles, true));
    $userRolesIds = array_filter(explode(';', $userRolesIds));
    if (count($mfaRoles) === 0 || count(array_intersect($mfaRoles, $userRolesIds)) > 0) {
        return true;
    }

    return false;
}

/**
 * Permits to clean a string for export purpose
 *
 * @param string $text
 * @param bool $emptyCheckOnly
 * 
 * @return string
 */
function cleanStringForExport(string $text, bool $emptyCheckOnly = false): string
{
    if (is_null($text) === true || empty($text) === true) {
        return '';
    }
    // only expected to check if $text was empty
    elseif ($emptyCheckOnly === true) {
        return $text;
    }

    return strip_tags(
        cleanString(
            html_entity_decode($text, ENT_QUOTES | ENT_XHTML, 'UTF-8'),
            true)
        );
}

/**
 * Permits to check if user ID is valid
 *
 * @param integer $post_user_id
 * @return bool
 */
function isUserIdValid($userId): bool
{
    if (is_null($userId) === false
        && isset($userId) === true
        && empty($userId) === false
    ) {
        return true;
    }
    return false;
}

/**
 * Check if a key exists and if its value equal the one expected
 *
 * @param string $key
 * @param integer|string $value
 * @param array $array
 * 
 * @return boolean
 */
function isKeyExistingAndEqual(
    string $key,
    /*PHP8 - integer|string*/$value,
    array $array
): bool
{
    if (isset($array[$key]) === true
        && (is_int($value) === true ?
            (int) $array[$key] === $value :
            (string) $array[$key] === $value)
    ) {
        return true;
    }
    return false;
}

/**
 * Check if a variable is not set or equal to a value
 *
 * @param string|null $var
 * @param integer|string $value
 * 
 * @return boolean
 */
function isKeyNotSetOrEqual(
    /*PHP8 - string|null*/$var,
    /*PHP8 - integer|string*/$value
): bool
{
    if (isset($var) === false
        || (is_int($value) === true ?
            (int) $var === $value :
            (string) $var === $value)
    ) {
        return true;
    }
    return false;
}

/**
 * Check if a key exists and if its value < to the one expected
 *
 * @param string $key
 * @param integer $value
 * @param array $array
 * 
 * @return boolean
 */
function isKeyExistingAndInferior(string $key, int $value, array $array): bool
{
    if (isset($array[$key]) === true && (int) $array[$key] < $value) {
        return true;
    }
    return false;
}

/**
 * Check if a key exists and if its value > to the one expected
 *
 * @param string $key
 * @param integer $value
 * @param array $array
 * 
 * @return boolean
 */
function isKeyExistingAndSuperior(string $key, int $value, array $array): bool
{
    if (isset($array[$key]) === true && (int) $array[$key] > $value) {
        return true;
    }
    return false;
}

/**
 * Check if values in array are set
 * Return true if all set
 * Return false if one of them is not set
 *
 * @param array $arrayOfValues
 * @return boolean
 */
function isSetArrayOfValues(array $arrayOfValues): bool
{
    foreach($arrayOfValues as $value) {
        if (isset($value) === false) {
            return false;
        }
    }
    return true;
}

/**
 * Check if values in array are set
 * Return true if all set
 * Return false if one of them is not set
 *
 * @param array $arrayOfValues
 * @param integer|string $value
 * @return boolean
 */
function isArrayOfVarsEqualToValue(
    array $arrayOfVars,
    /*PHP8 - integer|string*/$value
) : bool
{
    foreach($arrayOfVars as $variable) {
        if ($variable !== $value) {
            return false;
        }
    }
    return true;
}

/**
 * Checks if at least one variable in array is equal to value
 *
 * @param array $arrayOfValues
 * @param integer|string $value
 * @return boolean
 */
function isOneVarOfArrayEqualToValue(
    array $arrayOfVars,
    /*PHP8 - integer|string*/$value
) : bool
{
    foreach($arrayOfVars as $variable) {
        if ($variable === $value) {
            return true;
        }
    }
    return false;
}

/**
 * Checks is value is null, not set OR empty
 *
 * @param string|int|null $value
 * @return boolean
 */
function isValueSetNullEmpty(/*PHP8 - string|int|null*/ $value) : bool
{
    if (is_null($value) === true || isset($value) === false || empty($value) === true) {
        return true;
    }
    return false;
}

/**
 * Checks if value is set and if empty is equal to passed boolean
 *
 * @param string|int $value
 * @param boolean $boolean
 * @return boolean
 */
function isValueSetEmpty($value, $boolean = true) : bool
{
    if (isset($value) === true && empty($value) === $boolean) {
        return true;
    }
    return false;
}

/**
 * Ensure Complexity is translated
 *
 * @return void
 */
function defineComplexity() : void
{
    // Load user's language
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');
    
    if (defined('TP_PW_COMPLEXITY') === false) {
        define(
            'TP_PW_COMPLEXITY',
            [
                TP_PW_STRENGTH_1 => array(TP_PW_STRENGTH_1, $lang->get('complex_level1'), 'fas fa-thermometer-empty text-danger'),
                TP_PW_STRENGTH_2 => array(TP_PW_STRENGTH_2, $lang->get('complex_level2'), 'fas fa-thermometer-quarter text-warning'),
                TP_PW_STRENGTH_3 => array(TP_PW_STRENGTH_3, $lang->get('complex_level3'), 'fas fa-thermometer-half text-warning'),
                TP_PW_STRENGTH_4 => array(TP_PW_STRENGTH_4, $lang->get('complex_level4'), 'fas fa-thermometer-three-quarters text-success'),
                TP_PW_STRENGTH_5 => array(TP_PW_STRENGTH_5, $lang->get('complex_level5'), 'fas fa-thermometer-full text-success'),
            ]
        );
    }
}

/**
 * Uses Sanitizer to perform data sanitization
 *
 * @param array     $data
 * @param array     $filters
 * @return array|string
 */
function dataSanitizer(array $data, array $filters): array|string
{
    // Load Sanitizer library
    $sanitizer = new Sanitizer($data, $filters);

    // Load AntiXSS
    $antiXss = new AntiXSS();

    // Sanitize post and get variables
    return $antiXss->xss_clean($sanitizer->sanitize());
}

/**
 * Permits to manage the cache tree for a user
 *
 * @param integer $user_id
 * @param string $data
 * @param array $SETTINGS
 * @param string $field_update
 * @return void
 */
function cacheTreeUserHandler(int $user_id, string $data, array $SETTINGS, string $field_update = '')
{
    // Load class DB
    loadClasses('DB');

    // Exists ?
    $userCacheId = DB::queryfirstrow(
        'SELECT increment_id
        FROM ' . prefixTable('cache_tree') . '
        WHERE user_id = %i',
        $user_id
    );
    
    if (is_null($userCacheId) === true || count($userCacheId) === 0) {
        // insert in table
        DB::insert(
            prefixTable('cache_tree'),
            array(
                'data' => $data,
                'timestamp' => time(),
                'user_id' => $user_id,
                'visible_folders' => '',
            )
        );
    } else {
        if (empty($field_update) === true) {
            DB::update(
                prefixTable('cache_tree'),
                [
                    'timestamp' => time(),
                    'data' => $data,
                ],
                'increment_id = %i',
                $userCacheId['increment_id']
            );
        /* USELESS
        } else {
            DB::update(
                prefixTable('cache_tree'),
                [
                    $field_update => $data,
                ],
                'increment_id = %i',
                $userCacheId['increment_id']
            );*/
        }
    }
}

/**
 * Permits to calculate a %
 *
 * @param float $nombre
 * @param float $total
 * @param float $pourcentage
 * @return float
 */
function pourcentage(float $nombre, float $total, float $pourcentage): float
{ 
    $resultat = ($nombre/$total) * $pourcentage;
    return round($resultat);
}

/**
 * Load the folders list from the cache
 *
 * @param string $fieldName
 * @param string $sessionName
 * @param boolean $forceRefresh
 * @return array
 */
function loadFoldersListByCache(
    string $fieldName,
    string $sessionName,
    bool $forceRefresh = false
): array
{
    // Case when refresh is EXPECTED / MANDATORY
    if ($forceRefresh === true) {
        return [
            'state' => false,
            'data' => [],
        ];
    }
    
    $session = SessionManager::getSession();

    // Get last folder update
    $lastFolderChange = DB::queryfirstrow(
        'SELECT valeur FROM ' . prefixTable('misc') . '
        WHERE type = %s AND intitule = %s',
        'timestamp',
        'last_folder_change'
    );
    if (DB::count() === 0) {
        $lastFolderChange['valeur'] = 0;
    }

    // Case when an update in the tree has been done
    // Refresh is then mandatory
    if ((int) $lastFolderChange['valeur'] > (int) (null !== $session->get('user-tree_last_refresh_timestamp') ? $session->get('user-tree_last_refresh_timestamp') : 0)) {
        return [
            'state' => false,
            'data' => [],
        ];
    }

    // Does this user has the tree structure in session?
    // If yes then use it
    if (count(null !== $session->get('user-folders_list') ? $session->get('user-folders_list') : []) > 0) {
        return [
            'state' => true,
            'data' => json_encode($session->get('user-folders_list')[0]),
            'extra' => 'to_be_parsed',
        ];
    }
    
    // Does this user has a tree cache
    $userCacheTree = DB::queryfirstrow(
        'SELECT '.$fieldName.'
        FROM ' . prefixTable('cache_tree') . '
        WHERE user_id = %i',
        $session->get('user-id')
    );
    if (empty($userCacheTree[$fieldName]) === false && $userCacheTree[$fieldName] !== '[]') {
        SessionManager::addRemoveFromSessionAssociativeArray(
            'user-folders_list',
            [$userCacheTree[$fieldName]],
            'add'
        );
        return [
            'state' => true,
            'data' => $userCacheTree[$fieldName],
            'extra' => '',
        ];
    }

    return [
        'state' => false,
        'data' => [],
    ];
}


/**
 * Permits to refresh the categories of folders
 *
 * @param array $folderIds
 * @return void
 */
function handleFoldersCategories(
    array $folderIds
)
{
    // Load class DB
    loadClasses('DB');

    $arr_data = array();

    // force full list of folders
    if (count($folderIds) === 0) {
        $folderIds = DB::queryFirstColumn(
            'SELECT id
            FROM ' . prefixTable('nested_tree') . '
            WHERE personal_folder=%i',
            0
        );
    }

    // Get complexity
    defineComplexity();

    // update
    foreach ($folderIds as $folder) {
        // Do we have Categories
        // get list of associated Categories
        $arrCatList = array();
        $rows_tmp = DB::query(
            'SELECT c.id, c.title, c.level, c.type, c.masked, c.order, c.encrypted_data, c.role_visibility, c.is_mandatory,
            f.id_category AS category_id
            FROM ' . prefixTable('categories_folders') . ' AS f
            INNER JOIN ' . prefixTable('categories') . ' AS c ON (f.id_category = c.parent_id)
            WHERE id_folder=%i',
            $folder
        );
        if (DB::count() > 0) {
            foreach ($rows_tmp as $row) {
                $arrCatList[$row['id']] = array(
                    'id' => $row['id'],
                    'title' => $row['title'],
                    'level' => $row['level'],
                    'type' => $row['type'],
                    'masked' => $row['masked'],
                    'order' => $row['order'],
                    'encrypted_data' => $row['encrypted_data'],
                    'role_visibility' => $row['role_visibility'],
                    'is_mandatory' => $row['is_mandatory'],
                    'category_id' => $row['category_id'],
                );
            }
        }
        $arr_data['categories'] = $arrCatList;

        // Now get complexity
        $valTemp = '';
        $data = DB::queryFirstRow(
            'SELECT valeur
            FROM ' . prefixTable('misc') . '
            WHERE type = %s AND intitule=%i',
            'complex',
            $folder
        );
        if (DB::count() > 0 && empty($data['valeur']) === false) {
            $valTemp = array(
                'value' => $data['valeur'],
                'text' => TP_PW_COMPLEXITY[$data['valeur']][1],
            );
        }
        $arr_data['complexity'] = $valTemp;

        // Now get Roles
        $valTemp = '';
        $rows_tmp = DB::query(
            'SELECT t.title
            FROM ' . prefixTable('roles_values') . ' as v
            INNER JOIN ' . prefixTable('roles_title') . ' as t ON (v.role_id = t.id)
            WHERE v.folder_id = %i
            GROUP BY title',
            $folder
        );
        foreach ($rows_tmp as $record) {
            $valTemp .= (empty($valTemp) === true ? '' : ' - ') . $record['title'];
        }
        $arr_data['visibilityRoles'] = $valTemp;

        // now save in DB
        DB::update(
            prefixTable('nested_tree'),
            array(
                'categories' => json_encode($arr_data),
            ),
            'id = %i',
            $folder
        );
    }
}

/**
 * List all users that have specific roles
 *
 * @param array $roles
 * @return array
 */
function getUsersWithRoles(
    array $roles
): array
{
    $session = SessionManager::getSession();
    $arrUsers = array();

    foreach ($roles as $role) {
        // loop on users and check if user has this role
        $rows = DB::query(
            'SELECT id, fonction_id
            FROM ' . prefixTable('users') . '
            WHERE id != %i AND admin = 0 AND fonction_id IS NOT NULL AND fonction_id != ""',
            $session->get('user-id')
        );
        foreach ($rows as $user) {
            $userRoles = is_null($user['fonction_id']) === false && empty($user['fonction_id']) === false ? explode(';', $user['fonction_id']) : [];
            if (in_array($role, $userRoles, true) === true) {
                array_push($arrUsers, $user['id']);
            }
        }
    }

    return $arrUsers;
}


/**
 * Get all users informations
 *
 * @param integer $userId
 * @return array
 */
function getFullUserInfos(
    int $userId
): array
{
    if (empty($userId) === true) {
        return array();
    }

    $val = DB::queryfirstrow(
        'SELECT *
        FROM ' . prefixTable('users') . '
        WHERE id = %i',
        $userId
    );

    return $val;
}

/**
 * Is required an upgrade
 *
 * @return boolean
 */
function upgradeRequired(): bool
{
    // Get settings.php
    include_once __DIR__. '/../includes/config/settings.php';

    // Get timestamp in DB
    $val = DB::queryfirstrow(
        'SELECT valeur
        FROM ' . prefixTable('misc') . '
        WHERE type = %s AND intitule = %s',
        'admin',
        'upgrade_timestamp'
    );
    
    // if not exists then error
    if (is_null($val) === true || count($val) === 0 || defined('UPGRADE_MIN_DATE') === false) return true;

    // if empty or too old then error
    if (empty($val['valeur']) === true || (int) $val['valeur'] < (int) UPGRADE_MIN_DATE) {
        return true;
    }

    return false;
}

/**
 * Permits to change the user keys on his demand
 *
 * @param integer $userId
 * @param string $passwordClear
 * @param integer $nbItemsToTreat
 * @param string $encryptionKey
 * @param boolean $deleteExistingKeys
 * @param boolean $sendEmailToUser
 * @param boolean $encryptWithUserPassword
 * @param boolean $generate_user_new_password
 * @param string $emailBody
 * @param boolean $user_self_change
 * @param string $recovery_public_key
 * @param string $recovery_private_key
 * @return string
 */
function handleUserKeys(
    int $userId,
    string $passwordClear,
    int $nbItemsToTreat,
    string $encryptionKey = '',
    bool $deleteExistingKeys = false,
    bool $sendEmailToUser = true,
    bool $encryptWithUserPassword = false,
    bool $generate_user_new_password = false,
    string $emailBody = '',
    bool $user_self_change = false,
    string $recovery_public_key = '',
    string $recovery_private_key = ''
): string
{
    $session = SessionManager::getSession();
    $lang = new Language($session->get('user-language') ?? 'english');

    // prepapre background tasks for item keys generation        
    $userTP = DB::queryFirstRow(
        'SELECT pw, public_key, private_key
        FROM ' . prefixTable('users') . '
        WHERE id = %i',
        TP_USER_ID
    );
    if (DB::count() === 0) {
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => 'User not exists',
            ),
            'encode'
        );
    }

    // Do we need to generate new user password
    if ($generate_user_new_password === true) {
        // Generate a new password
        $passwordClear = GenerateCryptKey(20, false, true, true, false, true);
    }

    // Create password hash
    $passwordManager = new PasswordManager();
    $hashedPassword = $passwordManager->hashPassword($passwordClear);
    if ($passwordManager->verifyPassword($hashedPassword, $passwordClear) === false) {
        return prepareExchangedData(
            array(
                'error' => true,
                'message' => $lang->get('pw_hash_not_correct'),
            ),
            'encode'
        );
    }

    // Check if valid public/private keys
    if ($recovery_public_key !== '' && $recovery_private_key !== '') {
        try {
            // Generate random string
            $random_str = generateQuickPassword(12, false);
            // Encrypt random string with user publick key
            $encrypted = encryptUserObjectKey($random_str, $recovery_public_key);
            // Decrypt $encrypted with private key
            $decrypted = decryptUserObjectKey($encrypted, $recovery_private_key);
            error_log('$random_str='.$random_str.' | $decrypted='.$decrypted);
            // Check if decryptUserObjectKey returns our random string
            if ($decrypted !== $random_str) {
                throw new Exception('Public/Private keypair invalid.');
            }
        } catch (Exception $e) {
            // Show error message to user and log event
            error_log('ERROR: User '.$userId.' - '.$e->getMessage());
    
            return prepareExchangedData([
                    'error' => true,
                    'message' => $lang->get('pw_encryption_error'),
                ],
                'encode'
            );
        }
    }
    
    // Generate new keys
    if ($user_self_change === true && empty($recovery_public_key) === false && empty($recovery_private_key) === false){
        $userKeys = [
            'public_key' => $recovery_public_key,
            'private_key_clear' => $recovery_private_key,
            'private_key' => encryptPrivateKey($passwordClear, $recovery_private_key),
        ];
    } else {
        $userKeys = generateUserKeys($passwordClear);
    }

    // Save in DB
    DB::update(
        prefixTable('users'),
        array(
            'pw' => $hashedPassword,
            'public_key' => $userKeys['public_key'],
            'private_key' => $userKeys['private_key'],
        ),
        'id=%i',
        $userId
    );

    // update session too
    if ($userId === $session->get('user-id')) {
        $session->set('user-private_key', $userKeys['private_key_clear']);
        $session->set('user-public_key', $userKeys['public_key']);
    }

    // Manage empty encryption key
    // Let's take the user's password if asked and if no encryption key provided
    $encryptionKey = $encryptWithUserPassword === true && empty($encryptionKey) === true ? $passwordClear : $encryptionKey;

    // Create process
    DB::insert(
        prefixTable('background_tasks'),
        array(
            'created_at' => time(),
            'process_type' => 'create_user_keys',
            'arguments' => json_encode([
                'new_user_id' => (int) $userId,
                'new_user_pwd' => cryption($passwordClear, '','encrypt')['string'],
                'new_user_code' => cryption(empty($encryptionKey) === true ? uniqidReal(20) : $encryptionKey, '','encrypt')['string'],
                'owner_id' => (int) TP_USER_ID,
                'creator_pwd' => $userTP['pw'],
                'send_email' => $sendEmailToUser === true ? 1 : 0,
                'otp_provided_new_value' => 1,
                'email_body' => empty($emailBody) === true ? '' : $lang->get($emailBody),
                'user_self_change' => $user_self_change === true ? 1 : 0,
            ]),
        )
    );
    $processId = DB::insertId();

    // Delete existing keys
    if ($deleteExistingKeys === true) {
        deleteUserObjetsKeys(
            (int) $userId,
        );
    }

    // Create tasks
    createUserTasks($processId, $nbItemsToTreat);

    // update user's new status
    DB::update(
        prefixTable('users'),
        [
            'is_ready_for_usage' => 0,
            'otp_provided' => 1,
            'ongoing_process_id' => $processId,
            'special' => 'generate-keys',
        ],
        'id=%i',
        $userId
    );

    return prepareExchangedData(
        array(
            'error' => false,
            'message' => '',
            'user_password' => $generate_user_new_password === true ? $passwordClear : '',
        ),
        'encode'
    );
}

/**
 * Permits to generate a new password for a user
 *
 * @param integer $processId
 * @param integer $nbItemsToTreat
 * @return void
 
 */
function createUserTasks($processId, $nbItemsToTreat): void
{
    DB::insert(
        prefixTable('background_subtasks'),
        array(
            'task_id' => $processId,
            'created_at' => time(),
            'task' => json_encode([
                'step' => 'step0',
                'index' => 0,
                'nb' => $nbItemsToTreat,
            ]),
        )
    );

    DB::insert(
        prefixTable('background_subtasks'),
        array(
            'task_id' => $processId,
            'created_at' => time(),
            'task' => json_encode([
                'step' => 'step10',
                'index' => 0,
                'nb' => $nbItemsToTreat,
            ]),
        )
    );

    DB::insert(
        prefixTable('background_subtasks'),
        array(
            'task_id' => $processId,
            'created_at' => time(),
            'task' => json_encode([
                'step' => 'step20',
                'index' => 0,
                'nb' => $nbItemsToTreat,
            ]),
        )
    );

    DB::insert(
        prefixTable('background_subtasks'),
        array(
            'task_id' => $processId,
            'created_at' => time(),
            'task' => json_encode([
                'step' => 'step30',
                'index' => 0,
                'nb' => $nbItemsToTreat,
            ]),
        )
    );

    DB::insert(
        prefixTable('background_subtasks'),
        array(
            'task_id' => $processId,
            'created_at' => time(),
            'task' => json_encode([
                'step' => 'step40',
                'index' => 0,
                'nb' => $nbItemsToTreat,
            ]),
        )
    );

    DB::insert(
        prefixTable('background_subtasks'),
        array(
            'task_id' => $processId,
            'created_at' => time(),
            'task' => json_encode([
                'step' => 'step50',
                'index' => 0,
                'nb' => $nbItemsToTreat,
            ]),
        )
    );

    DB::insert(
        prefixTable('background_subtasks'),
        array(
            'task_id' => $processId,
            'created_at' => time(),
            'task' => json_encode([
                'step' => 'step60',
                'index' => 0,
                'nb' => $nbItemsToTreat,
            ]),
        )
    );
}

/**
 * Permeits to check the consistency of date versus columns definition
 *
 * @param string $table
 * @param array $dataFields
 * @return array
 */
function validateDataFields(
    string $table,
    array $dataFields
): array
{
    // Get table structure
    $result = DB::query(
        "SELECT `COLUMN_NAME`, `CHARACTER_MAXIMUM_LENGTH` FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '%l' AND TABLE_NAME = '%l';",
        DB_NAME,
        $table
    );

    foreach ($result as $row) {
        $field = $row['COLUMN_NAME'];
        $maxLength = is_null($row['CHARACTER_MAXIMUM_LENGTH']) === false ? (int) $row['CHARACTER_MAXIMUM_LENGTH'] : '';

        if (isset($dataFields[$field]) === true && is_array($dataFields[$field]) === false && empty($maxLength) === false) {
            if (strlen((string) $dataFields[$field]) > $maxLength) {
                return [
                    'state' => false,
                    'field' => $field,
                    'maxLength' => $maxLength,
                    'currentLength' => strlen((string) $dataFields[$field]),
                ];
            }
        }
    }
    
    return [
        'state' => true,
        'message' => '',
    ];
}

/**
 * Adapt special characters sanitized during filter_var with option FILTER_SANITIZE_SPECIAL_CHARS operation
 *
 * @param string $string
 * @return string
 */
function filterVarBack(string $string): string
{
    $arr = [
        '&#060;' => '<',
        '&#062;' => '>',
        '&#034;' => '"',
        '&#039;' => "'",
        '&#038;' => '&',
    ];

    foreach ($arr as $key => $value) {
        $string = str_replace($key, $value, $string);
    }

    return $string;
}

/**
 * 
 */
function storeTask(
    string $taskName,
    int $user_id,
    int $is_personal_folder,
    int $folder_destination_id,
    int $item_id,
    string $object_keys,
    array $fields_keys = [],
    array $files_keys = []
)
{
    if (in_array($taskName, ['item_copy', 'new_item', 'update_item'])) {
        // Create process
        DB::insert(
            prefixTable('background_tasks'),
            array(
                'created_at' => time(),
                'process_type' => $taskName,
                'arguments' => json_encode([
                    'item_id' => $item_id,
                    'object_key' => $object_keys,
                ]),
                'item_id' => $item_id,
            )
        );
        $processId = DB::insertId();

        // Create tasks
        // 1- Create password sharekeys for users of this new ITEM
        DB::insert(
            prefixTable('background_subtasks'),
            array(
                'task_id' => $processId,
                'created_at' => time(),
                'task' => json_encode([
                    'step' => 'create_users_pwd_key',
                    'index' => 0,
                ]),
            )
        );

        // 2- Create fields sharekeys for users of this new ITEM
        DB::insert(
            prefixTable('background_subtasks'),
            array(
                'task_id' => $processId,
                'created_at' => time(),
                'task' => json_encode([
                    'step' => 'create_users_fields_key',
                    'index' => 0,
                    'fields_keys' => $fields_keys,
                ]),
            )
        );

        // 3- Create files sharekeys for users of this new ITEM
        DB::insert(
            prefixTable('background_subtasks'),
            array(
                'task_id' => $processId,
                'created_at' => time(),
                'task' => json_encode([
                    'step' => 'create_users_files_key',
                    'index' => 0,
                    'files_keys' => $files_keys,
                ]),
            )
        );
    }
}

/**
 * 
 */
function createTaskForItem(
    string $processType,
    string|array $taskName,
    int $itemId,
    int $userId,
    string $objectKey,
    int $parentId = -1,
    array $fields_keys = [],
    array $files_keys = []
)
{
    // 1- Create main process
    // ---
    
    // Create process
    DB::insert(
        prefixTable('background_tasks'),
        array(
            'created_at' => time(),
            'process_type' => $processType,
            'arguments' => json_encode([
                'all_users_except_id' => (int) $userId,
                'item_id' => (int) $itemId,
                'object_key' => $objectKey,
                'author' => (int) $userId,
            ]),
            'item_id' => (int) $parentId !== -1 ?  $parentId : null,
        )
    );
    $processId = DB::insertId();

    // 2- Create expected tasks
    // ---
    if (is_array($taskName) === false) {
        $taskName = [$taskName];
    }
    foreach($taskName as $task) {
        error_log('createTaskForItem - task: '.$task);
        switch ($task) {
            case 'item_password':
                
                DB::insert(
                    prefixTable('background_subtasks'),
                    array(
                        'task_id' => $processId,
                        'created_at' => time(),
                        'task' => json_encode([
                            'step' => 'create_users_pwd_key',
                            'index' => 0,
                        ]),
                    )
                );

                break;
            case 'item_field':
                
                DB::insert(
                    prefixTable('background_subtasks'),
                    array(
                        'task_id' => $processId,
                        'created_at' => time(),
                        'task' => json_encode([
                            'step' => 'create_users_fields_key',
                            'index' => 0,
                            'fields_keys' => $fields_keys,
                        ]),
                    )
                );

                break;
            case 'item_file':

                DB::insert(
                    prefixTable('background_subtasks'),
                    array(
                        'task_id' => $processId,
                        'created_at' => time(),
                        'task' => json_encode([
                            'step' => 'create_users_files_key',
                            'index' => 0,
                            'fields_keys' => $files_keys,
                        ]),
                    )
                );
                break;
            default:
                # code...
                break;
        }
    }
}


function deleteProcessAndRelatedTasks(int $processId)
{
    // Delete process
    DB::delete(
        prefixTable('background_tasks'),
        'id=%i',
        $processId
    );

    // Delete tasks
    DB::delete(
        prefixTable('background_subtasks'),
        'task_id=%i',
        $processId
    );

}

/**
 * Return PHP binary path
 *
 * @return string
 */
function getPHPBinary(): string
{
    // Get PHP binary path
    $phpBinaryFinder = new PhpExecutableFinder();
    $phpBinaryPath = $phpBinaryFinder->find();
    return $phpBinaryPath === false ? 'false' : $phpBinaryPath;
}



/**
 * Delete unnecessary keys for personal items
 *
 * @param boolean $allUsers
 * @param integer $user_id
 * @return void
 */
function purgeUnnecessaryKeys(bool $allUsers = true, int $user_id=0)
{
    if ($allUsers === true) {
        // Load class DB
        if (class_exists('DB') === false) {
            loadClasses('DB');
        }

        $users = DB::query(
            'SELECT id
            FROM ' . prefixTable('users') . '
            WHERE id NOT IN ('.OTV_USER_ID.', '.TP_USER_ID.', '.SSH_USER_ID.', '.API_USER_ID.')
            ORDER BY login ASC'
        );
        foreach ($users as $user) {
            purgeUnnecessaryKeysForUser((int) $user['id']);
        }
    } else {
        purgeUnnecessaryKeysForUser((int) $user_id);
    }
}

/**
 * Delete unnecessary keys for personal items
 *
 * @param integer $user_id
 * @return void
 */
function purgeUnnecessaryKeysForUser(int $user_id=0)
{
    if ($user_id === 0) {
        return;
    }

    // Load class DB
    loadClasses('DB');

    $personalItems = DB::queryFirstColumn(
        'SELECT id
        FROM ' . prefixTable('items') . ' AS i
        INNER JOIN ' . prefixTable('log_items') . ' AS li ON li.id_item = i.id
        WHERE i.perso = 1 AND li.action = "at_creation" AND li.id_user IN (%i, '.TP_USER_ID.')',
        $user_id
    );
    if (count($personalItems) > 0) {
        // Item keys
        DB::delete(
            prefixTable('sharekeys_items'),
            'object_id IN %li AND user_id NOT IN (%i, '.TP_USER_ID.')',
            $personalItems,
            $user_id
        );
        // Files keys
        DB::delete(
            prefixTable('sharekeys_files'),
            'object_id IN %li AND user_id NOT IN (%i, '.TP_USER_ID.')',
            $personalItems,
            $user_id
        );
        // Fields keys
        DB::delete(
            prefixTable('sharekeys_fields'),
            'object_id IN %li AND user_id NOT IN (%i, '.TP_USER_ID.')',
            $personalItems,
            $user_id
        );
        // Logs keys
        DB::delete(
            prefixTable('sharekeys_logs'),
            'object_id IN %li AND user_id NOT IN (%i, '.TP_USER_ID.')',
            $personalItems,
            $user_id
        );
    }
}

/**
 * Generate recovery keys file
 *
 * @param integer $userId
 * @param array $SETTINGS
 * @return string
 */
function handleUserRecoveryKeysDownload(int $userId, array $SETTINGS):string
{
    $session = SessionManager::getSession();
    // Check if user exists
    $userInfo = DB::queryFirstRow(
        'SELECT pw, public_key, private_key, login, name
        FROM ' . prefixTable('users') . '
        WHERE id = %i',
        $userId
    );

    if (DB::count() > 0) {
        $now = (int) time();

        // Prepare file content
        $export_value = file_get_contents(__DIR__."/../includes/core/teampass_ascii.txt")."\n".
            "Generation date: ".date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], $now)."\n\n".
            "RECOVERY KEYS - Not to be shared - To be store safely\n\n".
            "Public Key:\n".$userInfo['public_key']."\n\n".
            "Private Key:\n".decryptPrivateKey($session->get('user-password'), $userInfo['private_key'])."\n\n";

        // Update user's keys_recovery_time
        DB::update(
            prefixTable('users'),
            [
                'keys_recovery_time' => $now,
            ],
            'id=%i',
            $userId
        );
        $session->set('user-keys_recovery_time', $now);

        //Log into DB the user's disconnection
        logEvents($SETTINGS, 'user_mngt', 'at_user_keys_download', (string) $userId, $userInfo['login']);
        
        // Return data
        return prepareExchangedData(
            array(
                'error' => false,
                'datetime' => date($SETTINGS['date_format'] . ' ' . $SETTINGS['time_format'], $now),
                'timestamp' => $now,
                'content' => base64_encode($export_value),
                'login' => $userInfo['login'],
            ),
            'encode'
        );
    }

    return prepareExchangedData(
        array(
            'error' => true,
            'datetime' => '',
        ),
        'encode'
    );
}

/**
 * Permits to load expected classes
 *
 * @param string $className
 * @return void
 */
function loadClasses(string $className = ''): void
{
    require_once __DIR__. '/../includes/config/include.php';
    require_once __DIR__. '/../includes/config/settings.php';
    require_once __DIR__.'/../vendor/autoload.php';

    if (defined('DB_PASSWD_CLEAR') === false) {
        define('DB_PASSWD_CLEAR', defuseReturnDecrypted(DB_PASSWD, []));
    }

    if (empty($className) === false) {
        // Load class DB
        if ((string) $className === 'DB') {
            //Connect to DB
            DB::$host = DB_HOST;
            DB::$user = DB_USER;
            DB::$password = DB_PASSWD_CLEAR;
            DB::$dbName = DB_NAME;
            DB::$port = DB_PORT;
            DB::$encoding = DB_ENCODING;
            DB::$ssl = DB_SSL;
            DB::$connect_options = DB_CONNECT_OPTIONS;
        }
    }

}

/**
 * Returns the page the user is visiting.
 *
 * @return string The page name
 */
function getCurrectPage($SETTINGS)
{
    
    $request = SymfonyRequest::createFromGlobals();

    // Parse the url
    parse_str(
        substr(
            (string) $request->getRequestUri(),
            strpos((string) $request->getRequestUri(), '?') + 1
        ),
        $result
    );

    return $result['page'];
}

/**
 * Permits to return value if set
 *
 * @param string|int $value
 * @param string|int|null $retFalse
 * @param string|int $retTrue
 * @return mixed
 */
function returnIfSet($value, $retFalse = '', $retTrue = null): mixed
{

    return isset($value) === true ? ($retTrue === null ? $value : $retTrue) : $retFalse;
}


/**
 * SEnd email to user
 *
 * @param string $post_receipt
 * @param string $post_body
 * @param string $post_subject
 * @param array $post_replace
 * @param boolean $immediate_email
 * @return string
 */
function sendMailToUser(
    string $post_receipt,
    string $post_body,
    string $post_subject,
    array $post_replace,
    bool $immediate_email = false
): ?string {
    global $SETTINGS;
    if (count($post_replace) > 0 && is_null($post_replace) === false) {
        $post_body = str_replace(
            array_keys($post_replace),
            array_values($post_replace),
            $post_body
        );
    }

    if ($immediate_email === true) {
        $ret = sendEmail(
            $post_subject,
            $post_body,
            $post_receipt,
            $SETTINGS,
            '',
            false
        );
    
        $ret = json_decode($ret, true);
    
        return prepareExchangedData(
            array(
                'error' => empty($ret['error']) === true ? false : true,
                'message' => $ret['message'],
            ),
            'encode'
        );
    } else {
        // Send through task handler
        prepareSendingEmail(
            $post_subject,
            $post_body,
            $post_receipt,
            ""
        );
    }

    return null;
}

/**
 * Converts a password strengh value to zxcvbn level
 * 
 * @param integer $passwordStrength
 * 
 * @return integer
 */
function convertPasswordStrength($passwordStrength): int
{
    if ($passwordStrength === 0) {
        return TP_PW_STRENGTH_1;
    } else if ($passwordStrength === 1) {
        return TP_PW_STRENGTH_2;
    } else if ($passwordStrength === 2) {
        return TP_PW_STRENGTH_3;
    } else if ($passwordStrength === 3) {
        return TP_PW_STRENGTH_4;
    } else {
        return TP_PW_STRENGTH_5;
    }
}