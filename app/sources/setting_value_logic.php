<?php

declare(strict_types=1);

/**
 * Text values of the admin settings (teampass_misc, type 'admin').
 *
 * The generic option save (case save_option_change of admin.queries.php) used
 * FILTER_SANITIZE_FULL_SPECIAL_CHARS, which acts as htmlentities(): besides the HTML special
 * characters, it stored every accented letter as a named entity ("é" became "&eacute;"). Pages
 * that print the value into HTML hide it, but the value is also used as text: the e-mail sender
 * name, the issuer shown by authenticator apps, or an input that escapes it once more and
 * displays "Inicio de sesi&oacute;n".
 *
 * tpEncodeSettingValue() keeps the escaping of the HTML special characters and leaves the other
 * characters as typed. tpDecodeLegacySettingEntities() is its exact inverse on the values stored
 * before, used by the upgrade to repair them.
 */

/**
 * Escape a submitted setting value the way the generic option save stores it.
 *
 * Same result as filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS) for every ASCII
 * character, including entities that are already present (they are not encoded twice) and
 * invalid UTF-8 (empty string), but without turning the other characters into named entities.
 *
 * @param mixed $value Decoded value received from the browser
 *
 * @return string|false The value to store, or false when it is not a scalar (as filter_var())
 */
function tpEncodeSettingValue(mixed $value): string|false
{
    if (is_array($value) === true || is_object($value) === true) {
        return false;
    }
    if (is_bool($value) === true) {
        return $value === true ? '1' : '';
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8', false);
}

/**
 * Decode the named and numeric entities that stand for a character above U+009F.
 *
 * Only those characters were encoded by FILTER_SANITIZE_FULL_SPECIAL_CHARS on top of what
 * tpEncodeSettingValue() does, so the result is the value the save stores today. The HTML
 * special characters (& < > " ') stay encoded, as do control characters and unknown entities:
 * the function never makes a value unsafe to print into a page, and applying it twice changes
 * nothing.
 *
 * @param string $value Stored value
 *
 * @return string
 */
function tpDecodeLegacySettingEntities(string $value): string
{
    if (strpos($value, '&') === false) {
        return $value;
    }

    $decoded = preg_replace_callback(
        '/&(?:#[0-9]+|#[xX][0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);/',
        static function (array $matches): string {
            // HTML 4.01 is the table htmlentities() encoded with (&lang; is U+2329 there).
            $character = html_entity_decode($matches[0], ENT_QUOTES | ENT_HTML401, 'UTF-8');

            return preg_match('/^[^\x{00}-\x{9F}]$/u', $character) === 1 ? $character : $matches[0];
        },
        $value
    );

    return is_string($decoded) === true ? $decoded : $value;
}
