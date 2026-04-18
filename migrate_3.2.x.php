<?php
/**
 * TeamPass 3.2.0.0 — Migration Script
 *
 * Run this script AFTER "git pull" and BEFORE the web-based upgrade.
 * It moves user data and configuration from the old 3.1.x directory
 * layout to the new 3.2.x layout (app/ / public/ / storage/).
 *
 * Usage:
 *   php migrate_3.2.x.php [--dry-run] [--web-user=www-data] [--no-color]
 *
 * Options:
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

// ── Step 8: File permissions ───────────────────────────────────────────────────
step('Step 8 — File permissions');
setPermissions($root);

// ── Summary ────────────────────────────────────────────────────────────────────
summary($errors, $root);

// ══════════════════════════════════════════════════════════════════════════════
// Functions
// ══════════════════════════════════════════════════════════════════════════════

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
    global $DRY_RUN;

    step('Pre-flight checks');

    // PHP version
    if (version_compare(PHP_VERSION, MIN_PHP_VERSION, '<')) {
        fail('PHP ' . MIN_PHP_VERSION . '+ is required. Found: ' . PHP_VERSION);
        exit(1);
    }
    ok('PHP version: ' . PHP_VERSION);

    if ($DRY_RUN) {
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

    ok('settings.php copied to app/config/settings.php');
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

    info("If permissions are wrong, run as root:");
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
