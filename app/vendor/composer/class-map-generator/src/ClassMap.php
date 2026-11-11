<?php declare(strict_types=1);

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\ClassMapGenerator;

use Composer\Pcre\Preg;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class ClassMap implements \Countable
{
    private const DEFAULT_DUPLICATES_FILTER = '{(?:^|/)(test|fixture|example|stub)s?/}i';

    /**
     * @var array<class-string, non-empty-string>
     */
    public $map = [];

    /**
     * @var array<class-string, array<non-empty-string>>
     */
    private $ambiguousClasses = [];

    /**
     * @var array<string, array<array{warning: string, className: string}>>
     */
    private $psrViolations = [];

    /**
     * Results of getAmbiguousFolders by filter, '' being used for false
     *
     * @var array<string, list<non-empty-list<non-empty-string>>>
     */
    private $ambiguousFoldersCache = [];

    /**
     * Returns the class map, which is a list of paths indexed by class name
     *
     * @return array<class-string, non-empty-string>
     */
    public function getMap(): array
    {
        return $this->map;
    }

    /**
     * Returns warning strings containing details about PSR-0/4 violations that were detected
     *
     * Violations are for ex a class which is in the wrong file/directory and thus should not be
     * found using psr-0/psr-4 autoloading but was found by the ClassMapGenerator as it scans all files.
     *
     * This is only happening when scanning paths using psr-0/psr-4 autoload type. Classmap type
     * always accepts every class as it finds it.
     *
     * @return string[]
     */
    public function getPsrViolations(): array
    {
        if (\count($this->psrViolations) === 0) {
            return [];
        }

        return array_map(static function (array $violation): string {
            return $violation['warning'];
        }, array_merge(...array_values($this->psrViolations)));
    }

    /**
     * A map of class names to their list of ambiguous paths
     *
     * This occurs when the same class can be found in several files
     *
     * To get the path the class is being mapped to, call getClassPath
     *
     * By default, paths that contain test(s), fixture(s), example(s) or stub(s) are ignored
     * as those are typically not problematic when they're dummy classes in the tests folder.
     * If you want to get these back as well you can pass false to $duplicatesFilter. Or
     * you can pass your own pattern to exclude if you need to change the default.
     *
     * @param non-empty-string|false $duplicatesFilter
     *
     * @return array<class-string, array<non-empty-string>>
     */
    public function getAmbiguousClasses($duplicatesFilter = self::DEFAULT_DUPLICATES_FILTER): array
    {
        if (false === $duplicatesFilter) {
            return $this->ambiguousClasses;
        }

        self::assertValidDuplicatesFilter($duplicatesFilter);

        $ambiguousClasses = [];
        foreach ($this->ambiguousClasses as $class => $paths) {
            $paths = array_filter($paths, static function ($path) use ($duplicatesFilter): bool {
                return !Preg::isMatch($duplicatesFilter, strtr($path, '\\', '/'));
            });
            if (\count($paths) > 0) {
                $ambiguousClasses[$class] = array_values($paths);
            }
        }

        return $ambiguousClasses;
    }

    /**
     * A list of sets of paths which are identical except for their casing
     *
     * Case-insensitive filesystems (Windows, macOS) fold each such set into a single file or
     * directory, so a project which autoloads correctly on a case-sensitive filesystem can behave
     * differently there. This typically happens when a folder is renamed to a different casing:
     * on a case-sensitive checkout src/Foo and src/foo then co-exist, while elsewhere they merge
     * and the classes below them no longer match the casing of the folder they end up in.
     *
     * Every folder or file whose own name differs in casing is reported, while differences it
     * only inherits from a parent folder are reported once at that parent's level.
     *
     * By default, paths that contain test(s), fixture(s), example(s) or stub(s) are ignored
     * as those are typically not problematic when they're dummy classes in the tests folder.
     * If you want to get these back as well you can pass false to $duplicatesFilter. Or
     * you can pass your own pattern to exclude if you need to change the default.
     *
     * A set of paths is only left out when every path below it is ignored though, as a test
     * folder merging with a production folder is still a problem for the latter.
     *
     * The result is cached until a class is added.
     *
     * @param non-empty-string|false $duplicatesFilter
     *
     * @return list<non-empty-list<non-empty-string>>
     */
    public function getAmbiguousFolders($duplicatesFilter = self::DEFAULT_DUPLICATES_FILTER): array
    {
        self::assertValidDuplicatesFilter($duplicatesFilter);

        $cacheKey = false === $duplicatesFilter ? '' : $duplicatesFilter;
        if (!isset($this->ambiguousFoldersCache[$cacheKey])) {
            $this->ambiguousFoldersCache[$cacheKey] = $this->findAmbiguousFolders($duplicatesFilter);
        }

        return $this->ambiguousFoldersCache[$cacheKey];
    }

    /**
     * @param non-empty-string|false $duplicatesFilter
     *
     * @return list<non-empty-list<non-empty-string>>
     */
    private function findAmbiguousFolders($duplicatesFilter): array
    {
        $paths = [];
        foreach ($this->map as $path) {
            $paths[] = strtr($path, '\\', '/');
        }
        // a class found in several files only has one of them in the map, but the others
        // are just as relevant here, and typically are the renamed folder's stale copies
        foreach ($this->ambiguousClasses as $duplicatePaths) {
            foreach ($duplicatePaths as $path) {
                $paths[] = strtr($path, '\\', '/');
            }
        }
        // sorted so the result does not depend on scan order, and so that each path can skip
        // the folders it shares with the previous one as those were already registered
        sort($paths, SORT_STRING);

        // folded prefix => first path seen for it, which has the lowest casing as paths are sorted
        /** @var array<string, non-empty-string> $first */
        $first = [];
        // folded prefix => name of its last segment as written => first path seen with that name
        /** @var array<string, array<string, non-empty-string>> $variants */
        $variants = [];
        $previous = '';
        foreach ($paths as $path) {
            // length of the common prefix with the previous path, as XOR turns equal bytes into \0
            $common = strspn($path ^ $previous, "\0");
            // cut back to the last / within it, as only whole folders were registered already
            $skipUntil = $common > 0 ? (int) strrpos($path, '/', $common - \strlen($path) - 1) : 0;
            $previous = $path;

            // walk every prefix of the path, the full path included so that files which only
            // differ in casing are caught as well as the folders above them
            $foldedPath = self::foldCase($path);
            // drive letters and stream wrapper schemes are never case sensitive so they are
            // skipped, see also ClassMapGenerator::normalizePath which matches the same prefixes
            $rootLength = Preg::isMatchStrictGroups('{^(?:[0-9a-z]{2,}+:(?://(?:[a-z]:)?)?|[a-z]:)}i', $path, $match) ? \strlen($match[0]) : 0;
            $offset = 0;
            foreach (explode('/', $path) as $name) {
                $end = $offset + \strlen($name);
                if ('' !== $name && $end > $rootLength && $end > $skipUntil) {
                    $foldedPrefix = substr($foldedPath, 0, $end);
                    if (!isset($first[$foldedPrefix])) {
                        $first[$foldedPrefix] = substr($path, 0, $offset).$name;
                    } elseif (0 !== substr_compare($first[$foldedPrefix], $name, $offset) && !isset($variants[$foldedPrefix][$name])) {
                        // a name written the same way as the first one only differs by its
                        // parents, which get reported at their own level
                        $variants[$foldedPrefix][$name] = substr($path, 0, $offset).$name;
                    }
                }
                $offset = $end + 1;
            }
        }

        if (\count($variants) === 0) {
            return [];
        }

        // only look for paths which are not filtered out once something was found
        /** @var array<string, true>|null $relevant */
        $relevant = null;
        if (false !== $duplicatesFilter) {
            $relevant = [];
            foreach ($paths as $path) {
                if (Preg::isMatch($duplicatesFilter, $path)) {
                    continue;
                }
                $foldedPath = self::foldCase($path);
                $offset = 0;
                while (false !== ($separator = strpos($foldedPath, '/', $offset))) {
                    $relevant[substr($foldedPath, 0, $separator)] = true;
                    $offset = $separator + 1;
                }
                $relevant[$foldedPath] = true;
            }
        }

        $ambiguousPaths = [];
        foreach ($variants as $foldedPrefix => $names) {
            if (null === $relevant || isset($relevant[$foldedPrefix])) {
                // already sorted as the paths were walked in order
                $ambiguousPaths[$foldedPrefix] = array_merge([$first[$foldedPrefix]], array_values($names));
            }
        }
        ksort($ambiguousPaths, SORT_STRING);

        return array_values($ambiguousPaths);
    }

    /**
     * @param non-empty-string|bool $duplicatesFilter
     */
    private static function assertValidDuplicatesFilter($duplicatesFilter): void
    {
        if (true === $duplicatesFilter) {
            throw new \InvalidArgumentException('$duplicatesFilter should be false or a string with a valid regex, got true.');
        }
    }

    /**
     * Lowercases ASCII characters only, as strtolower is locale dependent before PHP 8.2
     */
    private static function foldCase(string $str): string
    {
        return strtr($str, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * Sorts the class map alphabetically by class names
     */
    public function sort(): void
    {
        ksort($this->map);
    }

    /**
     * @param class-string $className
     * @param non-empty-string $path
     */
    public function addClass(string $className, string $path): void
    {
        unset($this->psrViolations[strtr($path, '\\', '/')]);

        $this->map[$className] = $path;
        $this->ambiguousFoldersCache = [];
    }

    /**
     * @param class-string $className
     * @return non-empty-string
     */
    public function getClassPath(string $className): string
    {
        if (!isset($this->map[$className])) {
            throw new \OutOfBoundsException('Class '.$className.' is not present in the map');
        }

        return $this->map[$className];
    }

    /**
     * @param class-string $className
     */
    public function hasClass(string $className): bool
    {
        return isset($this->map[$className]);
    }

    public function addPsrViolation(string $warning, string $className, string $path): void
    {
        $path = rtrim(strtr($path, '\\', '/'), '/');

        $this->psrViolations[$path][] = ['warning' => $warning, 'className' => $className];
    }

    public function clearPsrViolationsByPath(string $pathPrefix): void
    {
        $pathPrefix = rtrim(strtr($pathPrefix, '\\', '/'), '/');

        foreach ($this->psrViolations as $path => $violations) {
            if ($path === $pathPrefix || 0 === strpos($path, $pathPrefix.'/')) {
                unset($this->psrViolations[$path]);
            }
        }
    }

    /**
     * @param class-string $className
     * @param non-empty-string $path
     */
    public function addAmbiguousClass(string $className, string $path): void
    {
        $this->ambiguousClasses[$className][] = $path;
        $this->ambiguousFoldersCache = [];
    }

    public function count(): int
    {
        return \count($this->map);
    }

    /**
     * Get the raw psr violations
     *
     * This is a map of filepath to an associative array of the warning string
     * and the offending class name.
     * @return array<string, array<array{warning: string, className: string}>>
     */
    public function getRawPsrViolations(): array
    {
        return $this->psrViolations;
    }
}
