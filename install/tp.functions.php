<?php
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
 * @file      tp.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception as CryptoException;
use Elegant\Sanitizer\Sanitizer;
use voku\helper\AntiXSS;

// new SECUREFILE - 3.0.0.23
function handleSecurefileConstant()
{
    if (defined('SECUREFILE') === false || SECUREFILE === 'teampass-seckey.txt' || file_exists(SECUREPATH.'/teampass-seckey.txt') === true) {
        // Anonymize the file if needed
        if (defined('SECUREFILE') === false) {
            define('SECUREFILE', generateRandomKey());
        }
    
        // manage the file itself by renaming it
        if (rename(SECUREPATH.'/teampass-seckey.txt', SECUREPATH.'/'.SECUREFILE) === false) {
            echo '[{
                "error" : "File `'.SECUREPATH.'/teampass-seckey.txt` could not be renamed. Please do it by yourself and click on button Launch.",
                "index" : ""
            }]';
            exit;
        }

        // Ensure DB is read as UTF8
        if (defined('DB_ENCODING') === false) {
            define('DB_ENCODING', "utf8");
        }

        // Now create new file
        $file_handled = fopen('../includes/config/settings.php', 'w');
        
        $settingsTxt = '<?php
// DATABASE connexion parameters
define("DB_HOST", "' . DB_HOST . '");
define("DB_USER", "' . DB_USER . '");
define("DB_PASSWD", "' . DB_PASSWD . '");
define("DB_NAME", "' . DB_NAME . '");
define("DB_PREFIX", "' . DB_PREFIX . '");
define("DB_PORT", "' . DB_PORT . '");
define("DB_ENCODING", "' . DB_ENCODING . '");';

if (isset(DB_SSL['key']) === true && empty(DB_SSL['key']) === false) {
    $settingsTxt .= '
define("DB_SSL", false); // if DB over SSL then comment this line
// if DB over SSL then uncomment the following lines
//define("DB_SSL", array(
//    "key" => "",
//    "cert" => "",
//    "ca_cert" => "",
//    "ca_path" => "",
//    "cipher" => ""
//));';
} else {
    $settingsTxt .= '
//define("DB_SSL", false); // if DB over SSL then comment this line
// if DB over SSL then uncomment the following lines
define("DB_SSL", array(
    "key" => "'.DB_SSL['key'].'",
    "cert" => "'.DB_SSL['cert'].'",
    "ca_cert" => ""'.DB_SSL['ca_cert'].',
    "ca_path" => "'.DB_SSL['ca_path'].'",
    "cipher" => "'.DB_SSL['cipher'].'"
));';
}

$settingsTxt .= '
define("DB_CONNECT_OPTIONS", array(
    MYSQLI_OPT_CONNECT_TIMEOUT => 10
));
define("SECUREPATH", "' . str_replace('\\', '\\\\', SECUREPATH) . '");
define("SECUREFILE", "' . SECUREFILE. '");';

        if (defined('IKEY') === true) $settingsTxt .= '
define("IKEY", "' . IKEY . '");';
        else $settingsTxt .= '
define("IKEY", "");';
        if (defined('SKEY') === true) $settingsTxt .= '
define("SKEY", "' . SKEY . '");';
        else $settingsTxt .= '
define("SKEY", "");';
        if (defined('HOST') === true) $settingsTxt .= '
define("HOST", "' . HOST . '");';
        else $settingsTxt .= '
define("HOST", "");';


        $settingsTxt .= '

if (isset($_SESSION[\'settings\'][\'timezone\']) === true) {
    date_default_timezone_set($_SESSION[\'settings\'][\'timezone\']);
}
';

        $fileCreation = fwrite(
            $file_handled,
            utf8_encode($settingsTxt)
        );

        fclose($file_handled);
        sleep(3);
        if ($fileCreation === false) {
            return [
                'error' => true,
                'message' => 'Setting.php file could not be created in /includes/config/ folder. Please check the path and the rights.',
            ];
        }

        return [
            'error' => false,
            'message' => ''
        ];
    }
}

/**
 * Undocumented function
 *
 * @param string $message   Message
 * @param string $ascii_key Key
 * @param string $type      Type
 *
 * @return array
 */
function defuseCryption($message, $ascii_key, $type)
{
    include_once '../includes/config/settings.php';

    // init
    $err = '';
    if (empty($ascii_key) === true) {
        // new check - 3.0.0.23
        $ascii_key = file_get_contents(SECUREPATH.'/'.SECUREFILE);
    }
    
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

    return array(
        'string' => isset($text) ? $text : '',
        'error' => $err,
    );
}


/**
 * Decrypt a defuse string if encrypted
 *
 * @param string $value Encrypted string
 *
 * @return string
 */
function defuse_return_decrypted($value)
{
    if (substr($value, 0, 3) === "def") {
        $value = defuseCryption(
            $value,
            "",
            "decrypt"
        )['string'];
    }
    return $value;
}

/**
 * Function permits to get the value from a line
 *
 * @param string $val A string
 *
 * @return string
 */
function getSettingValue($val): string
{
    $val = trim(strstr($val, "="));
    return trim(str_replace('"', '', substr($val, 1, strpos($val, ";") - 1)));
}

/**
 * Undocumented function
 *
 * @param string $dbname     DB
 * @param string $column     Column
 * @param string $columnAttr Attribute
 *
 * @return boolean
 */
function addColumnIfNotExist($dbname, $column, $columnAttr = "VARCHAR(255) NULL")
{
    global $db_link;
    $exists = false;
    $columns = mysqli_query($db_link, "show columns from $dbname");
    while ($col = mysqli_fetch_assoc($columns)) {
        if ((string) $col['Field'] === $column) {
            $exists = true;
            return true;
        }
    }
    if (!$exists && empty($column) === false) {
        return mysqli_query($db_link, "ALTER TABLE `$dbname` ADD `$column`  $columnAttr");
    }

    return false;
}

/**
 * Modify a column
 *
 * @param string $tableName  DB
 * @param string $existingColumnName  Column
 * @param string $newColumnName New name
 * @param string $newColumnAttributes New attributes
 *
 * @return boolean
 */
function modifyColumn($tableName, $existingColumnName, $newColumnName, $newColumnAttributes = "VARCHAR(255) NULL")
{
    global $db_link;

    // Vérifie si la colonne existe
    $columnExists = false;
    $queryCheckColumn = mysqli_query($db_link, "SHOW COLUMNS FROM `$tableName` LIKE '$existingColumnName'");
    if (mysqli_num_rows($queryCheckColumn) > 0) {
        $columnExists = true;
    }

    // Si la colonne existe, procède à la modification
    if ($columnExists) {
        // Si le nom de la colonne doit rester le même, mais que les attributs changent
        if ($existingColumnName === $newColumnName) {
            $query = "ALTER TABLE `$tableName` MODIFY COLUMN `$existingColumnName` $newColumnAttributes";
        } else {
            // Change le nom de la colonne et/ou ses attributs
            $query = "ALTER TABLE `$tableName` CHANGE `$existingColumnName` `$newColumnName` $newColumnAttributes";
        }

        return mysqli_query($db_link, $query);
    } else {
        // La colonne n'existe pas, retourne false
        return false;
    }
}


/**
 * Remove column from table if exists
 *
 * @param string $table      Table
 * @param string $column     Column
 *
 * @return boolean
 */
function removeColumnIfNotExist($table, $column): bool
{
    global $db_link;
    $exists = false;
    $columns = mysqli_query($db_link, "show columns from $table");
    while ($col = mysqli_fetch_assoc($columns)) {
        if ((string) $col['Field'] === $column) {
            $exists = true;
        }
    }
    if ($exists === true) {
        return mysqli_query($db_link, "ALTER TABLE `$table` DROP `$column`;");
    }
    return false;
}

/**
 * Check if an INDEX exist, run the SQL query if not
 *
 * @param string $table Table
 * @param string $index Index
 * @param string $sql   SQL
 *
 * @return array
 */
function checkIndexExist($table, $index, $sql)
{
    global $db_link;
    $mysqli_result = mysqli_query($db_link, "SHOW INDEX FROM $table WHERE key_name LIKE \"$index\"");
    $res = mysqli_fetch_row($mysqli_result);

    // if index does not exist, then add it
    if (!$res) {
        $res = mysqli_query(
            $db_link,
            "ALTER TABLE `$table` ".$sql
        );
    }

    return $res;
}

/**
 * Check if a table exists in DB
 *
 * @param string $tablename Table
 *
 * @return boolean
 */
function tableExists($tablename)
{
    global $db_link, $database;

    $res = mysqli_query(
        $db_link,
        "SELECT COUNT(*) as count
        FROM information_schema.tables
        WHERE table_schema = '".$database."'
        AND table_name = '$tablename'"
    );

    if ($res > 0) {
        return true;
    }

    return false;
}

/**
 * Undocumented function
 *
 * @param string $txt My text
 *
 * @return string
 */
function cleanFields($txt)
{
    $tmp = str_replace(",", ";", trim($txt));
    if (empty($tmp)) {
        return $tmp;
    }
    if ($tmp === ";") {
        return "";
    }
    if (strpos($tmp, ';') === 0) {
        $tmp = substr($tmp, 1);
    }
    if (substr($tmp, -1) !== ";") {
        $tmp = $tmp.";";
    }
    return $tmp;
}

/**
 * Undocumented function
 *
 * @return string
 */
function generateRandomKey()
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    $n = 40;
 
    for ($i = 0; $i < $n; $i++) {
        $index = rand(0, strlen($characters) - 1);
        $randomString .= $characters[$index];
    }
 
    return $randomString;
}

/**
 * Undocumented function
 *
 * @param string $table
 * @param string $type
 * @param string $label
 * @param string $value
 * @return void
 */
function addNewSetting($table, $type, $label, $value): void
{
    global $db_link;

    // check if setting already exists
    $data = mysqli_fetch_row(mysqli_query($db_link, "SELECT COUNT(*) FROM ".$table." WHERE type = '".$type."' AND intitule = '".$label."'"));
    if ((int) $data[0] === 0) {
        // add setting
        mysqli_query(
            $db_link,
            "INSERT INTO ".$table."
            (`type`, `intitule`, `valeur`)
            VALUES ('".$type."', '".$label."', '".$value."')"
        );
    }
}

/**
 * Permits to remove a setting
 *
 * @param string $table
 * @param string $type
 * @param string $label
 * @return void
 */
function removeSetting($table, $type, $label): void
{
    global $db_link;

    // check if setting already exists
    $data = mysqli_fetch_row(mysqli_query($db_link, "SELECT COUNT(*) FROM ".$table." WHERE type = '".$type."' AND intitule = '".$label."'"));
    if ((int) $data[0] === 1) {
        // delete setting
        mysqli_query(
            $db_link,
            "DELETE FROM ".$table."
            WHERE type = '".$type."' AND intitule = '".$label."'"
        );
    }
}

/**
 * Permits to change a column name if exists
 *
 * @param string $table
 * @param string $oldName
 * @param string $newName
 * @param string $type
 * @return void
 */
function changeColumnName($table, $oldName, $newName, $type): void
{
    global $db_link;

    // check if column already exists
    $columns = mysqli_query($db_link, "show columns from `" . $table . "`");
    while ($col = mysqli_fetch_assoc($columns)) {
        if ((string) $col['Field'] === $oldName) {
            // change column name
            mysqli_query(
                $db_link,
                "ALTER TABLE ".$table." CHANGE `".$oldName."` `".$newName."` ".$type
            );
            break;
        }
    }
}

/**
 * Permits to locate the php binary
 * 
 * @return array
 */
function findPhpBinary(): array
{
    $phpPath = '';
    
    // Essayer de trouver le fichier binaire de PHP dans les chemins de recherche standards
    $paths = explode(PATH_SEPARATOR, getenv('PATH'));
    foreach ($paths as $path) {
        $phpBinary = $path . DIRECTORY_SEPARATOR . 'php';
        if (is_executable($phpBinary)) {
        $phpPath = $phpBinary;
        break;
        }
    }

    // Si le fichier binaire de PHP n'a pas été trouvé, on essaie de le chercher via les variables d'environnement
    if (!$phpPath && getenv('PHP_BINARY')) {
        $phpPath = getenv('PHP_BINARY');
    }

    // Si on n'a toujours pas trouvé le fichier binaire de PHP, on lance une exception
    if (!$phpPath) {
        return [
            'path' => '',
            'error' => true,
        ];
    }

    return [
        'path' => $phpPath,
        'error' => false,
    ];
}

/**
 * delete all files and directories
 *
 * @param array $folders
 * @return void
 */
function deleteAll(array $folders)
{

    foreach($folders as $folder) {
        deleteAllFolder($folder);
    }
}

/**
 * Delete recursively a folder
 *
 * @param string $str
 * @return void
 */
function deleteAllFolder(string $str)
{
    // Check for files 
    if (is_file($str)) { 
        // If it is file then remove by 
        // using unlink function 
        @unlink($str); 
    } 
    // If it is a directory. 
    elseif (is_dir($str)) { 
        // Get the list of the files in this 
        // directory 
        $scan = glob(rtrim($str, '/').'/*'); 

        // Loop through the list of files 
        foreach($scan as $index=>$path) { 

            // Call recursive function 
            deleteAllFolder($path); 
        } 

        // Remove the directory itself 
        @rmdir($str); 
    } 
}


/**
 * Permits to encrypt a message using Defuse.
 *
 * @param string $message   Message to encrypt
 * @param string $ascii_key Key to hash
 *
 * @return array String + Error
 */
function encryptFollowingDefuseForInstall($message, $ascii_key): array
{
    // convert KEY
    $key = Key::loadFromAsciiSafeString($ascii_key);
    $err = "";

    try {
        $text = Crypto::encrypt($message, $key);
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

    return array(
        'string' => isset($text) ? $text : '',
        'error' => $err,
    );
}

/**
 * Uses Sanitizer to perform data sanitization
 *
 * @param array     $data
 * @param array     $filters
 * @return array|string
 */
function dataSanitizerForInstall(array $data, array $filters): array|string
{
    // Load Sanitizer library
    $sanitizer = new Sanitizer($data, $filters);

    // Load AntiXSS
    $antiXss = new AntiXSS();

    // Sanitize post and get variables
    return $antiXss->xss_clean($sanitizer->sanitize());
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

function recursiveChmodForInstall(
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