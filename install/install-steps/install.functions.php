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
 * @file      install.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
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
if (!function_exists('resolveInstallAsciiKey')) {
    /**
     * Resolve the Defuse ASCII key used during installation.
     *
     * @param string     $ascii_key Explicit ASCII key if already known.
     * @param array|null $SETTINGS  Optional install settings fallback.
     *
     * @return string
     */
    function resolveInstallAsciiKey(string $ascii_key = '', ?array $SETTINGS = []): string
    {
        if ($ascii_key !== '') {
            return $ascii_key;
        }

        $securePath = '';
        $secureFile = '';

        if (defined('SECUREPATH') === true && SECUREPATH !== '') {
            $securePath = SECUREPATH;
        } elseif (is_array($SETTINGS) === true) {
            $securePath = $SETTINGS['SECUREPATH']
                ?? $SETTINGS['teampassSecurePath']
                ?? '';
        }

        if (defined('SECUREFILE') === true && SECUREFILE !== '') {
            $secureFile = SECUREFILE;
        } elseif (is_array($SETTINGS) === true) {
            $secureFile = $SETTINGS['SECUREFILE']
                ?? $SETTINGS['teampassSecureFile']
                ?? '';
        }

        if ($securePath === '' || $secureFile === '') {
            throw new RuntimeException('Missing SECUREPATH/SECUREFILE for installation cryptography context.');
        }

        $keyPath = rtrim($securePath, '/') . '/' . $secureFile;
        if (is_readable($keyPath) === false) {
            throw new RuntimeException('Encryption key file is missing or unreadable: ' . $keyPath);
        }

        $resolvedKey = file_get_contents($keyPath);
        if ($resolvedKey === false || $resolvedKey === '') {
            throw new RuntimeException('Unable to read encryption key file: ' . $keyPath);
        }

        return $resolvedKey;
    }
}

function cryptionForInstall(string $message, string $ascii_key, string $type, ?array $SETTINGS = []): array
{
    $ascii_key = resolveInstallAsciiKey($ascii_key, $SETTINGS);
    $err = false;
    
    try {
        // convert KEY (throws BadFormatException if key is malformed)
        $key = Key::loadFromAsciiSafeString($ascii_key);
        if ($type === 'encrypt') {
            $text = Crypto::encrypt($message, $key);
        } elseif ($type === 'decrypt') {
            $text = Crypto::decrypt($message, $key);
        } else {
            throw new InvalidArgumentException('Unsupported cryptionForInstall type: ' . $type);
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

    // Generate RSA key pair using CryptoManager (phpseclib v3)
    $res = \TeampassClasses\CryptoManager\CryptoManager::generateRSAKeyPair(4096);

    // Encrypt the private key with user password using AES (SHA-256 for v3)
    $privatekey = \TeampassClasses\CryptoManager\CryptoManager::aesEncrypt($res['privatekey'], $userPwd, 'cbc', 'sha256');

    return [
        'private_key' => base64_encode($privatekey),
        'public_key' => base64_encode($res['publickey']),
        'private_key_clear' => base64_encode($res['privatekey']),
    ];
}
