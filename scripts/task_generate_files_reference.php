#!/usr/bin/php
<?php

/**
 * task_generate_files_reference.php
 * ---
 * Helper script to generate a `files_reference.txt` file
 * listing all project files and their MD5 hashes.
 * 
 * Output format:
 *     <relative/path/to/file> <md5_hash>
 * 
 * Example:
 *     vendor/autoload.php 1bc29b36f623ba82aaf6724fd3b16718
 *
 * This is used by the enhanced TeamPass file integrity check.
 *
 * Usage:
 *     php scripts/task_generate_files_reference.php
 *
 * Configuration:
 * - Adjust `$excludeDirs` to skip entire directories.
 * - Adjust `$excludeFilePrefixes` to skip specific files.
 *
 * Intended to be run inside the TeamPass project root. 
 * It will output `files_reference.txt` in the same base directory.
 *
 * ---
 * @file      task_generate_files_refrence.php
 * @author    Gudmundur Mar Kristjansson (gudmmk@gmail.com)
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

$baseDir = realpath(__DIR__ . '/..');
$outputFile = $baseDir . '/files_reference.txt';

// Optionally exclude folders or specific file prefixes
$excludeDirs = ['upload', 'files', 'install', '_tools', 'random_compat', 'avatars'];
$excludeFilePrefixes = ['csrfp.config.php', 'settings.php', 'version-commit.php', 'phpstan.neon'];

function getAllFilesWithMd5($dir, $baseDir, $excludeDirs, $excludeFilePrefixes) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            function ($current, $key, $iterator) {
                return $current->getFilename()[0] !== '.'; // skip dotfiles & dotdirs
            }
        ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);

            // Check for excluded dirs
            foreach (explode('/', $relativePath) as $part) {
                if (in_array($part, $GLOBALS['excludeDirs'], true)) {
                    continue 2;
                }
            }

            // Check for excluded prefixes
            $filename = basename($relativePath);
            foreach ($GLOBALS['excludeFilePrefixes'] as $prefix) {
                if (strpos($filename, $prefix) === 0) {
                    continue 2;
                }
            }

            $md5 = md5_file($file->getPathname());
            $files[$relativePath] = $md5;
        }
    }
    return $files;
}

$files = getAllFilesWithMd5($baseDir, $baseDir, $excludeDirs, $excludeFilePrefixes);

$handle = fopen($outputFile, 'w');
foreach ($files as $file => $md5) {
    fwrite($handle, $file . ' ' . $md5 . PHP_EOL);
}
fclose($handle);

echo "[OK] files_reference.txt generated with " . count($files) . " files.\n";
