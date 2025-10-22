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
 * @copyright 2009-2025 Teampass.net
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

/**
 * Is the JWT valid
 *
 * @param string $jwt
 * @return boolean
 */
function is_jwt_valid($jwt) {
	try {
		$decoded = (array) JWT::decode($jwt, new Key(DB_PASSWD, 'HS256'));

		// Check if expiration is reached
		if ($decoded['exp'] - time() < 0) {
			return false;
		}
/*
		$decoded1 = JWT::decode($jwt, new Key(DB_PASSWD, 'HS256'), $headers = new stdClass());
		print_r($headers);
*/

		return true;
	} catch (InvalidArgumentException $e) {
		// provided key/key-array is empty or malformed.
		return false;
	} catch (DomainException $e) {
		// provided algorithm is unsupported OR
		// provided key is invalid OR
		// unknown error thrown in openSSL or libsodium OR
		// libsodium is required but not available.
		return false;
	} catch (SignatureInvalidException $e) {
		// provided JWT signature verification failed.
		return false;
	} catch (BeforeValidException $e) {
		// provided JWT is trying to be used before "nbf" claim OR
		// provided JWT is trying to be used before "iat" claim.
		return false;
	} catch (ExpiredException $e) {
		// provided JWT is trying to be used after "exp" claim.
		return false;
	} catch (UnexpectedValueException $e) {
		// provided JWT is malformed OR
		// provided JWT is missing an algorithm / using an unsupported algorithm OR
		// provided JWT algorithm does not match provided key OR
		// provided key ID in key/key-array is empty or invalid.
		return false;
	}
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

function get_bearer_data($jwt) {
    // split the jwt
	$tokenParts = explode('.', $jwt);
	$payload = base64_decode($tokenParts[1]);

    // HEADER: Get the access token from the header
    if (empty($payload) === false) {
        return json_decode($payload, true);
    }
    return null;
}

/**
 * Get user encryption keys from database
 * This function retrieves the user's public and private keys from the database
 * and validates the session using key_tempo for security
 *
 * @param int $userId User ID from JWT token
 * @param string $keyTempo Session key from JWT token
 * @param string|null $userPassword Optional user password to decrypt private key
 * @return array|null Array containing public_key and private_key, or null if validation fails
 */
function get_user_keys(int $userId, string $keyTempo, ?string $userPassword = null): ?array
{
    // Validate user session by checking key_tempo
    $userInfo = DB::queryfirstrow(
        "SELECT public_key, private_key, key_tempo
        FROM " . prefixTable('users') . "
        WHERE id = %i",
        $userId
    );

    if (DB::count() === 0) {
        // User not found
        return null;
    }

    // Validate key_tempo matches (security check)
    if ($userInfo['key_tempo'] !== $keyTempo) {
        // Session invalid or expired
        return null;
    }

    return [
        'public_key' => $userInfo['public_key'],
        'private_key' => $userInfo['private_key'],
    ];
}