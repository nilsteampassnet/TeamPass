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
 * @file      otp.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/../config/include.php';

/**
 * Strip the separators services use when they display a Base32 secret.
 *
 * Authenticator setup pages usually show the secret in readable groups
 * ("JBSW Y3DP EHPK 3PXP" or "JBSWY3DP-EHPK-3PXP"). Those separators carry no
 * meaning, so they are removed instead of making the value invalid. Only
 * spaces and hyphens are dropped: anything else outside the Base32 alphabet
 * must still be rejected by the decoder.
 *
 * @param string $secret Raw secret as typed or as stored
 *
 * @return string Secret ready to be handed to the Base32 decoder
 */
function normalizeItemTotpSecret(string $secret): string
{
    return strtoupper(trim((string) preg_replace('/[\s-]+/', '', $secret), '='));
}

/**
 * Validate a TOTP profile against the supported values.
 *
 * @param string $algorithm HMAC algorithm
 * @param int $digits Code length
 * @param int $period Validity period in seconds
 *
 * @return array{algorithm:string, digits:int, period:int} Normalized profile
 *
 * @throws InvalidArgumentException When a value is outside the supported set
 */
function validateItemTotpProfile(string $algorithm, int $digits, int $period): array
{
    $algorithm = strtolower(trim($algorithm));
    if (in_array($algorithm, ['sha1', 'sha256', 'sha512'], true) === false) {
        throw new InvalidArgumentException('The TOTP algorithm must be SHA-1, SHA-256, or SHA-512.');
    }
    if (in_array($digits, [6, 8], true) === false) {
        throw new InvalidArgumentException('The TOTP code length must be 6 or 8 digits.');
    }
    if ($period < 1 || $period > ITEM_TOTP_MAX_PERIOD) {
        throw new InvalidArgumentException('The TOTP period must be between 1 and 86400 seconds.');
    }

    return [
        'algorithm' => $algorithm,
        'digits' => $digits,
        'period' => $period,
    ];
}

/**
 * Normalize and validate a TOTP secret or otpauth provisioning URI.
 *
 * This is the write path: it is the only place that accepts free-form user
 * input, so it pays for the full validation, including a forced Base32
 * decoding that rejects an unusable secret before it reaches the database.
 *
 * Provisioning URI parameters take precedence over the separately supplied
 * profile values. Bare Base32 secrets cannot identify their algorithm, so
 * they use the explicit profile values, which default to the historical
 * Teampass SHA-1/6-digit/30-second profile.
 *
 * @param string $input Base32 secret or otpauth://totp provisioning URI
 * @param string $algorithm Requested HMAC algorithm for a bare secret
 * @param int $digits Requested code length for a bare secret
 * @param int $period Requested validity period for a bare secret
 *
 * @return array{secret:string, algorithm:string, digits:int, period:int}
 *
 * @throws InvalidArgumentException When the secret, the URI or the profile is unusable
 */
function normalizeItemTotpConfiguration(
    string $input,
    string $algorithm = ITEM_TOTP_DEFAULT_ALGORITHM,
    int $digits = ITEM_TOTP_DEFAULT_DIGITS,
    int $period = ITEM_TOTP_DEFAULT_PERIOD
): array {
    $input = trim($input);
    if ($input === '') {
        throw new InvalidArgumentException('The TOTP secret cannot be empty.');
    }

    try {
        if (str_starts_with(strtolower($input), 'otpauth://')) {
            $otp = \OTPHP\Factory::loadFromProvisioningUri($input, new \Carbon\FactoryImmutable());
            if (($otp instanceof \OTPHP\TOTPInterface) === false) {
                throw new InvalidArgumentException('Only TOTP provisioning URIs are supported.');
            }

            $secret = $otp->getSecret();
            $algorithm = $otp->getDigest();
            $digits = $otp->getDigits();
            $period = $otp->getPeriod();
        } else {
            $secret = $input;
        }

        $profile = validateItemTotpProfile($algorithm, $digits, $period);

        $totp = \OTPHP\TOTP::create(
            normalizeItemTotpSecret($secret),
            $profile['period'],
            $profile['algorithm'],
            $profile['digits'],
            0,
            new \Carbon\FactoryImmutable()
        );

        // Force Base32 decoding now so invalid secrets are rejected before storage.
        $totp->at(0);
    } catch (InvalidArgumentException $exception) {
        throw $exception;
    } catch (Throwable $exception) {
        throw new InvalidArgumentException('The TOTP secret or provisioning URI is invalid.', 0, $exception);
    }

    return [
        'secret' => $totp->getSecret(),
        'algorithm' => $totp->getDigest(),
        'digits' => $totp->getDigits(),
        'period' => $totp->getPeriod(),
    ];
}

/**
 * Create a configured TOTP generator from values already stored on the item.
 *
 * This is the read path, called every time a code is displayed or refreshed.
 * The secret and the profile went through normalizeItemTotpConfiguration()
 * before being stored, so only the cheap checks are repeated here: no
 * provisioning URI parsing and no throw-away code generation. An unusable
 * secret still surfaces on the first at()/now() call, which every caller
 * already wraps in a try/catch.
 *
 * @param string $secret Base32-encoded shared secret
 * @param string $algorithm HMAC algorithm
 * @param int $digits Code length
 * @param int $period Validity period in seconds
 *
 * @return \OTPHP\TOTP
 *
 * @throws InvalidArgumentException When the stored profile is unusable
 */
function createItemTotp(
    string $secret,
    string $algorithm = ITEM_TOTP_DEFAULT_ALGORITHM,
    int $digits = ITEM_TOTP_DEFAULT_DIGITS,
    int $period = ITEM_TOTP_DEFAULT_PERIOD
): \OTPHP\TOTP {
    $profile = validateItemTotpProfile($algorithm, $digits, $period);

    return \OTPHP\TOTP::create(
        normalizeItemTotpSecret($secret),
        $profile['period'],
        $profile['algorithm'],
        $profile['digits'],
        0,
        new \Carbon\FactoryImmutable()
    );
}
