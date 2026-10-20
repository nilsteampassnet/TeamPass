<?php

declare(strict_types=1);

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
 * @file      device_pairing_client.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

// Reference client for an operator-controlled test installation, not a persistent credential store.
require_once __DIR__ . '/../../app/vendor/autoload.php';
require_once __DIR__ . '/../../app/sources/device_pairing_logic.php';

use TeampassClasses\CryptoManager\CryptoManager;

/** HTTPS-only JSON transport, with no redirects, cookies, diagnostics or secret-bearing URLs. */
function pairingClientRequest(string $url, ?array $body = null, ?string $jwt = null): array
{
    $curl = curl_init($url);
    $response = '';
    $headers = ['Accept: application/json'];
    if ($jwt !== null) {
        $headers[] = 'Authorization: Bearer ' . $jwt;
    }
    $options = [CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'TeamPass-Pairing-Reference/1',
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response): int {
            if (strlen($response) + strlen($chunk) > 65536) {
                return 0;
            }
            $response .= $chunk;
            return strlen($chunk);
        }];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR);
    }
    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($curl, $options);
    $sent = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($sent === false) {
        throw new RuntimeException('Network or trusted TLS connection failed; retry the same claim within its deadline.');
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('Request failed with HTTP ' . $status . '. No response body was logged.');
    }
    $decoded = json_decode($response, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new RuntimeException('Invalid server response');
    }
    return $decoded;
}

try {
    fwrite(STDERR, "Paste the temporary QR JSON, then press Enter. Use a test account.\n");
    $raw = fgets(STDIN, 4097);
    if ($raw === false || strlen($raw) > 4096) {
        throw new RuntimeException('Invalid QR size');
    }
    $qr = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($qr) || ($qr['type'] ?? '') !== 'teampass.device-pairing' || ($qr['version'] ?? null) !== 1) {
        throw new RuntimeException('Unsupported QR type/version');
    }
    if (!is_string($qr['server_url'] ?? null)) {
        throw new RuntimeException('Invalid instance URL');
    }
    $server = devicePairingServerUrl(['cpassman_url' => $qr['server_url']]);
    $id = devicePairingInput($qr, 'pairing_id', '/^[a-f0-9]{32}$/');
    $secret = devicePairingInput($qr, 'pairing_secret', '/^[A-Za-z0-9_-]{43}$/');
    fwrite(STDOUT, "Instance: $server\n");
    $capabilities = pairingClientRequest($server . '/api/v1/devicePairing/capabilities');
    if (($capabilities['version'] ?? null) !== 1 || ($capabilities['algorithm'] ?? '') !== 'RSA-OAEP-256'
        || ($capabilities['key_bits'] ?? null) !== 3072
    ) {
        throw new RuntimeException('Unsupported pairing capability');
    }
    $keys = CryptoManager::generateRSAKeyPair(3072);
    $public = CryptoManager::devicePairingPublicKey($keys['publickey']);
    $verifier = devicePairingBase64Url(random_bytes(32));
    $claim = pairingClientRequest($server . '/api/v1/devicePairing/claim', ['pairing_id' => $id, 'pairing_secret' => $secret,
        'challenge' => devicePairingBase64Url(hash('sha256', $verifier, true)), 'public_key' => $public['pem'],
        'device_label' => 'Reference test client']);
    $claimId = devicePairingInput($claim, 'claim_id', '/^[a-f0-9]{32}$/');
    $code = devicePairingInput($claim, 'comparison_code', '/^[0-9]{6}$/');
    fwrite(STDOUT, "Compare this code with the web profile and approve only if it matches: $code\n");
    $proof = ['pairing_id' => $id, 'claim_id' => $claimId, 'verifier' => $verifier];
    $deadline = microtime(true) + TP_DEVICE_PAIRING_TTL;
    $handoff = null;
    while (microtime(true) < $deadline) {
        sleep(TP_DEVICE_PAIRING_POLL_INTERVAL);
        $state = pairingClientRequest($server . '/api/v1/devicePairing/status', $proof);
        if (in_array($state['state'] ?? '', ['cancelled', 'expired', 'rejected', 'completed'], true)) {
            throw new RuntimeException('Pairing ended without an available handoff. Generate a new QR code.');
        }
        if (in_array($state['state'] ?? '', ['approved', 'delivered'], true)) {
            // A retry sends exactly the same proof and receives the same protected result.
            try {
                $delivery = pairingClientRequest($server . '/api/v1/devicePairing/consume', $proof);
            } catch (RuntimeException $e) {
                continue;
            }
            $handoff = $delivery['handoff'] ?? null;
            break;
        }
    }
    if (!is_array($handoff) || ($handoff['version'] ?? null) !== 1 || ($handoff['algorithm'] ?? '') !== 'RSA-OAEP-256'
        || ($handoff['key_fingerprint'] ?? '') !== $public['fingerprint'] || ($handoff['server_url'] ?? '') !== $server
        || !is_string($handoff['username'] ?? null) || !is_string($handoff['ciphertext'] ?? null)
    ) {
        throw new RuntimeException('Missing, expired or mismatched protected handoff');
    }
    $encrypted = base64_decode(strtr($handoff['ciphertext'], '-_', '+/'), true);
    if ($encrypted === false || strlen($encrypted) !== 384) {
        throw new RuntimeException('Invalid protected handoff');
    }
    $pat = CryptoManager::rsaDecryptWithVersion($encrypted, $keys['privatekey'], 3);
    if (preg_match('/^[a-f0-9]{64}$/', $pat) !== 1) {
        throw new RuntimeException('Invalid credential');
    }
    $first = pairingClientRequest($server . '/api/v1/authorizeToken', ['login' => $handoff['username'], 'token' => $pat]);
    $second = pairingClientRequest($server . '/api/v1/authorizeToken', ['login' => $handoff['username'], 'token' => $pat]);
    if (!is_string($first['token'] ?? null) || !is_string($second['token'] ?? null) || $first['token'] === $second['token']) {
        throw new RuntimeException('The dedicated credential did not issue two independent JWTs');
    }
    // Fetch a permission-protected endpoint, without printing or saving the returned data.
    pairingClientRequest($server . '/api/v1/folder/listFolders', null, $second['token']);
    pairingClientRequest($server . '/api/v1/auth/logout', [], $first['token']);
    pairingClientRequest($server . '/api/v1/auth/logout', [], $second['token']);
    unset($pat, $keys, $handoff, $first, $second);
    fwrite(STDOUT, "OK: approved pairing, protected handoff, two JWTs, authenticated request and session logout.\nRevoke 'Reference test client' in the profile after testing. No credential was saved or printed.\n");
} catch (Throwable $e) {
    // Fixed local messages only: JSON/crypto exceptions could contain supplied data.
    fwrite(STDERR, "Reference pairing did not finish. Check the web state, TLS trust and API/PAT policy, then generate a new QR if necessary.\n");
    exit(1);
}
