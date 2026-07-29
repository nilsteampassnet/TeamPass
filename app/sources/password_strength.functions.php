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
 * along with TeamPass. If not, see <https://www.gnu.org/licenses/>.
 * ---
 * Safe adapter around zxcvbn-php.
 *
 * zxcvbn-php expects valid UTF-8 and may throw a Throwable for malformed legacy
 * password bytes or another unexpected matcher failure. Callers must be able to
 * keep processing other items without altering the original password.
 *
 * @file      password_strength.functions.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

if (function_exists('evaluatePasswordStrengthSafely') === false) {
    /**
     * Evaluate a plaintext password without allowing zxcvbn to abort the caller.
     *
     * The plaintext is never converted or normalised: doing so could change the
     * credential. Invalid UTF-8 is reported as unassessable and left untouched.
     *
     * @param string        $password  Exact plaintext password bytes.
     * @param callable|null $evaluator Optional evaluator for reuse/testing. It must
     *                                 return the same array shape as Zxcvbn::passwordStrength().
     *
     * @return array{success: bool, score: int|null, reason: string}
     */
    function evaluatePasswordStrengthSafely(string $password, ?callable $evaluator = null): array
    {
        if (mb_check_encoding($password, 'UTF-8') === false) {
            return [
                'success' => false,
                'score' => null,
                'reason' => 'invalid_utf8',
            ];
        }

        try {
            $result = $evaluator !== null
                ? $evaluator($password)
                : (new \ZxcvbnPhp\Zxcvbn())->passwordStrength($password);
        } catch (\Throwable) {
            return [
                'success' => false,
                'score' => null,
                'reason' => 'evaluation_failed',
            ];
        }

        if (
            is_array($result) === false
            || array_key_exists('score', $result) === false
            || is_numeric($result['score']) === false
            || (int) $result['score'] < 0
            || (int) $result['score'] > 4
        ) {
            return [
                'success' => false,
                'score' => null,
                'reason' => 'invalid_result',
            ];
        }

        return [
            'success' => true,
            'score' => (int) $result['score'],
            'reason' => '',
        ];
    }
}
