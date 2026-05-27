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
 * @file      PasswordGeneratorService.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

namespace TeampassClasses\PasswordGeneratorService;

use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
use Hackzilla\PasswordGenerator\RandomGenerator\Php7RandomGenerator;

/**
 * Generates passwords that meet the minimum complexity required by a folder.
 *
 * Complexity levels match the TP_PW_STRENGTH_* constants:
 *   0  → no constraint (any combination of chars is acceptable)
 *   20 → lowercase + numbers, length ≥ 8
 *   38 → lower + upper + numbers, length ≥ 12
 *   48 → lower + upper + numbers, length ≥ 16
 *   60 → lower + upper + numbers + symbols, length ≥ 16
 *
 * User-requested options are merged with the preset using a union strategy:
 * the preset can only ADD requirements, never remove them.
 */
class PasswordGeneratorService
{
    /**
     * Minimum hackzilla parameters indexed by TP_PW_STRENGTH_* value.
     *
     * @var array<int, array{min_length: int, lowercase: bool, uppercase: bool, numbers: bool, symbols: bool}>
     */
    private const COMPLEXITY_PRESETS = [
        0  => ['min_length' => 4,  'lowercase' => false, 'uppercase' => false, 'numbers' => false, 'symbols' => false],
        20 => ['min_length' => 8,  'lowercase' => true,  'uppercase' => false, 'numbers' => true,  'symbols' => false],
        38 => ['min_length' => 12, 'lowercase' => true,  'uppercase' => true,  'numbers' => true,  'symbols' => false],
        48 => ['min_length' => 16, 'lowercase' => true,  'uppercase' => true,  'numbers' => true,  'symbols' => false],
        60 => ['min_length' => 16, 'lowercase' => true,  'uppercase' => true,  'numbers' => true,  'symbols' => true],
    ];

    /**
     * Returns the minimum generation parameters for a given complexity level.
     *
     * If the level does not match exactly, the closest lower preset is used.
     *
     * @param int $complexityLevel One of the TP_PW_STRENGTH_* values (0, 20, 38, 48, 60).
     * @return array{min_length: int, lowercase: bool, uppercase: bool, numbers: bool, symbols: bool}
     */
    public static function getPresetForComplexity(int $complexityLevel): array
    {
        $selected = self::COMPLEXITY_PRESETS[0];
        foreach (self::COMPLEXITY_PRESETS as $level => $preset) {
            if ($complexityLevel >= $level) {
                $selected = $preset;
            }
        }

        return $selected;
    }

    /**
     * Generates a password that meets the minimum complexity required by a folder.
     *
     * The folder's required complexity is loaded from the database (teampass_misc,
     * type='complex'). User-supplied options are merged with the preset via union:
     * the preset forces its requirements but cannot remove user-requested ones.
     *
     * @param int   $folderId      Folder ID (0 = no folder, no complexity constraint).
     * @param int   $requestedSize Desired password length (may be increased to meet preset minimum).
     * @param bool  $lowercase     Whether the user wants lowercase letters.
     * @param bool  $capitalize    Whether the user wants uppercase letters.
     * @param bool  $numerals      Whether the user wants digits.
     * @param bool  $symbols       Whether the user wants special characters.
     * @param int   $maxLength     Application-wide maximum password length (0 = no limit).
     * @return array{key: string, error: string, effective_options: array<string, mixed>}
     */
    public function generateForFolder(
        int $folderId,
        int $requestedSize,
        bool $lowercase,
        bool $capitalize,
        bool $numerals,
        bool $symbols,
        int $maxLength
    ): array {
        $complexityLevel = $this->getFolderComplexity($folderId);
        $preset = self::getPresetForComplexity($complexityLevel);

        // Union: preset requirements cannot be removed by user options
        $effectiveLowercase = $lowercase  || $preset['lowercase'];
        $effectiveUppercase = $capitalize || $preset['uppercase'];
        $effectiveNumbers   = $numerals   || $preset['numbers'];
        $effectiveSymbols   = $symbols    || $preset['symbols'];

        // Ensure at least one character class is active
        if (!$effectiveLowercase && !$effectiveUppercase && !$effectiveNumbers && !$effectiveSymbols) {
            $effectiveLowercase = true;
            $effectiveUppercase = true;
            $effectiveNumbers   = true;
        }

        // Ensure length meets preset minimum; cap at application maximum
        $effectiveSize = max($requestedSize > 0 ? $requestedSize : 10, $preset['min_length']);
        if ($maxLength > 0) {
            $effectiveSize = min($effectiveSize, $maxLength);
        }

        $generator = new ComputerPasswordGenerator();
        $generator->setRandomGenerator(new Php7RandomGenerator());
        $generator->setLength($effectiveSize);
        $generator->setLowercase($effectiveLowercase);
        $generator->setUppercase($effectiveUppercase);
        $generator->setNumbers($effectiveNumbers);
        $generator->setSymbols($effectiveSymbols);

        return [
            'key'   => $generator->generatePassword(),
            'error' => '',
            'effective_options' => [
                'size'       => $effectiveSize,
                'lowercase'  => $effectiveLowercase,
                'capitalize' => $effectiveUppercase,
                'numerals'   => $effectiveNumbers,
                'symbols'    => $effectiveSymbols,
                'secure_pwd' => false,
            ],
        ];
    }

    /**
     * Loads the required complexity level for a folder from the database.
     *
     * @param int $folderId Folder ID.
     * @return int Complexity level (0 if not set or folderId ≤ 0).
     */
    private function getFolderComplexity(int $folderId): int
    {
        if ($folderId <= 0) {
            return 0;
        }

        $row = \DB::queryFirstRow(
            'SELECT valeur FROM ' . prefixTable('misc') . ' WHERE type = %s AND intitule = %i',
            'complex',
            $folderId
        );

        return $row !== null ? (int) $row['valeur'] : 0;
    }
}
