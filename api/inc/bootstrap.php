<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass API
 *
 * @file      bootstrap.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */
define("PROJECT_ROOT_PATH", __DIR__ . "/..");

// include main configuration file
require_once "../includes/config/settings.php";
require_once "../includes/config/tp.config.php";

if (defined('DB_PASSWD_CLEAR') === false) {
    define('DB_PASSWD_CLEAR', cryption(DB_PASSWD, 'decrypt', PROJECT_ROOT_PATH.'/../')['string']);
}

// include the base controller file
require_once PROJECT_ROOT_PATH . "/Controller/Api/BaseController.php";

// include the use model file
require_once PROJECT_ROOT_PATH . "/Model/UserModel.php";
require_once PROJECT_ROOT_PATH . "/Model/ItemModel.php";


function cryption(string $message, string $type, $path): array
{
    $err = false;
    $path = $path . 'includes/libraries/Encryption/Encryption/';
    $ascii_key = file_get_contents(SECUREPATH . '/teampass-seckey.txt');
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
    //echo \Defuse\Crypto\Crypto::decrypt($message, $key).' ## ';

    return [
        'string' => $text ?? '',
        'error' => $err,
    ];
}

function prefixTable(string $table): string
{
    $safeTable = htmlspecialchars(DB_PREFIX . $table);
    if (! empty($safeTable)) {
        // sanitize string
        return $safeTable;
    }
    // stop error no table
    return 'table_not_exists';
}