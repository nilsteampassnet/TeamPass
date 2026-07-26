<?php
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
 * Remediation for SEC-8: personal items created with the previous code received a sharekey for
 * EVERY user (the personal/owner-only intent was silently ignored). This script removes the
 * foreign sharekeys from existing personal items, keeping only the owner and the TP_USER_ID /
 * system recovery keys — i.e. it enforces invariant I1 on already-stored data.
 *
 * It is a bulk generalisation of EnsurePersonalItemHasOnlyKeysForOwner() (sources/main.functions.php),
 * but, unlike the lazy cleanup, it also processes personal items owned by an admin (decision 2026-06-15).
 *
 * This file is only the CLI front-end: the engine lives in personal_sharekeys_remediation.php and is
 * shared with the automatic run performed at upgrade (public/install/upgrade_run_3.2.1.php).
 *
 * Owner resolution (conservative, no deletion on doubt):
 *   - folder owner = numeric title of the personal root folder (personal_folder = 1) above the item;
 *   - cross-checked with log_items.at_creation.id_user;
 *   - any disagreement or unresolved owner => the item is reported and SKIPPED (no deletion).
 *
 * Usage (run from the TeamPass root directory):
 *   php app/scripts/remediate_personal_sharekeys.php [--dry-run|--execute] [--help]
 *
 * Options:
 *   --dry-run   Analyse and report only — NO changes (default).
 *   --execute   Actually delete the foreign sharekeys (irreversible — back up the DB first).
 *   --help      Show this help message.
 *
 * @file      remediate_personal_sharekeys.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

// CLI only
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Bootstraps config, constants (TP_USER_ID, ...), DB and helpers (prefixTable, loadClasses, ...)
require_once __DIR__ . '/../sources/main.functions.php';

// Remediation engine (shared with the automatic run at upgrade), which itself pulls in the pure,
// unit-tested decision logic of personal_sharekeys_logic.php.
require_once __DIR__ . '/personal_sharekeys_remediation.php';

loadClasses('DB');

$options = getopt('', ['dry-run', 'execute', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
TeamPass — Personal items sharekey remediation (SEC-8)
======================================================

Removes foreign sharekeys wrongly distributed to all users on personal items.
Only the owner and the TP_USER_ID / system recovery keys are kept.

Usage: php app/scripts/remediate_personal_sharekeys.php [OPTIONS]
       (run from the TeamPass root directory)

Options:
  --dry-run   Analyse and report only — NO changes (default)
  --execute   Apply the deletions (irreversible — BACK UP THE DATABASE FIRST)
  --help      Show this help message

The script never deletes anything for an item whose owner is ambiguous
(folder owner unresolved, or disagreeing with the at_creation log).

HELP;
    exit(0);
}

// Dry-run is the default; deletions happen only with --execute.
$execute = isset($options['execute']);

echo "\n=== TeamPass — Personal items sharekey remediation (SEC-8) ===\n\n";
echo $execute
    ? "EXECUTE mode: foreign sharekeys WILL be deleted. Make sure you have a DB backup.\n\n"
    : "DRY-RUN mode (default): no change will be made. Use --execute to apply.\n\n";

// Run the shared engine, streaming its per-item report to the console.
$stats = remediatePersonalSharekeys(
    $execute,
    static function (string $line): void {
        echo $line . "\n";
    }
);

// ─────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────
echo "\n=== Summary ===\n";
echo "Personal items analysed   : {$stats['total']}\n";
echo "Already clean             : {$stats['already_clean']}\n";
echo "Items with foreign keys   : {$stats['items_with_foreign']}\n";
echo "Skipped (unresolved owner): {$stats['skipped_unresolved']}\n";
echo "Skipped (owner conflict)  : {$stats['skipped_conflict']}\n";
echo "Foreign sharekeys found   : {$stats['foreign_found']}\n";
if ($execute === true) {
    echo "Items cleaned             : {$stats['cleaned']}\n";
    echo "Foreign sharekeys deleted : {$stats['foreign_deleted']}\n";
    echo "\nDone.\n";
} else {
    echo "\nDRY-RUN only — re-run with --execute to apply (back up the database first).\n";
}

exit(0);
