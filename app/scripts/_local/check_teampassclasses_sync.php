#!/usr/bin/env php
<?php
/**
 * TeamPass Libraries Synchronization Checker
 *
 * This script compares TeamPass custom libraries between source and vendor directories
 * to detect any discrepancies that might occur when modifications are made directly
 * in vendor instead of the source directory.
 *
 * Usage: php scripts/check_teampassclasses_sync.php [--verbose]
 */

declare(strict_types=1);

// Color codes for terminal output
const COLOR_GREEN = "\033[32m";
const COLOR_RED = "\033[31m";
const COLOR_YELLOW = "\033[33m";
const COLOR_BLUE = "\033[34m";
const COLOR_RESET = "\033[0m";
const COLOR_BOLD = "\033[1m";

// Paths
const SOURCE_DIR = __DIR__ . '/../includes/libraries/teampassclasses';
const VENDOR_DIR = __DIR__ . '/../vendor/teampassclasses';

// Parse command line arguments
$verbose = in_array('--verbose', $argv);

// Main execution
echo COLOR_BOLD . "TeamPass Libraries Synchronization Checker\n" . COLOR_RESET;
echo str_repeat("=", 70) . "\n\n";

// Check if directories exist
if (!is_dir(SOURCE_DIR)) {
    echo COLOR_RED . "ERROR: Source directory not found: " . SOURCE_DIR . COLOR_RESET . "\n";
    exit(1);
}

if (!is_dir(VENDOR_DIR)) {
    echo COLOR_RED . "ERROR: Vendor directory not found: " . VENDOR_DIR . COLOR_RESET . "\n";
    exit(1);
}

// Get list of libraries
$sourceLibraries = getLibraries(SOURCE_DIR);
$vendorLibraries = getLibraries(VENDOR_DIR);

echo "Found " . COLOR_BOLD . count($sourceLibraries) . COLOR_RESET . " libraries in source directory\n";
echo "Found " . COLOR_BOLD . count($vendorLibraries) . COLOR_RESET . " libraries in vendor directory\n\n";

// Check for missing libraries
$missingInVendor = array_diff($sourceLibraries, $vendorLibraries);
$missingInSource = array_diff($vendorLibraries, $sourceLibraries);

if (!empty($missingInVendor)) {
    echo COLOR_YELLOW . "⚠ Libraries missing in vendor:\n" . COLOR_RESET;
    foreach ($missingInVendor as $lib) {
        echo "  - $lib\n";
    }
    echo "\n";
}

if (!empty($missingInSource)) {
    echo COLOR_YELLOW . "⚠ Libraries missing in source:\n" . COLOR_RESET;
    foreach ($missingInSource as $lib) {
        echo "  - $lib\n";
    }
    echo "\n";
}

// Compare common libraries
$commonLibraries = array_intersect($sourceLibraries, $vendorLibraries);
$differences = [];
$totalFiles = 0;
$differentFiles = 0;

foreach ($commonLibraries as $library) {
    echo "Checking library: " . COLOR_BLUE . $library . COLOR_RESET . "\n";

    $sourcePath = SOURCE_DIR . '/' . $library;
    $vendorPath = VENDOR_DIR . '/' . $library;

    $libDifferences = compareDirectory($sourcePath, $vendorPath, $library);

    if (!empty($libDifferences)) {
        $differences[$library] = $libDifferences;
        $differentFiles += count($libDifferences);
    }

    $totalFiles += countPhpFiles($sourcePath);
}

echo "\n" . str_repeat("=", 70) . "\n";
echo COLOR_BOLD . "Summary\n" . COLOR_RESET;
echo str_repeat("=", 70) . "\n\n";

echo "Total PHP files checked: " . COLOR_BOLD . $totalFiles . COLOR_RESET . "\n";
echo "Files with differences: " . COLOR_BOLD . $differentFiles . COLOR_RESET . "\n\n";

if (empty($differences)) {
    echo COLOR_GREEN . "✓ All libraries are synchronized!\n" . COLOR_RESET;
    exit(0);
} else {
    echo COLOR_RED . "✗ Found differences in " . count($differences) . " library(ies)\n\n" . COLOR_RESET;

    foreach ($differences as $library => $files) {
        echo COLOR_BOLD . "\nLibrary: " . $library . COLOR_RESET . "\n";
        echo str_repeat("-", 70) . "\n";

        foreach ($files as $file => $info) {
            echo COLOR_YELLOW . "  • " . $file . COLOR_RESET . "\n";

            if ($info['status'] === 'missing_in_vendor') {
                echo "    Status: " . COLOR_RED . "Missing in vendor" . COLOR_RESET . "\n";
            } elseif ($info['status'] === 'missing_in_source') {
                echo "    Status: " . COLOR_RED . "Missing in source" . COLOR_RESET . "\n";
            } elseif ($info['status'] === 'different') {
                echo "    Status: " . COLOR_YELLOW . "Content differs" . COLOR_RESET . "\n";
                echo "    Source hash: " . $info['source_hash'] . "\n";
                echo "    Vendor hash: " . $info['vendor_hash'] . "\n";

                if ($verbose) {
                    echo "\n    " . COLOR_BOLD . "Diff preview:" . COLOR_RESET . "\n";
                    showDiff($info['source_path'], $info['vendor_path']);
                }
            }
            echo "\n";
        }
    }

    echo "\n" . COLOR_BOLD . "Recommended actions:\n" . COLOR_RESET;
    echo "1. Review the differences above\n";
    echo "2. If changes were made in vendor, copy them back to source:\n";
    echo "   cp vendor/teampassclasses/<library>/src/<File>.php includes/libraries/teampassclasses/<library>/src/\n";
    echo "3. Run: composer update teampassclasses/<library>\n";
    echo "4. Re-run this script to verify synchronization\n\n";

    echo "Use --verbose flag to see detailed diffs\n\n";

    exit(1);
}

/**
 * Get list of library directories
 */
function getLibraries(string $baseDir): array
{
    $libraries = [];
    $items = scandir($baseDir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $baseDir . '/' . $item;
        if (is_dir($path)) {
            $libraries[] = $item;
        }
    }

    sort($libraries);
    return $libraries;
}

/**
 * Compare two directories recursively
 */
function compareDirectory(string $sourcePath, string $vendorPath, string $library): array
{
    $differences = [];

    // Get all PHP files in source
    $sourceFiles = getPhpFiles($sourcePath);
    $vendorFiles = getPhpFiles($vendorPath);

    // Compare files
    foreach ($sourceFiles as $relativePath => $sourceFile) {
        if (!isset($vendorFiles[$relativePath])) {
            $differences[$relativePath] = [
                'status' => 'missing_in_vendor',
                'source_path' => $sourceFile
            ];
            continue;
        }

        $vendorFile = $vendorFiles[$relativePath];

        // Compare file contents
        $sourceHash = md5_file($sourceFile);
        $vendorHash = md5_file($vendorFile);

        if ($sourceHash !== $vendorHash) {
            $differences[$relativePath] = [
                'status' => 'different',
                'source_path' => $sourceFile,
                'vendor_path' => $vendorFile,
                'source_hash' => $sourceHash,
                'vendor_hash' => $vendorHash
            ];
        }
    }

    // Check for files in vendor but not in source
    foreach ($vendorFiles as $relativePath => $vendorFile) {
        if (!isset($sourceFiles[$relativePath])) {
            $differences[$relativePath] = [
                'status' => 'missing_in_source',
                'vendor_path' => $vendorFile
            ];
        }
    }

    return $differences;
}

/**
 * Get all PHP files in a directory recursively
 */
function getPhpFiles(string $dir): array
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if (!($file instanceof SplFileInfo)) {
            continue;
        }
        if ($file->isFile() && $file->getExtension() === 'php') {
            $relativePath = str_replace($dir . '/', '', $file->getPathname());
            $files[$relativePath] = $file->getPathname();
        }
    }

    ksort($files);
    return $files;
}

/**
 * Count PHP files in a directory
 */
function countPhpFiles(string $dir): int
{
    return count(getPhpFiles($dir));
}

/**
 * Show diff between two files
 */
function showDiff(string $sourceFile, string $vendorFile): void
{
    $sourceLines = file($sourceFile);
    $vendorLines = file($vendorFile);

    $maxLines = max(count($sourceLines), count($vendorLines));
    $contextShown = 0;
    $maxContext = 10; // Show max 10 lines of diff

    for ($i = 0; $i < $maxLines && $contextShown < $maxContext; $i++) {
        $sourceLine = $sourceLines[$i] ?? null;
        $vendorLine = $vendorLines[$i] ?? null;

        if ($sourceLine !== $vendorLine) {
            $lineNum = $i + 1;

            if ($sourceLine !== null) {
                echo "    " . COLOR_RED . "- [L$lineNum] " . rtrim($sourceLine) . COLOR_RESET . "\n";
            }

            if ($vendorLine !== null) {
                echo "    " . COLOR_GREEN . "+ [L$lineNum] " . rtrim($vendorLine) . COLOR_RESET . "\n";
            }

            $contextShown++;
        }
    }

    if ($contextShown >= $maxContext) {
        echo "    " . COLOR_YELLOW . "... (more differences not shown)" . COLOR_RESET . "\n";
    }
}
