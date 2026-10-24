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

// DB-free installer security logic (escaping, action allow-list, CSRF comparison).
// Required here so every installer step that loads this file can reach the helpers below.
if (defined('TEAMPASS_ROOT')) {
    require_once TEAMPASS_ROOT . '/app/sources/install_security_logic.php';
}

/**
 * Start the installer session (native PHP session, default cookie name).
 *
 * The installer runs before settings.php exists, so SessionManager is not available; a plain
 * session is used, the same one cleanup_install.php relies on. Idempotent and header-safe.
 *
 * @return void
 */
function installStartSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
        @session_start();
    }
}

/**
 * Create (once) and return the install CSRF token held in the session.
 *
 * Called by install.php to seed the token and mark the session as an authorised install flow.
 * The token is emitted to the page as <meta name="csrf-token"> and sent back by install.js on
 * every step as the X-CSRF-Token header.
 *
 * @return string The install CSRF token.
 */
function installIssueCsrfToken(): string
{
    installStartSession();
    if (empty($_SESSION['install_csrf']) || !is_string($_SESSION['install_csrf'])) {
        $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
    }
    $_SESSION['install_granted'] = '1';

    return $_SESSION['install_csrf'];
}

/**
 * Guard an installer step endpoint.
 *
 * Refuses the request unless it carries a valid install-session grant and a matching CSRF token.
 * An unauthenticated request (no session cookie, no token) is rejected with 403 before any POST
 * input is processed — the primary fix for GHSA-8xp7-fh3p-5pcr (unauthenticated RCE via the
 * re-runnable installer). On failure it emits a JSON body and terminates.
 *
 * @return void
 */
function installAssertStepAuthorized(): void
{
    installStartSession();

    $granted = isset($_SESSION['install_granted']) && $_SESSION['install_granted'] === '1';
    $sessionToken = isset($_SESSION['install_csrf']) && is_string($_SESSION['install_csrf'])
        ? $_SESSION['install_csrf']
        : null;
    $providedToken = isset($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])
        ? $_SERVER['HTTP_X_CSRF_TOKEN']
        : null;

    if ($granted === true && installCsrfTokenMatches($sessionToken, $providedToken) === true
        && !is_file(TEAMPASS_ROOT . '/storage/recovery/pending.json')
        && (!in_array(basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')), ['run.step4.php', 'run.step5.php', 'run.step6.php'], true)
            || installAcquireWriteLock())) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(403);
        header('Content-type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
    }
    echo json_encode([
        'success' => false,
        'message' => 'Installation request rejected: start the installer from install.php.',
    ]);
    exit;
}

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
    // If no key provided, try to load it from secure file
    // Check if secure file is defined
    // If not, return error and empty string
    if (empty($ascii_key) === true) {
        if (!defined('TEAMPASS_SECRETS') || !defined('SECUREFILE')) {
            return ['string' => '', 'error' => 'securepath_not_defined'];
        }
        $ascii_key = file_get_contents(TEAMPASS_SECRETS.'/'.SECUREFILE);
    }
    $err = false;
    
    try {
        // convert KEY (throws BadFormatException if key is malformed)
        $key = Key::loadFromAsciiSafeString($ascii_key);
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
    // NOTE: xss_clean() is intentionally NOT applied to $userPwd: AES v2 private
    // keys are always encrypted with the raw password (decryptPrivateKey() only
    // attempts the raw password on the "v2:" format).

    // Generate RSA key pair using CryptoManager (phpseclib v3)
    $res = \TeampassClasses\CryptoManager\CryptoManager::generateRSAKeyPair(4096);

    // Encrypt the private key in AES v2 (authenticated GCM, PBKDF2-SHA256),
    // same format as encryptPrivateKey() in main.functions.php — fresh installs
    // start with aes_v2_write_enabled = 1.
    $v2 = \TeampassClasses\CryptoManager\CryptoManager::aesGcmEncrypt($res['privatekey'], $userPwd, '', 'pbkdf2');
    $privatekey = 'v2:' . base64_encode($v2['meta']) . ':' . base64_encode($v2['ciphertext']);

    return [
        'private_key' => $privatekey,
        'public_key' => base64_encode($res['publickey']),
        'private_key_clear' => base64_encode($res['privatekey']),
    ];
}
