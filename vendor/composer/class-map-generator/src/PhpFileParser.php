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

use RuntimeException;
use Composer\Pcre\Preg;

/**
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class PhpFileParser
{
    /**
     * Extract the classes in the given file
     *
     * @param  string            $path The file to check
     * @throws RuntimeException
     * @return list<class-string> The found classes
     */
    public static function findClasses(string $path): array
    {
        $extraTypes = self::getExtraTypes();

        if (!function_exists('php_strip_whitespace')) {
            throw new RuntimeException('Classmap generation relies on the php_strip_whitespace function, but it has been disabled by the disable_functions directive.');
        }

        // Use @ here instead of Silencer to actively suppress 'unhelpful' output
        // @link https://github.com/composer/composer/pull/4886
        $contents = @php_strip_whitespace($path);
        if ('' === $contents) {
            if (!file_exists($path)) {
                $message = 'File at "%s" does not exist, check your classmap definitions';
            } elseif (!self::isReadable($path)) {
                $message = 'File at "%s" is not readable, check its permissions';
            } elseif ('' === trim((string) file_get_contents($path))) {
                // The input file was really empty and thus contains no classes
                return [];
            } else {
                $message = 'File at "%s" could not be parsed as PHP, it may be binary or corrupted';
            }

            $error = error_get_last();
            if (isset($error['message'])) {
                $message .= PHP_EOL . 'The following message may be helpful:' . PHP_EOL . $error['message'];
            }

            throw new RuntimeException(sprintf($message, $path));
        }

        // return early if there is no chance of matching anything in this file
        Preg::matchAllStrictGroups('{\b(?:class|interface|trait'.$extraTypes.')\s}i', $contents, $matches);
        if ([] === $matches) {
            return [];
        }

        $p = new PhpFileCleaner($contents, count($matches[0]));
        $contents = $p->clean();
        unset($p);

        Preg::matchAll('{
            (?:
                 \b(?<![\\\\$:>])(?P<type>class|interface|trait'.$extraTypes.') \s++ (?P<name>[a-zA-Z_\x7f-\xff:][a-zA-Z0-9_\x7f-\xff:\-]*+)
               | \b(?<![\\\\$:>])(?P<ns>namespace) (?P<nsname>\s++[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+(?:\s*+\\\\\s*+[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+)*+)? \s*+ [\{;]
            )
        }ix', $contents, $matches);

        $classes = [];
        $namespace = '';

        for ($i = 0, $len = count($matches['type']); $i < $len; ++$i) {
            if (isset($matches['ns'][$i]) && $matches['ns'][$i] !== '') {
                $namespace = str_replace([' ', "\t", "\r", "\n"], '', (string) $matches['nsname'][$i]) . '\\';
            } else {
                $name = $matches['name'][$i];
                assert(is_string($name));
                // skip anon classes extending/implementing
                if ($name === 'extends') {
                    continue;
                }
                if ($name === 'implements') {
                    continue;
                }

                if ($name[0] === ':') {
                    // This is an XHP class, https://github.com/facebook/xhp
                    $name = 'xhp'.substr(str_replace(['-', ':'], ['_', '__'], $name), 1);
                } elseif (strtolower((string) $matches['type'][$i]) === 'enum') {
                    // something like:
                    //   enum Foo: int { HERP = '123'; }
                    // The regex above captures the colon, which isn't part of
                    // the class name.
                    // or:
                    //   enum Foo:int { HERP = '123'; }
                    // The regex above captures the colon and type, which isn't part of
                    // the class name.
                    $colonPos = strrpos($name, ':');
                    if (false !== $colonPos) {
                        $name = substr($name, 0, $colonPos);
                    }
                }

                /** @var class-string */
                $className = ltrim($namespace . $name, '\\');
                $classes[] = $className;
            }
        }

        return $classes;
    }

    private static function getExtraTypes(): string
    {
        static $extraTypes = null;

        if (null === $extraTypes) {
            $extraTypes = '';
            $extraTypesArray = [];
            if (PHP_VERSION_ID >= 80100 || (defined('HHVM_VERSION') && version_compare(HHVM_VERSION, '3.3', '>='))) {
                $extraTypes .= '|enum';
                $extraTypesArray = ['enum'];
            }

            PhpFileCleaner::setTypeConfig(array_merge(['class', 'interface', 'trait'], $extraTypesArray));
        }

        return $extraTypes;
    }

    /**
     * Cross-platform safe version of is_readable()
     *
     * This will also check for readability by reading the file as is_readable can not be trusted on network-mounts
     * and \\wsl$ paths. See https://github.com/composer/composer/issues/8231 and https://bugs.php.net/bug.php?id=68926
     *
     * @see Composer\Util\Filesystem::isReadable
     *
     * @return bool
     */
    private static function isReadable(string $path)
    {
        if (is_readable($path)) {
            return true;
        }

        if (is_file($path)) {
            return false !== @file_get_contents($path, false, null, 0, 1);
        }

        // assume false otherwise
        return false;
    }
}
