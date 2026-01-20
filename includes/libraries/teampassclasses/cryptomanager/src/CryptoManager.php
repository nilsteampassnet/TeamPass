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
     * @param string $hashAlgorithm Hash algorithm for PBKDF2 ('sha1' for v1 compatibility, 'sha256' for v3)
     * @return string Encrypted data (raw binary)
     * @throws Exception
     */
    public static function aesEncrypt(string $data, string $password, string $mode = 'cbc', string $hashAlgorithm = 'sha1'): string
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

                // Use PBKDF2 with configurable hash algorithm
                // v1 defaults: method='pbkdf2', hash='sha1', salt='phpseclib/salt', iterations=1000
                // v3 uses: method='pbkdf2', hash='sha256', salt='phpseclib/salt', iterations=1000
                $cipher->setPassword($password, 'pbkdf2', $hashAlgorithm, 'phpseclib/salt', 1000);

                return $cipher->encrypt($data);
            }

            // Fallback to phpseclib v1 for backward compatibility (always uses SHA-1)
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
                        $method = 'setMode';
                        $cipher->$method($modeConstants[$mode]);
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
     * @param string $hashAlgorithm Hash algorithm for PBKDF2 ('sha1' for v1 compatibility, 'sha256' for v3)
     * @return string Decrypted data
     * @throws Exception
     */
    public static function aesDecrypt(string $data, string $password, string $mode = 'cbc', string $hashAlgorithm = 'sha1'): string
    {
        // Try phpseclib v3 first
        if (class_exists('phpseclib3\\Crypt\\AES')) {
            try {
                $cipher = new AES($mode);

                // phpseclib v1 used a zero IV when not explicitly set
                // IMPORTANT: Set IV BEFORE setPassword() because PBKDF2 doesn't set IV
                // and setPassword() might reset internal state
                if ($mode === 'cbc') {
                    $cipher->setIV(str_repeat("\0", 16)); // AES block size is 16 bytes
                }

                // Use PBKDF2 with configurable hash algorithm
                // v1 defaults: method='pbkdf2', hash='sha1', salt='phpseclib/salt', iterations=1000
                // v3 uses: method='pbkdf2', hash='sha256', salt='phpseclib/salt', iterations=1000
                $cipher->setPassword($password, 'pbkdf2', $hashAlgorithm, 'phpseclib/salt', 1000);

                $decrypted = $cipher->decrypt($data);

                // Success with v3
                return $decrypted;
            } catch (Exception $e) {
                // v3 failed, try v1 fallback if available
                // This handles data encrypted with v1 that v3 can't decrypt
                if (class_exists('Crypt_AES')) {
                    try {
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
                                $method = 'setMode';
                                $cipher->$method($modeConstants[$mode]);
                            }
                        }

                        $cipher->setPassword($password);
                        $decrypted = $cipher->decrypt($data);

                        // Success with v1 fallback
                        return $decrypted;
                    } catch (Exception $v1Exception) {
                        // Both v3 and v1 failed
                        throw new Exception('Failed to decrypt with AES (v3: ' . $e->getMessage() . ', v1: ' . $v1Exception->getMessage() . ')');
                    }
                }

                // v3 failed and v1 not available
                throw new Exception('Failed to decrypt with AES: ' . $e->getMessage());
            }
        }

        // Fallback to phpseclib v1 if v3 not available
        if (class_exists('Crypt_AES')) {
            try {
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
                        $method = 'setMode';
                        $cipher->$method($modeConstants[$mode]);
                    }
                }

                $cipher->setPassword($password);
                return $cipher->decrypt($data);
            } catch (Exception $e) {
                throw new Exception('Failed to decrypt with AES: ' . $e->getMessage());
            }
        }

        throw new Exception('No AES implementation available (phpseclib v1 or v3 required)');
    }

    /**
     * Decrypt data using AES with automatic version detection
     *
     * Tries to decrypt with SHA-256 (v3) first, then falls back to SHA-1 (v1)
     * Returns both the decrypted data and the version used for successful decryption
     *
     * @param string $data Encrypted data
     * @param string $password Password/key for decryption
     * @param string $mode AES mode (cbc, ctr, ecb, cfb, ofb, gcm)
     * @return array ['data' => string, 'version_used' => int] where version_used is 1 or 3
     * @throws Exception If decryption fails with both versions
     */
    public static function aesDecryptWithVersionDetection(string $data, string $password, string $mode = 'cbc'): array
    {
        // Try SHA-256 first (v3 default)
        try {
            $decrypted = self::aesDecrypt($data, $password, $mode, 'sha256');
            if ($decrypted !== false && !empty($decrypted)) {
                return [
                    'data' => $decrypted,
                    'version_used' => 3,
                ];
            }
        } catch (Exception $e) {
            // SHA-256 failed, will try SHA-1
        }

        // Fallback to SHA-1 (v1 compatibility)
        try {
            $decrypted = self::aesDecrypt($data, $password, $mode, 'sha1');
            if ($decrypted !== false && !empty($decrypted)) {
                return [
                    'data' => $decrypted,
                    'version_used' => 1,
                ];
            }
        } catch (Exception $e) {
            throw new Exception('Failed to decrypt with both v3 (SHA-256) and v1 (SHA-1): ' . $e->getMessage());
        }

        throw new Exception('AES decryption failed with both SHA-256 and SHA-1');
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
                        $method = 'setMode';
                        $cipher->$method($modeConstants[$mode]);
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
     * Decrypt data using RSA private key with version detection
     *
     * Returns both the decrypted data and the version that was used to decrypt
     *
     * @param string $data Encrypted data (raw binary or base64)
     * @param string $privateKey Private key in PKCS1 format (base64 encoded or plain)
     * @return array Array with keys: 'data' (decrypted string), 'version_used' (int: 1 or 3)
     * @throws Exception
     */
    public static function rsaDecryptWithVersionDetection(string $data, string $privateKey): array
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
                    $decrypted = $key->decrypt($data);
                    return [
                        'data' => $decrypted,
                        'version_used' => 3,
                    ];
                } catch (Exception $e) {
                    // Fallback to SHA-1 (v1 default) for backward compatibility
                    try {
                        $key = $key->withHash('sha1')->withMGFHash('sha1');
                        $decrypted = $key->decrypt($data);
                        return [
                            'data' => $decrypted,
                            'version_used' => 1,  // Used v1 compatibility mode
                        ];
                    } catch (Exception $v1Exception) {
                        throw new Exception('Failed to decrypt with both v3 and v1: ' . $v1Exception->getMessage());
                    }
                }
            }

            // Fallback to phpseclib v1 library
            if (class_exists('Crypt_RSA')) {
                $rsa = new \Crypt_RSA();
                $rsa->loadKey($privateKey);
                $decrypted = $rsa->decrypt($data);
                return [
                    'data' => $decrypted,
                    'version_used' => 1,
                ];
            }

            throw new Exception('No RSA implementation available (phpseclib v1 or v3 required)');
        } catch (Exception $e) {
            throw new Exception('Failed to decrypt with RSA: ' . $e->getMessage());
        }
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
