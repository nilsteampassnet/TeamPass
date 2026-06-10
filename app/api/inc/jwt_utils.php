<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass
 * @version    API
 *
 * @file      jwt_utils.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2026 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */

use Symfony\Component\HttpFoundation\Request AS symfonyRequest;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use TeampassClasses\ConfigManager\ConfigManager;

/**
 * Return a dedicated JWT signing key, distinct from the DB password.
 * The key is lazily generated on first call and persisted in teampass_misc.
 * Rotation: delete the 'api_jwt_secret' row and all live tokens are invalidated.
 *
 * @return string 64-char hex key (256 bits of entropy)
 */
function getApiJwtSigningKey(): string
{
    $configManager = new ConfigManager();
    $SETTINGS = $configManager->getAllSettings();

    if (!empty($SETTINGS['api_jwt_secret']) && strlen((string) $SETTINGS['api_jwt_secret']) >= 32) {
        return (string) $SETTINGS['api_jwt_secret'];
    }

    // Generate a new 256-bit signing key
    $newKey = bin2hex(random_bytes(32));

    $existing = DB::queryFirstRow(
        'SELECT increment_id FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %s',
        'admin',
        'api_jwt_secret'
    );

    if ($existing === null) {
        DB::insert(prefixTable('misc'), [
            'type'     => 'admin',
            'intitule' => 'api_jwt_secret',
            'valeur'   => $newKey,
        ]);
    } else {
        DB::update(
            prefixTable('misc'),
            ['valeur' => $newKey],
            'increment_id = %i',
            (int) $existing['increment_id']
        );
    }

    ConfigManager::invalidateCache();

    return $newKey;
}

/**
 * Audience claim expected in API JWTs (RFC 7519 "aud").
 */
const TEAMPASS_API_JWT_AUDIENCE = 'teampass-api';

/**
 * Verify the JWT (signature, exp/nbf/iat, iss/aud) and return its payload.
 *
 * Single verified decode: callers must use this payload instead of re-parsing
 * the token without signature verification.
 *
 * iss/aud are validated only when present so that tokens issued before these
 * claims were introduced keep working until they expire (max 24h).
 *
 * @param string $jwt
 * @return array|null Decoded payload, or null when the token is invalid
 */
function validate_and_get_jwt_payload(string $jwt): ?array
{
	try {
		// Tolerate small clock skew between servers for nbf/iat/exp checks
		JWT::$leeway = 60;
		$decoded = (array) JWT::decode($jwt, new Key(getApiJwtSigningKey(), 'HS256'));

		// Check if expiration is reached
		if (isset($decoded['exp']) === false || $decoded['exp'] - time() < 0) {
			return null;
		}

		// Audience must match when the claim is present (legacy tokens lack it)
		if (isset($decoded['aud']) && $decoded['aud'] !== TEAMPASS_API_JWT_AUDIENCE) {
			return null;
		}

		// Issuer must match this instance when both the claim and the setting exist
		if (isset($decoded['iss']) && $decoded['iss'] !== '') {
			$configManager = new ConfigManager();
			$expectedIssuer = rtrim((string) ($configManager->getSetting('cpassman_url') ?? ''), '/');
			if ($expectedIssuer !== '' && rtrim((string) $decoded['iss'], '/') !== $expectedIssuer) {
				return null;
			}
		}

		return $decoded;
	} catch (InvalidArgumentException $e) {
		// provided key/key-array is empty or malformed.
		return null;
	} catch (DomainException $e) {
		// provided algorithm is unsupported OR
		// provided key is invalid OR
		// unknown error thrown in openSSL or libsodium OR
		// libsodium is required but not available.
		return null;
	} catch (SignatureInvalidException $e) {
		// provided JWT signature verification failed.
		return null;
	} catch (BeforeValidException $e) {
		// provided JWT is trying to be used before "nbf" claim OR
		// provided JWT is trying to be used before "iat" claim.
		return null;
	} catch (ExpiredException $e) {
		// provided JWT is trying to be used after "exp" claim.
		return null;
	} catch (UnexpectedValueException $e) {
		// provided JWT is malformed OR
		// provided JWT is missing an algorithm / using an unsupported algorithm OR
		// provided JWT algorithm does not match provided key OR
		// provided key ID in key/key-array is empty or invalid.
		return null;
	}
}

/**
 * Is the JWT valid
 *
 * @param string $jwt
 * @return boolean
 */
function is_jwt_valid($jwt) {
	return validate_and_get_jwt_payload((string) $jwt) !== null;
}

function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_authorization_header()
{
	$request = symfonyRequest::createFromGlobals();
	$authorizationHeader = $request->headers->get('Authorization');
	$headers = null;
	
	// Check if the authorization header is not empty
	if (!empty($authorizationHeader)) {
		$headers = trim($authorizationHeader);
	} else if (function_exists('apache_request_headers') === true) {
		$requestHeaders = (array) apache_request_headers();
		// Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
		$requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
		//print_r($requestHeaders);
		if (isset($requestHeaders['Authorization']) === true) {
			$headers = trim($requestHeaders['Authorization']);
		}
	}
	
	return $headers;
}

function get_bearer_token() {
    $headers = get_authorization_header();
	
    // HEADER: Get the access token from the header
    if (empty($headers) === false) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}

/**
 * Get user encryption keys from database
 *
 * The private key is stored encrypted in teampass_api (encrypted_private_key).
 * The AES key used for that encryption (session_aes_key) is also stored server-side —
 * it is never embedded in the JWT. A stolen JWT alone is therefore insufficient
 * to decrypt the private key; server (DB) access is also required.
 *
 * @param int $userId User ID from JWT token
 * @param string $keyTempo Session token from JWT (for validation)
 * @return array|null Array containing public_key and private_key (decrypted), or null if validation fails
 */
function get_user_keys(int $userId, string $keyTempo): ?array
{
    require_once API_ROOT_PATH . '/inc/encryption_utils.php';

    // Retrieve user's public key, encrypted private key, and server-side AES key
    $userInfo = DB::queryFirstRow(
        "SELECT u.public_key, a.encrypted_private_key, a.session_key AS key_tempo, a.session_aes_key
        FROM " . prefixTable('users') . " AS u
        INNER JOIN " . prefixTable('api') . " AS a ON (a.user_id = u.id)
        WHERE u.id = %i",
        $userId
    );

    if (DB::count() === 0) {
        error_log('[API] get_user_keys: User not found or no API config for user ID ' . $userId);
        return null;
    }

    // Validate key_tempo (ensures the session is still valid)
    // Do not log the presented value — key_tempo is a live session credential
    if (($userInfo['key_tempo']) !== $keyTempo) {
        error_log('[API] get_user_keys: key_tempo mismatch for user ID ' . $userId);
        return null;
    }

    // Check that both encrypted blobs are present
    if (empty($userInfo['encrypted_private_key'])) {
        error_log('[API] get_user_keys: No encrypted private key found for user ID ' . $userId);
        return null;
    }

    if (empty($userInfo['session_aes_key'])) {
        error_log('[API] get_user_keys: No session AES key found for user ID ' . $userId . ' — re-authentication required');
        return null;
    }

    // Decode the AES key stored server-side
    $sessionKeyDecoded = base64_decode((string) $userInfo['session_aes_key'], true);

    if ($sessionKeyDecoded === false || strlen($sessionKeyDecoded) !== 32) {
        error_log('[API] get_user_keys: Invalid session AES key format for user ID ' . $userId);
        return null;
    }

    // Decrypt the private key using the server-side AES key
    $privateKeyDecrypted = decrypt_with_session_key(
        $userInfo['encrypted_private_key'],
        $sessionKeyDecoded
    );

    if ($privateKeyDecrypted === false) {
        error_log('[API] get_user_keys: Failed to decrypt private key for user ID ' . $userId);
        return null;
    }

    return [
        'public_key' => $userInfo['public_key'],
        'private_key' => $privateKeyDecrypted,
    ];
}