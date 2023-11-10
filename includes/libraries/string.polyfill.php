<?php
/**
 * Provides polyfills of string functions str_starts_with, str_contains and str_ends_with,
 * core functions since PHP 8, along with their multibyte implementations mb_str_starts_with,
 * mb_str_contains and mb_str_ends_with
 *
 * Covers PHP 4 - PHP 7, safe to utilize with PHP 8
 */

/**
 * @see https://www.php.net/manual/en/function.str-starts-with
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with (string $haystack, string $needle)
    {
        return strlen($needle) === 0 || strpos($haystack, $needle) === 0;
    }
}

/**
 * @see https://www.php.net/manual/en/function.str-contains
 */
if (!function_exists('str_contains')) {
    function str_contains (string $haystack, string $needle)
    {
        return strlen($needle) === 0 || strpos($haystack, $needle) !== false;
    }
}

/**
 * @see https://www.php.net/manual/en/function.str-ends-with
 */
if (!function_exists('str_ends_with')) {
    function str_ends_with (string $haystack, string $needle)
    {
        return strlen($needle) === 0 || substr($haystack, -strlen($needle)) === $needle;
    }
}

if (!function_exists('mb_str_starts_with')) {
    function mb_str_starts_with (string $haystack, string $needle)
    {
        return mb_strlen($needle) === 0 || mb_strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('mb_str_contains')) {
    function mb_str_contains (string $haystack, string $needle)
    {
        return mb_strlen($needle) === 0 || mb_strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('mb_str_ends_with')) {
    function mb_str_ends_with (string $haystack, string $needle)
    {
        return mb_strlen($needle) === 0 || mb_substr($haystack, -mb_strlen($needle)) === $needle;
    }
}