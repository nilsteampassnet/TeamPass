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
            // Generate the private key
            $private = RSA::createKey($bits);
            $public = $private->getPublicKey();

            return [
                'privatekey' => $private->toString('PKCS1'),
                'publickey' => $public->toString('PKCS1'),
                'private_key_object' => $private,
                'public_key_object' => $public,
            ];
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

            $key = PublicKeyLoader::load($publicKey);
            return $key->encrypt($data);
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

            $key = PublicKeyLoader::load($privateKey);

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
        } catch (Exception $e) {
            throw new Exception('Failed to decrypt with RSA: ' . $e->getMessage());
        }
    }

    /**
     * Encrypt data using AES
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
            $cipher = new AES($mode);
            $cipher->setPassword($password);
            return $cipher->encrypt($data);
        } catch (Exception $e) {
            throw new Exception('Failed to encrypt with AES: ' . $e->getMessage());
        }
    }

    /**
     * Decrypt data using AES
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
            $cipher = new AES($mode);
            $cipher->setPassword($password);
            return $cipher->decrypt($data);
        } catch (Exception $e) {
            throw new Exception('Failed to decrypt with AES: ' . $e->getMessage());
        }
    }

    /**
     * Create AES cipher instance with custom options
     *
     * @param string $mode AES mode (cbc, ctr, ecb, cfb, ofb, gcm)
     * @return AES AES cipher instance
     * @throws Exception
     */
    public static function createAESCipher(string $mode = 'cbc'): AES
    {
        try {
            return new AES($mode);
        } catch (Exception $e) {
            throw new Exception('Failed to create AES cipher: ' . $e->getMessage());
        }
    }

    /**
     * Load RSA key (public or private)
     *
     * @param string $key Key data (base64 encoded or plain PEM)
     * @return mixed RSA key object
     * @throws Exception
     */
    public static function loadRSAKey(string $key)
    {
        try {
            // Try to decode if base64
            $decodedKey = base64_decode($key, true);
            if ($decodedKey !== false && self::isPEM($decodedKey)) {
                $key = $decodedKey;
            }

            return PublicKeyLoader::load($key);
        } catch (Exception $e) {
            throw new Exception('Failed to load RSA key: ' . $e->getMessage());
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
