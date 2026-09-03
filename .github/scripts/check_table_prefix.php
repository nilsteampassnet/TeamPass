<?php
/**
 * Guard against hard-coded "teampass_" table names.
 *
 * TeamPass lets the administrator choose a database table prefix at install time
 * (DB_PREFIX in app/config/settings.php). Every query must therefore build its table
 * names with prefixTable(), never write "teampass_items" literally: on an installation
 * using another prefix the query hits a table that does not exist, and db_error_handler()
 * throws an uncaught exception that aborts the whole page (issue #5347).
 *
 * The reference list of table names is extracted from the installer, so it stays correct
 * as tables are added — no list to maintain here.
 *
 * Usage: php .github/scripts/check_table_prefix.php
 * Exit code 0 = clean, 1 = at least one hard-coded table name found.
 */

declare(strict_types=1);

const INSTALLER = 'public/install/install-steps/run.step5.php';

/** Tables restored by hand by the administrator; they are not created by the installer. */
const LEGACY_TABLES = ['items_old', 'items_v2'];

/** Directories never scanned: third-party code and the installer's own DDL. */
const EXCLUDED_PATHS = ['app/vendor/'];

/**
 * Build the reference table list from the installer's CREATE TABLE statements.
 *
 * @param string $installerPath Path to run.step5.php
 *
 * @return array<int, string> Table names without prefix, longest first
 */
function collectTableNames(string $installerPath): array
{
    $source = file_get_contents($installerPath);
    if ($source === false) {
        fwrite(STDERR, "Cannot read {$installerPath}\n");
        exit(1);
    }

    $matches = [];
    preg_match_all('/tablePrefix\'\]\s*\.\s*[\'"]([a-z0-9_]+)/', $source, $matches);

    $tables = array_unique(array_merge($matches[1], LEGACY_TABLES));
    // Longest first so "items_revisions" is tested before "items".
    usort($tables, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

    return $tables;
}

/**
 * List the PHP files to scan.
 *
 * @return array<int, string>
 */
function collectPhpFiles(): array
{
    $files = [];
    foreach (['app', 'public'] as $root) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() === false || $file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', $file->getPathname());
            foreach (EXCLUDED_PATHS as $excluded) {
                if (str_starts_with($path, $excluded) === true) {
                    continue 2;
                }
            }
            $files[] = $path;
        }
    }
    sort($files);

    return $files;
}

/**
 * Tell whether a line is a pure comment and can be ignored.
 *
 * @param string $line Raw source line
 *
 * @return bool
 */
function isCommentLine(string $line): bool
{
    return preg_match('#^\s*(//|\*|/\*|\#)#', $line) === 1;
}

$tables = collectTableNames(INSTALLER);
// The trailing boundary rejects three non-table forms that legitimately embed a table name:
//   - a longer identifier  ("teampass_items_backup"  -> a user-supplied backup table)
//   - a file path          ("teampass_background_tasks.lock")
//   - a doc reference      ("teampass_items.id" in a SQL COMMENT or a code comment)
$pattern = '/teampass_(' . implode('|', array_map('preg_quote', $tables)) . ')(?![a-z0-9_.])/i';

$violations = [];
foreach (collectPhpFiles() as $path) {
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }
    foreach ($lines as $index => $line) {
        if (isCommentLine($line) === true || preg_match($pattern, $line) !== 1) {
            continue;
        }
        $violations[] = sprintf('%s:%d: %s', $path, $index + 1, trim($line));
    }
}

if ($violations === []) {
    echo 'OK — no hard-coded table name found (' . count($tables) . " table names checked).\n";
    exit(0);
}

foreach ($violations as $violation) {
    [$file, $line] = explode(':', $violation, 3);
    echo '::error file=' . $file . ',line=' . $line . '::Hard-coded table name — use prefixTable() instead. ' . $violation . "\n";
}
echo "\n" . count($violations) . " hard-coded table name(s) found. TeamPass supports a custom DB_PREFIX:\n";
echo "build table names with prefixTable('items'), never write 'teampass_items' literally.\n";
exit(1);
