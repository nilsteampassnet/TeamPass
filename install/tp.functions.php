<?php


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
    // load PhpEncryption library
    $path = '../includes/libraries/Encryption/Encryption/';

    if (!class_exists('Defuse\Crypto\Crypto', false)) {
		include_once $path . 'Exception/CryptoException.php';
		include_once $path . 'Exception/BadFormatException.php';
		include_once $path . 'Exception/EnvironmentIsBrokenException.php';
		include_once $path . 'Exception/IOException.php';
		include_once $path . 'Exception/WrongKeyOrModifiedCiphertextException.php';
		include_once $path . 'Crypto.php';
		include_once $path . 'Encoding.php';
		include_once $path . 'DerivedKeys.php';
		include_once $path . 'Key.php';
		include_once $path . 'KeyOrPassword.php';
		include_once $path . 'File.php';
		include_once $path . 'RuntimeTests.php';
		include_once $path . 'KeyProtectedByPassword.php';
		include_once $path . 'Core.php';
	}

    include_once '../includes/config/settings.php';

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
 * @return void
 */
function getSettingValue($val)
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
        if ($col['Field'] == $column) {
            $exists = true;
            return true;
        }
    }
    if (!$exists) {
        return mysqli_query($db_link, "ALTER TABLE `$dbname` ADD `$column`  $columnAttr");
    }

    return false;
}

/**
 * Undocumented function
 *
 * @param string $table Table
 * @param string $index Index
 * @param string $sql   SQL
 *
 * @return array
 */
function addIndexIfNotExist($table, $index, $sql)
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
 * Undocumented function
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
    // load passwordLib library
    $path = '../includes/libraries/PasswordGenerator/Generator/';
    include_once $path.'ComputerPasswordGenerator.php';

    $generator = new PasswordGenerator\Generator\ComputerPasswordGenerator();

    $generator->setLength(40);
    $generator->setSymbols(false);
    $generator->setLowercase(true);
    $generator->setUppercase(true);
    $generator->setNumbers(true);

    $key = $generator->generatePasswords();

    return $key[0];
}
