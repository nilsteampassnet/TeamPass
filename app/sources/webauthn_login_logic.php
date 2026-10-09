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
 * Passkeys used to sign in to TeamPass itself: TeamPass acting as a relying party.
 *
 * Every decision of that role lives here, free of any database and session dependency so it
 * can be unit-tested (tests/Unit/WebauthnLoginLogicTest.php): which relying party id and origin
 * to use, who may get a passwordless wrap, how the wrap key is derived, and the registration
 * and assertion ceremonies, which web-auth/webauthn-lib verifies.
 *
 * A passwordless sign-in never sees the password, and the user's private key is encrypted with
 * it. So a passkey that signs in without a password carries a second copy of the private key,
 * encrypted (AES-256-GCM) with a key derived either from the WebAuthn PRF output of the
 * authenticator (key_wrap_mode 1: a database dump cannot open it) or, for authenticators
 * without PRF, from the instance secret file (key_wrap_mode 2: the server can open it alone).
 *
 * @file      webauthn_login_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/webauthn.functions.php';
require_once __DIR__ . '/../api/inc/encryption_utils.php';

use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\FidoU2FAttestationStatementSupport;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/** webauthn_login_mode: passkeys cannot sign in to TeamPass. */
const TP_WEBAUTHN_LOGIN_MODE_DISABLED = 0;

/** webauthn_login_mode: a passkey is a second factor, the password is still typed. */
const TP_WEBAUTHN_LOGIN_MODE_SECOND_FACTOR = 1;

/** webauthn_login_mode: a passkey can also sign in alone (local accounts only). */
const TP_WEBAUTHN_LOGIN_MODE_PASSWORDLESS = 2;

/** key_wrap_mode: no copy of the private key — second factor only. */
const TP_WEBAUTHN_LOGIN_WRAP_NONE = 0;

/** key_wrap_mode: private key copy opened by the PRF output of the authenticator. */
const TP_WEBAUTHN_LOGIN_WRAP_PRF = 1;

/** key_wrap_mode: private key copy opened by the instance secret (authenticator without PRF). */
const TP_WEBAUTHN_LOGIN_WRAP_SERVER = 2;

/** A pending ceremony (options handed to the browser) expires after this many seconds. */
const TP_WEBAUTHN_LOGIN_CEREMONY_TTL = 300;

/** Time the browser gives the user to complete a ceremony, in milliseconds. */
const TP_WEBAUTHN_LOGIN_TIMEOUT_MS = 120000;

/** Passkeys one account may register. */
const TP_WEBAUTHN_LOGIN_MAX_CREDENTIALS = 20;

/** Longest device name the user may give. */
const TP_WEBAUTHN_LOGIN_LABEL_MAX = 100;

/** Size of the PRF output, of the wrap salt and of the user handle, in bytes. */
const TP_WEBAUTHN_LOGIN_SECRET_BYTES = 32;

/**
 * A password confirmed before adding a passkey stays valid this many seconds: the PRF
 * evaluation that may follow a registration does not ask for it twice.
 */
const TP_WEBAUTHN_LOGIN_STEPUP_TTL = 300;

/** An account without a password TeamPass can check proves itself with a sign-in this recent. */
const TP_WEBAUTHN_LOGIN_RECENT_SIGNIN = 600;

/**
 * Read webauthn_login_mode, an unknown value meaning disabled.
 *
 * @param array<string, mixed> $settings TeamPass settings
 *
 * @return int One of the TP_WEBAUTHN_LOGIN_MODE_* constants
 */
function webauthnLoginMode(array $settings): int
{
    $mode = (int) ($settings['webauthn_login_mode'] ?? 0);

    return in_array($mode, [TP_WEBAUTHN_LOGIN_MODE_SECOND_FACTOR, TP_WEBAUTHN_LOGIN_MODE_PASSWORDLESS], true)
        ? $mode
        : TP_WEBAUTHN_LOGIN_MODE_DISABLED;
}

/**
 * Host of a URL, lower case, '' when there is none.
 *
 * @param string $url URL
 *
 * @return string
 */
function webauthnLoginHostOf(string $url): string
{
    return strtolower((string) parse_url(trim($url), PHP_URL_HOST));
}

/**
 * Origin of a URL — scheme, host and non-default port — as the browser writes it in the
 * client data. '' when the URL has no scheme or host.
 *
 * @param string $url URL
 *
 * @return string
 */
function webauthnLoginOriginOf(string $url): string
{
    $parts = parse_url(trim($url));
    if (is_array($parts) === false || empty($parts['scheme']) === true || empty($parts['host']) === true) {
        return '';
    }
    $scheme = strtolower((string) $parts['scheme']);
    $port = isset($parts['port']) === true ? (int) $parts['port'] : null;
    $defaultPort = $scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : null);

    return $scheme . '://' . strtolower((string) $parts['host'])
        . ($port !== null && $port !== $defaultPort ? ':' . $port : '');
}

/**
 * Tell whether a relying party id may be used by a page served from a host: the host itself,
 * or a parent domain of it. An IP address only accepts itself.
 *
 * @param string $rpId Candidate relying party id
 * @param string $host Host serving TeamPass
 *
 * @return bool
 */
function webauthnLoginRpIdIsValidFor(string $rpId, string $host): bool
{
    if ($rpId === '' || $host === '') {
        return false;
    }
    if ($rpId === $host) {
        return true;
    }
    if (filter_var($host, FILTER_VALIDATE_IP) !== false || strpos($rpId, '.') === false) {
        return false;
    }

    return str_ends_with($host, '.' . $rpId);
}

/**
 * Relying party id of the TeamPass sign-in: the configured one when it suits the host of
 * cpassman_url, that host otherwise.
 *
 * Changing it orphans every passkey already registered, which the authenticators bind to it.
 *
 * @param array<string, mixed> $settings TeamPass settings
 *
 * @return string '' when cpassman_url has no host
 */
function webauthnLoginRpId(array $settings): string
{
    $host = webauthnLoginHostOf((string) ($settings['cpassman_url'] ?? ''));
    $configured = strtolower(trim(html_entity_decode((string) ($settings['webauthn_rp_id'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

    return $configured !== '' && webauthnLoginRpIdIsValidFor($configured, $host) === true ? $configured : $host;
}

/**
 * Name of the relying party the authenticator shows, 'TeamPass' by default.
 *
 * @param array<string, mixed> $settings TeamPass settings
 *
 * @return string
 */
function webauthnLoginRpName(array $settings): string
{
    $name = trim(html_entity_decode((string) ($settings['webauthn_rp_name'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    return $name !== '' ? mb_substr($name, 0, 64) : 'TeamPass';
}

/**
 * Tell whether a passkey of this account may carry a passwordless copy of the private key.
 *
 * Only in passwordless mode, and only for local accounts: a directory or OAuth2 account signing
 * in without its password would bypass the directory, which may have disabled it.
 *
 * @param array<string, mixed> $settings TeamPass settings
 * @param string               $authType users.auth_type
 *
 * @return bool
 */
function webauthnLoginCanWrap(array $settings, string $authType): bool
{
    return webauthnLoginMode($settings) === TP_WEBAUTHN_LOGIN_MODE_PASSWORDLESS && $authType === 'local';
}

/**
 * Tell whether an account must present a passkey after its password.
 *
 * Opt-in: turning passkeys on imposes nothing. An account that registered a passkey is asked
 * for one at each sign-in, unless the administrator turned MFA off for it (mfa_enabled).
 *
 * @param array<string, mixed> $settings   TeamPass settings
 * @param int                  $mfaEnabled users.mfa_enabled
 * @param bool                 $hasPasskey Whether the account has a sign-in passkey
 *
 * @return bool
 */
function webauthnLoginIsSecondFactor(array $settings, int $mfaEnabled, bool $hasPasskey): bool
{
    return webauthnLoginMode($settings) !== TP_WEBAUTHN_LOGIN_MODE_DISABLED
        && $mfaEnabled === 1
        && $hasPasskey === true;
}

/**
 * Tell why a passkey cannot sign in without a password, null when it can.
 *
 * Only in passwordless mode, only for local accounts (a directory account would bypass its
 * directory), only with a copy of the private key, and never while the account is in a state
 * that needs the password: keys to generate or to re-encrypt, one-time code to enter. A copy
 * the server opens alone is refused once the administrator requires PRF, including the copies
 * made before that setting was turned on.
 *
 * @param array<string, mixed> $settings TeamPass settings
 * @param array<string, mixed> $account  auth_type, special and key_wrap_mode of the passkey
 *
 * @return string|null Language key of the refusal
 */
function webauthnLoginPasswordlessRefusal(array $settings, array $account): ?string
{
    if (webauthnLoginMode($settings) !== TP_WEBAUTHN_LOGIN_MODE_PASSWORDLESS) {
        return 'webauthn_login_disabled';
    }
    if ((string) ($account['auth_type'] ?? '') !== 'local'
        || in_array((string) ($account['special'] ?? ''), ['generate-keys', 'recrypt-private-key', 'otc_is_required_on_next_login', 'user_added_from_ad'], true) === true
    ) {
        return 'webauthn_login_passwordless_unavailable';
    }
    $wrapMode = (int) ($account['key_wrap_mode'] ?? 0);
    if ($wrapMode === TP_WEBAUTHN_LOGIN_WRAP_NONE) {
        return 'webauthn_login_passwordless_not_enabled';
    }
    if ($wrapMode === TP_WEBAUTHN_LOGIN_WRAP_SERVER && (int) ($settings['webauthn_login_require_prf'] ?? 0) === 1) {
        return 'webauthn_login_passwordless_prf_required';
    }

    return null;
}

/**
 * Tell whether an account confirms its password before adding a sign-in passkey: local
 * accounts, and directory accounts, whose password of the last sign-in TeamPass keeps hashed.
 * OAuth2 accounts have no password TeamPass can check.
 *
 * @param string $authType users.auth_type
 *
 * @return bool
 */
function webauthnLoginStepUpUsesPassword(string $authType): bool
{
    return in_array($authType, ['local', 'ldap'], true);
}

/**
 * Tell what the caller must still do before adding a sign-in passkey or giving one a
 * passwordless copy of the private key. An open session alone is not enough: whoever holds it
 * — an unattended browser, a stolen cookie, an XSS — could plant a passkey that outlives it,
 * and survives a change of password.
 *
 * @param string $authType   users.auth_type
 * @param int    $provenAt   When the password was last confirmed for a passkey, 0 if never
 * @param int    $signedInAt When the session signed in, 0 if unknown
 * @param int    $now        Current time
 *
 * @return string 'none' when a recent proof stands, 'password' to confirm the password,
 *                'signin' to sign in again
 */
function webauthnLoginStepUpRequirement(string $authType, int $provenAt, int $signedInAt, int $now): string
{
    if (webauthnLoginIsRecent($provenAt, $now, TP_WEBAUTHN_LOGIN_STEPUP_TTL) === true) {
        return 'none';
    }
    if (webauthnLoginStepUpUsesPassword($authType) === true) {
        return 'password';
    }

    return webauthnLoginIsRecent($signedInAt, $now, TP_WEBAUTHN_LOGIN_RECENT_SIGNIN) === true ? 'none' : 'signin';
}

/**
 * Tell whether a moment lies within the last $ttl seconds.
 *
 * @param int $at  Moment, 0 when unknown
 * @param int $now Current time
 * @param int $ttl Window in seconds
 *
 * @return bool
 */
function webauthnLoginIsRecent(int $at, int $now, int $ttl): bool
{
    return $at > 0 && $at <= $now && $now - $at < $ttl;
}

/**
 * Tell whether a passwordless sign-in must be refused because the account requires another
 * second factor. A passkey that verified its user is already two factors (possession and PIN or
 * biometrics); the administrator decides whether that satisfies an imposed MFA.
 *
 * @param array<string, mixed> $settings         TeamPass settings
 * @param bool                 $otherMfaRequired Whether Google, Duo... would be required
 *
 * @return bool
 */
function webauthnLoginPasswordlessBlockedByMfa(array $settings, bool $otherMfaRequired): bool
{
    return $otherMfaRequired === true
        && (int) ($settings['webauthn_passwordless_satisfies_mfa'] ?? 1) !== 1;
}

/**
 * The WebAuthn user handle of an account: stable, so an authenticator keeps one passkey per
 * account, and opaque, so it discloses neither the user id nor the login.
 *
 * @param int    $userId       User id
 * @param string $serverSecret Instance secret
 *
 * @return string 32 raw bytes
 */
function webauthnLoginUserHandle(int $userId, string $serverSecret): string
{
    return hash_hmac('sha256', 'teampass-webauthn-login-user-v1|' . $userId, $serverSecret, true);
}

/**
 * The PRF input every sign-in passkey is evaluated with.
 *
 * Constant on purpose: a passwordless sign-in lets the user pick any discoverable passkey, so the
 * server cannot send a per-passkey input (evalByCredential needs the passkeys listed). The output
 * stays secret and specific to each passkey — the PRF is keyed by the authenticator — and the
 * per-passkey random salt goes into the key derivation instead.
 *
 * @return string 32 raw bytes
 */
function webauthnLoginPrfInput(): string
{
    return hash('sha256', 'teampass-webauthn-login-prf-input-v1', true);
}

/**
 * Derive the wrap key from the PRF output of the authenticator.
 *
 * @param string $prfOutput 32 raw bytes returned by the PRF extension
 * @param string $salt      32 raw bytes, random, stored with the credential
 *
 * @return string 32 raw bytes
 *
 * @throws InvalidArgumentException
 */
function webauthnLoginPrfWrapKey(string $prfOutput, string $salt): string
{
    if (strlen($prfOutput) !== TP_WEBAUTHN_LOGIN_SECRET_BYTES || strlen($salt) !== TP_WEBAUTHN_LOGIN_SECRET_BYTES) {
        throw new InvalidArgumentException('PRF output and salt must be 32 bytes.');
    }

    return hash_hkdf('sha256', $prfOutput, 32, 'teampass-webauthn-login-prf-v1', $salt);
}

/**
 * Derive the wrap key of an authenticator without PRF from the instance secret. The secret is
 * a file outside the database: a dump alone cannot open the wrap, the server can.
 *
 * @param string $serverSecret Instance secret
 * @param string $salt         32 raw bytes, stored with the credential
 * @param string $credentialId Raw credential id, binding the key to one passkey
 *
 * @return string 32 raw bytes
 *
 * @throws InvalidArgumentException
 */
function webauthnLoginServerWrapKey(string $serverSecret, string $salt, string $credentialId): string
{
    if ($serverSecret === '' || strlen($salt) !== TP_WEBAUTHN_LOGIN_SECRET_BYTES || $credentialId === '') {
        throw new InvalidArgumentException('A server wrap needs the instance secret, a 32-byte salt and the credential id.');
    }

    return hash_hkdf('sha256', $serverSecret, 32, 'teampass-webauthn-login-server-v1|' . webauthnBase64UrlEncode($credentialId), $salt);
}

/**
 * Encrypt the cleartext private key under a wrap key (AES-256-GCM).
 *
 * @param string $privateKey Cleartext private key
 * @param string $wrapKey    32 raw bytes
 *
 * @return string base64
 *
 * @throws RuntimeException
 */
function webauthnLoginWrapPrivateKey(string $privateKey, string $wrapKey): string
{
    $wrapped = $privateKey === '' ? false : encrypt_with_session_key($privateKey, $wrapKey);
    if ($wrapped === false) {
        throw new RuntimeException('The private key could not be wrapped.');
    }

    return $wrapped;
}

/**
 * Decrypt a wrapped private key; null when the key is wrong or the data was altered.
 *
 * @param string $wrapped base64 wrap
 * @param string $wrapKey 32 raw bytes
 *
 * @return string|null
 */
function webauthnLoginUnwrapPrivateKey(string $wrapped, string $wrapKey): ?string
{
    $privateKey = decrypt_with_session_key($wrapped, $wrapKey);

    return is_string($privateKey) === true && $privateKey !== '' ? $privateKey : null;
}

/**
 * Normalize the device name a user gives a passkey. Stored as plain text, escaped on output.
 *
 * @param mixed $label Submitted value
 *
 * @return string
 */
function webauthnLoginNormalizeLabel($label): string
{
    if (is_string($label) === false || mb_check_encoding($label, 'UTF-8') === false) {
        return '';
    }
    $label = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($label)));

    return mb_substr($label, 0, TP_WEBAUTHN_LOGIN_LABEL_MAX);
}

/**
 * Read a base64url value the browser sent, '' when absent or malformed.
 *
 * @param mixed $value Submitted value
 * @param int   $bytes Expected decoded length, 0 for any
 *
 * @return string Raw bytes
 */
function webauthnLoginReadBytes($value, int $bytes = 0): string
{
    if (is_string($value) === false || $value === '' || preg_match('/^[A-Za-z0-9_-]+={0,2}$/', $value) !== 1) {
        return '';
    }
    try {
        $raw = webauthnBase64UrlDecode($value);
    } catch (Throwable $e) {
        return '';
    }

    return $bytes === 0 || strlen($raw) === $bytes ? $raw : '';
}

/**
 * Tell whether a pending ceremony stored in session can still be completed.
 *
 * @param mixed  $pending Session value
 * @param string $purpose Ceremony the caller expects
 * @param int    $now     Current time
 *
 * @return bool
 */
function webauthnLoginPendingIsUsable($pending, string $purpose, int $now): bool
{
    return is_array($pending) === true
        && ($pending['purpose'] ?? '') === $purpose
        && is_string($pending['options'] ?? null) === true
        && (int) ($pending['created_at'] ?? 0) > $now - TP_WEBAUTHN_LOGIN_CEREMONY_TTL
        && (int) ($pending['created_at'] ?? 0) <= $now;
}

/**
 * The webauthn-lib serializer, which reads and writes the WebAuthn JSON wire format.
 *
 * @return SerializerInterface
 */
function webauthnLoginSerializer(): SerializerInterface
{
    return (new WebauthnSerializerFactory(webauthnLoginAttestationSupport()))->create();
}

/**
 * Attestation formats accepted at registration. TeamPass asks for none; a few authenticators
 * still send packed or fido-u2f statements, verified but not trusted further.
 *
 * @return AttestationStatementSupportManager
 */
function webauthnLoginAttestationSupport(): AttestationStatementSupportManager
{
    return new AttestationStatementSupportManager([
        new NoneAttestationStatementSupport(),
        PackedAttestationStatementSupport::create(webauthnLoginAlgorithms()),
        FidoU2FAttestationStatementSupport::create(),
    ]);
}

/**
 * Signature algorithms accepted: ES256, and RS256 which Windows Hello uses.
 *
 * @return Manager
 */
function webauthnLoginAlgorithms(): Manager
{
    return Manager::create()->add(ES256::create(), RS256::create());
}

/**
 * Ceremony checks bound to the one origin TeamPass is served from.
 *
 * @param string $origin Origin of cpassman_url
 *
 * @return CeremonyStepManagerFactory
 */
function webauthnLoginCeremonyFactory(string $origin): CeremonyStepManagerFactory
{
    $factory = new CeremonyStepManagerFactory();
    $factory->setAllowedOrigins([$origin]);
    $factory->setAlgorithmManager(webauthnLoginAlgorithms());
    $factory->setAttestationStatementSupportManager(webauthnLoginAttestationSupport());

    return $factory;
}

/**
 * Serialize options to the JSON the browser receives (binary fields in base64url).
 *
 * @param PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions $options Options
 *
 * @return string
 */
function webauthnLoginSerializeOptions($options): string
{
    return webauthnLoginSerializer()->serialize($options, 'json', [
        AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        JsonEncode::OPTIONS => JSON_THROW_ON_ERROR,
    ]);
}

/**
 * Build the registration options of a sign-in passkey.
 *
 * @param string   $rpId           Relying party id
 * @param string   $rpName         Relying party name
 * @param string   $userHandle     32-byte user handle
 * @param string   $login          User login
 * @param string   $displayName    User full name
 * @param string[] $excludeIds     Raw ids of the passkeys the account already has
 * @param bool     $forPasswordless Whether the passkey will sign in alone
 *
 * @return PublicKeyCredentialCreationOptions
 */
function webauthnLoginCreationOptions(
    string $rpId,
    string $rpName,
    string $userHandle,
    string $login,
    string $displayName,
    array $excludeIds,
    bool $forPasswordless
): PublicKeyCredentialCreationOptions {
    // Signing in alone requires a discoverable passkey that verifies the user; as a second
    // factor, the password already proves who is there.
    $selection = AuthenticatorSelectionCriteria::create(
        null,
        $forPasswordless === true
            ? AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED
            : AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
        $forPasswordless === true
            ? AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
            : AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED
    );

    return PublicKeyCredentialCreationOptions::create(
        PublicKeyCredentialRpEntity::create($rpName, $rpId),
        PublicKeyCredentialUserEntity::create($login, $userHandle, $displayName !== '' ? $displayName : $login),
        random_bytes(TP_WEBAUTHN_LOGIN_SECRET_BYTES),
        [
            PublicKeyCredentialParameters::create('public-key', -7),
            PublicKeyCredentialParameters::create('public-key', -257),
        ],
        $selection,
        PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
        array_map(
            static fn (string $id): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create('public-key', $id),
            $excludeIds
        ),
        TP_WEBAUTHN_LOGIN_TIMEOUT_MS
    );
}

/**
 * Build the assertion options of a sign-in passkey.
 *
 * @param string   $rpId       Relying party id
 * @param string[] $allowIds   Raw ids of the passkeys accepted, [] for any discoverable one
 * @param bool     $requireUv  Whether the user must be verified (PIN or biometrics)
 *
 * @return PublicKeyCredentialRequestOptions
 */
function webauthnLoginRequestOptions(string $rpId, array $allowIds, bool $requireUv): PublicKeyCredentialRequestOptions
{
    return PublicKeyCredentialRequestOptions::create(
        random_bytes(TP_WEBAUTHN_LOGIN_SECRET_BYTES),
        $rpId,
        array_map(
            static fn (string $id): PublicKeyCredentialDescriptor => PublicKeyCredentialDescriptor::create('public-key', $id),
            $allowIds
        ),
        $requireUv === true
            ? PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED
            : PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
        TP_WEBAUTHN_LOGIN_TIMEOUT_MS
    );
}

/**
 * Turn the credential the browser sent into a webauthn-lib object.
 *
 * Only the members the ceremony needs are kept: clientExtensionResults carries the PRF output,
 * which must never reach a log or an exception message.
 *
 * @param array<string, mixed> $credential Browser credential, binary fields in base64url
 *
 * @return PublicKeyCredential
 *
 * @throws InvalidArgumentException
 */
function webauthnLoginReadCredential(array $credential): PublicKeyCredential
{
    $response = is_array($credential['response'] ?? null) === true ? $credential['response'] : [];
    $keep = ['clientDataJSON', 'attestationObject', 'transports', 'authenticatorData', 'signature', 'userHandle'];
    $clean = [
        'id' => (string) ($credential['id'] ?? ''),
        'rawId' => (string) ($credential['rawId'] ?? ''),
        'type' => (string) ($credential['type'] ?? ''),
        'response' => array_intersect_key($response, array_flip($keep)),
    ];
    // A passkey that is not discoverable returns no user handle
    if (isset($clean['response']['userHandle']) === true && $clean['response']['userHandle'] === '') {
        unset($clean['response']['userHandle']);
    }
    if ($clean['type'] !== 'public-key' || $clean['id'] === '' || $clean['id'] !== $clean['rawId']) {
        throw new InvalidArgumentException('Malformed passkey response.');
    }

    try {
        return webauthnLoginSerializer()->deserialize((string) json_encode($clean), PublicKeyCredential::class, 'json');
    } catch (Throwable $e) {
        throw new InvalidArgumentException('Malformed passkey response.', 0, $e);
    }
}

/**
 * Verify a registration against the options handed to the browser.
 *
 * @param array<string, mixed> $credential  Browser credential
 * @param string               $optionsJson Options stored when the ceremony started
 * @param string               $origin      Origin of cpassman_url
 *
 * @return CredentialRecord
 *
 * @throws InvalidArgumentException When the response does not verify
 */
function webauthnLoginVerifyRegistration(array $credential, string $optionsJson, string $origin): CredentialRecord
{
    $options = webauthnLoginSerializer()->deserialize($optionsJson, PublicKeyCredentialCreationOptions::class, 'json');
    $response = webauthnLoginReadCredential($credential)->response;
    if ($response instanceof AuthenticatorAttestationResponse === false) {
        throw new InvalidArgumentException('Malformed passkey response.');
    }

    try {
        return AuthenticatorAttestationResponseValidator::create(webauthnLoginCeremonyFactory($origin)->creationCeremony())
            ->check($response, $options, webauthnLoginHostOf($origin));
    } catch (Throwable $e) {
        throw new InvalidArgumentException('The passkey could not be verified: ' . $e->getMessage(), 0, $e);
    }
}

/**
 * Rebuild the webauthn-lib record of a stored passkey.
 *
 * @param array<string, mixed> $row        user_webauthn_credentials row
 * @param string               $userHandle User handle of its owner
 *
 * @return CredentialRecord
 */
function webauthnLoginRecordFromRow(array $row, string $userHandle): CredentialRecord
{
    $transports = json_decode((string) ($row['transports'] ?? ''), true);

    return CredentialRecord::create(
        webauthnBase64UrlDecode((string) $row['credential_id']),
        'public-key',
        is_array($transports) === true ? array_values(array_filter($transports, 'is_string')) : [],
        'none',
        EmptyTrustPath::create(),
        Uuid::isValid((string) ($row['aaguid'] ?? '')) === true ? Uuid::fromString((string) $row['aaguid']) : Uuid::fromString('00000000-0000-0000-0000-000000000000'),
        (string) base64_decode((string) $row['public_key_cose'], true),
        $userHandle,
        (int) $row['sign_count'],
        null,
        (int) ($row['backup_eligible'] ?? 0) === 1,
        (int) ($row['backup_state'] ?? 0) === 1
    );
}

/**
 * Verify an assertion of a stored passkey against the options handed to the browser.
 *
 * @param CredentialRecord     $record      Stored passkey
 * @param array<string, mixed> $credential  Browser credential
 * @param string               $optionsJson Options stored when the ceremony started
 * @param string               $origin      Origin of cpassman_url
 *
 * @return CredentialRecord The record with its new counter and backup state
 *
 * @throws InvalidArgumentException When the response does not verify
 */
function webauthnLoginVerifyAssertion(CredentialRecord $record, array $credential, string $optionsJson, string $origin): CredentialRecord
{
    $options = webauthnLoginSerializer()->deserialize($optionsJson, PublicKeyCredentialRequestOptions::class, 'json');
    $publicKeyCredential = webauthnLoginReadCredential($credential);
    $response = $publicKeyCredential->response;
    if ($response instanceof AuthenticatorAssertionResponse === false
        || $publicKeyCredential->rawId !== $record->publicKeyCredentialId
    ) {
        throw new InvalidArgumentException('Malformed passkey response.');
    }

    try {
        return AuthenticatorAssertionResponseValidator::create(webauthnLoginCeremonyFactory($origin)->requestCeremony())
            ->check($record, $response, $options, webauthnLoginHostOf($origin), $record->userHandle);
    } catch (Throwable $e) {
        throw new InvalidArgumentException('The passkey could not be verified: ' . $e->getMessage(), 0, $e);
    }
}
