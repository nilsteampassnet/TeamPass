<?php
/**
 * TeamPass 3.2.0.0 — Migration Script
 *
 * Run this script AFTER "git pull" and BEFORE the web-based upgrade.
 * It moves user data and configuration from the old 3.1.x directory
 * layout to the new 3.2.x layout (app/ / public/ / storage/).
 *
 * Usage:
 *   php migrate_3.2.x.php [--check] [--dry-run] [--web-user=www-data] [--no-color]
 *
 * Options:
 *   --check            Inspect what the script will find/migrate and exit (no changes)
 *   --dry-run          Show what would be done without making any change
 *   --web-user=USER    Web server user for permission setup (default: www-data)
 *   --no-color         Disable ANSI color output
 *
 * @author  TeamPass
 * @license GPL-3.0
 */

declare(strict_types=1);

// ── Constants ─────────────────────────────────────────────────────────────────

const SCRIPT_VERSION = '1.0.0';
const MIN_PHP_VERSION = '8.1.0';

// ── CLI bootstrap ─────────────────────────────────────────────────────────────

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    exit('This script must be run from the command line.');
}

// Parse arguments
$opts = parseArgs($argv);
$DRY_RUN   = $opts['dry-run']  ?? false;
$CHECK     = $opts['check']    ?? false;
$WEB_USER  = $opts['web-user'] ?? 'www-data';
$NO_COLOR  = $opts['no-color'] ?? false;

// ── Colour helpers ─────────────────────────────────────────────────────────────

function col(string $text, string $code): string
{
    global $NO_COLOR;
    if ($NO_COLOR) return $text;
    return "\033[{$code}m{$text}\033[0m";
}
function ok(string $msg): void   { echo col('  [OK]  ', '0;32') . " $msg\n"; }
function info(string $msg): void { echo col(' [INFO] ', '0;36') . " $msg\n"; }
function warn(string $msg): void { echo col(' [WARN] ', '0;33') . " $msg\n"; }
function fail(string $msg): void { echo col('[ERROR] ', '0;31') . " $msg\n"; }
function step(string $msg): void { echo "\n" . col("── $msg", '1;37') . "\n"; }
function dry(string $msg): void  { echo col('  [DRY] ', '0;35') . " $msg\n"; }

// ── Arg parser ─────────────────────────────────────────────────────────────────

/**
 * @param  string[] $argv
 * @return array<string, string|bool>
 */
function parseArgs(array $argv): array
{
    $result = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--')) {
            $arg = substr($arg, 2);
            if (str_contains($arg, '=')) {
                [$key, $val] = explode('=', $arg, 2);
                $result[$key] = $val;
            } else {
                $result[$arg] = true;
            }
        }
    }
    return $result;
}

// ── Entry point ────────────────────────────────────────────────────────────────

banner();
preflightChecks();

$root = realpath(__DIR__);

if ($CHECK) {
    runCheck($root);
    exit(0);
}

// Detect old installation paths
$oldSettings  = $root . '/includes/config/settings.php';
$oldFiles     = $root . '/files';
$oldUpload    = $root . '/upload';
$oldAvatars   = $root . '/includes/avatars';
$oldBackups   = $root . '/backups';

// Detect new installation paths
$newSettings  = $root . '/app/config/settings.php';
$newFiles     = $root . '/storage/files';
$newUpload    = $root . '/storage/upload';
$newAvatars   = $root . '/public/assets/avatars';
$newBackups   = $root . '/storage/backups';
$newLogs      = $root . '/storage/logs';
$newSecrets   = $root . '/secrets';
$oldCsrfp     = $root . '/includes/libraries/csrfp/libs/csrfp.config.php';
$newCsrfp     = $root . '/app/includes/libraries/csrfp/libs/csrfp.config.php';

$errors = 0;

// ── Step 1: Validate new structure ─────────────────────────────────────────────
step('Step 1 — Validate 3.2.0 directory structure');
$errors += validateNewStructure($root);

// ── Step 2: Copy settings.php ──────────────────────────────────────────────────
step('Step 2 — Configuration file (settings.php)');
$errors += migrateSettings($oldSettings, $newSettings);

// ── Step 3: Move files/ → storage/files/ ──────────────────────────────────────
step('Step 3 — Encrypted files  (files/ → storage/files/)');
$errors += migrateDirectory($oldFiles, $newFiles, 'files', $root);

// ── Step 4: Move upload/ → storage/upload/ ────────────────────────────────────
step('Step 4 — Upload directory  (upload/ → storage/upload/)');
$errors += migrateDirectory($oldUpload, $newUpload, 'upload', $root);

// ── Step 5: Move includes/avatars/ → public/assets/avatars/ ───────────────────
step('Step 5 — Avatars  (includes/avatars/ → public/assets/avatars/)');
$errors += migrateDirectory($oldAvatars, $newAvatars, 'avatars', $root);

// ── Step 6: Move backups/ → storage/backups/ ──────────────────────────────────
step('Step 6 — Backups  (backups/ → storage/backups/)');
$errors += migrateDirectory($oldBackups, $newBackups, 'backups', $root);

// ── Step 7: Create storage/logs/ ──────────────────────────────────────────────
step('Step 7 — Create storage/logs/');
$errors += ensureLogsDir($newLogs);

// ── Step 9: Migrate encryption key file to secrets/ ───────────────────────────
step('Step 9 — Encryption key file  (SECUREPATH/SECUREFILE → secrets/)');
$errors += migrateSecureFile($root, $oldSettings, $newSettings, $newSecrets);

// ── Step 10: Ensure csrfp.config.php is present ───────────────────────────────
step('Step 10 — CSRF Protector config  (csrfp.config.php)');
$errors += migrateCsrfpConfig($oldCsrfp, $newCsrfp);

// ── Step 11: Retire old includes/ directory ────────────────────────────────────
step('Step 11 — Retire old includes/ directory');
$errors += retireOldIncludes($root, $errors);

// ── Step 8: File permissions ───────────────────────────────────────────────────
step('Step 8 — File permissions');
setPermissions($root);

// ── Summary ────────────────────────────────────────────────────────────────────
summary($errors, $root);

// ══════════════════════════════════════════════════════════════════════════════
// Functions
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Pre-migration check: inspect what the script will find and migrate, without
 * making any change. Exits 0 if all prerequisites are met, 1 otherwise.
 */
function runCheck(string $root): void
{
    step('Pre-migration check (--check mode, no changes will be made)');

    $problems = 0;

    // ── 1. New 3.2.0 directory structure ──────────────────────────────────────
    $required = ['app', 'public', 'storage', 'app/config', 'app/sources', 'public/install'];
    $newOk = true;
    foreach ($required as $dir) {
        if (!is_dir($root . '/' . $dir)) {
            fail("Missing 3.2.0 directory: $dir/  — run 'git pull' or extract the release archive first");
            $newOk = false;
            $problems++;
        }
    }
    if ($newOk) {
        ok('New 3.2.0 directory structure is in place');
    }

    // ── 2. settings.php ───────────────────────────────────────────────────────
    $oldSettings = $root . '/includes/config/settings.php';
    $newSettings = $root . '/app/config/settings.php';
    if (is_file($newSettings) && filesize($newSettings) > 200) {
        $c = file_get_contents($newSettings);
        if ($c !== false && str_contains($c, 'DB_HOST')) {
            ok('app/config/settings.php already populated — will be skipped');
        } else {
            warn('app/config/settings.php exists but looks empty — will be overwritten from includes/config/');
        }
    } elseif (is_file($oldSettings)) {
        ok('includes/config/settings.php found — will be copied to app/config/');
    } else {
        warn('includes/config/settings.php not found and app/config/settings.php not populated');
        info('  Ensure app/config/settings.php contains your DB credentials before running the upgrade.');
        $problems++;
    }

    // ── 3–6. Data directories ─────────────────────────────────────────────────
    $dataDirs = [
        'files'            => [$root . '/files',           $root . '/storage/files'],
        'upload'           => [$root . '/upload',          $root . '/storage/upload'],
        'backups'          => [$root . '/backups',         $root . '/storage/backups'],
        'includes/avatars' => [$root . '/includes/avatars',$root . '/public/assets/avatars'],
    ];

    foreach ($dataDirs as $label => [$src, $dst]) {
        if (!is_dir($src)) {
            info("$label/ — not found (empty or already migrated)");
            continue;
        }
        $items = scandir($src);
        $toMove = $items === false ? [] : array_filter(
            $items,
            static fn(string $f): bool => !in_array($f, ['.', '..', '.gitkeep', '.htaccess', 'empty_file.txt', 'index.html'], true)
        );
        $count = count($toMove);
        if ($count === 0) {
            ok("$label/ — empty, nothing to migrate");
        } else {
            ok("$label/ — $count item(s) will be migrated to " . str_replace($root . '/', '', $dst) . '/');
        }
    }

    // ── 7. Encryption key ─────────────────────────────────────────────────────
    [$securePath, $secureFile] = extractSecureSettings([$oldSettings, $newSettings]);
    if ($secureFile === '') {
        warn('SECUREFILE not defined in settings.php — encryption key will need manual migration');
        $problems++;
    } elseif (is_file($root . '/secrets/' . $secureFile)) {
        ok("Encryption key secrets/$secureFile already in place");
    } elseif ($securePath !== '' && is_file(rtrim($securePath, '/') . '/' . $secureFile)) {
        ok("Encryption key found at $securePath/$secureFile — will be copied to secrets/");
    } else {
        warn("Encryption key '$secureFile' not found — copy it manually to secrets/ before the upgrade");
        $problems++;
    }

    // ── 8. csrfp.config.php ───────────────────────────────────────────────────
    $newCsrfp = $root . '/app/includes/libraries/csrfp/libs/csrfp.config.php';
    $oldCsrfp = $root . '/includes/libraries/csrfp/libs/csrfp.config.php';
    if (is_file($newCsrfp)) {
        ok('csrfp.config.php already in place at app/includes/libraries/csrfp/libs/');
    } elseif (is_file($oldCsrfp)) {
        ok('csrfp.config.php found at old location — will be migrated and jsUrl updated');
    } else {
        $sampleFile = dirname($newCsrfp) . '/csrfp.config.sample.php';
        if (is_file($sampleFile)) {
            warn('csrfp.config.php not found — will be generated from sample (token set by web upgrade)');
        } else {
            warn('csrfp.config.php and sample file not found — will need manual creation');
            $problems++;
        }
    }

    // ── Summary ───────────────────────────────────────────────────────────────
    echo "\n";
    if ($problems === 0) {
        echo col("  All prerequisites met. Run without --check to apply the migration.\n", '0;32');
    } else {
        echo col("  {$problems} issue(s) found. Address the warnings above before migrating.\n", '0;31');
        echo col("  Use --dry-run for a full step-by-step simulation.\n", '0;33');
    }
    echo "\n";
}

function banner(): void
{
    echo "\n";
    echo col("╔══════════════════════════════════════════════════════════╗\n", '1;34');
    echo col("║        TeamPass 3.2.0.0 — Migration Script v" . SCRIPT_VERSION . "       ║\n", '1;34');
    echo col("╚══════════════════════════════════════════════════════════╝\n", '1;34');
    echo "\n";
}

function preflightChecks(): void
{
    global $DRY_RUN, $CHECK;

    step('Pre-flight checks');

    // PHP version
    if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
        fail('PHP ' . MIN_PHP_VERSION . '+ is required. Found: ' . PHP_VERSION);
        exit(1);
    }
    ok('PHP version: ' . PHP_VERSION);

    if ($CHECK) {
        warn('CHECK mode — inspecting prerequisites, no changes will be made');
    } elseif ($DRY_RUN) {
        warn('DRY-RUN mode — no files will be moved or copied');
    }
}

/** @return int Number of errors */
function validateNewStructure(string $root): int
{
    $errors = 0;
    $required = ['app', 'public', 'storage', 'app/config', 'app/sources', 'public/install'];

    foreach ($required as $dir) {
        if (!is_dir($root . '/' . $dir)) {
            fail("Missing 3.2.0 directory: $dir/  — did you run 'git pull' first?");
            $errors++;
        } else {
            ok("Found: $dir/");
        }
    }

    // Check that TP_VERSION matches 3.2.x
    $includeFile = $root . '/app/config/include.php';
    if (is_file($includeFile)) {
        $content = file_get_contents($includeFile);
        if ($content !== false && !str_contains($content, "'3.2.")) {
            warn("TP_VERSION in app/config/include.php does not look like 3.2.x — double-check your branch");
        } else {
            ok('Version marker: 3.2.x confirmed in app/config/include.php');
        }
    }

    return $errors;
}

/** @return int Number of errors */
function migrateSettings(string $src, string $dst): int
{
    global $DRY_RUN;

    // Already in place?
    if (is_file($dst) && filesize($dst) > 200) {
        // Check it has DB credentials (rough heuristic)
        $content = file_get_contents($dst);
        if ($content !== false && str_contains($content, 'DB_HOST')) {
            ok('app/config/settings.php already exists and looks populated — skipped');
            return 0;
        }
    }

    if (!is_file($src)) {
        warn('Old settings.php not found at includes/config/settings.php');
        warn('If this is a fresh install (no previous data), this is expected.');
        info('Make sure app/config/settings.php exists before running the upgrade.');
        return 0;
    }

    info("Source : $src");
    info("Target : $dst");

    if ($DRY_RUN) {
        dry("Would copy settings.php to app/config/settings.php");
        return 0;
    }

    // Backup existing dst if present
    if (is_file($dst)) {
        $backup = $dst . '.bak.' . date('Ymd_His');
        if (!copy($dst, $backup)) {
            fail("Could not backup existing app/config/settings.php to $backup");
            return 1;
        }
        info("Existing app/config/settings.php backed up to: " . basename($backup));
    }

    if (!copy($src, $dst)) {
        fail("Failed to copy settings.php to app/config/");
        return 1;
    }

    // 0644: www-data (other) must be able to read settings.php before chown is applied.
    // Harden to 0640 only after: chown www-data:www-data app/config/settings.php
    chmod($dst, 0644);

    ok('settings.php copied to app/config/settings.php');
    ok('chmod 0644 app/config/settings.php (readable by web server)');
    info('The original includes/config/settings.php is left in place as a backup.');
    return 0;
}

/** @return int Number of errors */
function migrateDirectory(string $src, string $dst, string $label, string $root): int
{
    global $DRY_RUN;

    if (!is_dir($src)) {
        info("Source directory not found: $label/ — nothing to migrate");
        return 0;
    }

    // List files to migrate (excluding .gitkeep and empty_file.txt sentinels)
    $items = listDir($src);
    if ($items === null) {
        fail("Cannot scan source directory: $src");
        return 1;
    }

    $toMove = array_filter($items, static function (string $f): bool {
        return !in_array($f, ['.', '..', '.gitkeep', '.htaccess', 'empty_file.txt', 'index.html'], true);
    });

    if (count($toMove) === 0) {
        ok("$label/ — no user files to migrate (directory is empty)");
        return 0;
    }

    info("$label/ — " . count($toMove) . " file(s)/dir(s) to migrate");

    if (!is_dir($dst)) {
        if ($DRY_RUN) {
            dry("Would create directory: " . str_replace($root . '/', '', $dst) . '/');
        } else {
            if (!mkdir($dst, 0750, true)) {
                fail("Cannot create target directory: $dst");
                return 1;
            }
        }
    }

    $errors = 0;
    foreach ($toMove as $item) {
        $srcPath = $src . '/' . $item;
        $dstPath = $dst . '/' . $item;

        if (file_exists($dstPath)) {
            info("  Already exists at destination, skipping: $item");
            continue;
        }

        if ($DRY_RUN) {
            dry("  Would move: $label/$item");
            continue;
        }

        if (!rename($srcPath, $dstPath)) {
            // rename() can fail across filesystems; fall back to copy+delete
            if (is_file($srcPath)) {
                if (copy($srcPath, $dstPath)) {
                    unlink($srcPath);
                    ok("  Moved (copy+delete): $item");
                } else {
                    fail("  Failed to move: $item");
                    $errors++;
                }
            } elseif (is_dir($srcPath)) {
                if (copyDir($srcPath, $dstPath)) {
                    deleteDir($srcPath);
                    ok("  Moved directory: $item/");
                } else {
                    fail("  Failed to move directory: $item/");
                    $errors++;
                }
            }
        } else {
            ok("  Moved: $item");
        }
    }

    return $errors;
}

/** @return int Number of errors */
function ensureLogsDir(string $logsDir): int
{
    global $DRY_RUN;

    if (is_dir($logsDir)) {
        ok('storage/logs/ already exists');
        return 0;
    }

    if ($DRY_RUN) {
        dry('Would create: storage/logs/');
        return 0;
    }

    if (!mkdir($logsDir, 0750, true)) {
        fail('Cannot create storage/logs/');
        return 1;
    }

    ok('Created: storage/logs/');
    return 0;
}

/**
 * Extract SECUREPATH and SECUREFILE values from a settings.php file using regex,
 * without eval()-ing PHP code.
 *
 * @param  string[] $settingsFiles Ordered list of settings files to try
 * @return array{string, string}   [SECUREPATH, SECUREFILE]
 */
function extractSecureSettings(array $settingsFiles): array
{
    $securePath = '';
    $secureFile = '';

    foreach ($settingsFiles as $file) {
        if (!is_file($file)) {
            continue;
        }
        $content = file_get_contents($file);
        if ($content === false) {
            continue;
        }

        if ($securePath === '' && preg_match(
            '/define\s*\(\s*["\']SECUREPATH["\']\s*,\s*["\']([^"\']*)["\']/',
            $content,
            $m
        )) {
            $securePath = $m[1];
        }

        if ($secureFile === '' && preg_match(
            '/define\s*\(\s*["\']SECUREFILE["\']\s*,\s*["\']([^"\']*)["\']/',
            $content,
            $m
        )) {
            $secureFile = $m[1];
        }

        if ($securePath !== '' && $secureFile !== '') {
            break;
        }
    }

    return [$securePath, $secureFile];
}

/** @return int Number of errors */
function migrateSecureFile(string $root, string $oldSettings, string $newSettings, string $secretsDir): int
{
    global $DRY_RUN;

    // Parse SECUREPATH and SECUREFILE from old then new settings.php
    [$securePath, $secureFile] = extractSecureSettings([$oldSettings, $newSettings]);

    if ($secureFile === '') {
        warn('SECUREFILE not found in settings.php — skipping');
        info("Manually copy your encryption key file into: secrets/");
        return 0;
    }

    $targetFile = $secretsDir . '/' . $secureFile;

    if (is_file($targetFile)) {
        ok("Encryption key file already in secrets/ — skipped");
        return 0;
    }

    if ($securePath === '') {
        warn('SECUREPATH not found in settings.php — cannot locate source key file');
        info("Manually copy '$secureFile' into: secrets/");
        return 0;
    }

    $sourceFile = rtrim($securePath, '/') . '/' . $secureFile;

    if (!is_file($sourceFile)) {
        warn("Encryption key file not found at: $sourceFile");
        info("Manually copy '$secureFile' into: secrets/");
        return 0;
    }

    info("Source : $sourceFile");
    info("Target : secrets/$secureFile");

    if ($DRY_RUN) {
        dry("Would copy encryption key file to secrets/$secureFile");
        return 0;
    }

    if (!is_dir($secretsDir) && !mkdir($secretsDir, 0700, true)) {
        fail("Cannot create directory: secrets/");
        return 1;
    }

    if (!copy($sourceFile, $targetFile)) {
        fail("Failed to copy encryption key file to secrets/");
        return 1;
    }

    chmod($targetFile, 0600);
    ok("Encryption key file copied to secrets/$secureFile");
    ok("chmod 0600: secrets/$secureFile");
    return 0;
}

/** @return int Number of errors */
function migrateCsrfpConfig(string $oldCsrfp, string $newCsrfp): int
{
    global $DRY_RUN;

    if (is_file($newCsrfp)) {
        ok('csrfp.config.php already exists — skipped');
        return 0;
    }

    $newLibsDir = dirname($newCsrfp);
    $oldLibsDir = dirname($oldCsrfp);
    $sampleFile = $newLibsDir . '/csrfp.config.sample.php';
    $source     = '';
    $label      = '';
    $rewriteUrl = false;

    if (is_file($oldCsrfp)) {
        // Pre-configured file from old layout (jsUrl uses old paths — must rewrite)
        $source     = $oldCsrfp;
        $label      = 'includes/libraries/csrfp/libs/csrfp.config.php (old layout)';
        $rewriteUrl = true;
    } else {
        // Look for the most recent dated backup (new location first, then old)
        foreach ([$newLibsDir, $oldLibsDir] as $dir) {
            $backup = findLatestCsrfpBackup($dir);
            if ($backup !== '') {
                $source     = $backup;
                $label      = basename($dir) . '/' . basename($backup) . ' (backup)';
                $rewriteUrl = true;
                break;
            }
        }
    }

    if ($source === '' && is_file($sampleFile)) {
        $source = $sampleFile;
        $label  = 'csrfp.config.sample.php';
    }

    if ($source === '') {
        fail('csrfp.config.php: no config, backup, or sample file found');
        info('Manually create: app/includes/libraries/csrfp/libs/csrfp.config.php');
        return 1;
    }

    info("Source : $label");
    info("Target : app/includes/libraries/csrfp/libs/csrfp.config.php");

    if ($DRY_RUN) {
        dry("Would create csrfp.config.php from $label");
        if ($rewriteUrl) {
            dry("Would rewrite jsUrl to /assets/lib/csrfp/csrfprotector.js");
        }
        return 0;
    }

    $content = file_get_contents($source);
    if ($content === false) {
        fail("Cannot read source: $source");
        return 1;
    }

    if ($rewriteUrl) {
        $content = rewriteCsrfpJsUrl($content);
    }

    if (file_put_contents($newCsrfp, $content) === false) {
        fail("Failed to write app/includes/libraries/csrfp/libs/csrfp.config.php");
        return 1;
    }

    chmod($newCsrfp, 0644);
    ok("csrfp.config.php created from $label");

    if ($source === $sampleFile) {
        warn('Created from sample — CSRFP_TOKEN and jsUrl will be set by the web upgrade.');
    } else {
        info('CSRFP_TOKEN preserved; jsUrl updated for the new asset path.');
        info('The web-based upgrade will finalize both values.');
    }

    return 0;
}

/**
 * Find the most recent dated backup of csrfp.config.php in a directory.
 * Matches filenames like: csrfp.config.php.2024_01_13_06_22_28
 *                     or: csrfp.config.php.2024_12_03.bak
 */
function findLatestCsrfpBackup(string $dir): string
{
    if (!is_dir($dir)) {
        return '';
    }

    $files = glob($dir . '/csrfp.config.php.*');
    if ($files === false || count($files) === 0) {
        return '';
    }

    // Keep only files whose suffix starts with a YYYY_MM_DD date
    $dated = array_filter($files, static function (string $f): bool {
        return preg_match('/csrfp\.config\.php\.\d{4}_\d{2}_\d{2}/', $f) === 1;
    });

    if (count($dated) === 0) {
        return '';
    }

    // Lexicographic sort works because the date prefix is YYYY_MM_DD[_HH_MM_SS]
    rsort($dated);
    return reset($dated) ?: '';
}

/**
 * Rewrite the jsUrl value in csrfp.config.php content to the new asset path
 * (/assets/lib/csrfp/csrfprotector.js), preserving the base URL.
 *
 * Known old suffixes are stripped to recover the base URL; if none match,
 * the base URL is reconstructed from the scheme+host+port plus any leading
 * subdirectory path segment that precedes known directory names.
 */
function rewriteCsrfpJsUrl(string $content): string
{
    if (!preg_match('/"jsUrl"\s*=>\s*"([^"]*)"/', $content, $m)) {
        return $content;
    }

    $oldUrl = $m[1];
    if ($oldUrl === '') {
        return $content;
    }

    // Path suffixes identifying the end of the base URL, most-specific first
    $knownSuffixes = [
        '/assets/lib/csrfp/csrfprotector.js',
        '/app/includes/libraries/csrfp/csrfprotector.js',
        '/includes/libraries/csrfp/js/csrfprotector.js',
        '/includes/libraries/csrfp/csrfprotector.js',
    ];

    $baseUrl = '';
    foreach ($knownSuffixes as $suffix) {
        if (str_ends_with($oldUrl, $suffix)) {
            $baseUrl = substr($oldUrl, 0, strlen($oldUrl) - strlen($suffix));
            break;
        }
    }

    if ($baseUrl === '') {
        // Unknown format: recover scheme+host+port, keep leading subdirectory segments
        $parsed = parse_url($oldUrl);
        if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
            $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
            if (isset($parsed['port'])) {
                $baseUrl .= ':' . $parsed['port'];
            }
            if (isset($parsed['path'])) {
                $stop = ['includes', 'app', 'assets'];
                $kept = [];
                foreach (explode('/', trim($parsed['path'], '/')) as $seg) {
                    if (in_array($seg, $stop, true)) break;
                    if (str_contains($seg, '.')) break; // stop at any filename
                    if ($seg !== '') $kept[] = $seg;
                }
                if (count($kept) > 0) {
                    $baseUrl .= '/' . implode('/', $kept);
                }
            }
        }
        warn("jsUrl format not recognised: $oldUrl");
        info("  Used best-effort base URL — verify jsUrl in csrfp.config.php after migration.");
    }

    $newUrl  = rtrim($baseUrl, '/') . '/assets/lib/csrfp/csrfprotector.js';
    $updated = preg_replace(
        '/"jsUrl"\s*=>\s*"[^"]*"/',
        '"jsUrl" => "' . $newUrl . '"',
        $content
    );

    info("  jsUrl: $oldUrl");
    info("      → $newUrl");

    return $updated ?? $content;
}

/**
 * Rename the old includes/ directory to _includes.bak.YYYYMMDD_HHMMSS so that
 * it is no longer reachable under the old document root while still being
 * recoverable if something went wrong.
 *
 * Only proceeds when no migration errors have accumulated (i.e. the critical
 * files — settings.php, avatars, key file, csrfp config — were already handled).
 *
 * @return int Number of errors
 */
function retireOldIncludes(string $root, int $priorErrors): int
{
    global $DRY_RUN;

    $oldDir = $root . '/includes';

    if (!is_dir($oldDir)) {
        ok('includes/ not found at root — nothing to retire');
        return 0;
    }

    if ($priorErrors > 0) {
        warn('Skipping includes/ retirement because earlier steps reported errors.');
        warn('Fix the errors above, re-run the script, then retire includes/ manually.');
        info("  mv $oldDir  {$oldDir}.bak");
        return 0;
    }

    $backupName = '_includes.bak.' . date('Ymd_His');
    $backupDir  = $root . '/' . $backupName;

    // List what remains so the user knows what is being preserved
    $remaining = [];
    $scanRoot  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($oldDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($scanRoot as $item) {
        if ($item->isFile()) {
            $remaining[] = str_replace($root . '/', '', $item->getPathname());
        }
    }

    if (count($remaining) === 0) {
        // Empty tree — just remove the directory
        if ($DRY_RUN) {
            dry('Would remove empty includes/ directory');
        } else {
            deleteDir($oldDir);
            ok('Removed empty includes/ directory');
        }
        return 0;
    }

    info(count($remaining) . ' file(s) still present inside includes/ — preserving as backup:');
    foreach ($remaining as $f) {
        info("  $f");
    }

    if ($DRY_RUN) {
        dry("Would rename includes/ → $backupName/");
        return 0;
    }

    if (!rename($oldDir, $backupDir)) {
        fail("Could not rename includes/ → $backupName/");
        info("  Rename manually: mv $oldDir $backupDir");
        info("  Or delete it once you have verified nothing is missing.");
        return 1;
    }

    ok("includes/ renamed → $backupName/");
    info('You may delete this backup once the upgrade completes successfully.');
    return 0;
}

function setPermissions(string $root): void
{
    global $DRY_RUN, $WEB_USER;

    $storageDirs = [
        $root . '/storage',
        $root . '/storage/files',
        $root . '/storage/upload',
        $root . '/storage/backups',
        $root . '/storage/logs',
        $root . '/public/assets/avatars',
    ];

    foreach ($storageDirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }

        $relPath = str_replace($root . '/', '', $dir);

        if ($DRY_RUN) {
            dry("Would chmod 0750 $relPath/");
            continue;
        }

        if (!chmod($dir, 0750)) {
            warn("Could not chmod 0750: $relPath/ — set manually if needed");
        } else {
            ok("chmod 0750: $relPath/");
        }
    }

    // secrets/ must be readable only by the web server user.
    $secretsDir = $root . '/secrets';
    if (is_dir($secretsDir)) {
        if ($DRY_RUN) {
            dry("Would chmod 0700 secrets/");
        } elseif (!chmod($secretsDir, 0700)) {
            warn("Could not chmod 0700: secrets/ — set manually if needed");
        } else {
            ok("chmod 0700: secrets/");
        }
    }

    // Directories that need 0755 now so www-data (other) can traverse them.
    // Must be hardened to 0750 only AFTER: chown {$WEB_USER}:{$WEB_USER}
    $traversableDirs = [
        $root . '/app/config',
        $root . '/app/includes/libraries/csrfp/libs',
        $root . '/app/includes/libraries/csrfp/log',
    ];

    foreach ($traversableDirs as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $relPath = str_replace($root . '/', '', $dir);
        if ($DRY_RUN) {
            dry("Would chmod 0755 $relPath/");
            continue;
        }
        if (!chmod($dir, 0755)) {
            warn("Could not chmod 0755: $relPath/ — set manually if needed");
        } else {
            ok("chmod 0755: $relPath/");
        }
    }

    info("Run the following as root before launching the web upgrade:");
    info("  # secrets/ — chmod files FIRST, then chown+chmod the directory");
    info("  # (chmod 0700 on the directory before chown would block glob expansion)");
    info("  find $root/secrets/ -type f -exec chmod 0600 {} \\;");
    info("  chown {$WEB_USER}:{$WEB_USER} $root/secrets");
    info("  chmod 0700 $root/secrets");
    info("  chown {$WEB_USER}:{$WEB_USER} $root/app/config");
    info("  chown {$WEB_USER}:{$WEB_USER} $root/app/config/settings.php");
    info("  chown {$WEB_USER}:{$WEB_USER} $root/app/includes/libraries/csrfp/libs");
    info("  chown {$WEB_USER}:{$WEB_USER} $root/app/includes/libraries/csrfp/log");
    info("  chmod 0750 $root/app/config");
    info("  chmod 0640 $root/app/config/settings.php");
    info("  chmod 0750 $root/app/includes/libraries/csrfp/libs");
    info("  chmod 0750 $root/app/includes/libraries/csrfp/log");
    info("  chown -R {$WEB_USER}:{$WEB_USER} $root/storage");
    info("  chown -R {$WEB_USER}:{$WEB_USER} $root/public/assets/avatars");
    info("  chmod -R 0750 $root/storage");
}

function summary(int $errors, string $root): void
{
    global $DRY_RUN;

    echo "\n";
    echo col("══════════════════════════════════════════════════════════\n", '1;37');

    if ($DRY_RUN) {
        echo col("  DRY-RUN complete — no changes were made.\n", '0;35');
        echo col("  Re-run without --dry-run to apply.\n", '0;35');
        echo col("══════════════════════════════════════════════════════════\n", '1;37');
        echo "\n";
        return;
    }

    if ($errors > 0) {
        echo col("  Migration completed with {$errors} error(s).\n", '0;31');
        echo col("  Please fix the errors above before running the upgrade.\n", '0;31');
    } else {
        echo col("  Migration completed successfully.\n", '0;32');
    }

    echo col("══════════════════════════════════════════════════════════\n", '1;37');
    echo "\n";

    echo col("Next steps:\n", '1;33');
    echo "\n";

    echo col("  1. Update your web server document root:\n", '1;37');
    echo "\n";
    echo col("     Apache — update your VirtualHost:\n", '0;36');
    echo "       DocumentRoot \"" . $root . "/public\"\n";
    echo "       <Directory \"" . $root . "/public\">\n";
    echo "           AllowOverride All\n";
    echo "           Require all granted\n";
    echo "       </Directory>\n";
    echo "     Then enable mod_rewrite if not already active:\n";
    echo "       sudo a2enmod rewrite && sudo systemctl reload apache2\n";
    echo "\n";
    echo col("     Nginx — update your server block:\n", '0;36');
    echo "       root " . $root . "/public;\n";
    echo "       index index.php;\n";
    echo "       location / { try_files \$uri \$uri/ /index.php?\$args; }\n";
    echo "       location ~ \\.php\$ { fastcgi_pass unix:/run/php/php8.2-fpm.sock;\n";
    echo "                             fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n";
    echo "                             include fastcgi_params; }\n";
    echo "\n";
    echo col("     ⚠  Step 4 (web upgrade) will not work until this is done.\n", '0;33');
    echo "\n";

    echo col("  2. If you use a cron job, update the path:\n", '1;37');
    echo "     php " . $root . "/app/scripts/background_tasks___handler.php\n";
    echo "\n";

    echo col("  3. If you use the WebSocket daemon, update the ExecStart path:\n", '1;37');
    echo "     ExecStart=/usr/bin/php " . $root . "/app/websocket/bin/server.php\n";
    echo "\n";

    echo col("  4. Run the web-based upgrade:\n", '1;37');
    echo "     https://<your-teampass>/install/upgrade.php\n";
    echo "     (point your browser to /install/upgrade.php)\n";
    echo "\n";

    if ($errors === 0) {
        echo col("  The migration is ready. Run the upgrade when the web server\n", '0;32');
        echo col("  document root has been updated.\n", '0;32');
    }
    echo "\n";
}

// ── Filesystem helpers ─────────────────────────────────────────────────────────

/**
 * @return string[]|null
 */
function listDir(string $path): ?array
{
    $result = scandir($path);
    return ($result === false) ? null : $result;
}

function copyDir(string $src, string $dst): bool
{
    if (!mkdir($dst, 0750, true) && !is_dir($dst)) {
        return false;
    }
    $items = scandir($src);
    if ($items === false) return false;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $s = $src . '/' . $item;
        $d = $dst . '/' . $item;
        if (is_dir($s)) {
            if (!copyDir($s, $d)) return false;
        } elseif (!copy($s, $d)) {
            return false;
        }
    }
    return true;
}

function deleteDir(string $dir): bool
{
    $items = scandir($dir);
    if ($items === false) return false;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            deleteDir($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}
