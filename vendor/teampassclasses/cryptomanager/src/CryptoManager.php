<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * @category  Teampass
 * @package   TeampassClasses\CryptoManager
 * @author    Nils Laumaillé <nils@teampass.net>
 * @copyright 2009-2024 Teampass.net
 * @license   GPL-3.0
 * @version   GIT: <git_id>
 * @link      https://www.teampass.net
 */

namespace TeampassClasses\CryptoManager;

use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\AES;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA\PublicKey;
use phpseclib3\Crypt\RSA\PrivateKey;
use Exception;

/**
 * CryptoManager class
 *
 * Wrapper for phpseclib v3 encryption operations with backward compatibility for v1
 */
class CryptoManager
{
    /**
     * Generate RSA key pair
     *
     * @param int $bits Key size in bits (default 4096)
     * @return array Array with keys: 'privatekey', 'publickey', 'private_key_object', 'public_key_object'
     * @throws Exception
     */
    public static function generateRSAKeyPair(int $bits = 4096): array
    {
        try {
            // Try phpseclib v3
            if (class_exists('phpseclib3\\Crypt\\RSA')) {
                $private = RSA::createKey($bits);
                $public = $private->getPublicKey();

                return [
                    'privatekey' => $private->toString('PKCS1'),
                    'publickey' => $public->toString('PKCS1'),
                    'private_key_object' => $private,
                    'public_key_object' => $public,
                ];
            }

            // Fallback to phpseclib v1
            if (class_exists('Crypt_RSA')) {
                $rsa = new \Crypt_RSA();
                $keys = $rsa->createKey($bits);

                return [
                    'privatekey' => $keys['privatekey'],
                    'publickey' => $keys['publickey'],
                    'private_key_object' => null,
                    'public_key_object' => null,
                ];
            }

            throw new Exception('No RSA implementation available (phpseclib v1 or v3 required)');
        } catch (Exception $e) {
            throw new Exception('Failed to generate RSA key pair: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt data using RSA public key
     *
     * @param string $data Data to encrypt
     * @param string $publicKey Public key in PKCS1 format (base64 encoded or plain)
     * @return string Encrypted data (raw binary)
     * @throws Exception
     */
    public static function rsaEncrypt(string $data, string $publicKey): string
    {
        try {
            // Try to decode if base64
            $decodedKey = base64_decode($publicKey, true);
            if ($decodedKey !== false && self::isPEM($decodedKey)) {
                $publicKey = $decodedKey;
            }

            // Try phpseclib v3
            if (class_exists('phpseclib3\\Crypt\\PublicKeyLoader')) {
                $key = PublicKeyLoader::load($publicKey);

                // Ensure we have a PublicKey instance for encryption
                if (!$key instanceof PublicKey) {
                    throw new Exception('Loaded key is not a valid RSA Public Key');
                }

                return $key->encrypt($data);
            }

            // Fallback to phpseclib v1
            if (class_exists('Crypt_RSA')) {
                $rsa = new \Crypt_RSA();
                $rsa->loadKey($publicKey);
                return $rsa->encrypt($data);
            }

            throw new Exception('No RSA implementation available (phpseclib v1 or v3 required)');
        } catch (Exception $e) {
            throw new Exception('Failed to encrypt with RSA: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt data using RSA private key
     *
     * Includes backward compatibility with phpseclib v1 (SHA-1 hash)
     *
     * @param string $data Encrypted data (raw binary or base64)
     * @param string $privateKey Private key in PKCS1 format (base64 encoded or plain)
     * @param bool $tryLegacy Try legacy v1 decryption if v3 fails (default true)
     * @return string Decrypted data
     * @throws Exception
     */
    public static function rsaDecrypt(string $data, string $privateKey, bool $tryLegacy = true): string
    {
        try {
            // Try to decode if base64
            $decodedKey = base64_decode($privateKey, true);
            if ($decodedKey !== false && self::isPEM($decodedKey)) {
                $privateKey = $decodedKey;
            }

            // Try phpseclib v3
            if (class_exists('phpseclib3\\Crypt\\PublicKeyLoader')) {
                $key = PublicKeyLoader::load($privateKey);

                // Ensure we have a PrivateKey instance for decryption
                if (!$key instanceof PrivateKey) {
                    throw new Exception('Loaded key is not a valid RSA Private Key');
                }

                try {
                    // Try with SHA-256 (v3 default)
                    return $key->decrypt($data);
                } catch (Exception $e) {
                    if (!$tryLegacy) {
                        throw $e;
                    }

                    // Fallback to SHA-1 (v1 default) for backward compatibility
                    $key = $key->withHash('sha1')->withMGFHash('sha1');
                    return $key->decrypt($data);
                }
            }

            // Fallback to phpseclib v1
            if (class_exists('Crypt_RSA')) {
                $rsa = new \Crypt_RSA();
                $rsa->loadKey($privateKey);
                return $rsa->decrypt($data);
            }

            throw new Exception('No RSA implementation available (phpseclib v1 or v3 required)');
        } catch (Exception $e) {
            throw new Exception('Failed to decrypt with RSA: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt data using AES
     *
     * Maintains backward compatibility with phpseclib v1 encrypted data
     *
     * @param string $data Data to encrypt
     * @param string $password Password/key for encryption
     * @param string $mode AES mode (cbc, ctr, ecb, cfb, ofb, gcm)
     * @return string Encrypted data (raw binary)
     * @throws Exception
     */
    public static function aesEncrypt(string $data, string $password, string $mode = 'cbc'): string
    {
        try {
            // Try phpseclib v3 first
            if (class_exists('phpseclib3\\Crypt\\AES')) {
                $cipher = new AES($mode);

                // phpseclib v1 used a zero IV when not explicitly set
                // IMPORTANT: Set IV BEFORE setPassword() because PBKDF2 doesn't set IV
                // and setPassword() might reset internal state
                if ($mode === 'cbc') {
                    $cipher->setIV(str_repeat("\0", 16)); // AES block size is 16 bytes
                }

                // Use PBKDF2 with same defaults as phpseclib v1 for compatibility
                // v1 defaults: method='pbkdf2', hash='sha1', salt='phpseclib/salt', iterations=1000
                $cipher->setPassword($password, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);

                return $cipher->encrypt($data);
            }

            // Fallback to phpseclib v1 for backward compatibility
            if (class_exists('Crypt_AES')) {
                $cipher = new \Crypt_AES();

                // Set mode if not CBC (CBC is default)
                if ($mode !== 'cbc') {
                    $modeConstants = [
                        'ctr' => CRYPT_MODE_CTR,
                        'ecb' => CRYPT_MODE_ECB,
                        'cbc' => CRYPT_MODE_CBC,
                        'cfb' => CRYPT_MODE_CFB,
                        'ofb' => CRYPT_MODE_OFB,
                    ];
                    if (isset($modeConstants[$mode])) {
                        $cipher->setMode($modeConstants[$mode]);
                    }
                }

                $cipher->setPassword($password);
                return $cipher->encrypt($data);
            }

            throw new Exception('No AES implementation available (phpseclib v1 or v3 required)');
        } catch (Exception $e) {
            throw new Exception('Failed to encrypt with AES: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt data using AES
     *
     * Maintains backward compatibility with phpseclib v1 encrypted data
     *
     * @param string $data Encrypted data
     * @param string $password Password/key for decryption
     * @param string $mode AES mode (cbc, ctr, ecb, cfb, ofb, gcm)
     * @return string Decrypted data
     * @throws Exception
     */
    public static function aesDecrypt(string $data, string $password, string $mode = 'cbc'): string
    {
        try {
            // Try phpseclib v3 first
            if (class_exists('phpseclib3\\Crypt\\AES')) {
                $cipher = new AES($mode);

                // phpseclib v1 used a zero IV when not explicitly set
                // IMPORTANT: Set IV BEFORE setPassword() because PBKDF2 doesn't set IV
                // and setPassword() might reset internal state
                if ($mode === 'cbc') {
                    $cipher->setIV(str_repeat("\0", 16)); // AES block size is 16 bytes
                }

                // Use PBKDF2 with same defaults as phpseclib v1 for compatibility
                // v1 defaults: method='pbkdf2', hash='sha1', salt='phpseclib/salt', iterations=1000
                $cipher->setPassword($password, 'pbkdf2', 'sha1', 'phpseclib/salt', 1000);

                return $cipher->decrypt($data);
            }

            // Fallback to phpseclib v1 for backward compatibility
            if (class_exists('Crypt_AES')) {
                $cipher = new \Crypt_AES();

                // Set mode if not CBC (CBC is default)
                if ($mode !== 'cbc') {
                    $modeConstants = [
                        'ctr' => CRYPT_MODE_CTR,
                        'ecb' => CRYPT_MODE_ECB,
                        'cbc' => CRYPT_MODE_CBC,
                        'cfb' => CRYPT_MODE_CFB,
                        'ofb' => CRYPT_MODE_OFB,
                    ];
                    if (isset($modeConstants[$mode])) {
                        $cipher->setMode($modeConstants[$mode]);
                    }
                }

                $cipher->setPassword($password);
                return $cipher->decrypt($data);
            }

            throw new Exception('No AES implementation available (phpseclib v1 or v3 required)');
        } catch (Exception $e) {
            throw new Exception('Failed to decrypt with AES: ' . $e->getMessage());
        }
    }

    /**
     * Create AES cipher instance with custom options
     *
     * @param string $mode AES mode (cbc, ctr, ecb, cfb, ofb, gcm)
     * @return AES|\Crypt_AES AES cipher instance
     * @throws Exception
     */
    public static function createAESCipher(string $mode = 'cbc'): object
    {
        try {
            // Try phpseclib v3
            if (class_exists('phpseclib3\\Crypt\\AES')) {
                return new AES($mode);
            }

            // Fallback to phpseclib v1
            if (class_exists('Crypt_AES')) {
                $cipher = new \Crypt_AES();

                // Set mode if not CBC (CBC is default)
                if ($mode !== 'cbc') {
                    $modeConstants = [
                        'ctr' => CRYPT_MODE_CTR,
                        'ecb' => CRYPT_MODE_ECB,
                        'cbc' => CRYPT_MODE_CBC,
                        'cfb' => CRYPT_MODE_CFB,
                        'ofb' => CRYPT_MODE_OFB,
                    ];
                    if (isset($modeConstants[$mode])) {
                        $cipher->setMode($modeConstants[$mode]);
                    }
                }

                return $cipher;
            }

            throw new Exception('No AES implementation available (phpseclib v1 or v3 required)');
        } catch (Exception $e) {
            throw new Exception('Failed to create AES cipher: ' . $e->getMessage());
        }
    }

    /**
     * Load RSA key (public or private)
     *
     * @param string $key Key data (base64 encoded or plain PEM)
     * @return PublicKey|PrivateKey|\Crypt_RSA RSA key object
     * @throws Exception
     */
    public static function loadRSAKey(string $key): object
    {
        try {
            // Try to decode if base64
            $decodedKey = base64_decode($key, true);
            if ($decodedKey !== false && self::isPEM($decodedKey)) {
                $key = $decodedKey;
            }

            // Try phpseclib v3
            if (class_exists('phpseclib3\\Crypt\\PublicKeyLoader')) {
                return PublicKeyLoader::load($key);
            }

            // Fallback to phpseclib v1
            if (class_exists('Crypt_RSA')) {
                $rsa = new \Crypt_RSA();
                $rsa->loadKey($key);
                return $rsa;
            }

            throw new Exception('No RSA implementation available (phpseclib v1 or v3 required)');
        } catch (Exception $e) {
            throw new Exception('Failed to load RSA key: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt data using RSA private key with explicit version
     *
     * Uses the specified phpseclib version for decryption
     *
     * @param string $data Encrypted data (raw binary or base64)
     * @param string $privateKey Private key in PKCS1 format (base64 encoded or plain)
     * @param int $version Encryption version (1=v1/SHA-1, 3=v3/SHA-256)
     * @return string Decrypted data
     * @throws Exception
     */
    public static function rsaDecryptWithVersion(string $data, string $privateKey, int $version): string
    {
        try {
            // Try to decode if base64
            $decodedKey = base64_decode($privateKey, true);
            if ($decodedKey !== false && self::isPEM($decodedKey)) {
                $privateKey = $decodedKey;
            }

            // Try phpseclib v3
            if (class_exists('phpseclib3\\Crypt\\PublicKeyLoader')) {
                $key = PublicKeyLoader::load($privateKey);

                // Ensure we have a PrivateKey instance for decryption
                if (!$key instanceof PrivateKey) {
                    throw new Exception('Loaded key is not a valid RSA Private Key');
                }

                // Use appropriate hash based on version
                if ($version === 1) {
                    // phpseclib v1 used SHA-1
                    $key = $key->withHash('sha1')->withMGFHash('sha1');
                }
                // Version 3 uses default SHA-256 (no modification needed)

                return $key->decrypt($data);
            }

            // Fallback to phpseclib v1
            if (class_exists('Crypt_RSA')) {
                $rsa = new \Crypt_RSA();
                $rsa->loadKey($privateKey);
                return $rsa->decrypt($data);
            }

            throw new Exception('No RSA implementation available (phpseclib v1 or v3 required)');
        } catch (Exception $e) {
            throw new Exception('Failed to decrypt with RSA (version ' . $version . '): ' . $e->getMessage());
        }
    }

    /**
     * Get the current encryption version for new data
     *
     * @return int Current version (always 3 for phpseclib v3)
     */
    public static function getCurrentVersion(): int
    {
        return 3;
    }

    /**
     * Check if a string is PEM formatted
     *
     * @param string $data Data to check
     * @return bool True if PEM formatted
     */
    private static function isPEM(string $data): bool
    {
        return strpos($data, '-----BEGIN') !== false;
    }
}
