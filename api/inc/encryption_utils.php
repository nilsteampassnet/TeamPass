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
 * @version    API
 *
 * @file      encryption_utils.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2025 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Encrypt data using AES-256-GCM with a session key
 *
 * This function encrypts sensitive data (like decrypted private keys) using
 * AES-256-GCM which provides both encryption and authentication. The encrypted
 * data can only be decrypted with the same session key.
 *
 * Security features:
 * - AES-256-GCM: Industry standard authenticated encryption
 * - Random nonce for each encryption (prevents pattern detection)
 * - Authentication tag to detect tampering
 *
 * @param string $data Data to encrypt (e.g., decrypted private key)
 * @param string $key Session key (must be 32 bytes / 256 bits)
 * @return string|false Encrypted data in base64 format (nonce.tag.ciphertext) or false on error
 */
function encrypt_with_session_key(string $data, string $key)
{
    if (strlen($key) !== 32) {
        error_log('encrypt_with_session_key: Invalid key length. Expected 32 bytes, got ' . strlen($key));
        return false;
    }

    try {
        // Generate a random nonce (96 bits is recommended for GCM)
        $nonce = random_bytes(12);

        $tag = '';

        // Encrypt using AES-256-GCM
        $ciphertext = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($ciphertext === false) {
            error_log('encrypt_with_session_key: OpenSSL encryption failed');
            return false;
        }

        // Combine nonce + tag + ciphertext and encode in base64
        // Format: [12 bytes nonce][16 bytes tag][variable ciphertext]
        return base64_encode($nonce . $tag . $ciphertext);

    } catch (Exception $e) {
        error_log('encrypt_with_session_key: Exception - ' . $e->getMessage());
        return false;
    }
}

/**
 * Decrypt data using AES-256-GCM with a session key
 *
 * This function decrypts data that was encrypted with encrypt_with_session_key().
 * The authentication tag is automatically verified, ensuring data integrity.
 *
 * @param string $encryptedData Encrypted data in base64 format (from encrypt_with_session_key)
 * @param string $key Session key (must be 32 bytes / 256 bits)
 * @return string|false Decrypted data or false on error/tampering
 */
function decrypt_with_session_key(string $encryptedData, string $key)
{
    if (strlen($key) !== 32) {
        error_log('decrypt_with_session_key: Invalid key length. Expected 32 bytes, got ' . strlen($key));
        return false;
    }

    try {
        // Decode from base64
        $decoded = base64_decode($encryptedData, true);

        if ($decoded === false) {
            error_log('decrypt_with_session_key: Invalid base64 data');
            return false;
        }

        // Extract components: nonce (12 bytes) + tag (16 bytes) + ciphertext (rest)
        if (strlen($decoded) < 28) {
            error_log('decrypt_with_session_key: Encrypted data too short');
            return false;
        }

        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        $ciphertext = substr($decoded, 28);

        // Decrypt using AES-256-GCM
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($plaintext === false) {
            error_log('decrypt_with_session_key: OpenSSL decryption failed (wrong key or tampered data)');
            return false;
        }

        return $plaintext;

    } catch (Exception $e) {
        error_log('decrypt_with_session_key: Exception - ' . $e->getMessage());
        return false;
    }
}
