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
 * ---
 * Logic behind the removal of the development dependencies from an installation.
 *
 * The release archive ships app/vendor with the development packages included
 * (PHPUnit, PHPStan and its 21 MB of precompiled native extensions, the license
 * checker and their transitive packages). None of them is ever loaded at runtime,
 * so on a production server they are dead weight and needless attack surface —
 * app/vendor/bin/phpstan.phar in particular is an executable archive.
 *
 * The lists are derived from composer.lock rather than hardcoded, so they cannot
 * drift when a dependency is added or dropped: the `packages-dev` section is
 * exactly what `composer install --no-dev` would leave out.
 *
 * Kept free of any database and TeamPass dependency so it can be unit-tested in
 * isolation (tests/Unit/DevDependenciesCleanupLogicTest.php).
 *
 * @file      dev_dependencies_cleanup_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

/**
 * Derive from composer.lock what a production installation does not need.
 *
 * A package listed in both sections is treated as production: a malformed lock must
 * never be able to make the cleanup delete something the application loads.
 *
 * @param string $lockContents Raw contents of composer.lock
 *
 * @return array{packages: array<int, string>, binaries: array<int, string>}
 *         `packages` holds "vendor/package" names, relative to app/vendor.
 *         `binaries` holds bare file names, relative to app/vendor/bin.
 */
function devDependenciesFromLock(string $lockContents): array
{
    $empty = ['packages' => [], 'binaries' => []];

    /** @var mixed $lock */
    $lock = json_decode($lockContents, true);
    if (is_array($lock) === false || isset($lock['packages-dev']) === false || is_array($lock['packages-dev']) === false) {
        return $empty;
    }

    // Names shipped for production, which the cleanup must leave untouched.
    $production = [];
    if (isset($lock['packages']) === true && is_array($lock['packages']) === true) {
        foreach ($lock['packages'] as $package) {
            if (is_array($package) === true && isset($package['name']) === true && is_string($package['name']) === true) {
                $production[$package['name']] = true;
            }
        }
    }

    $packages = [];
    $binaries = [];
    foreach ($lock['packages-dev'] as $package) {
        if (is_array($package) === false || isset($package['name']) === false || is_string($package['name']) === false) {
            continue;
        }

        $name = $package['name'];
        if (isset($production[$name]) === true || devDependencyPackageNameIsSafe($name) === false) {
            continue;
        }
        $packages[$name] = true;

        // Composer installs one launcher per declared binary in app/vendor/bin,
        // under the base name only, plus a .bat companion on Windows installs.
        if (isset($package['bin']) === false || is_array($package['bin']) === false) {
            continue;
        }
        foreach ($package['bin'] as $binary) {
            if (is_string($binary) === false) {
                continue;
            }
            $baseName = basename($binary);
            if (devDependencyBinaryNameIsSafe($baseName) === false) {
                continue;
            }
            $binaries[$baseName] = true;
            $binaries[$baseName . '.bat'] = true;
        }
    }

    return ['packages' => array_keys($packages), 'binaries' => array_keys($binaries)];
}

/**
 * Tell whether a package name is a plain "vendor/package" pair safe to append to a path.
 *
 * Every segment has to start with an alphanumeric character, so neither segment can
 * ever be "." or ".." and the name cannot escape app/vendor.
 *
 * @param string $name Package name read from composer.lock
 *
 * @return bool
 */
function devDependencyPackageNameIsSafe(string $name): bool
{
    return preg_match('#^[A-Za-z0-9][A-Za-z0-9._-]*/[A-Za-z0-9][A-Za-z0-9._-]*$#', $name) === 1;
}

/**
 * Tell whether a binary name is a bare file name safe to append to a path.
 *
 * @param string $name Base name of a binary declared in composer.lock
 *
 * @return bool
 */
function devDependencyBinaryNameIsSafe(string $name): bool
{
    return preg_match('#^[A-Za-z0-9][A-Za-z0-9._-]*$#', $name) === 1;
}

/**
 * Delete a file or a directory tree, refusing anything outside the base directory.
 *
 * Best effort on purpose: the caller is an upgrade step, so a missing target counts
 * as success and an unremovable entry is reported instead of interrupting the upgrade.
 *
 * @param string $baseDir Directory the target must be contained in
 * @param string $path    File or directory to delete
 *
 * @return bool True when the target no longer exists
 */
function devDependencyRemovePath(string $baseDir, string $path): bool
{
    // Resolved without following the target, so a symlink is deleted as a link and
    // never as whatever it points to.
    if (is_link($path) === true) {
        return @unlink($path);
    }

    $realPath = realpath($path);
    if ($realPath === false) {
        // Nothing to remove.
        return true;
    }

    if (devDependencyPathIsInside($baseDir, $realPath) === false) {
        return false;
    }

    if (is_dir($realPath) === false) {
        return @unlink($realPath);
    }

    $iterator = new RecursiveIteratorIterator(
        // Symlinked directories are not descended into (FOLLOW_SYMLINKS is not set),
        // so the traversal cannot leave the tree being deleted.
        new RecursiveDirectoryIterator($realPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $entry) {
        /** @var SplFileInfo $entry */
        $entryPath = $entry->getPathname();
        if ($entry->isDir() === true && $entry->isLink() === false) {
            @rmdir($entryPath);
            continue;
        }
        @unlink($entryPath);
    }

    return @rmdir($realPath);
}

/**
 * Delete a directory only when it is already empty.
 *
 * Used on the vendor namespace directories a removal leaves behind: app/vendor/phpunit
 * becomes empty once phpunit/phpunit is gone, while app/vendor/symfony must survive
 * because production packages still live in it.
 *
 * @param string $baseDir Directory the target must be contained in
 * @param string $path    Directory to delete when empty
 *
 * @return bool True when the directory was removed
 */
function devDependencyRemoveDirectoryIfEmpty(string $baseDir, string $path): bool
{
    $realPath = realpath($path);
    if ($realPath === false || is_dir($realPath) === false) {
        return false;
    }

    if (devDependencyPathIsInside($baseDir, $realPath) === false) {
        return false;
    }

    return @rmdir($realPath);
}

/**
 * Remove from an installation every package a production install does not need.
 *
 * Single entry point shared by the installer (`cleanInstall()` in run.step6.php) and the
 * upgrade (`upgrade_run_3.2.1.php`), so a fresh install and an upgraded one end up with the
 * same tree.
 *
 * Best effort by contract: a missing composer.lock, an unreadable file or a protected
 * directory is counted in the result instead of raising. Neither call site may fail over
 * this cleanup. Idempotent — running it on an already-clean installation is a no-op.
 *
 * @param string $teampassRoot Absolute path of the TeamPass root (the parent of app/)
 *
 * @return array{packages_removed: int, binaries_removed: int, skipped: int}
 */
function devDependenciesCleanupRun(string $teampassRoot): array
{
    $result = ['packages_removed' => 0, 'binaries_removed' => 0, 'skipped' => 0];

    $vendorDir = $teampassRoot . '/app/vendor';
    $lockFile = $teampassRoot . '/composer.lock';
    if (is_dir($vendorDir) === false || is_readable($lockFile) === false) {
        return $result;
    }

    $contents = @file_get_contents($lockFile);
    if ($contents === false) {
        return $result;
    }

    $devDependencies = devDependenciesFromLock($contents);

    foreach ($devDependencies['packages'] as $packageName) {
        $packageDir = $vendorDir . '/' . $packageName;
        if (is_dir($packageDir) === false) {
            continue;
        }
        if (devDependencyRemovePath($vendorDir, $packageDir) === true) {
            $result['packages_removed']++;
            // Drop the vendor namespace directory once the removal emptied it. It stays
            // when production packages remain in it, as in app/vendor/symfony.
            devDependencyRemoveDirectoryIfEmpty($vendorDir, dirname($packageDir));
        } else {
            $result['skipped']++;
        }
    }

    // Composer launchers of the packages just removed.
    foreach ($devDependencies['binaries'] as $binaryName) {
        $binaryPath = $vendorDir . '/bin/' . $binaryName;
        if (file_exists($binaryPath) === false && is_link($binaryPath) === false) {
            continue;
        }
        if (devDependencyRemovePath($vendorDir . '/bin', $binaryPath) === true) {
            $result['binaries_removed']++;
        } else {
            $result['skipped']++;
        }
    }

    return $result;
}

/**
 * Tell whether a canonical path sits inside a base directory.
 *
 * @param string $baseDir Base directory, canonicalized when it exists
 * @param string $realPath Canonical path to test
 *
 * @return bool
 */
function devDependencyPathIsInside(string $baseDir, string $realPath): bool
{
    $realBase = realpath($baseDir);
    if ($realBase === false) {
        return false;
    }

    $prefix = rtrim($realBase, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return strncmp($realPath, $prefix, strlen($prefix)) === 0;
}
