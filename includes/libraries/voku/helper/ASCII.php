<?php

declare(strict_types=1);

namespace voku\helper;

final class ASCII
{
    //
    // INFO: https://en.wikipedia.org/wiki/List_of_ISO_639-1_codes
    //

    const UZBEK_LANGUAGE_CODE = 'uz';

    const TURKMEN_LANGUAGE_CODE = 'tk';

    const THAI_LANGUAGE_CODE = 'th';

    const PASHTO_LANGUAGE_CODE = 'ps';

    const ORIYA_LANGUAGE_CODE = 'or';

    const MONGOLIAN_LANGUAGE_CODE = 'mn';

    const KOREAN_LANGUAGE_CODE = 'ko';

    const KIRGHIZ_LANGUAGE_CODE = 'ky';

    const ARMENIAN_LANGUAGE_CODE = 'hy';

    const BENGALI_LANGUAGE_CODE = '	bn';

    const BELARUSIAN_LANGUAGE_CODE = 'be';

    const AMHARIC_LANGUAGE_CODE = 'am';

    const JAPANESE_LANGUAGE_CODE = 'ja';

    const CHINESE_LANGUAGE_CODE = 'zh';

    const DUTCH_LANGUAGE_CODE = 'nl';

    const ITALIAN_LANGUAGE_CODE = 'it';

    const MACEDONIAN_LANGUAGE_CODE = 'mk';

    const PORTUGUESE_LANGUAGE_CODE = 'pt';

    const GREEKLISH_LANGUAGE_CODE = 'el__greeklish';

    const GREEK_LANGUAGE_CODE = 'el';

    const HINDI_LANGUAGE_CODE = 'hi';

    const SWEDISH_LANGUAGE_CODE = 'sv';

    const TURKISH_LANGUAGE_CODE = 'tr';

    const BULGARIAN_LANGUAGE_CODE = 'bg';

    const HUNGARIAN_LANGUAGE_CODE = 'hu';

    const MYANMAR_LANGUAGE_CODE = 'my';

    const CROATIAN_LANGUAGE_CODE = 'hr';

    const FINNISH_LANGUAGE_CODE = 'fi';

    const GEORGIAN_LANGUAGE_CODE = 'ka';

    const RUSSIAN_LANGUAGE_CODE = 'ru';

    const RUSSIAN_PASSPORT_2013_LANGUAGE_CODE = 'ru__passport_2013';

    const RUSSIAN_GOST_2000_B_LANGUAGE_CODE = 'ru__gost_2000_b';

    const UKRAINIAN_LANGUAGE_CODE = 'uk';

    const KAZAKH_LANGUAGE_CODE = 'kk';

    const CZECH_LANGUAGE_CODE = 'cs';

    const DANISH_LANGUAGE_CODE = 'da';

    const POLISH_LANGUAGE_CODE = 'pl';

    const ROMANIAN_LANGUAGE_CODE = 'ro';

    const ESPERANTO_LANGUAGE_CODE = 'eo';

    const ESTONIAN_LANGUAGE_CODE = 'et';

    const LATVIAN_LANGUAGE_CODE = 'lv';

    const LITHUANIAN_LANGUAGE_CODE = 'lt';

    const NORWEGIAN_LANGUAGE_CODE = 'no';

    const VIETNAMESE_LANGUAGE_CODE = 'vi';

    const ARABIC_LANGUAGE_CODE = 'ar';

    const PERSIAN_LANGUAGE_CODE = 'fa';

    const SERBIAN_LANGUAGE_CODE = 'sr';

    const SERBIAN_CYRILLIC_LANGUAGE_CODE = 'sr__cyr';

    const SERBIAN_LATIN_LANGUAGE_CODE = 'sr__lat';

    const AZERBAIJANI_LANGUAGE_CODE = 'az';

    const SLOVAK_LANGUAGE_CODE = 'sk';

    const FRENCH_LANGUAGE_CODE = 'fr';

    const FRENCH_AUSTRIAN_LANGUAGE_CODE = 'fr_at';

    const FRENCH_SWITZERLAND_LANGUAGE_CODE = 'fr_ch';

    const GERMAN_LANGUAGE_CODE = 'de';

    const GERMAN_AUSTRIAN_LANGUAGE_CODE = 'de_at';

    const GERMAN_SWITZERLAND_LANGUAGE_CODE = 'de_ch';

    const ENGLISH_LANGUAGE_CODE = 'en';

    const EXTRA_LATIN_CHARS_LANGUAGE_CODE = 'latin';

    const EXTRA_WHITESPACE_CHARS_LANGUAGE_CODE = ' ';

    const EXTRA_MSWORD_CHARS_LANGUAGE_CODE = 'msword';

    /**
     * @var array<string, array<string, string>>|null
     */
    private static $ASCII_MAPS;

    /**
     * @var array<string, array<string, string>>|null
     */
    private static $ASCII_MAPS_AND_EXTRAS;

    /**
     * @var array<string, int>|null
     */
    private static $ORD;

    /**
     * url: https://en.wikipedia.org/wiki/Wikipedia:ASCII#ASCII_printable_characters
     *
     * @var string
     */
    private static $REGEX_ASCII = "/[^\x09\x10\x13\x0A\x0D\x20-\x7E]/";

    /**
     * bidirectional text chars
     *
     * url: https://www.w3.org/International/questions/qa-bidi-unicode-controls
     *
     * @var array<int, string>
     */
    private static $BIDI_UNI_CODE_CONTROLS_TABLE = [
        // LEFT-TO-RIGHT EMBEDDING (use -> dir = "ltr")
        8234 => "\xE2\x80\xAA",
        // RIGHT-TO-LEFT EMBEDDING (use -> dir = "rtl")
        8235 => "\xE2\x80\xAB",
        // POP DIRECTIONAL FORMATTING // (use -> </bdo>)
        8236 => "\xE2\x80\xAC",
        // LEFT-TO-RIGHT OVERRIDE // (use -> <bdo dir = "ltr">)
        8237 => "\xE2\x80\xAD",
        // RIGHT-TO-LEFT OVERRIDE // (use -> <bdo dir = "rtl">)
        8238 => "\xE2\x80\xAE",
        // LEFT-TO-RIGHT ISOLATE // (use -> dir = "ltr")
        8294 => "\xE2\x81\xA6",
        // RIGHT-TO-LEFT ISOLATE // (use -> dir = "rtl")
        8295 => "\xE2\x81\xA7",
        // FIRST STRONG ISOLATE // (use -> dir = "auto")
        8296 => "\xE2\x81\xA8",
        // POP DIRECTIONAL ISOLATE
        8297 => "\xE2\x81\xA9",
    ];

    /**
     * Returns an replacement array for ASCII methods.
     *
     * @psalm-suppress InvalidNullableReturnType - we use the prepare* methods here, so we don't get NULL here
     *
     * @param bool $replace_extra_symbols [optional] <p>Add some more replacements e.g. "£" with " pound ".</p>
     *
     * @return array<string, array<string , string>>
     */
    public static function charsArray(bool $replace_extra_symbols = false): array
    {
        if ($replace_extra_symbols) {
            self::prepareAsciiAndExtrasMaps();

            /** @psalm-suppress NullableReturnStatement */
            return self::$ASCII_MAPS_AND_EXTRAS;
        }

        self::prepareAsciiMaps();

        /** @psalm-suppress NullableReturnStatement */
        return self::$ASCII_MAPS;
    }

    /**
     * Returns an replacement array for ASCII methods with a mix of multiple languages.
     *
     * @param bool $replace_extra_symbols [optional] <p>Add some more replacements e.g. "£" with " pound ".</p>
     *
     * @return array<string, array<int, string>>
     *                       <p>An array of replacements.</p>
     */
    public static function charsArrayWithMultiLanguageValues(bool $replace_extra_symbols = false): array
    {
        static $CHARS_ARRAY = [];
        $cacheKey = '' . $replace_extra_symbols;

        if (isset($CHARS_ARRAY[$cacheKey])) {
            return $CHARS_ARRAY[$cacheKey];
        }

        // init
        $return = [];
        $language_all_chars = self::charsArrayWithSingleLanguageValues($replace_extra_symbols);

        /** @noinspection AlterInForeachInspection */
        foreach ($language_all_chars['replace'] as $replaceKey => &$replaceValue) {
            /** @noinspection AlterInForeachInspection */
            foreach ($language_all_chars['orig'] as $origKey => &$origValue) {
                if ($replaceKey === $origKey) {
                    $return[$replaceValue][] = $origValue;
                }
            }
        }

        $CHARS_ARRAY[$cacheKey] = $return;

        return $return;
    }

    /**
     * Returns an replacement array for ASCII methods with one language.
     *
     * For example, German will map 'ä' to 'ae', while other languages
     * will simply return e.g. 'a'.
     *
     * @psalm-suppress InvalidNullableReturnType - we use the prepare* methods here, so we don't get NULL here
     *
     * @param string $language              [optional] <p>Language of the source string e.g.: en, de_at, or de-ch.
     *                                      (default is 'en') | ASCII::*_LANGUAGE_CODE</p>
     * @param bool   $replace_extra_symbols [optional] <p>Add some more replacements e.g. "£" with " pound ".</p>
     *
     * @return array{orig: string[], replace: string[]}
     *                     <p>An array of replacements.</p>
     */
    public static function charsArrayWithOneLanguage(
        string $language = self::ENGLISH_LANGUAGE_CODE,
        bool $replace_extra_symbols = false
    ): array {
        $language = self::get_language($language);

        // init
        static $CHARS_ARRAY = [];
        $cacheKey = '' . $replace_extra_symbols;

        // check static cache
        if (isset($CHARS_ARRAY[$cacheKey][$language])) {
            return $CHARS_ARRAY[$cacheKey][$language];
        }

        if ($replace_extra_symbols) {
            self::prepareAsciiAndExtrasMaps();

            if (isset(self::$ASCII_MAPS_AND_EXTRAS[$language])) {
                $tmpArray = self::$ASCII_MAPS_AND_EXTRAS[$language];

                $CHARS_ARRAY[$cacheKey][$language] = [
                    'orig'    => \array_keys($tmpArray),
                    'replace' => \array_values($tmpArray),
                ];
            } else {
                $CHARS_ARRAY[$cacheKey][$language] = [
                    'orig'    => [],
                    'replace' => [],
                ];
            }
        } else {
            self::prepareAsciiMaps();

            if (isset(self::$ASCII_MAPS[$language])) {
                $tmpArray = self::$ASCII_MAPS[$language];

                $CHARS_ARRAY[$cacheKey][$language] = [
                    'orig'    => \array_keys($tmpArray),
                    'replace' => \array_values($tmpArray),
                ];
            } else {
                $CHARS_ARRAY[$cacheKey][$language] = [
                    'orig'    => [],
                    'replace' => [],
                ];
            }
        }

        return $CHARS_ARRAY[$cacheKey][$language];
    }

    /**
     * Returns an replacement array for ASCII methods with multiple languages.
     *
     * @param bool $replace_extra_symbols [optional] <p>Add some more replacements e.g. "£" with " pound ".</p>
     *
     * @return array{orig: string[], replace: string[]}
     *                     <p>An array of replacements.</p>
     */
    public static function charsArrayWithSingleLanguageValues(bool $replace_extra_symbols = false): array
    {
        // init
        static $CHARS_ARRAY = [];
        $cacheKey = '' . $replace_extra_symbols;

        if (isset($CHARS_ARRAY[$cacheKey])) {
            return $CHARS_ARRAY[$cacheKey];
        }

        if ($replace_extra_symbols) {
            self::prepareAsciiAndExtrasMaps();

            /** @noinspection AlterInForeachInspection */
            /** @psalm-suppress PossiblyNullIterator - we use the prepare* methods here, so we don't get NULL here */
            foreach (self::$ASCII_MAPS_AND_EXTRAS as &$map) {
                $CHARS_ARRAY[$cacheKey][] = $map;
            }
        } else {
            self::prepareAsciiMaps();

            /** @noinspection AlterInForeachInspection */
            /** @psalm-suppress PossiblyNullIterator - we use the prepare* methods here, so we don't get NULL here */
            foreach (self::$ASCII_MAPS as &$map) {
                $CHARS_ARRAY[$cacheKey][] = $map;
            }
        }

        $CHARS_ARRAY[$cacheKey] = \array_merge([], ...$CHARS_ARRAY[$cacheKey]);

        $CHARS_ARRAY[$cacheKey] = [
            'orig'    => \array_keys($CHARS_ARRAY[$cacheKey]),
            'replace' => \array_values($CHARS_ARRAY[$cacheKey]),
        ];

        return $CHARS_ARRAY[$cacheKey];
    }

    /**
     * Accepts a string and removes all non-UTF-8 characters from it + extras if needed.
     *
     * @param string $str                         <p>The string to be sanitized.</p>
     * @param bool   $normalize_whitespace        [optional] <p>Set to true, if you need to normalize the
     *                                            whitespace.</p>
     * @param bool   $normalize_msword            [optional] <p>Set to true, if you need to normalize MS Word chars
     *                                            e.g.: "…"
     *                                            => "..."</p>
     * @param bool   $keep_non_breaking_space     [optional] <p>Set to true, to keep non-breaking-spaces, in
     *                                            combination with
     *                                            $normalize_whitespace</p>
     * @param bool   $remove_invisible_characters [optional] <p>Set to false, if you not want to remove invisible
     *                                            characters e.g.: "\0"</p>
     *
     * @return string
     *                <p>A clean UTF-8 string.</p>
     */
    public static function clean(
        string $str,
        bool $normalize_whitespace = true,
        bool $keep_non_breaking_space = false,
        bool $normalize_msword = true,
        bool $remove_invisible_characters = true
    ): string {
        // http://stackoverflow.com/questions/1401317/remove-non-utf8-characters-from-string
        // caused connection reset problem on larger strings

        $regex = '/
          (
            (?: [\x00-\x7F]               # single-byte sequences   0xxxxxxx
            |   [\xC0-\xDF][\x80-\xBF]    # double-byte sequences   110xxxxx 10xxxxxx
            |   [\xE0-\xEF][\x80-\xBF]{2} # triple-byte sequences   1110xxxx 10xxxxxx * 2
            |   [\xF0-\xF7][\x80-\xBF]{3} # quadruple-byte sequence 11110xxx 10xxxxxx * 3
            ){1,100}                      # ...one or more times
          )
        | ( [\x80-\xBF] )                 # invalid byte in range 10000000 - 10111111
        | ( [\xC0-\xFF] )                 # invalid byte in range 11000000 - 11111111
        /x';
        $str = (string) \preg_replace($regex, '$1', $str);

        if ($normalize_whitespace === true) {
            $str = self::normalize_whitespace($str, $keep_non_breaking_space);
        }

        if ($normalize_msword === true) {
            $str = self::normalize_msword($str);
        }

        if ($remove_invisible_characters === true) {
            $str = self::remove_invisible_characters($str);
        }

        return $str;
    }

    /**
     * Checks if a string is 7 bit ASCII.
     *
     * @param string $str <p>The string to check.</p>
     *
     * @return bool
     *              <p>
     *              <strong>true</strong> if it is ASCII<br>
     *              <strong>false</strong> otherwise
     *              </p>
     */
    public static function is_ascii(string $str): bool
    {
        if ($str === '') {
            return true;
        }

        return !\preg_match(self::$REGEX_ASCII, $str);
    }

    /**
     * Returns a string with smart quotes, ellipsis characters, and dashes from
     * Windows-1252 (commonly used in Word documents) replaced by their ASCII
     * equivalents.
     *
     * @param string $str <p>The string to be normalized.</p>
     *
     * @return string
     *                <p>A string with normalized characters for commonly used chars in Word documents.</p>
     */
    public static function normalize_msword(string $str): string
    {
        if ($str === '') {
            return '';
        }

        // init
        static $MSWORD_CACHE = [];

        if (!isset($MSWORD_CACHE['orig'])) {
            self::prepareAsciiMaps();

            /**
             * @psalm-suppress PossiblyNullArrayAccess - we use the prepare* methods here, so we don't get NULL here
             *
             * @var array
             */
            $map = self::$ASCII_MAPS[self::EXTRA_MSWORD_CHARS_LANGUAGE_CODE];

            $MSWORD_CACHE = [
                'orig'    => \array_keys($map),
                'replace' => \array_values($map),
            ];
        }

        return \str_replace($MSWORD_CACHE['orig'], $MSWORD_CACHE['replace'], $str);
    }

    /**
     * Normalize the whitespace.
     *
     * @param string $str                     <p>The string to be normalized.</p>
     * @param bool   $keepNonBreakingSpace    [optional] <p>Set to true, to keep non-breaking-spaces.</p>
     * @param bool   $keepBidiUnicodeControls [optional] <p>Set to true, to keep non-printable (for the web)
     *                                        bidirectional text chars.</p>
     *
     * @return string
     *                <p>A string with normalized whitespace.</p>
     */
    public static function normalize_whitespace(
        string $str,
        bool $keepNonBreakingSpace = false,
        bool $keepBidiUnicodeControls = false
    ): string {
        if ($str === '') {
            return '';
        }

        static $WHITESPACE_CACHE = [];
        $cacheKey = (int) $keepNonBreakingSpace;

        if (!isset($WHITESPACE_CACHE[$cacheKey])) {
            self::prepareAsciiMaps();

            /** @psalm-suppress PossiblyNullArrayAccess - we use the prepare* methods here, so we don't get NULL here */
            $WHITESPACE_CACHE[$cacheKey] = self::$ASCII_MAPS[self::EXTRA_WHITESPACE_CHARS_LANGUAGE_CODE];

            if ($keepNonBreakingSpace === true) {
                unset($WHITESPACE_CACHE[$cacheKey]["\xc2\xa0"]);
            }

            $WHITESPACE_CACHE[$cacheKey] = \array_keys($WHITESPACE_CACHE[$cacheKey]);
        }

        if ($keepBidiUnicodeControls === false) {
            static $BIDI_UNICODE_CONTROLS_CACHE = null;

            if ($BIDI_UNICODE_CONTROLS_CACHE === null) {
                $BIDI_UNICODE_CONTROLS_CACHE = \array_values(self::$BIDI_UNI_CODE_CONTROLS_TABLE);
            }

            $str = \str_replace($BIDI_UNICODE_CONTROLS_CACHE, '', $str);
        }

        return \str_replace($WHITESPACE_CACHE[$cacheKey], ' ', $str);
    }

    /**
     * Remove invisible characters from a string.
     *
     * e.g.: This prevents sandwiching null characters between ascii characters, like Java\0script.
     *
     * copy&past from https://github.com/bcit-ci/CodeIgniter/blob/develop/system/core/Common.php
     *
     * @param string $str
     * @param bool   $url_encoded
     * @param string $replacement
     *
     * @return string
     */
    public static function remove_invisible_characters(
        string $str,
        bool $url_encoded = false,
        string $replacement = ''
    ): string {
        // init
        $non_displayables = [];

        // every control character except newline (dec 10),
        // carriage return (dec 13) and horizontal tab (dec 09)
        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcefBCEF]/'; // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-fA-F]/'; // url encoded 16-31
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'; // 00-08, 11, 12, 14-31, 127

        do {
            $str = (string) \preg_replace($non_displayables, $replacement, $str, -1, $count);
        } while ($count !== 0);

        return $str;
    }

    /**
     * Returns an ASCII version of the string. A set of non-ASCII characters are
     * replaced with their closest ASCII counterparts, and the rest are removed
     * by default. The language or locale of the source string can be supplied
     * for language-specific transliteration in any of the following formats:
     * en, en_GB, or en-GB. For example, passing "de" results in "äöü" mapping
     * to "aeoeue" rather than "aou" as in other languages.
     *
     * @param string $str                      <p>The input string.</p>
     * @param string $language                 [optional] <p>Language of the source string.
     *                                         (default is 'en') | ASCII::*_LANGUAGE_CODE</p>
     * @param bool   $remove_unsupported_chars [optional] <p>Whether or not to remove the
     *                                         unsupported characters.</p>
     * @param bool   $replace_extra_symbols    [optional]  <p>Add some more replacements e.g. "£" with " pound ".</p>
     * @param bool   $use_transliterate        [optional]  <p>Use ASCII::to_transliterate() for unknown chars.</p>
     *
     * @return string
     *                <p>A string that contains only ASCII characters.</p>
     */
    public static function to_ascii(
        string $str,
        string $language = self::ENGLISH_LANGUAGE_CODE,
        bool $remove_unsupported_chars = true,
        bool $replace_extra_symbols = false,
        bool $use_transliterate = false
    ): string {
        if ($str === '') {
            return '';
        }

        $language = self::get_language($language);

        $language_specific_chars = self::charsArrayWithOneLanguage($language, $replace_extra_symbols);
        if (!empty($language_specific_chars['orig'])) {
            $str = \str_replace($language_specific_chars['orig'], $language_specific_chars['replace'], $str);
        }

        $language_all_chars = self::charsArrayWithSingleLanguageValues($replace_extra_symbols);
        $str = \str_replace($language_all_chars['orig'], $language_all_chars['replace'], $str);

        /** @psalm-suppress PossiblyNullOperand - we use the prepare* methods here, so we don't get NULL here */
        if (!isset(self::$ASCII_MAPS[$language])) {
            $use_transliterate = true;
        }

        if ($use_transliterate === true) {
            $str = self::to_transliterate($str, null, false);
        }

        if ($remove_unsupported_chars === true) {
            $str = (string) \str_replace(["\n\r", "\n", "\r", "\t"], ' ', $str);
            $str = (string) \preg_replace(self::$REGEX_ASCII, '', $str);
        }

        return $str;
    }

    /**
     * Convert given string to safe filename (and keep string case).
     *
     * @param string $str
     * @param bool   $use_transliterate <p>ASCII::to_transliterate() is used by default - unsafe characters are
     *                                  simply replaced with hyphen otherwise.</p>
     * @param string $fallback_char
     *
     * @return string
     *                <p>A string that contains only safe characters for a filename.</p>
     */
    public static function to_filename(
        string $str,
        bool $use_transliterate = true,
        string $fallback_char = '-'
    ): string {
        if ($use_transliterate === true) {
            $str = self::to_transliterate($str, $fallback_char);
        }

        $fallback_char_escaped = \preg_quote($fallback_char, '/');

        $str = (string) \preg_replace(
            [
                '/[^' . $fallback_char_escaped . '.\\-a-zA-Z0-9\\s]/', // 1) remove un-needed chars
                '/[\\s]+/u',                                           // 2) convert spaces to $fallback_char
                '/[' . $fallback_char_escaped . ']+/u',                // 3) remove double $fallback_char's
            ],
            [
                '',
                $fallback_char,
                $fallback_char,
            ],
            $str
        );

        return \trim($str, $fallback_char);
    }

    /**
     * Converts the string into an URL slug. This includes replacing non-ASCII
     * characters with their closest ASCII equivalents, removing remaining
     * non-ASCII and non-alphanumeric characters, and replacing whitespace with
     * $separator. The separator defaults to a single dash, and the string
     * is also converted to lowercase. The language of the source string can
     * also be supplied for language-specific transliteration.
     *
     * @param string                $str
     * @param string                $separator             [optional] <p>The string used to replace whitespace.</p>
     * @param string                $language              [optional] <p>Language of the source string.
     *                                                     (default is 'en') | ASCII::*_LANGUAGE_CODE</p>
     * @param array<string, string> $replacements          [optional] <p>A map of replaceable strings.</p>
     * @param bool                  $replace_extra_symbols [optional]  <p>Add some more replacements e.g. "£" with "
     *                                                     pound ".</p>
     * @param bool                  $use_str_to_lower      [optional] <p>Use "string to lower" for the input.</p>
     * @param bool                  $use_transliterate     [optional]  <p>Use ASCII::to_transliterate() for unknown
     *                                                     chars.</p>
     *
     * @return string
     *                <p>A string that has been converted to an URL slug.</p>
     */
    public static function to_slugify(
        string $str,
        string $separator = '-',
        string $language = self::ENGLISH_LANGUAGE_CODE,
        array $replacements = [],
        bool $replace_extra_symbols = false,
        bool $use_str_to_lower = true,
        bool $use_transliterate = false
    ): string {
        if ($str === '') {
            return '';
        }

        foreach ($replacements as $from => $to) {
            $str = \str_replace($from, $to, $str);
        }

        $str = self::to_ascii(
            $str,
            $language,
            false,
            $replace_extra_symbols,
            $use_transliterate
        );

        $str = \str_replace('@', $separator, $str);

        $str = (string) \preg_replace(
            '/[^a-zA-Z\\d\\s\\-_' . \preg_quote($separator, '/') . ']/',
            '',
            $str
        );

        if ($use_str_to_lower === true) {
            $str = \strtolower($str);
        }

        $str = (string) \preg_replace('/^[\'\\s]+|[\'\\s]+$/', '', $str);
        $str = (string) \preg_replace('/\\B([A-Z])/', '-\1', $str);
        $str = (string) \preg_replace('/[\\-_\\s]+/', $separator, $str);

        $l = \strlen($separator);
        if (\strpos($str, $separator) === 0) {
            $str = (string) \substr($str, $l);
        }

        if (\substr($str, -$l) === $separator) {
            $str = (string) \substr($str, 0, \strlen($str) - $l);
        }

        return $str;
    }

    /**
     * Returns an ASCII version of the string. A set of non-ASCII characters are
     * replaced with their closest ASCII counterparts, and the rest are removed
     * unless instructed otherwise.
     *
     * @param string      $str     <p>The input string.</p>
     * @param string|null $unknown [optional] <p>Character use if character unknown. (default is '?')
     *                             But you can also use NULL to keep the unknown chars.</p>
     * @param bool        $strict  [optional] <p>Use "transliterator_transliterate()" from PHP-Intl
     *
     * @return string
     *                <p>A String that contains only ASCII characters.</p>
     */
    public static function to_transliterate(
        string $str,
        $unknown = '?',
        bool $strict = false
    ): string {
        static $UTF8_TO_TRANSLIT = null;
        static $TRANSLITERATOR = null;
        static $SUPPORT = [];

        if ($str === '') {
            return '';
        }

        if (!isset($SUPPORT['intl'])) {
            $SUPPORT['intl'] = \extension_loaded('intl');
        }

        // check if we only have ASCII, first (better performance)
        $str_tmp = $str;
        if (self::is_ascii($str) === true) {
            return $str;
        }

        $str = self::clean($str);

        // check again, if we only have ASCII, now ...
        if (
            $str_tmp !== $str
            &&
            self::is_ascii($str) === true
        ) {
            return $str;
        }

        if (
            $strict === true
            &&
            $SUPPORT['intl'] === true
        ) {
            if (!isset($TRANSLITERATOR)) {
                // INFO: see "*-Latin" rules via "transliterator_list_ids()"
                /**
                 * @noinspection PhpComposerExtensionStubsInspection
                 *
                 * @var \Transliterator
                 */
                $TRANSLITERATOR = \transliterator_create('NFKC; [:Nonspacing Mark:] Remove; NFKC; Any-Latin; Latin-ASCII;');
            }

            // INFO: https://unicode.org/cldr/utility/character.jsp
            /** @noinspection PhpComposerExtensionStubsInspection */
            $str_tmp = \transliterator_transliterate($TRANSLITERATOR, $str);

            if ($str_tmp !== false) {

                // check again, if we only have ASCII, now ...
                if (
                    $str_tmp !== $str
                    &&
                    self::is_ascii($str_tmp) === true
                ) {
                    return $str_tmp;
                }

                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                $str = $str_tmp;
            }
        }

        if (self::$ORD === null) {
            self::$ORD = self::getData('ascii_ord');
        }

        \preg_match_all('/.|[^\x00]$/us', $str, $array_tmp);
        $chars = $array_tmp[0];
        $ord = null;
        $str_tmp = '';
        foreach ($chars as &$c) {
            $ordC0 = self::$ORD[$c[0]];

            if ($ordC0 >= 0 && $ordC0 <= 127) {
                $str_tmp .= $c;

                continue;
            }

            $ordC1 = self::$ORD[$c[1]];

            // ASCII - next please
            if ($ordC0 >= 192 && $ordC0 <= 223) {
                $ord = ($ordC0 - 192) * 64 + ($ordC1 - 128);
            }

            if ($ordC0 >= 224) {
                $ordC2 = self::$ORD[$c[2]];

                if ($ordC0 <= 239) {
                    $ord = ($ordC0 - 224) * 4096 + ($ordC1 - 128) * 64 + ($ordC2 - 128);
                }

                if ($ordC0 >= 240) {
                    $ordC3 = self::$ORD[$c[3]];

                    if ($ordC0 <= 247) {
                        $ord = ($ordC0 - 240) * 262144 + ($ordC1 - 128) * 4096 + ($ordC2 - 128) * 64 + ($ordC3 - 128);
                    }

                    // We only process valid UTF-8 chars (<= 4 byte), so we don't need this code here ...
                    /*
                    if ($ordC0 >= 248) {
                        $ordC4 = self::$ORD[$c[4]];

                        if ($ordC0 <= 251) {
                            $ord = ($ordC0 - 248) * 16777216 + ($ordC1 - 128) * 262144 + ($ordC2 - 128) * 4096 + ($ordC3 - 128) * 64 + ($ordC4 - 128);
                        }

                        if ($ordC0 >= 252) {
                            $ordC5 = self::$ORD[$c[5]];

                            if ($ordC0 <= 253) {
                                $ord = ($ordC0 - 252) * 1073741824 + ($ordC1 - 128) * 16777216 + ($ordC2 - 128) * 262144 + ($ordC3 - 128) * 4096 + ($ordC4 - 128) * 64 + ($ordC5 - 128);
                            }
                        }
                    }
                     */
                }
            }

            if (
                $ordC0 === 254
                ||
                $ordC0 === 255
                ||
                $ord === null
            ) {
                $str_tmp .= $unknown ?? $c;

                continue;
            }

            $bank = $ord >> 8;
            if (!isset($UTF8_TO_TRANSLIT[$bank])) {
                $UTF8_TO_TRANSLIT[$bank] = self::getDataIfExists(\sprintf('x%02x', $bank));
            }

            $new_char = $ord & 255;

            if (isset($UTF8_TO_TRANSLIT[$bank][$new_char])) {

                // keep for debugging
                /*
                echo "file: " . sprintf('x%02x', $bank) . "\n";
                echo "char: " . $c . "\n";
                echo "ord: " . $ord . "\n";
                echo "new_char: " . $new_char . "\n";
                echo "new_char: " . mb_chr($new_char) . "\n";
                echo "ascii: " . $UTF8_TO_TRANSLIT[$bank][$new_char] . "\n";
                echo "bank:" . $bank . "\n\n";
                 */

                $new_char = $UTF8_TO_TRANSLIT[$bank][$new_char];

                if ($unknown === null && $new_char === '') {
                    $c = $unknown ?? $c;
                } elseif (
                    $new_char === '[?]'
                    ||
                    $new_char === '[?] '
                ) {
                    $c = $unknown ?? $c;
                } else {
                    $c = $new_char;
                }
            } else {

                // keep for debugging missing chars
                /*
                echo "file: " . sprintf('x%02x', $bank) . "\n";
                echo "char: " . $c . "\n";
                echo "ord: " . $ord . "\n";
                echo "new_char: " . $new_char . "\n";
                echo "new_char: " . mb_chr($new_char) . "\n";
                echo "bank:" . $bank . "\n\n";
                 */

                $c = $unknown ?? $c;
            }

            $str_tmp .= $c;
        }

        return $str_tmp;
    }

    /**
     * Get the language from a string.
     *
     * e.g.: de_at -> de_at
     *       de_DE -> de
     *       DE_DE -> de
     *       de-de -> de
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     *
     * @param string $language
     *
     * @return string
     */
    private static function get_language(string $language)
    {
        if ($language === '') {
            return '';
        }

        if (
            \strpos($language, '_') === false
            &&
            \strpos($language, '-') === false
        ) {
            return \strtolower($language);
        }

        $regex = '/(?<first>[a-z]+)[\-_]\g{first}/i';

        return \str_replace(
            '-',
            '_',
            \strtolower(
                (string) \preg_replace($regex, '$1', $language)
            )
        );
    }

    /**
     * Get data from "/data/*.php".
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     *
     * @param string $file
     *
     * @return array
     */
    private static function getData(string $file)
    {
        /** @noinspection PhpIncludeInspection */
        /** @noinspection UsingInclusionReturnValueInspection */
        /** @psalm-suppress UnresolvableInclude */
        return include __DIR__ . '/data/' . $file . '.php';
    }

    /**
     * Get data from "/data/*.php".
     *
     * @param string $file
     *
     * @return array
     */
    private static function getDataIfExists(string $file)
    {
        $file = __DIR__ . '/data/' . $file . '.php';
        if (\file_exists($file)) {
            /** @noinspection PhpIncludeInspection */
            /** @noinspection UsingInclusionReturnValueInspection */
            return include $file;
        }

        return [];
    }

    /**
     * @psalm-suppress MissingReturnType
     */
    private static function prepareAsciiAndExtrasMaps()
    {
        if (self::$ASCII_MAPS_AND_EXTRAS === null) {
            self::prepareAsciiMaps();

            /** @psalm-suppress PossiblyNullArgument - we use the prepare* methods here, so we don't get NULL here */
            self::$ASCII_MAPS_AND_EXTRAS = \array_merge_recursive(
                self::$ASCII_MAPS,
                self::getData('ascii_extras_by_languages')
            );
        }
    }

    /**
     * @psalm-suppress MissingReturnType
     */
    private static function prepareAsciiMaps()
    {
        if (self::$ASCII_MAPS === null) {
            self::$ASCII_MAPS = self::getData('ascii_by_languages');
        }
    }
}
