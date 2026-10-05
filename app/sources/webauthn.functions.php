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
 * ---
 * Passkey (WebAuthn) primitives.
 *
 * TeamPass acts as an authenticator for third-party sites: it holds the credential private
 * key, the browser extension intercepts the WebAuthn call on the site and relays it, and the
 * server builds the authenticator data and signs. This file holds the byte-level part of that
 * role — key generation, COSE key, authenticator data, attestation object, signature — plus
 * the input checks the API applies to what the extension sends.
 *
 * Kept free of any database and session dependency so it can be unit-tested in isolation
 * (tests/Unit/WebauthnAuthenticatorTest.php), where the web-auth/webauthn-lib relying-party
 * validators check that what is produced here is accepted by a real relying party.
 *
 * "passkey" already names the backup encryption passphrase in this codebase, hence the
 * webauthn prefix on every identifier.
 *
 * @file      webauthn.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * AAGUID of the TeamPass authenticator. Public: relying parties read it from the attested
 * credential data, and some enterprise ones filter on it. Never change it once released —
 * every credential created before would suddenly identify as another authenticator.
 */
const TP_WEBAUTHN_AAGUID = '7c30bcef-f035-4e21-9175-5c985b4b239c';

/** COSE algorithm identifier of ES256 (ECDSA P-256 with SHA-256), the only one supported. */
const TP_WEBAUTHN_ALG_ES256 = -7;

/** Size of a server-generated credential id, in bytes. */
const TP_WEBAUTHN_CREDENTIAL_ID_BYTES = 32;

/** WebAuthn caps the relying party user handle at 64 bytes. */
const TP_WEBAUTHN_USER_HANDLE_MAX_BYTES = 64;

/** Authenticator data flags (WebAuthn Level 3, §6.1). */
const TP_WEBAUTHN_FLAG_UP = 0x01; // user present
const TP_WEBAUTHN_FLAG_UV = 0x04; // user verified
const TP_WEBAUTHN_FLAG_BE = 0x08; // backup eligible
const TP_WEBAUTHN_FLAG_BS = 0x10; // backed up
const TP_WEBAUTHN_FLAG_AT = 0x40; // attested credential data included

/** Transports reported for a vault credential: it is reachable wherever the extension runs. */
const TP_WEBAUTHN_TRANSPORTS = ['internal', 'hybrid'];

/**
 * Encode bytes as unpadded base64url, the WebAuthn wire format.
 *
 * @param string $bytes Raw bytes
 *
 * @return string
 */
function webauthnBase64UrlEncode(string $bytes): string
{
    return Base64UrlSafe::encodeUnpadded($bytes);
}

/**
 * Decode a base64url value, padded or not.
 *
 * @param string $value base64url text
 *
 * @return string Raw bytes
 *
 * @throws InvalidArgumentException When the value is not base64url
 */
function webauthnBase64UrlDecode(string $value): string
{
    if (preg_match('/^[A-Za-z0-9_-]*={0,2}$/', $value) !== 1) {
        throw new InvalidArgumentException('Invalid base64url value.');
    }

    try {
        return Base64UrlSafe::decode(rtrim($value, '='));
    } catch (RangeException $e) {
        throw new InvalidArgumentException('Invalid base64url value.', 0, $e);
    }
}

/**
 * Return the TeamPass AAGUID as the 16 raw bytes carried in the attested credential data.
 *
 * @return string
 */
function webauthnAaguidBytes(): string
{
    return (string) hex2bin(str_replace('-', '', TP_WEBAUTHN_AAGUID));
}

/**
 * Generate a random credential id. Always server-side: a client-supplied id could collide
 * with an existing credential or be chosen to be recognizable.
 *
 * @return string Raw bytes
 */
function webauthnGenerateCredentialId(): string
{
    return random_bytes(TP_WEBAUTHN_CREDENTIAL_ID_BYTES);
}

/**
 * Generate an ES256 credential key pair.
 *
 * @return array{pem: string, x: string, y: string, cose: string}
 *         `pem` is the PKCS#8 private key, `x` and `y` the 32-byte public coordinates and
 *         `cose` the COSE_Key a relying party stores.
 *
 * @throws RuntimeException When OpenSSL cannot generate or export the key
 */
function webauthnGenerateCredentialKeyPair(): array
{
    $key = openssl_pkey_new([
        'curve_name' => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if ($key === false) {
        throw new RuntimeException('Unable to generate the credential key pair.');
    }

    $pem = '';
    if (openssl_pkey_export($key, $pem) === false) {
        throw new RuntimeException('Unable to export the credential private key.');
    }

    $details = openssl_pkey_get_details($key);
    if ($details === false || isset($details['ec']['x'], $details['ec']['y']) === false) {
        throw new RuntimeException('Unable to read the credential public key.');
    }

    // OpenSSL returns the coordinates as big numbers, without their leading zero bytes.
    $x = webauthnPadCoordinate((string) $details['ec']['x']);
    $y = webauthnPadCoordinate((string) $details['ec']['y']);

    return [
        'pem' => (string) $pem,
        'x' => $x,
        'y' => $y,
        'cose' => webauthnBuildCoseKey($x, $y),
    ];
}

/**
 * Left-pad a P-256 coordinate to its fixed 32-byte size.
 *
 * @param string $coordinate Big-endian coordinate, possibly shorter than 32 bytes
 *
 * @return string
 *
 * @throws InvalidArgumentException When the coordinate is longer than 32 bytes
 */
function webauthnPadCoordinate(string $coordinate): string
{
    if (strlen($coordinate) > 32) {
        throw new InvalidArgumentException('A P-256 coordinate cannot exceed 32 bytes.');
    }

    return str_pad($coordinate, 32, "\0", STR_PAD_LEFT);
}

/**
 * Build the COSE_Key of an ES256 public key.
 *
 * Keys are written in the CTAP2 canonical order (1, 3, -1, -2, -3): some relying parties
 * compare the encoded bytes rather than the decoded map.
 *
 * @param string $x 32-byte X coordinate
 * @param string $y 32-byte Y coordinate
 *
 * @return string CBOR bytes
 *
 * @throws InvalidArgumentException When a coordinate is not 32 bytes long
 */
function webauthnBuildCoseKey(string $x, string $y): string
{
    if (strlen($x) !== 32 || strlen($y) !== 32) {
        throw new InvalidArgumentException('P-256 coordinates must be 32 bytes long.');
    }

    $map = MapObject::create()
        ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))   // kty: EC2
        ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(TP_WEBAUTHN_ALG_ES256)) // alg
        ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))  // crv: P-256
        ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($x))
        ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($y));

    return (string) $map;
}

/**
 * Build the flags byte of an assertion or a registration.
 *
 * A server-held credential is usable from every device the user signs in to, so it is
 * always reported as backup eligible and backed up — the same as the password managers
 * relying parties already know. The AT flag is not set here: it follows from the presence
 * of attested credential data in webauthnBuildAuthenticatorData().
 *
 * @param bool $userVerified Whether the extension performed user verification
 *
 * @return int
 */
function webauthnBuildFlags(bool $userVerified): int
{
    $flags = TP_WEBAUTHN_FLAG_UP | TP_WEBAUTHN_FLAG_BE | TP_WEBAUTHN_FLAG_BS;
    if ($userVerified === true) {
        $flags |= TP_WEBAUTHN_FLAG_UV;
    }

    return $flags;
}

/**
 * Build the authenticator data.
 *
 * Layout: SHA-256(rpId) (32) ‖ flags (1) ‖ signCount (4, big-endian), followed on
 * registration by AAGUID (16) ‖ credentialId length (2, big-endian) ‖ credentialId ‖ COSE_Key.
 *
 * @param string      $rpId         Relying party id
 * @param int         $flags        Flags byte, without AT
 * @param int         $signCount    Signature counter
 * @param string|null $credentialId Raw credential id — registration only
 * @param string|null $coseKey      COSE_Key — registration only
 *
 * @return string Raw bytes
 *
 * @throws InvalidArgumentException On an out-of-range value or incomplete attested data
 */
function webauthnBuildAuthenticatorData(
    string $rpId,
    int $flags,
    int $signCount,
    ?string $credentialId = null,
    ?string $coseKey = null
): string {
    if ($flags < 0 || $flags > 0xFF) {
        throw new InvalidArgumentException('Invalid authenticator data flags.');
    }
    if ($signCount < 0 || $signCount > 0xFFFFFFFF) {
        throw new InvalidArgumentException('Invalid signature counter.');
    }
    if (($credentialId === null) !== ($coseKey === null)) {
        throw new InvalidArgumentException('Attested credential data needs both the credential id and its key.');
    }

    $attested = '';
    $flags &= ~TP_WEBAUTHN_FLAG_AT;
    if ($credentialId !== null && $coseKey !== null) {
        $length = strlen($credentialId);
        if ($length === 0 || $length > 1023) {
            throw new InvalidArgumentException('Invalid credential id length.');
        }
        $attested = webauthnAaguidBytes() . pack('n', $length) . $credentialId . $coseKey;
        $flags |= TP_WEBAUTHN_FLAG_AT;
    }

    return hash('sha256', $rpId, true) . chr($flags) . pack('N', $signCount) . $attested;
}

/**
 * Build a "none" attestation object.
 *
 * "none" is what password managers emit and what relying parties accept: nothing is signed at
 * registration, so no client data is needed to create a credential. Keys follow the CTAP2
 * canonical order (fmt, attStmt, authData).
 *
 * @param string $authenticatorData Authenticator data holding the attested credential data
 *
 * @return string CBOR bytes
 */
function webauthnBuildAttestationObject(string $authenticatorData): string
{
    $map = MapObject::create()
        ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
        ->add(TextStringObject::create('attStmt'), MapObject::create())
        ->add(TextStringObject::create('authData'), ByteStringObject::create($authenticatorData));

    return (string) $map;
}

/**
 * Sign an assertion.
 *
 * The signed payload is authenticatorData ‖ SHA-256(clientDataJSON). OpenSSL returns a DER
 * ECDSA signature, which is exactly the ES256 WebAuthn wire format.
 *
 * @param string $privateKeyPem     PKCS#8 private key
 * @param string $authenticatorData Authenticator data of the assertion
 * @param string $clientDataJson    Client data JSON, as bytes
 *
 * @return string DER signature
 *
 * @throws RuntimeException When the key is unusable or signing fails
 */
function webauthnSignAssertion(string $privateKeyPem, string $authenticatorData, string $clientDataJson): string
{
    $key = openssl_pkey_get_private($privateKeyPem);
    if ($key === false) {
        throw new RuntimeException('Unusable credential private key.');
    }

    $signature = '';
    $payload = $authenticatorData . hash('sha256', $clientDataJson, true);
    if (openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256) === false) {
        throw new RuntimeException('Unable to sign the assertion.');
    }

    return (string) $signature;
}

/**
 * Pick the algorithm of a new credential from the relying party's preferences.
 *
 * An empty list means the WebAuthn default (ES256 and RS256), so ES256 is acceptable. A list
 * without ES256 is refused rather than answered with a credential the relying party cannot use.
 *
 * @param array<int, mixed> $pubKeyCredParams COSE algorithm identifiers
 *
 * @return int|null The algorithm, or null when ES256 is not offered
 */
function webauthnSelectAlgorithm(array $pubKeyCredParams): ?int
{
    if ($pubKeyCredParams === []) {
        return TP_WEBAUTHN_ALG_ES256;
    }

    foreach ($pubKeyCredParams as $algorithm) {
        if (is_int($algorithm) === true && $algorithm === TP_WEBAUTHN_ALG_ES256) {
            return TP_WEBAUTHN_ALG_ES256;
        }
    }

    return null;
}

/**
 * Normalize and validate a relying party id.
 *
 * A relying party id is a domain: lowercase LDH labels, no scheme, port, path or IP literal.
 * A single label is refused except `localhost`, which keeps local development usable. Public
 * suffixes ("co.uk") cannot be detected without the public suffix list — the extension, which
 * plays the browser's role, is the one expected to refuse them.
 *
 * @param string $rpId Relying party id as received
 *
 * @return string|null Normalized id, or null when it is not a valid relying party id
 */
function webauthnNormalizeRpId(string $rpId): ?string
{
    $rpId = strtolower(trim($rpId));
    if ($rpId === '' || strlen($rpId) > 253) {
        return null;
    }
    if ($rpId === 'localhost') {
        return $rpId;
    }

    $labels = explode('.', $rpId);
    if (count($labels) < 2) {
        return null;
    }
    foreach ($labels as $label) {
        if (preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label) !== 1) {
            return null;
        }
    }

    // An IPv4 literal is made of labels that are LDH-valid, but it is not a domain.
    if (preg_match('/^[0-9.]+$/', $rpId) === 1) {
        return null;
    }

    return $rpId;
}

/**
 * Tell whether an origin is allowed to use a relying party id.
 *
 * The origin host must equal the relying party id or be one of its subdomains. HTTPS is
 * required, except on localhost, which browsers treat as a secure context.
 *
 * @param string $origin Serialized origin, e.g. https://github.com
 * @param string $rpId   Normalized relying party id
 *
 * @return bool
 */
function webauthnOriginMatchesRpId(string $origin, string $rpId): bool
{
    if (preg_match('#^(https?)://([a-z0-9.-]+)(?::([0-9]{1,5}))?$#', strtolower($origin), $matches) !== 1) {
        return false;
    }

    $scheme = $matches[1];
    $host = $matches[2];
    $isLocalhost = $host === 'localhost' || str_ends_with($host, '.localhost');
    if ($scheme !== 'https' && $isLocalhost === false) {
        return false;
    }

    return $host === $rpId || str_ends_with($host, '.' . $rpId);
}

/**
 * Parse and check client data JSON received from the extension.
 *
 * @param string $clientDataJson Client data JSON, as bytes
 * @param string $expectedType   'webauthn.get' or 'webauthn.create'
 *
 * @return array{type: string, challenge: string, origin: string, crossOrigin: bool}
 *         `challenge` stays base64url, as in the JSON.
 *
 * @throws InvalidArgumentException When the client data is malformed or of another type
 */
function webauthnParseClientDataJson(string $clientDataJson, string $expectedType): array
{
    $data = json_decode($clientDataJson, true);
    if (is_array($data) === false) {
        throw new InvalidArgumentException('Client data is not a JSON object.');
    }

    if (isset($data['type']) === false || $data['type'] !== $expectedType) {
        throw new InvalidArgumentException('Unexpected client data type.');
    }

    if (isset($data['challenge']) === false || is_string($data['challenge']) === false || $data['challenge'] === '') {
        throw new InvalidArgumentException('Client data has no challenge.');
    }
    webauthnBase64UrlDecode($data['challenge']);

    if (isset($data['origin']) === false || is_string($data['origin']) === false || $data['origin'] === '') {
        throw new InvalidArgumentException('Client data has no origin.');
    }

    $crossOrigin = $data['crossOrigin'] ?? false;
    if (is_bool($crossOrigin) === false) {
        throw new InvalidArgumentException('Invalid client data crossOrigin value.');
    }

    return [
        'type' => $data['type'],
        'challenge' => $data['challenge'],
        'origin' => $data['origin'],
        'crossOrigin' => $crossOrigin,
    ];
}

/**
 * Tell whether a relying party user handle is acceptable (1 to 64 bytes).
 *
 * @param string $userHandle Raw user handle
 *
 * @return bool
 */
function webauthnIsValidUserHandle(string $userHandle): bool
{
    $length = strlen($userHandle);

    return $length >= 1 && $length <= TP_WEBAUTHN_USER_HANDLE_MAX_BYTES;
}

/**
 * Build the DER SubjectPublicKeyInfo of an ES256 public key.
 *
 * Browsers expose it through AuthenticatorAttestationResponse.getPublicKey(), and many relying
 * parties read the key from there rather than from the attestation object.
 *
 * @param string $x 32-byte X coordinate
 * @param string $y 32-byte Y coordinate
 *
 * @return string DER bytes
 *
 * @throws InvalidArgumentException When a coordinate is not 32 bytes long
 */
function webauthnBuildSpkiPublicKey(string $x, string $y): string
{
    if (strlen($x) !== 32 || strlen($y) !== 32) {
        throw new InvalidArgumentException('P-256 coordinates must be 32 bytes long.');
    }

    // SEQUENCE { SEQUENCE { id-ecPublicKey, prime256v1 }, BIT STRING { 04 ‖ X ‖ Y } }
    return (string) hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $x . $y;
}

/** Largest client data JSON accepted from the extension, in bytes. */
const TP_WEBAUTHN_CLIENT_DATA_MAX_BYTES = 8192;

/** Largest list of excluded credential ids accepted on creation. */
const TP_WEBAUTHN_EXCLUDED_IDS_MAX = 100;

/**
 * Validate the body of POST /webauthn/create.
 *
 * Exception codes carry the HTTP status: 400 for a missing or malformed field, 422 for a
 * well-formed request TeamPass cannot honour (unsupported algorithm, origin that does not
 * belong to the relying party).
 *
 * @param array<string, mixed> $input Decoded request body
 *
 * @return array{
 *     item_id: int, rp_id: string, rp_name: string, user_handle: string, user_name: string,
 *     user_display_name: string, algorithm: int, client_data_json: string, user_verified: bool,
 *     excluded_credential_ids: array<int, string>
 * } Binary fields (user_handle, client_data_json) are decoded; excluded ids are re-encoded
 *   as canonical unpadded base64url, the form stored in the database.
 *
 * @throws InvalidArgumentException
 */
function webauthnNormalizeCreateRequest(array $input): array
{
    $itemId = webauthnReadPositiveInt($input, 'item_id');
    $rpId = webauthnReadRpId($input);
    $userHandle = webauthnReadBase64Url($input, 'user_handle', true);
    if (webauthnIsValidUserHandle($userHandle) === false) {
        throw new InvalidArgumentException('user_handle must be 1 to 64 bytes long.', 422);
    }

    $algorithms = [];
    $params = $input['pub_key_cred_params'] ?? [];
    if (is_array($params) === false) {
        throw new InvalidArgumentException('pub_key_cred_params must be an array.', 400);
    }
    foreach ($params as $param) {
        // Accept the bare COSE identifier or the PublicKeyCredentialParameters object.
        if (is_array($param) === true) {
            if (($param['type'] ?? 'public-key') !== 'public-key' || is_int($param['alg'] ?? null) === false) {
                continue;
            }
            $param = $param['alg'];
        }
        if (is_int($param) === true) {
            $algorithms[] = $param;
        }
    }
    // An empty list is the WebAuthn default; a list whose entries were all unusable is not.
    $algorithm = $params === [] ? webauthnSelectAlgorithm([]) : ($algorithms === [] ? null : webauthnSelectAlgorithm($algorithms));
    if ($algorithm === null) {
        throw new InvalidArgumentException('The relying party does not accept ES256, the only algorithm TeamPass supports.', 422);
    }

    $clientDataJson = webauthnReadClientData($input, 'webauthn.create', $rpId);

    $excluded = $input['excluded_credential_ids'] ?? [];
    if (is_array($excluded) === false || count($excluded) > TP_WEBAUTHN_EXCLUDED_IDS_MAX) {
        throw new InvalidArgumentException('excluded_credential_ids must be an array of at most 100 ids.', 400);
    }
    $excludedIds = [];
    foreach ($excluded as $excludedId) {
        if (is_string($excludedId) === false || $excludedId === '') {
            throw new InvalidArgumentException('excluded_credential_ids must hold base64url strings.', 400);
        }
        $excludedIds[] = webauthnBase64UrlEncode(webauthnBase64UrlDecode($excludedId));
    }

    return [
        'item_id' => $itemId,
        'rp_id' => $rpId,
        'rp_name' => webauthnReadLabel($input, 'rp_name'),
        'user_handle' => $userHandle,
        'user_name' => webauthnReadLabel($input, 'user_name'),
        'user_display_name' => webauthnReadLabel($input, 'user_display_name'),
        'algorithm' => $algorithm,
        'client_data_json' => $clientDataJson,
        'user_verified' => webauthnReadBool($input, 'user_verified'),
        'excluded_credential_ids' => array_values(array_unique($excludedIds)),
    ];
}

/**
 * Validate the body of POST /webauthn/assert.
 *
 * @param array<string, mixed> $input Decoded request body
 *
 * @return array{credential_id: string, rp_id: string, client_data_json: string, user_verified: bool}
 *         `credential_id` is canonical unpadded base64url, `client_data_json` decoded bytes.
 *
 * @throws InvalidArgumentException Codes as in webauthnNormalizeCreateRequest()
 */
function webauthnNormalizeAssertRequest(array $input): array
{
    $credentialId = webauthnReadBase64Url($input, 'credential_id', true);
    if (strlen($credentialId) < 16 || strlen($credentialId) > 1023) {
        throw new InvalidArgumentException('Invalid credential_id length.', 400);
    }
    $rpId = webauthnReadRpId($input);

    return [
        'credential_id' => webauthnBase64UrlEncode($credentialId),
        'rp_id' => $rpId,
        'client_data_json' => webauthnReadClientData($input, 'webauthn.get', $rpId),
        'user_verified' => webauthnReadBool($input, 'user_verified'),
    ];
}

/**
 * Read a mandatory positive integer.
 *
 * @param array<string, mixed> $input Request body
 * @param string               $name  Field name
 *
 * @return int
 *
 * @throws InvalidArgumentException
 */
function webauthnReadPositiveInt(array $input, string $name): int
{
    $value = $input[$name] ?? null;
    if (is_string($value) === true && preg_match('/^[1-9][0-9]{0,9}$/', $value) === 1) {
        $value = (int) $value;
    }
    if (is_int($value) === false || $value <= 0) {
        throw new InvalidArgumentException($name . ' is mandatory and must be a positive integer.', 400);
    }

    return $value;
}

/**
 * Read and normalize the mandatory rp_id field.
 *
 * @param array<string, mixed> $input Request body
 *
 * @return string
 *
 * @throws InvalidArgumentException
 */
function webauthnReadRpId(array $input): string
{
    if (is_string($input['rp_id'] ?? null) === false || $input['rp_id'] === '') {
        throw new InvalidArgumentException('rp_id is mandatory.', 400);
    }

    $rpId = webauthnNormalizeRpId($input['rp_id']);
    if ($rpId === null) {
        throw new InvalidArgumentException('rp_id is not a valid relying party id.', 422);
    }

    return $rpId;
}

/**
 * Read a base64url field and return its bytes.
 *
 * @param array<string, mixed> $input    Request body
 * @param string               $name     Field name
 * @param bool                 $required Whether an absent field is an error
 *
 * @return string Raw bytes, '' when optional and absent
 *
 * @throws InvalidArgumentException
 */
function webauthnReadBase64Url(array $input, string $name, bool $required): string
{
    $value = $input[$name] ?? null;
    if ($value === null || $value === '') {
        if ($required === true) {
            throw new InvalidArgumentException($name . ' is mandatory.', 400);
        }

        return '';
    }
    if (is_string($value) === false) {
        throw new InvalidArgumentException($name . ' must be a base64url string.', 400);
    }

    try {
        return webauthnBase64UrlDecode($value);
    } catch (InvalidArgumentException $e) {
        throw new InvalidArgumentException($name . ' must be a base64url string.', 400, $e);
    }
}

/**
 * Read the client data, check its type and that its origin belongs to the relying party.
 *
 * This check makes the request consistent, it does not authenticate the extension: an
 * extension that lies can write any origin. The guarantee that the origin is the tab's real
 * one is the extension's, which must take it from the browser and never from the page.
 *
 * @param array<string, mixed> $input        Request body
 * @param string               $expectedType 'webauthn.create' or 'webauthn.get'
 * @param string               $rpId         Normalized relying party id
 *
 * @return string Client data JSON bytes, exactly as the relying party will hash them
 *
 * @throws InvalidArgumentException
 */
function webauthnReadClientData(array $input, string $expectedType, string $rpId): string
{
    $clientDataJson = webauthnReadBase64Url($input, 'client_data_json', true);
    if (strlen($clientDataJson) > TP_WEBAUTHN_CLIENT_DATA_MAX_BYTES) {
        throw new InvalidArgumentException('client_data_json is too large.', 400);
    }

    try {
        $clientData = webauthnParseClientDataJson($clientDataJson, $expectedType);
    } catch (InvalidArgumentException $e) {
        throw new InvalidArgumentException($e->getMessage(), 400, $e);
    }

    if ($clientData['crossOrigin'] === true) {
        throw new InvalidArgumentException('Cross-origin WebAuthn requests are not supported.', 422);
    }
    if (webauthnOriginMatchesRpId($clientData['origin'], $rpId) === false) {
        throw new InvalidArgumentException('The client data origin does not belong to the relying party.', 422);
    }

    return $clientDataJson;
}

/**
 * Read an optional display label (relying party name, account name). These strings come from
 * the relying party's page: they are stored as received and must be escaped on output.
 *
 * @param array<string, mixed> $input Request body
 * @param string               $name  Field name
 *
 * @return string
 *
 * @throws InvalidArgumentException
 */
function webauthnReadLabel(array $input, string $name): string
{
    $value = $input[$name] ?? '';
    if (is_string($value) === false) {
        throw new InvalidArgumentException($name . ' must be a string.', 400);
    }
    $value = trim($value);
    if (mb_check_encoding($value, 'UTF-8') === false || mb_strlen($value) > 255) {
        throw new InvalidArgumentException($name . ' must be valid UTF-8 of at most 255 characters.', 422);
    }

    return $value;
}

/**
 * Read an optional boolean, accepting the JSON boolean and its 0/1 form-data spelling.
 *
 * @param array<string, mixed> $input Request body
 * @param string               $name  Field name
 *
 * @return bool False when absent
 *
 * @throws InvalidArgumentException
 */
function webauthnReadBool(array $input, string $name): bool
{
    $value = $input[$name] ?? false;
    if (is_bool($value) === true) {
        return $value;
    }
    if ($value === 1 || $value === '1' || $value === 'true') {
        return true;
    }
    if ($value === 0 || $value === '0' || $value === 'false' || $value === '') {
        return false;
    }

    throw new InvalidArgumentException($name . ' must be a boolean.', 400);
}
