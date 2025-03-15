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
 * @file      run.step1.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use Elegant\Sanitizer\Sanitizer;
use voku\helper\AntiXSS;
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\RandomGenerator\Php7RandomGenerator;
use Defuse\Crypto\Key;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception as CryptoException;

// Check if function exists
if (!function_exists('dataSanitizer')) {
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
}


/**
 * GenerateCryptKeyForInstall
 *
 * @param int     $size      Length
 * @param bool $secure Secure
 * @param bool $numerals Numerics
 * @param bool $uppercase Uppercase letters
 * @param bool $symbols Symbols
 * @param bool $lowercase Lowercase
 * 
 * @return string
 */
function GenerateCryptKeyForInstall(
    int $size = 20,
    bool $secure = false,
    bool $numerals = false,
    bool $uppercase = false,
    bool $symbols = false,
    bool $lowercase = false
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
 * Defuse cryptionForInstall function.
 *
 * @param string $message   what to de/crypt
 * @param string $ascii_key key to use
 * @param string $type      operation to perform
 * @param array  $SETTINGS  Teampass settings
 *
 * @return array
 */
function cryptionForInstall(string $message, string $ascii_key, string $type, ?array $SETTINGS = []): array
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
        error_log('TEAMPASS-Error-Wrong key or modified ciphertext: ' . $ex->getMessage());
        $err = 'wrong_key_or_modified_ciphertext';
    } catch (CryptoException\BadFormatException $ex) {
        error_log('TEAMPASS-Error-Bad format exception: ' . $ex->getMessage());
        $err = 'bad_format';
    } catch (CryptoException\EnvironmentIsBrokenException $ex) {
        error_log('TEAMPASS-Error-Environment: ' . $ex->getMessage());
        $err = 'environment_error';
    } catch (CryptoException\IOException $ex) {
        error_log('TEAMPASS-Error-IO: ' . $ex->getMessage());
        $err = 'io_error';
    } catch (Exception $ex) {
        error_log('TEAMPASS-Error-Unexpected exception: ' . $ex->getMessage());
        $err = 'unexpected_error';
    }

    return [
        'string' => $text ?? '',
        'error' => $err,
    ];
}


/**
 * Generate user pair of keys.
 *
 * @param string $userPwd User password
 *
 * @return array
 */
function generateUserKeysForInstall(string $userPwd): array
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