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
 * Whether the database schema lags the deployed files, kept free of any database access so
 * every upgrade gate shares one definition and it can be unit-tested on its own.
 *
 * It is included by:
 *   - app/sources/main.functions.php            (upgradeRequired(), web sign-in gate)
 *   - app/sources/upgrade_cli_logic.php         (command-line upgrade decision)
 *   - app/scripts/read-db-upgrade-timestamp.php (Docker entrypoint)
 *   - tests/Unit/UpgradeRequirementLogicTest.php
 *
 * @file      upgrade_requirement_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Schema level the database actually reached.
 *
 * upgrade_timestamp is the time the last upgrade script ended, not a schema level: an older
 * release upgraded after the cutoff of a newer one shows a fresh timestamp. teampass_schema_level,
 * recorded once a whole upgrade chain has finished, is the level itself. The lower of the two
 * wins; a missing or non-numeric level leaves the timestamp alone.
 *
 * @param int    $upgradeTimestamp upgrade_timestamp recorded in the database (0 when missing)
 * @param string $schemaLevel      teampass_schema_level recorded in the database ('' when missing)
 *
 * @return int The effective level, compared with UPGRADE_MIN_DATE
 */
function tpEffectiveSchemaLevel(int $upgradeTimestamp, string $schemaLevel): int
{
    $schemaLevel = trim($schemaLevel);
    if (ctype_digit($schemaLevel) === false) {
        return $upgradeTimestamp;
    }

    return min($upgradeTimestamp, (int) $schemaLevel);
}

/**
 * Whether the database must go through the upgrade before the deployed files can use it.
 *
 * @param int    $upgradeTimestamp upgrade_timestamp recorded in the database (0 when missing)
 * @param string $schemaLevel      teampass_schema_level recorded in the database ('' when missing)
 * @param int    $minDate          UPGRADE_MIN_DATE of the deployed files
 *
 * @return bool True when the schema is behind
 */
function tpSchemaUpgradeRequired(int $upgradeTimestamp, string $schemaLevel, int $minDate): bool
{
    return tpEffectiveSchemaLevel($upgradeTimestamp, $schemaLevel) < $minDate;
}
