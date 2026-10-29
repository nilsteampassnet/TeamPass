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
 * @file      list-upgrade-operations.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * CLI helper for the Docker entrypoint. Prints, one per line, the operations of
 * public/install/upgrade_operations.php that the upgrade manifest runs after
 * upgrade_run_<version>.php and that can run from the command line.
 *
 * The entrypoint chain used to run the scripts alone, so a container upgraded
 * by its image never got the data clean-ups the web wizard applies after them.
 * Reading the shared manifest keeps the three upgrade paths on the same steps.
 *
 * Usage: php list-upgrade-operations.php 3.2.3
 * Exits 1 when the manifest is missing: the caller must not take that for
 * "no operation".
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$manifestFile = __DIR__ . '/../../public/install/upgrade_manifest.php';
if (is_file($manifestFile) === false) {
    fwrite(STDERR, "Upgrade manifest not found\n");
    exit(1);
}

require_once __DIR__ . '/../sources/upgrade_cli_logic.php';

$manifest = require $manifestFile;
foreach (tpUpgradeCliOperationsAfterScript($manifest, (string) ($argv[1] ?? '')) as $operation) {
    echo $operation, PHP_EOL;
}
exit(0);
