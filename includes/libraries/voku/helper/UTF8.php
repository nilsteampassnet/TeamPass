<?php

declare(strict_types=1);

namespace voku\helper;

final class UTF8
{
    /**
     * (CRLF|([ZWNJ-ZWJ]|T+|L*(LV?V+|LV|LVT)T*|L+|[^Control])[Extend]*|[Control])
     * This regular expression is a work around for http://bugs.exim.org/1279
     */
    const GRAPHEME_CLUSTER_RX = "(?:\r\n|(?:[ -~\x{200C}\x{200D}]|[ᆨ-ᇹ]+|[ᄀ-ᅟ]*(?:[가개갸걔거게겨계고과괘괴교구궈궤귀규그긔기까깨꺄꺠꺼께껴꼐꼬꽈꽤꾀꾜꾸꿔꿰뀌뀨끄끠끼나내냐냬너네녀녜노놔놰뇌뇨누눠눼뉘뉴느늬니다대댜댸더데뎌뎨도돠돼되됴두둬뒈뒤듀드듸디따때땨떄떠떼뗘뗴또똬뙈뙤뚀뚜뚸뛔뛰뜌뜨띄띠라래랴럐러레려례로롸뢔뢰료루뤄뤠뤼류르릐리마매먀먜머메며몌모뫄뫠뫼묘무뭐뭬뮈뮤므믜미바배뱌뱨버베벼볘보봐봬뵈뵤부붜붸뷔뷰브븨비빠빼뺘뺴뻐뻬뼈뼤뽀뽜뽸뾔뾰뿌뿨쀄쀠쀼쁘쁴삐사새샤섀서세셔셰소솨쇄쇠쇼수숴쉐쉬슈스싀시싸쌔쌰썌써쎄쎠쎼쏘쏴쐐쐬쑈쑤쒀쒜쒸쓔쓰씌씨아애야얘어에여예오와왜외요우워웨위유으의이자재쟈쟤저제져졔조좌좨죄죠주줘줴쥐쥬즈즤지짜째쨔쨰쩌쩨쪄쪠쪼쫘쫴쬐쬬쭈쭤쮀쮜쮸쯔쯰찌차채챠챼처체쳐쳬초촤쵀최쵸추춰췌취츄츠츼치카캐캬컈커케켜켸코콰쾌쾨쿄쿠쿼퀘퀴큐크킈키타태탸턔터테텨톄토톼퇘퇴툐투퉈퉤튀튜트틔티파패퍄퍠퍼페펴폐포퐈퐤푀표푸풔풰퓌퓨프픠피하해햐햬허헤혀혜호화홰회효후훠훼휘휴흐희히]?[ᅠ-ᆢ]+|[가-힣])[ᆨ-ᇹ]*|[ᄀ-ᅟ]+|[^\p{Cc}\p{Cf}\p{Zl}\p{Zp}])[\p{Mn}\p{Me}\x{09BE}\x{09D7}\x{0B3E}\x{0B57}\x{0BBE}\x{0BD7}\x{0CC2}\x{0CD5}\x{0CD6}\x{0D3E}\x{0D57}\x{0DCF}\x{0DDF}\x{200C}\x{200D}\x{1D165}\x{1D16E}-\x{1D172}]*|[\p{Cc}\p{Cf}\p{Zl}\p{Zp}])";

    /**
     * Bom => Byte-Length
     *
     * INFO: https://en.wikipedia.org/wiki/Byte_order_mark
     *
     * @var array
     */
    private static $BOM = [
        "\xef\xbb\xbf"     => 3, // UTF-8 BOM
        'ï»¿'              => 6, // UTF-8 BOM as "WINDOWS-1252" (one char has [maybe] more then one byte ...)
        "\x00\x00\xfe\xff" => 4, // UTF-32 (BE) BOM
        '  þÿ'             => 6, // UTF-32 (BE) BOM as "WINDOWS-1252"
        "\xff\xfe\x00\x00" => 4, // UTF-32 (LE) BOM
        'ÿþ  '             => 6, // UTF-32 (LE) BOM as "WINDOWS-1252"
        "\xfe\xff"         => 2, // UTF-16 (BE) BOM
        'þÿ'               => 4, // UTF-16 (BE) BOM as "WINDOWS-1252"
        "\xff\xfe"         => 2, // UTF-16 (LE) BOM
        'ÿþ'               => 4, // UTF-16 (LE) BOM as "WINDOWS-1252"
    ];

    /**
     * Numeric code point => UTF-8 Character
     *
     * url: http://www.w3schools.com/charsets/ref_utf_punctuation.asp
     *
     * @var array
     */
    private static $WHITESPACE = [
        // NUL Byte
        0 => "\x0",
        // Tab
        9 => "\x9",
        // New Line
        10 => "\xa",
        // Vertical Tab
        11 => "\xb",
        // Carriage Return
        13 => "\xd",
        // Ordinary Space
        32 => "\x20",
        // NO-BREAK SPACE
        160 => "\xc2\xa0",
        // OGHAM SPACE MARK
        5760 => "\xe1\x9a\x80",
        // MONGOLIAN VOWEL SEPARATOR
        6158 => "\xe1\xa0\x8e",
        // EN QUAD
        8192 => "\xe2\x80\x80",
        // EM QUAD
        8193 => "\xe2\x80\x81",
        // EN SPACE
        8194 => "\xe2\x80\x82",
        // EM SPACE
        8195 => "\xe2\x80\x83",
        // THREE-PER-EM SPACE
        8196 => "\xe2\x80\x84",
        // FOUR-PER-EM SPACE
        8197 => "\xe2\x80\x85",
        // SIX-PER-EM SPACE
        8198 => "\xe2\x80\x86",
        // FIGURE SPACE
        8199 => "\xe2\x80\x87",
        // PUNCTUATION SPACE
        8200 => "\xe2\x80\x88",
        // THIN SPACE
        8201 => "\xe2\x80\x89",
        //HAIR SPACE
        8202 => "\xe2\x80\x8a",
        // LINE SEPARATOR
        8232 => "\xe2\x80\xa8",
        // PARAGRAPH SEPARATOR
        8233 => "\xe2\x80\xa9",
        // NARROW NO-BREAK SPACE
        8239 => "\xe2\x80\xaf",
        // MEDIUM MATHEMATICAL SPACE
        8287 => "\xe2\x81\x9f",
        // HALFWIDTH HANGUL FILLER
        65440 => "\xef\xbe\xa0",
        // IDEOGRAPHIC SPACE
        12288 => "\xe3\x80\x80",
    ];

    /**
     * @var array
     */
    private static $WHITESPACE_TABLE = [
        'SPACE'                     => "\x20",
        'NO-BREAK SPACE'            => "\xc2\xa0",
        'OGHAM SPACE MARK'          => "\xe1\x9a\x80",
        'EN QUAD'                   => "\xe2\x80\x80",
        'EM QUAD'                   => "\xe2\x80\x81",
        'EN SPACE'                  => "\xe2\x80\x82",
        'EM SPACE'                  => "\xe2\x80\x83",
        'THREE-PER-EM SPACE'        => "\xe2\x80\x84",
        'FOUR-PER-EM SPACE'         => "\xe2\x80\x85",
        'SIX-PER-EM SPACE'          => "\xe2\x80\x86",
        'FIGURE SPACE'              => "\xe2\x80\x87",
        'PUNCTUATION SPACE'         => "\xe2\x80\x88",
        'THIN SPACE'                => "\xe2\x80\x89",
        'HAIR SPACE'                => "\xe2\x80\x8a",
        'LINE SEPARATOR'            => "\xe2\x80\xa8",
        'PARAGRAPH SEPARATOR'       => "\xe2\x80\xa9",
        'ZERO WIDTH SPACE'          => "\xe2\x80\x8b",
        'NARROW NO-BREAK SPACE'     => "\xe2\x80\xaf",
        'MEDIUM MATHEMATICAL SPACE' => "\xe2\x81\x9f",
        'IDEOGRAPHIC SPACE'         => "\xe3\x80\x80",
        'HALFWIDTH HANGUL FILLER'   => "\xef\xbe\xa0",
    ];

    /**
     * @var array{upper: string[], lower: string[]}
     */
    private static $COMMON_CASE_FOLD = [
        'upper' => [
            'µ',
            'ſ',
            "\xCD\x85",
            'ς',
            'ẞ',
            "\xCF\x90",
            "\xCF\x91",
            "\xCF\x95",
            "\xCF\x96",
            "\xCF\xB0",
            "\xCF\xB1",
            "\xCF\xB5",
            "\xE1\xBA\x9B",
            "\xE1\xBE\xBE",
        ],
        'lower' => [
            'μ',
            's',
            'ι',
            'σ',
            'ß',
            'β',
            'θ',
            'φ',
            'π',
            'κ',
            'ρ',
            'ε',
            "\xE1\xB9\xA1",
            'ι',
        ],
    ];

    /**
     * @var array
     */
    private static $SUPPORT = [];

    /**
     * @var array|null
     */
    private static $BROKEN_UTF8_FIX;

    /**
     * @var array|null
     */
    private static $WIN1252_TO_UTF8;

    /**
     * @var array|null
     */
    private static $INTL_TRANSLITERATOR_LIST;

    /**
     * @var array|null
     */
    private static $ENCODINGS;

    /**
     * @var array|null
     */
    private static $ORD;

    /**
     * @var array|null
     */
    private static $EMOJI;

    /**
     * @var array|null
     */
    private static $EMOJI_VALUES_CACHE;

    /**
     * @var array|null
     */
    private static $EMOJI_KEYS_CACHE;

    /**
     * @var array|null
     */
    private static $EMOJI_KEYS_REVERSIBLE_CACHE;

    /**
     * @var array|null
     */
    private static $CHR;

    /**
     * __construct()
     */
    public function __construct()
    {
    }

    /**
     * Return the character at the specified position: $str[1] like functionality.
     *
     * @param string $str      <p>A UTF-8 string.</p>
     * @param int    $pos      <p>The position of character to return.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string single multi-byte character
     */
    public static function access(string $str, int $pos, string $encoding = 'UTF-8'): string
    {
        if ($str === '' || $pos < 0) {
            return '';
        }

        if ($encoding === 'UTF-8') {
            return (string) \mb_substr($str, $pos, 1);
        }

        return (string) self::substr($str, $pos, 1, $encoding);
    }

    /**
     * Prepends UTF-8 BOM character to the string and returns the whole string.
     *
     * INFO: If BOM already existed there, the Input string is returned.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return string the output string that contains BOM
     */
    public static function add_bom_to_string(string $str): string
    {
        if (self::string_has_bom($str) === false) {
            $str = self::bom() . $str;
        }

        return $str;
    }

    /**
     * Changes all keys in an array.
     *
     * @param array  $array    <p>The array to work on</p>
     * @param int    $case     [optional] <p> Either <strong>CASE_UPPER</strong><br>
     *                         or <strong>CASE_LOWER</strong> (default)</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string[]
     *                  <p>An array with its keys lower- or uppercased.</p>
     */
    public static function array_change_key_case(
        array $array,
        int $case = \CASE_LOWER,
        string $encoding = 'UTF-8'
    ): array {
        if (
            $case !== \CASE_LOWER
            &&
            $case !== \CASE_UPPER
        ) {
            $case = \CASE_LOWER;
        }

        $return = [];
        foreach ($array as $key => &$value) {
            $key = $case === \CASE_LOWER
                ? self::strtolower((string) $key, $encoding)
                : self::strtoupper((string) $key, $encoding);

            $return[$key] = $value;
        }

        return $return;
    }

    /**
     * Returns the substring between $start and $end, if found, or an empty
     * string. An optional offset may be supplied from which to begin the
     * search for the start string.
     *
     * @param string $str
     * @param string $start    <p>Delimiter marking the start of the substring.</p>
     * @param string $end      <p>Delimiter marking the end of the substring.</p>
     * @param int    $offset   [optional] <p>Index from which to begin the search. Default: 0</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function between(
        string $str,
        string $start,
        string $end,
        int $offset = 0,
        string $encoding = 'UTF-8'
    ): string {
        if ($encoding === 'UTF-8') {
            $start_position = \mb_strpos($str, $start, $offset);
            if ($start_position === false) {
                return '';
            }

            $substr_index = $start_position + (int) \mb_strlen($start);
            $end_position = \mb_strpos($str, $end, $substr_index);
            if (
                $end_position === false
                ||
                $end_position === $substr_index
            ) {
                return '';
            }

            return (string) \mb_substr($str, $substr_index, $end_position - $substr_index);
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        $start_position = self::strpos($str, $start, $offset, $encoding);
        if ($start_position === false) {
            return '';
        }

        $substr_index = $start_position + (int) self::strlen($start, $encoding);
        $end_position = self::strpos($str, $end, $substr_index, $encoding);
        if (
            $end_position === false
            ||
            $end_position === $substr_index
        ) {
            return '';
        }

        return (string) self::substr(
            $str,
            $substr_index,
            $end_position - $substr_index,
            $encoding
        );
    }

    /**
     * Convert binary into a string.
     *
     * @param mixed $bin 1|0
     *
     * @return string
     */
    public static function binary_to_str($bin): string
    {
        if (!isset($bin[0])) {
            return '';
        }

        $convert = \base_convert($bin, 2, 16);
        if ($convert === '0') {
            return '';
        }

        return \pack('H*', $convert);
    }

    /**
     * Returns the UTF-8 Byte Order Mark Character.
     *
     * INFO: take a look at UTF8::$bom for e.g. UTF-16 and UTF-32 BOM values
     *
     * @return string UTF-8 Byte Order Mark
     */
    public static function bom(): string
    {
        return "\xef\xbb\xbf";
    }

    /**
     * @alias of UTF8::chr_map()
     *
     * @param callable $callback
     * @param string   $str
     *
     * @return string[]
     *
     * @see UTF8::chr_map()
     */
    public static function callback($callback, string $str): array
    {
        return self::chr_map($callback, $str);
    }

    /**
     * Returns the character at $index, with indexes starting at 0.
     *
     * @param string $str      <p>The input string.</p>
     * @param int    $index    <p>Position of the character.</p>
     * @param string $encoding [optional] <p>Default is UTF-8</p>
     *
     * @return string the character at $index
     */
    public static function char_at(string $str, int $index, string $encoding = 'UTF-8'): string
    {
        if ($encoding === 'UTF-8') {
            return (string) \mb_substr($str, $index, 1);
        }

        return (string) self::substr($str, $index, 1, $encoding);
    }

    /**
     * Returns an array consisting of the characters in the string.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return string[] an array of chars
     */
    public static function chars(string $str): array
    {
        return self::str_split($str);
    }

    /**
     * This method will auto-detect your server environment for UTF-8 support.
     *
     * @return true|null
     *
     * @internal <p>You don't need to run it manually, it will be triggered if it's needed.</p>
     */
    public static function checkForSupport()
    {
        if (!isset(self::$SUPPORT['already_checked_via_portable_utf8'])) {
            self::$SUPPORT['already_checked_via_portable_utf8'] = true;

            // http://php.net/manual/en/book.mbstring.php
            self::$SUPPORT['mbstring'] = self::mbstring_loaded();
            self::$SUPPORT['mbstring_func_overload'] = self::mbstring_overloaded();
            if (self::$SUPPORT['mbstring'] === true) {
                \mb_internal_encoding('UTF-8');
                /** @noinspection UnusedFunctionResultInspection */
                /** @noinspection PhpComposerExtensionStubsInspection */
                \mb_regex_encoding('UTF-8');
                self::$SUPPORT['mbstring_internal_encoding'] = 'UTF-8';
            }

            // http://php.net/manual/en/book.iconv.php
            self::$SUPPORT['iconv'] = self::iconv_loaded();

            // http://php.net/manual/en/book.intl.php
            self::$SUPPORT['intl'] = self::intl_loaded();

            // http://php.net/manual/en/class.intlchar.php
            self::$SUPPORT['intlChar'] = self::intlChar_loaded();

            // http://php.net/manual/en/book.ctype.php
            self::$SUPPORT['ctype'] = self::ctype_loaded();

            // http://php.net/manual/en/class.finfo.php
            self::$SUPPORT['finfo'] = self::finfo_loaded();

            // http://php.net/manual/en/book.json.php
            self::$SUPPORT['json'] = self::json_loaded();

            // http://php.net/manual/en/book.pcre.php
            self::$SUPPORT['pcre_utf8'] = self::pcre_utf8_support();

            self::$SUPPORT['symfony_polyfill_used'] = self::symfony_polyfill_used();
            if (self::$SUPPORT['symfony_polyfill_used'] === true) {
                \mb_internal_encoding('UTF-8');
                self::$SUPPORT['mbstring_internal_encoding'] = 'UTF-8';
            }

            return true;
        }

        return null;
    }

    /**
     * Generates a UTF-8 encoded character from the given code point.
     *
     * INFO: opposite to UTF8::ord()
     *
     * @param int|string $code_point <p>The code point for which to generate a character.</p>
     * @param string     $encoding   [optional] <p>Default is UTF-8</p>
     *
     * @return string|null multi-byte character, returns null on failure or empty input
     */
    public static function chr($code_point, string $encoding = 'UTF-8')
    {
        // init
        static $CHAR_CACHE = [];

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if (
            $encoding !== 'UTF-8'
            &&
            $encoding !== 'ISO-8859-1'
            &&
            $encoding !== 'WINDOWS-1252'
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::chr() without mbstring cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        $cache_key = $code_point . $encoding;
        if (isset($CHAR_CACHE[$cache_key]) === true) {
            return $CHAR_CACHE[$cache_key];
        }

        if ($code_point <= 127) { // use "simple"-char only until "\x80"

            if (self::$CHR === null) {
                self::$CHR = self::getData('chr');
            }

            /**
             * @psalm-suppress PossiblyNullArrayAccess
             */
            $chr = self::$CHR[$code_point];

            if ($encoding !== 'UTF-8') {
                $chr = self::encode($encoding, $chr);
            }

            return $CHAR_CACHE[$cache_key] = $chr;
        }

        //
        // fallback via "IntlChar"
        //

        if (self::$SUPPORT['intlChar'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            $chr = \IntlChar::chr($code_point);

            if ($encoding !== 'UTF-8') {
                $chr = self::encode($encoding, $chr);
            }

            return $CHAR_CACHE[$cache_key] = $chr;
        }

        //
        // fallback via vanilla php
        //

        if (self::$CHR === null) {
            self::$CHR = self::getData('chr');
        }

        $code_point = (int) $code_point;
        if ($code_point <= 0x7F) {
            /**
             * @psalm-suppress PossiblyNullArrayAccess
             */
            $chr = self::$CHR[$code_point];
        } elseif ($code_point <= 0x7FF) {
            /**
             * @psalm-suppress PossiblyNullArrayAccess
             */
            $chr = self::$CHR[($code_point >> 6) + 0xC0] .
                   self::$CHR[($code_point & 0x3F) + 0x80];
        } elseif ($code_point <= 0xFFFF) {
            /**
             * @psalm-suppress PossiblyNullArrayAccess
             */
            $chr = self::$CHR[($code_point >> 12) + 0xE0] .
                   self::$CHR[(($code_point >> 6) & 0x3F) + 0x80] .
                   self::$CHR[($code_point & 0x3F) + 0x80];
        } else {
            /**
             * @psalm-suppress PossiblyNullArrayAccess
             */
            $chr = self::$CHR[($code_point >> 18) + 0xF0] .
                   self::$CHR[(($code_point >> 12) & 0x3F) + 0x80] .
                   self::$CHR[(($code_point >> 6) & 0x3F) + 0x80] .
                   self::$CHR[($code_point & 0x3F) + 0x80];
        }

        if ($encoding !== 'UTF-8') {
            $chr = self::encode($encoding, $chr);
        }

        return $CHAR_CACHE[$cache_key] = $chr;
    }

    /**
     * Applies callback to all characters of a string.
     *
     * @param callable $callback <p>The callback function.</p>
     * @param string   $str      <p>UTF-8 string to run callback on.</p>
     *
     * @return string[]
     *                  <p>The outcome of the callback, as array.</p>
     */
    public static function chr_map($callback, string $str): array
    {
        return \array_map(
            $callback,
            self::str_split($str)
        );
    }

    /**
     * Generates an array of byte length of each character of a Unicode string.
     *
     * 1 byte => U+0000  - U+007F
     * 2 byte => U+0080  - U+07FF
     * 3 byte => U+0800  - U+FFFF
     * 4 byte => U+10000 - U+10FFFF
     *
     * @param string $str <p>The original unicode string.</p>
     *
     * @return int[] an array of byte lengths of each character
     */
    public static function chr_size_list(string $str): array
    {
        if ($str === '') {
            return [];
        }

        if (self::$SUPPORT['mbstring_func_overload'] === true) {
            return \array_map(
                static function (string $data): int {
                    // "mb_" is available if overload is used, so use it ...
                    return \mb_strlen($data, 'CP850'); // 8-BIT
                },
                self::str_split($str)
            );
        }

        return \array_map('\strlen', self::str_split($str));
    }

    /**
     * Get a decimal code representation of a specific character.
     *
     * @param string $char <p>The input character.</p>
     *
     * @return int
     */
    public static function chr_to_decimal(string $char): int
    {
        if (self::$SUPPORT['iconv'] === true) {
            $chr_tmp = \iconv('UTF-8', 'UCS-4LE', $char);
            if ($chr_tmp !== false) {
                /** @noinspection OffsetOperationsInspection */
                return \unpack('V', $chr_tmp)[1];
            }
        }

        $code = self::ord($char[0]);
        $bytes = 1;

        if (!($code & 0x80)) {
            // 0xxxxxxx
            return $code;
        }

        if (($code & 0xe0) === 0xc0) {
            // 110xxxxx
            $bytes = 2;
            $code &= ~0xc0;
        } elseif (($code & 0xf0) === 0xe0) {
            // 1110xxxx
            $bytes = 3;
            $code &= ~0xe0;
        } elseif (($code & 0xf8) === 0xf0) {
            // 11110xxx
            $bytes = 4;
            $code &= ~0xf0;
        }

        for ($i = 2; $i <= $bytes; ++$i) {
            // 10xxxxxx
            $code = ($code << 6) + (self::ord($char[$i - 1]) & ~0x80);
        }

        return $code;
    }

    /**
     * Get hexadecimal code point (U+xxxx) of a UTF-8 encoded character.
     *
     * @param int|string $char   <p>The input character</p>
     * @param string     $prefix [optional]
     *
     * @return string The code point encoded as U+xxxx
     */
    public static function chr_to_hex($char, string $prefix = 'U+'): string
    {
        if ($char === '') {
            return '';
        }

        if ($char === '&#0;') {
            $char = '';
        }

        return self::int_to_hex(self::ord((string) $char), $prefix);
    }

    /**
     * alias for "UTF8::chr_to_decimal()"
     *
     * @param string $chr
     *
     * @return int
     *
     * @see UTF8::chr_to_decimal()
     * @deprecated <p>please use "UTF8::chr_to_decimal()"</p>
     */
    public static function chr_to_int(string $chr): int
    {
        return self::chr_to_decimal($chr);
    }

    /**
     * Splits a string into smaller chunks and multiple lines, using the specified line ending character.
     *
     * @param string $body         <p>The original string to be split.</p>
     * @param int    $chunk_length [optional] <p>The maximum character length of a chunk.</p>
     * @param string $end          [optional] <p>The character(s) to be inserted at the end of each chunk.</p>
     *
     * @return string the chunked string
     */
    public static function chunk_split(string $body, int $chunk_length = 76, string $end = "\r\n"): string
    {
        return \implode($end, self::str_split($body, $chunk_length));
    }

    /**
     * Accepts a string and removes all non-UTF-8 characters from it + extras if needed.
     *
     * @param string $str                                     <p>The string to be sanitized.</p>
     * @param bool   $remove_bom                              [optional] <p>Set to true, if you need to remove UTF-BOM.</p>
     * @param bool   $normalize_whitespace                    [optional] <p>Set to true, if you need to normalize the
     *                                                        whitespace.</p>
     * @param bool   $normalize_msword                        [optional] <p>Set to true, if you need to normalize MS Word chars
     *                                                        e.g.: "…"
     *                                                        => "..."</p>
     * @param bool   $keep_non_breaking_space                 [optional] <p>Set to true, to keep non-breaking-spaces, in
     *                                                        combination with
     *                                                        $normalize_whitespace</p>
     * @param bool   $replace_diamond_question_mark           [optional] <p>Set to true, if you need to remove diamond question
     *                                                        mark e.g.: "�"</p>
     * @param bool   $remove_invisible_characters             [optional] <p>Set to false, if you not want to remove invisible
     *                                                        characters e.g.: "\0"</p>
     * @param bool   $remove_invisible_characters_url_encoded [optional] <p>Set to true, if you not want to remove invisible
     *                                                        url encoded characters e.g.: "%0B"<br>
     *                                                        WARNING: maybe contains false-positives e.g. aa%0Baa -> aaaa.
     *                                                        </p>
     *
     * @return string clean UTF-8 encoded string
     */
    public static function clean(
        string $str,
        bool $remove_bom = false,
        bool $normalize_whitespace = false,
        bool $normalize_msword = false,
        bool $keep_non_breaking_space = false,
        bool $replace_diamond_question_mark = false,
        bool $remove_invisible_characters = true,
        bool $remove_invisible_characters_url_encoded = false
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
        /** @noinspection NotOptimalRegularExpressionsInspection */
        $str = (string) \preg_replace($regex, '$1', $str);

        if ($replace_diamond_question_mark === true) {
            $str = self::replace_diamond_question_mark($str, '');
        }

        if ($remove_invisible_characters === true) {
            $str = self::remove_invisible_characters($str, $remove_invisible_characters_url_encoded);
        }

        if ($normalize_whitespace === true) {
            $str = self::normalize_whitespace($str, $keep_non_breaking_space);
        }

        if ($normalize_msword === true) {
            $str = self::normalize_msword($str);
        }

        if ($remove_bom === true) {
            $str = self::remove_bom($str);
        }

        return $str;
    }

    /**
     * Clean-up a string and show only printable UTF-8 chars at the end  + fix UTF-8 encoding.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return string
     */
    public static function cleanup($str): string
    {
        // init
        $str = (string) $str;

        if ($str === '') {
            return '';
        }

        // fixed ISO <-> UTF-8 Errors
        $str = self::fix_simple_utf8($str);

        // remove all none UTF-8 symbols
        // && remove diamond question mark (�)
        // && remove remove invisible characters (e.g. "\0")
        // && remove BOM
        // && normalize whitespace chars (but keep non-breaking-spaces)
        return self::clean(
            $str,
            true,
            true,
            false,
            true,
            true,
            true
        );
    }

    /**
     * Accepts a string or a array of strings and returns an array of Unicode code points.
     *
     * INFO: opposite to UTF8::string()
     *
     * @param string|string[] $arg     <p>A UTF-8 encoded string or an array of such strings.</p>
     * @param bool            $u_style <p>If True, will return code points in U+xxxx format,
     *                                 default, code points will be returned as integers.</p>
     *
     * @return array<int|string>
     *                           The array of code points:<br>
     *                           array<int> for $u_style === false<br>
     *                           array<string> for $u_style === true<br>
     */
    public static function codepoints($arg, bool $u_style = false): array
    {
        if (\is_string($arg) === true) {
            $arg = self::str_split($arg);
        }

        /**
         * @psalm-suppress DocblockTypeContradiction
         */
        if (!\is_array($arg)) {
            return [];
        }

        if ($arg === []) {
            return [];
        }

        $arg = \array_map(
            [
                self::class,
                'ord',
            ],
            $arg
        );

        if ($u_style === true) {
            $arg = \array_map(
                [
                    self::class,
                    'int_to_hex',
                ],
                $arg
            );
        }

        return $arg;
    }

    /**
     * Trims the string and replaces consecutive whitespace characters with a
     * single space. This includes tabs and newline characters, as well as
     * multibyte whitespace such as the thin space and ideographic space.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return string string with a trimmed $str and condensed whitespace
     */
    public static function collapse_whitespace(string $str): string
    {
        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return \trim((string) \mb_ereg_replace('[[:space:]]+', ' ', $str));
        }

        return \trim(self::regex_replace($str, '[[:space:]]+', ' '));
    }

    /**
     * Returns count of characters used in a string.
     *
     * @param string $str                     <p>The input string.</p>
     * @param bool   $clean_utf8              [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param bool   $try_to_use_mb_functions [optional] <p>Set to false, if you don't want to use
     *
     * @return int[] an associative array of Character as keys and
     *               their count as values
     */
    public static function count_chars(
        string $str,
        bool $clean_utf8 = false,
        bool $try_to_use_mb_functions = true
    ): array {
        return \array_count_values(
            self::str_split(
                $str,
                1,
                $clean_utf8,
                $try_to_use_mb_functions
            )
        );
    }

    /**
     * Remove css media-queries.
     *
     * @param string $str
     *
     * @return string
     */
    public static function css_stripe_media_queries(string $str): string
    {
        return (string) \preg_replace(
            '#@media\\s+(?:only\\s)?(?:[\\s{(]|screen|all)\\s?[^{]+{.*}\\s*}\\s*#isumU',
            '',
            $str
        );
    }

    /**
     * Checks whether ctype is available on the server.
     *
     * @return bool
     *              <strong>true</strong> if available, <strong>false</strong> otherwise
     */
    public static function ctype_loaded(): bool
    {
        return \extension_loaded('ctype');
    }

    /**
     * Converts an int value into a UTF-8 character.
     *
     * @param mixed $int
     *
     * @return string
     */
    public static function decimal_to_chr($int): string
    {
        return self::html_entity_decode('&#' . $int . ';', \ENT_QUOTES | \ENT_HTML5);
    }

    /**
     * Decodes a MIME header field
     *
     * @param string $str
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return false|string
     *                      A decoded MIME field on success,
     *                      or false if an error occurs during the decoding
     */
    public static function decode_mimeheader($str, string $encoding = 'UTF-8')
    {
        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if (self::$SUPPORT['iconv'] === true) {
            return \iconv_mime_decode($str, \ICONV_MIME_DECODE_CONTINUE_ON_ERROR, $encoding);
        }

        if ($encoding !== 'UTF-8') {
            $str = self::encode($encoding, $str);
        }

        return \mb_decode_mimeheader($str);
    }

    /**
     * Decodes a string which was encoded by "UTF8::emoji_encode()".
     *
     * @param string $str                            <p>The input string.</p>
     * @param bool   $use_reversible_string_mappings [optional] <p>
     *                                               When <b>TRUE</b>, we se a reversible string mapping
     *                                               between "emoji_encode" and "emoji_decode".</p>
     *
     * @return string
     */
    public static function emoji_decode(
        string $str,
        bool $use_reversible_string_mappings = false
    ): string {
        self::initEmojiData();

        if ($use_reversible_string_mappings === true) {
            return (string) \str_replace(
                (array) self::$EMOJI_KEYS_REVERSIBLE_CACHE,
                (array) self::$EMOJI_VALUES_CACHE,
                $str
            );
        }

        return (string) \str_replace(
            (array) self::$EMOJI_KEYS_CACHE,
            (array) self::$EMOJI_VALUES_CACHE,
            $str
        );
    }

    /**
     * Encode a string with emoji chars into a non-emoji string.
     *
     * @param string $str                            <p>The input string</p>
     * @param bool   $use_reversible_string_mappings [optional] <p>
     *                                               when <b>TRUE</b>, we se a reversible string mapping
     *                                               between "emoji_encode" and "emoji_decode"</p>
     *
     * @return string
     */
    public static function emoji_encode(
        string $str,
        bool $use_reversible_string_mappings = false
    ): string {
        self::initEmojiData();

        if ($use_reversible_string_mappings === true) {
            return (string) \str_replace(
                (array) self::$EMOJI_VALUES_CACHE,
                (array) self::$EMOJI_KEYS_REVERSIBLE_CACHE,
                $str
            );
        }

        return (string) \str_replace(
            (array) self::$EMOJI_VALUES_CACHE,
            (array) self::$EMOJI_KEYS_CACHE,
            $str
        );
    }

    /**
     * Encode a string with a new charset-encoding.
     *
     * INFO:  This function will also try to fix broken / double encoding,
     *        so you can call this function also on a UTF-8 string and you don't mess up the string.
     *
     * @param string $to_encoding                   <p>e.g. 'UTF-16', 'UTF-8', 'ISO-8859-1', etc.</p>
     * @param string $str                           <p>The input string</p>
     * @param bool   $auto_detect_the_from_encoding [optional] <p>Force the new encoding (we try to fix broken / double
     *                                              encoding for UTF-8)<br> otherwise we auto-detect the current
     *                                              string-encoding</p>
     * @param string $from_encoding                 [optional] <p>e.g. 'UTF-16', 'UTF-8', 'ISO-8859-1', etc.<br>
     *                                              A empty string will trigger the autodetect anyway.</p>
     *
     * @return string
     *
     * @psalm-suppress InvalidReturnStatement
     */
    public static function encode(
        string $to_encoding,
        string $str,
        bool $auto_detect_the_from_encoding = true,
        string $from_encoding = ''
    ): string {
        if ($str === '' || $to_encoding === '') {
            return $str;
        }

        if ($to_encoding !== 'UTF-8' && $to_encoding !== 'CP850') {
            $to_encoding = self::normalize_encoding($to_encoding, 'UTF-8');
        }

        if ($from_encoding && $from_encoding !== 'UTF-8' && $from_encoding !== 'CP850') {
            $from_encoding = self::normalize_encoding($from_encoding, null);
        }

        if (
            $to_encoding
            &&
            $from_encoding
            &&
            $from_encoding === $to_encoding
        ) {
            return $str;
        }

        if ($to_encoding === 'JSON') {
            $return = self::json_encode($str);
            if ($return === false) {
                throw new \InvalidArgumentException('The input string [' . $str . '] can not be used for json_encode().');
            }

            return $return;
        }
        if ($from_encoding === 'JSON') {
            $str = self::json_decode($str);
            $from_encoding = '';
        }

        if ($to_encoding === 'BASE64') {
            return \base64_encode($str);
        }
        if ($from_encoding === 'BASE64') {
            $str = \base64_decode($str, true);
            $from_encoding = '';
        }

        if ($to_encoding === 'HTML-ENTITIES') {
            return self::html_encode($str, true, 'UTF-8');
        }
        if ($from_encoding === 'HTML-ENTITIES') {
            $str = self::html_entity_decode($str, \ENT_COMPAT, 'UTF-8');
            $from_encoding = '';
        }

        $from_encoding_auto_detected = false;
        if (
            $auto_detect_the_from_encoding === true
            ||
            !$from_encoding
        ) {
            $from_encoding_auto_detected = self::str_detect_encoding($str);
        }

        // DEBUG
        //var_dump($to_encoding, $from_encoding, $from_encoding_auto_detected, $str, "\n\n");

        if ($from_encoding_auto_detected !== false) {
            /** @noinspection CallableParameterUseCaseInTypeContextInspection - FP */
            $from_encoding = $from_encoding_auto_detected;
        } elseif ($auto_detect_the_from_encoding === true) {
            // fallback for the "autodetect"-mode
            return self::to_utf8($str);
        }

        if (
            !$from_encoding
            ||
            $from_encoding === $to_encoding
        ) {
            return $str;
        }

        if (
            $to_encoding === 'UTF-8'
            &&
            (
                $from_encoding === 'WINDOWS-1252'
                ||
                $from_encoding === 'ISO-8859-1'
            )
        ) {
            return self::to_utf8($str);
        }

        if (
            $to_encoding === 'ISO-8859-1'
            &&
            (
                $from_encoding === 'WINDOWS-1252'
                ||
                $from_encoding === 'UTF-8'
            )
        ) {
            return self::to_iso8859($str);
        }

        if (
            $to_encoding !== 'UTF-8'
            &&
            $to_encoding !== 'ISO-8859-1'
            &&
            $to_encoding !== 'WINDOWS-1252'
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::encode() without mbstring cannot handle "' . $to_encoding . '" encoding', \E_USER_WARNING);
        }

        if (self::$SUPPORT['mbstring'] === true) {
            // warning: do not use the symfony polyfill here
            $str_encoded = \mb_convert_encoding(
                $str,
                $to_encoding,
                $from_encoding
            );

            if ($str_encoded) {
                return $str_encoded;
            }
        }

        $return = \iconv($from_encoding, $to_encoding, $str);
        if ($return !== false) {
            return $return;
        }

        return $str;
    }

    /**
     * @param string $str
     * @param string $from_charset      [optional] <p>Set the input charset.</p>
     * @param string $to_charset        [optional] <p>Set the output charset.</p>
     * @param string $transfer_encoding [optional] <p>Set the transfer encoding.</p>
     * @param string $linefeed          [optional] <p>Set the used linefeed.</p>
     * @param int    $indent            [optional] <p>Set the max length indent.</p>
     *
     * @return false|string
     *                      <p>An encoded MIME field on success,
     *                      or false if an error occurs during the encoding.</p>
     */
    public static function encode_mimeheader(
        $str,
        $from_charset = 'UTF-8',
        $to_charset = 'UTF-8',
        $transfer_encoding = 'Q',
        $linefeed = '\\r\\n',
        $indent = 76
    ) {
        if ($from_charset !== 'UTF-8' && $from_charset !== 'CP850') {
            $from_charset = self::normalize_encoding($from_charset, 'UTF-8');
        }

        if ($to_charset !== 'UTF-8' && $to_charset !== 'CP850') {
            $to_charset = self::normalize_encoding($to_charset, 'UTF-8');
        }

        return \iconv_mime_encode(
            '',
            $str,
            [
                'scheme'           => $transfer_encoding,
                'line-length'      => $indent,
                'input-charset'    => $from_charset,
                'output-charset'   => $to_charset,
                'line-break-chars' => $linefeed,
            ]
        );
    }

    /**
     * Create an extract from a sentence, so if the search-string was found, it try to centered in the output.
     *
     * @param string   $str                       <p>The input string.</p>
     * @param string   $search                    <p>The searched string.</p>
     * @param int|null $length                    [optional] <p>Default: null === text->length / 2</p>
     * @param string   $replacer_for_skipped_text [optional] <p>Default: …</p>
     * @param string   $encoding                  [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function extract_text(
        string $str,
        string $search = '',
        int $length = null,
        string $replacer_for_skipped_text = '…',
        string $encoding = 'UTF-8'
    ): string {
        if ($str === '') {
            return '';
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        $trim_chars = "\t\r\n -_()!~?=+/*\\,.:;\"'[]{}`&";

        if ($length === null) {
            $length = (int) \round((int) self::strlen($str, $encoding) / 2, 0);
        }

        if ($search === '') {
            if ($encoding === 'UTF-8') {
                if ($length > 0) {
                    $string_length = (int) \mb_strlen($str);
                    $end = ($length - 1) > $string_length ? $string_length : ($length - 1);
                } else {
                    $end = 0;
                }

                $pos = (int) \min(
                    \mb_strpos($str, ' ', $end),
                    \mb_strpos($str, '.', $end)
                );
            } else {
                if ($length > 0) {
                    $string_length = (int) self::strlen($str, $encoding);
                    $end = ($length - 1) > $string_length ? $string_length : ($length - 1);
                } else {
                    $end = 0;
                }

                $pos = (int) \min(
                    self::strpos($str, ' ', $end, $encoding),
                    self::strpos($str, '.', $end, $encoding)
                );
            }

            if ($pos) {
                if ($encoding === 'UTF-8') {
                    $str_sub = \mb_substr($str, 0, $pos);
                } else {
                    $str_sub = self::substr($str, 0, $pos, $encoding);
                }

                if ($str_sub === false) {
                    return '';
                }

                return \rtrim($str_sub, $trim_chars) . $replacer_for_skipped_text;
            }

            return $str;
        }

        if ($encoding === 'UTF-8') {
            $word_position = (int) \mb_stripos($str, $search);
            $half_side = (int) ($word_position - $length / 2 + (int) \mb_strlen($search) / 2);
        } else {
            $word_position = (int) self::stripos($str, $search, 0, $encoding);
            $half_side = (int) ($word_position - $length / 2 + (int) self::strlen($search, $encoding) / 2);
        }

        $pos_start = 0;
        if ($half_side > 0) {
            if ($encoding === 'UTF-8') {
                $half_text = \mb_substr($str, 0, $half_side);
            } else {
                $half_text = self::substr($str, 0, $half_side, $encoding);
            }
            if ($half_text !== false) {
                if ($encoding === 'UTF-8') {
                    $pos_start = (int) \max(
                        \mb_strrpos($half_text, ' '),
                        \mb_strrpos($half_text, '.')
                    );
                } else {
                    $pos_start = (int) \max(
                        self::strrpos($half_text, ' ', 0, $encoding),
                        self::strrpos($half_text, '.', 0, $encoding)
                    );
                }
            }
        }

        if ($word_position && $half_side > 0) {
            $offset = $pos_start + $length - 1;
            $real_length = (int) self::strlen($str, $encoding);

            if ($offset > $real_length) {
                $offset = $real_length;
            }

            if ($encoding === 'UTF-8') {
                $pos_end = (int) \min(
                    \mb_strpos($str, ' ', $offset),
                    \mb_strpos($str, '.', $offset)
                ) - $pos_start;
            } else {
                $pos_end = (int) \min(
                    self::strpos($str, ' ', $offset, $encoding),
                    self::strpos($str, '.', $offset, $encoding)
                ) - $pos_start;
            }

            if (!$pos_end || $pos_end <= 0) {
                if ($encoding === 'UTF-8') {
                    $str_sub = \mb_substr($str, $pos_start, (int) \mb_strlen($str));
                } else {
                    $str_sub = self::substr($str, $pos_start, (int) self::strlen($str, $encoding), $encoding);
                }
                if ($str_sub !== false) {
                    $extract = $replacer_for_skipped_text . \ltrim($str_sub, $trim_chars);
                } else {
                    $extract = '';
                }
            } else {
                if ($encoding === 'UTF-8') {
                    $str_sub = \mb_substr($str, $pos_start, $pos_end);
                } else {
                    $str_sub = self::substr($str, $pos_start, $pos_end, $encoding);
                }
                if ($str_sub !== false) {
                    $extract = $replacer_for_skipped_text . \trim($str_sub, $trim_chars) . $replacer_for_skipped_text;
                } else {
                    $extract = '';
                }
            }
        } else {
            $offset = $length - 1;
            $true_length = (int) self::strlen($str, $encoding);

            if ($offset > $true_length) {
                $offset = $true_length;
            }

            if ($encoding === 'UTF-8') {
                $pos_end = (int) \min(
                    \mb_strpos($str, ' ', $offset),
                    \mb_strpos($str, '.', $offset)
                );
            } else {
                $pos_end = (int) \min(
                    self::strpos($str, ' ', $offset, $encoding),
                    self::strpos($str, '.', $offset, $encoding)
                );
            }

            if ($pos_end) {
                if ($encoding === 'UTF-8') {
                    $str_sub = \mb_substr($str, 0, $pos_end);
                } else {
                    $str_sub = self::substr($str, 0, $pos_end, $encoding);
                }
                if ($str_sub !== false) {
                    $extract = \rtrim($str_sub, $trim_chars) . $replacer_for_skipped_text;
                } else {
                    $extract = '';
                }
            } else {
                $extract = $str;
            }
        }

        return $extract;
    }

    /**
     * Reads entire file into a string.
     *
     * WARNING: Do not use UTF-8 Option ($convert_to_utf8) for binary files (e.g.: images) !!!
     *
     * @see http://php.net/manual/en/function.file-get-contents.php
     *
     * @param string        $filename         <p>
     *                                        Name of the file to read.
     *                                        </p>
     * @param bool          $use_include_path [optional] <p>
     *                                        Prior to PHP 5, this parameter is called
     *                                        use_include_path and is a bool.
     *                                        As of PHP 5 the FILE_USE_INCLUDE_PATH can be used
     *                                        to trigger include path
     *                                        search.
     *                                        </p>
     * @param resource|null $context          [optional] <p>
     *                                        A valid context resource created with
     *                                        stream_context_create. If you don't need to use a
     *                                        custom context, you can skip this parameter by &null;.
     *                                        </p>
     * @param int|null      $offset           [optional] <p>
     *                                        The offset where the reading starts.
     *                                        </p>
     * @param int|null      $max_length       [optional] <p>
     *                                        Maximum length of data read. The default is to read until end
     *                                        of file is reached.
     *                                        </p>
     * @param int           $timeout          <p>The time in seconds for the timeout.</p>
     * @param bool          $convert_to_utf8  <strong>WARNING!!!</strong> <p>Maybe you can't use this option for
     *                                        some files, because they used non default utf-8 chars. Binary files
     *                                        like images or pdf will not be converted.</p>
     * @param string        $from_encoding    [optional] <p>e.g. 'UTF-16', 'UTF-8', 'ISO-8859-1', etc.<br>
     *                                        A empty string will trigger the autodetect anyway.</p>
     *
     * @return false|string
     *                      <p>The function returns the read data as string or <b>false</b> on failure.</p>
     */
    public static function file_get_contents(
        string $filename,
        bool $use_include_path = false,
        $context = null,
        int $offset = null,
        int $max_length = null,
        int $timeout = 10,
        bool $convert_to_utf8 = true,
        string $from_encoding = ''
    ) {
        // init
        $filename = \filter_var($filename, \FILTER_SANITIZE_STRING);
        /** @noinspection CallableParameterUseCaseInTypeContextInspection - FP */
        if ($filename === false) {
            return false;
        }

        if ($timeout && $context === null) {
            $context = \stream_context_create(
                [
                    'http' => [
                        'timeout' => $timeout,
                    ],
                ]
            );
        }

        if ($offset === null) {
            $offset = 0;
        }

        if (\is_int($max_length) === true) {
            $data = \file_get_contents($filename, $use_include_path, $context, $offset, $max_length);
        } else {
            $data = \file_get_contents($filename, $use_include_path, $context, $offset);
        }

        // return false on error
        if ($data === false) {
            return false;
        }

        if ($convert_to_utf8 === true) {
            if (
                self::is_binary($data, true) !== true
                ||
                self::is_utf16($data, false) !== false
                ||
                self::is_utf32($data, false) !== false
            ) {
                $data = self::encode('UTF-8', $data, false, $from_encoding);
                $data = self::cleanup($data);
            }
        }

        return $data;
    }

    /**
     * Checks if a file starts with BOM (Byte Order Mark) character.
     *
     * @param string $file_path <p>Path to a valid file.</p>
     *
     * @throws \RuntimeException if file_get_contents() returned false
     *
     * @return bool
     *              <p><strong>true</strong> if the file has BOM at the start, <strong>false</strong> otherwise</p>
     */
    public static function file_has_bom(string $file_path): bool
    {
        $file_content = \file_get_contents($file_path);
        if ($file_content === false) {
            throw new \RuntimeException('file_get_contents() returned false for:' . $file_path);
        }

        return self::string_has_bom($file_content);
    }

    /**
     * Normalizes to UTF-8 NFC, converting from WINDOWS-1252 when needed.
     *
     * @param mixed  $var
     * @param int    $normalization_form
     * @param string $leading_combining
     *
     * @return mixed
     */
    public static function filter(
        $var,
        int $normalization_form = \Normalizer::NFC,
        string $leading_combining = '◌'
    ) {
        switch (\gettype($var)) {
            case 'array':
                /** @noinspection ForeachSourceInspection */
                foreach ($var as $k => &$v) {
                    $v = self::filter($v, $normalization_form, $leading_combining);
                }
                unset($v);

                break;
            case 'object':
                /** @noinspection ForeachSourceInspection */
                foreach ($var as $k => &$v) {
                    $v = self::filter($v, $normalization_form, $leading_combining);
                }
                unset($v);

                break;
            case 'string':

                if (\strpos($var, "\r") !== false) {
                    // Workaround https://bugs.php.net/65732
                    $var = self::normalize_line_ending($var);
                }

                if (ASCII::is_ascii($var) === false) {
                    if (\Normalizer::isNormalized($var, $normalization_form)) {
                        $n = '-';
                    } else {
                        $n = \Normalizer::normalize($var, $normalization_form);

                        if (isset($n[0])) {
                            $var = $n;
                        } else {
                            $var = self::encode('UTF-8', $var, true);
                        }
                    }

                    if (
                        $var[0] >= "\x80"
                        &&
                        isset($n[0], $leading_combining[0])
                        &&
                        \preg_match('/^\\p{Mn}/u', $var)
                    ) {
                        // Prevent leading combining chars
                        // for NFC-safe concatenations.
                        $var = $leading_combining . $var;
                    }
                }

                break;
        }

        return $var;
    }

    /**
     * "filter_input()"-wrapper with normalizes to UTF-8 NFC, converting from WINDOWS-1252 when needed.
     *
     * Gets a specific external variable by name and optionally filters it
     *
     * @see http://php.net/manual/en/function.filter-input.php
     *
     * @param int    $type          <p>
     *                              One of <b>INPUT_GET</b>, <b>INPUT_POST</b>,
     *                              <b>INPUT_COOKIE</b>, <b>INPUT_SERVER</b>, or
     *                              <b>INPUT_ENV</b>.
     *                              </p>
     * @param string $variable_name <p>
     *                              Name of a variable to get.
     *                              </p>
     * @param int    $filter        [optional] <p>
     *                              The ID of the filter to apply. The
     *                              manual page lists the available filters.
     *                              </p>
     * @param mixed  $options       [optional] <p>
     *                              Associative array of options or bitwise disjunction of flags. If filter
     *                              accepts options, flags can be provided in "flags" field of array.
     *                              </p>
     *
     * @return mixed Value of the requested variable on success, <b>FALSE</b> if the filter fails, or <b>NULL</b> if the
     *               <i>variable_name</i> variable is not set. If the flag <b>FILTER_NULL_ON_FAILURE</b> is used, it
     *               returns <b>FALSE</b> if the variable is not set and <b>NULL</b> if the filter fails.
     */
    public static function filter_input(
        int $type,
        string $variable_name,
        int $filter = \FILTER_DEFAULT,
        $options = null
    ) {
        if ($options === null || \func_num_args() < 4) {
            $var = \filter_input($type, $variable_name, $filter);
        } else {
            $var = \filter_input($type, $variable_name, $filter, $options);
        }

        return self::filter($var);
    }

    /**
     * "filter_input_array()"-wrapper with normalizes to UTF-8 NFC, converting from WINDOWS-1252 when needed.
     *
     * Gets external variables and optionally filters them
     *
     * @see http://php.net/manual/en/function.filter-input-array.php
     *
     * @param int   $type       <p>
     *                          One of <b>INPUT_GET</b>, <b>INPUT_POST</b>,
     *                          <b>INPUT_COOKIE</b>, <b>INPUT_SERVER</b>, or
     *                          <b>INPUT_ENV</b>.
     *                          </p>
     * @param mixed $definition [optional] <p>
     *                          An array defining the arguments. A valid key is a string
     *                          containing a variable name and a valid value is either a filter type, or an array
     *                          optionally specifying the filter, flags and options. If the value is an
     *                          array, valid keys are filter which specifies the
     *                          filter type,
     *                          flags which specifies any flags that apply to the
     *                          filter, and options which specifies any options that
     *                          apply to the filter. See the example below for a better understanding.
     *                          </p>
     *                          <p>
     *                          This parameter can be also an integer holding a filter constant. Then all values in the
     *                          input array are filtered by this filter.
     *                          </p>
     * @param bool  $add_empty  [optional] <p>
     *                          Add missing keys as <b>NULL</b> to the return value.
     *                          </p>
     *
     * @return mixed An array containing the values of the requested variables on success, or <b>FALSE</b> on failure.
     *               An array value will be <b>FALSE</b> if the filter fails, or <b>NULL</b> if the variable is not
     *               set. Or if the flag <b>FILTER_NULL_ON_FAILURE</b> is used, it returns <b>FALSE</b> if the variable
     *               is not set and <b>NULL</b> if the filter fails.
     */
    public static function filter_input_array(
        int $type,
        $definition = null,
        bool $add_empty = true
    ) {
        if ($definition === null || \func_num_args() < 2) {
            $a = \filter_input_array($type);
        } else {
            $a = \filter_input_array($type, $definition, $add_empty);
        }

        return self::filter($a);
    }

    /**
     * "filter_var()"-wrapper with normalizes to UTF-8 NFC, converting from WINDOWS-1252 when needed.
     *
     * Filters a variable with a specified filter
     *
     * @see http://php.net/manual/en/function.filter-var.php
     *
     * @param mixed $variable <p>
     *                        Value to filter.
     *                        </p>
     * @param int   $filter   [optional] <p>
     *                        The ID of the filter to apply. The
     *                        manual page lists the available filters.
     *                        </p>
     * @param mixed $options  [optional] <p>
     *                        Associative array of options or bitwise disjunction of flags. If filter
     *                        accepts options, flags can be provided in "flags" field of array. For
     *                        the "callback" filter, callable type should be passed. The
     *                        callback must accept one argument, the value to be filtered, and return
     *                        the value after filtering/sanitizing it.
     *                        </p>
     *                        <p>
     *                        <code>
     *                        // for filters that accept options, use this format
     *                        $options = array(
     *                        'options' => array(
     *                        'default' => 3, // value to return if the filter fails
     *                        // other options here
     *                        'min_range' => 0
     *                        ),
     *                        'flags' => FILTER_FLAG_ALLOW_OCTAL,
     *                        );
     *                        $var = filter_var('0755', FILTER_VALIDATE_INT, $options);
     *                        // for filter that only accept flags, you can pass them directly
     *                        $var = filter_var('oops', FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
     *                        // for filter that only accept flags, you can also pass as an array
     *                        $var = filter_var('oops', FILTER_VALIDATE_BOOLEAN,
     *                        array('flags' => FILTER_NULL_ON_FAILURE));
     *                        // callback validate filter
     *                        function foo($value)
     *                        {
     *                        // Expected format: Surname, GivenNames
     *                        if (strpos($value, ", ") === false) return false;
     *                        list($surname, $givennames) = explode(", ", $value, 2);
     *                        $empty = (empty($surname) || empty($givennames));
     *                        $notstrings = (!is_string($surname) || !is_string($givennames));
     *                        if ($empty || $notstrings) {
     *                        return false;
     *                        } else {
     *                        return $value;
     *                        }
     *                        }
     *                        $var = filter_var('Doe, Jane Sue', FILTER_CALLBACK, array('options' => 'foo'));
     *                        </code>
     *                        </p>
     *
     * @return mixed the filtered data, or <b>FALSE</b> if the filter fails
     */
    public static function filter_var(
        $variable,
        int $filter = \FILTER_DEFAULT,
        $options = null
    ) {
        if (\func_num_args() < 3) {
            $variable = \filter_var($variable, $filter);
        } else {
            $variable = \filter_var($variable, $filter, $options);
        }

        return self::filter($variable);
    }

    /**
     * "filter_var_array()"-wrapper with normalizes to UTF-8 NFC, converting from WINDOWS-1252 when needed.
     *
     * Gets multiple variables and optionally filters them
     *
     * @see http://php.net/manual/en/function.filter-var-array.php
     *
     * @param array $data       <p>
     *                          An array with string keys containing the data to filter.
     *                          </p>
     * @param mixed $definition [optional] <p>
     *                          An array defining the arguments. A valid key is a string
     *                          containing a variable name and a valid value is either a
     *                          filter type, or an
     *                          array optionally specifying the filter, flags and options.
     *                          If the value is an array, valid keys are filter
     *                          which specifies the filter type,
     *                          flags which specifies any flags that apply to the
     *                          filter, and options which specifies any options that
     *                          apply to the filter. See the example below for a better understanding.
     *                          </p>
     *                          <p>
     *                          This parameter can be also an integer holding a filter constant. Then all values in the
     *                          input array are filtered by this filter.
     *                          </p>
     * @param bool  $add_empty  [optional] <p>
     *                          Add missing keys as <b>NULL</b> to the return value.
     *                          </p>
     *
     * @return mixed an array containing the values of the requested variables on success, or <b>FALSE</b> on failure.
     *               An array value will be <b>FALSE</b> if the filter fails, or <b>NULL</b> if the variable is not
     *               set
     */
    public static function filter_var_array(
        array $data,
        $definition = null,
        bool $add_empty = true
    ) {
        if (\func_num_args() < 2) {
            $a = \filter_var_array($data);
        } else {
            $a = \filter_var_array($data, $definition, $add_empty);
        }

        return self::filter($a);
    }

    /**
     * Checks whether finfo is available on the server.
     *
     * @return bool
     *              <strong>true</strong> if available, <strong>false</strong> otherwise
     */
    public static function finfo_loaded(): bool
    {
        return \class_exists('finfo');
    }

    /**
     * Returns the first $n characters of the string.
     *
     * @param string $str      <p>The input string.</p>
     * @param int    $n        <p>Number of characters to retrieve from the start.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function first_char(
        string $str,
        int $n = 1,
        string $encoding = 'UTF-8'
    ): string {
        if ($str === '' || $n <= 0) {
            return '';
        }

        if ($encoding === 'UTF-8') {
            return (string) \mb_substr($str, 0, $n);
        }

        return (string) self::substr($str, 0, $n, $encoding);
    }

    /**
     * Check if the number of Unicode characters isn't greater than the specified integer.
     *
     * @param string $str      the original string to be checked
     * @param int    $box_size the size in number of chars to be checked against string
     *
     * @return bool true if string is less than or equal to $box_size, false otherwise
     */
    public static function fits_inside(string $str, int $box_size): bool
    {
        return (int) self::strlen($str) <= $box_size;
    }

    /**
     * Try to fix simple broken UTF-8 strings.
     *
     * INFO: Take a look at "UTF8::fix_utf8()" if you need a more advanced fix for broken UTF-8 strings.
     *
     * If you received an UTF-8 string that was converted from Windows-1252 as it was ISO-8859-1
     * (ignoring Windows-1252 chars from 80 to 9F) use this function to fix it.
     * See: http://en.wikipedia.org/wiki/Windows-1252
     *
     * @param string $str <p>The input string</p>
     *
     * @return string
     */
    public static function fix_simple_utf8(string $str): string
    {
        if ($str === '') {
            return '';
        }

        static $BROKEN_UTF8_TO_UTF8_KEYS_CACHE = null;
        static $BROKEN_UTF8_TO_UTF8_VALUES_CACHE = null;

        if ($BROKEN_UTF8_TO_UTF8_KEYS_CACHE === null) {
            if (self::$BROKEN_UTF8_FIX === null) {
                self::$BROKEN_UTF8_FIX = self::getData('utf8_fix');
            }

            $BROKEN_UTF8_TO_UTF8_KEYS_CACHE = \array_keys(self::$BROKEN_UTF8_FIX);
            $BROKEN_UTF8_TO_UTF8_VALUES_CACHE = \array_values(self::$BROKEN_UTF8_FIX);
        }

        return \str_replace($BROKEN_UTF8_TO_UTF8_KEYS_CACHE, $BROKEN_UTF8_TO_UTF8_VALUES_CACHE, $str);
    }

    /**
     * Fix a double (or multiple) encoded UTF8 string.
     *
     * @param string|string[] $str you can use a string or an array of strings
     *
     * @return string|string[]
     *                         Will return the fixed input-"array" or
     *                         the fixed input-"string"
     *
     * @psalm-suppress InvalidReturnType
     */
    public static function fix_utf8($str)
    {
        if (\is_array($str) === true) {
            foreach ($str as $k => &$v) {
                $v = self::fix_utf8($v);
            }
            unset($v);

            /**
             * @psalm-suppress InvalidReturnStatement
             */
            return $str;
        }

        $str = (string) $str;
        $last = '';
        while ($last !== $str) {
            $last = $str;
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $str = self::to_utf8(
                self::utf8_decode($str, true)
            );
        }

        /**
         * @psalm-suppress InvalidReturnStatement
         */
        return $str;
    }

    /**
     * Get character of a specific character.
     *
     * @param string $char
     *
     * @return string 'RTL' or 'LTR'
     */
    public static function getCharDirection(string $char): string
    {
        if (self::$SUPPORT['intlChar'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            $tmp_return = \IntlChar::charDirection($char);

            // from "IntlChar"-Class
            $char_direction = [
                'RTL' => [1, 13, 14, 15, 21],
                'LTR' => [0, 11, 12, 20],
            ];

            if (\in_array($tmp_return, $char_direction['LTR'], true)) {
                return 'LTR';
            }

            if (\in_array($tmp_return, $char_direction['RTL'], true)) {
                return 'RTL';
            }
        }

        $c = static::chr_to_decimal($char);

        if (!($c >= 0x5be && $c <= 0x10b7f)) {
            return 'LTR';
        }

        if ($c <= 0x85e) {
            if ($c === 0x5be ||
                $c === 0x5c0 ||
                $c === 0x5c3 ||
                $c === 0x5c6 ||
                ($c >= 0x5d0 && $c <= 0x5ea) ||
                ($c >= 0x5f0 && $c <= 0x5f4) ||
                $c === 0x608 ||
                $c === 0x60b ||
                $c === 0x60d ||
                $c === 0x61b ||
                ($c >= 0x61e && $c <= 0x64a) ||
                ($c >= 0x66d && $c <= 0x66f) ||
                ($c >= 0x671 && $c <= 0x6d5) ||
                ($c >= 0x6e5 && $c <= 0x6e6) ||
                ($c >= 0x6ee && $c <= 0x6ef) ||
                ($c >= 0x6fa && $c <= 0x70d) ||
                $c === 0x710 ||
                ($c >= 0x712 && $c <= 0x72f) ||
                ($c >= 0x74d && $c <= 0x7a5) ||
                $c === 0x7b1 ||
                ($c >= 0x7c0 && $c <= 0x7ea) ||
                ($c >= 0x7f4 && $c <= 0x7f5) ||
                $c === 0x7fa ||
                ($c >= 0x800 && $c <= 0x815) ||
                $c === 0x81a ||
                $c === 0x824 ||
                $c === 0x828 ||
                ($c >= 0x830 && $c <= 0x83e) ||
                ($c >= 0x840 && $c <= 0x858) ||
                $c === 0x85e
            ) {
                return 'RTL';
            }
        } elseif ($c === 0x200f) {
            return 'RTL';
        } elseif ($c >= 0xfb1d) {
            if ($c === 0xfb1d ||
                ($c >= 0xfb1f && $c <= 0xfb28) ||
                ($c >= 0xfb2a && $c <= 0xfb36) ||
                ($c >= 0xfb38 && $c <= 0xfb3c) ||
                $c === 0xfb3e ||
                ($c >= 0xfb40 && $c <= 0xfb41) ||
                ($c >= 0xfb43 && $c <= 0xfb44) ||
                ($c >= 0xfb46 && $c <= 0xfbc1) ||
                ($c >= 0xfbd3 && $c <= 0xfd3d) ||
                ($c >= 0xfd50 && $c <= 0xfd8f) ||
                ($c >= 0xfd92 && $c <= 0xfdc7) ||
                ($c >= 0xfdf0 && $c <= 0xfdfc) ||
                ($c >= 0xfe70 && $c <= 0xfe74) ||
                ($c >= 0xfe76 && $c <= 0xfefc) ||
                ($c >= 0x10800 && $c <= 0x10805) ||
                $c === 0x10808 ||
                ($c >= 0x1080a && $c <= 0x10835) ||
                ($c >= 0x10837 && $c <= 0x10838) ||
                $c === 0x1083c ||
                ($c >= 0x1083f && $c <= 0x10855) ||
                ($c >= 0x10857 && $c <= 0x1085f) ||
                ($c >= 0x10900 && $c <= 0x1091b) ||
                ($c >= 0x10920 && $c <= 0x10939) ||
                $c === 0x1093f ||
                $c === 0x10a00 ||
                ($c >= 0x10a10 && $c <= 0x10a13) ||
                ($c >= 0x10a15 && $c <= 0x10a17) ||
                ($c >= 0x10a19 && $c <= 0x10a33) ||
                ($c >= 0x10a40 && $c <= 0x10a47) ||
                ($c >= 0x10a50 && $c <= 0x10a58) ||
                ($c >= 0x10a60 && $c <= 0x10a7f) ||
                ($c >= 0x10b00 && $c <= 0x10b35) ||
                ($c >= 0x10b40 && $c <= 0x10b55) ||
                ($c >= 0x10b58 && $c <= 0x10b72) ||
                ($c >= 0x10b78 && $c <= 0x10b7f)
            ) {
                return 'RTL';
            }
        }

        return 'LTR';
    }

    /**
     * Check for php-support.
     *
     * @param string|null $key
     *
     * @return mixed
     *               Return the full support-"array", if $key === null<br>
     *               return bool-value, if $key is used and available<br>
     *               otherwise return <strong>null</strong>
     */
    public static function getSupportInfo(string $key = null)
    {
        if ($key === null) {
            return self::$SUPPORT;
        }

        if (self::$INTL_TRANSLITERATOR_LIST === null) {
            self::$INTL_TRANSLITERATOR_LIST = self::getData('transliterator_list');
        }
        // compatibility fix for old versions
        self::$SUPPORT['intl__transliterator_list_ids'] = self::$INTL_TRANSLITERATOR_LIST;

        return self::$SUPPORT[$key] ?? null;
    }

    /**
     * Warning: this method only works for some file-types (png, jpg)
     *          if you need more supported types, please use e.g. "finfo"
     *
     * @param string $str
     * @param array  $fallback <p>with this keys: 'ext', 'mime', 'type'
     *
     * @return array<string, string|null>
     *                       <p>with this keys: 'ext', 'mime', 'type'</p>
     */
    public static function get_file_type(
        string $str,
        array $fallback = [
            'ext'  => null,
            'mime' => 'application/octet-stream',
            'type' => null,
        ]
    ): array {
        if ($str === '') {
            return $fallback;
        }

        /** @var false|string $str_info - needed for PhpStan (stubs error) */
        $str_info = \substr($str, 0, 2);
        if ($str_info === false || \strlen($str_info) !== 2) {
            return $fallback;
        }

        // DEBUG
        //var_dump($str_info);

        /** @var array|false $str_info - needed for PhpStan (stubs error) */
        $str_info = \unpack('C2chars', $str_info);
        if ($str_info === false) {
            return $fallback;
        }
        /** @noinspection OffsetOperationsInspection */
        $type_code = (int) ($str_info['chars1'] . $str_info['chars2']);

        // DEBUG
        //var_dump($type_code);

        //
        // info: https://en.wikipedia.org/wiki/Magic_number_%28programming%29#Format_indicator
        //
        switch ($type_code) {
            // WARNING: do not add too simple comparisons, because of false-positive results:
            //
            // 3780 => 'pdf', 7790 => 'exe', 7784 => 'midi', 8075 => 'zip',
            // 8297 => 'rar', 7173 => 'gif', 7373 => 'tiff' 6677 => 'bmp', ...
            //
            case 255216:
                $ext = 'jpg';
                $mime = 'image/jpeg';
                $type = 'binary';

                break;
            case 13780:
                $ext = 'png';
                $mime = 'image/png';
                $type = 'binary';

                break;
            default:
                return $fallback;
        }

        return [
            'ext'  => $ext,
            'mime' => $mime,
            'type' => $type,
        ];
    }

    /**
     * @param int    $length         <p>Length of the random string.</p>
     * @param string $possible_chars [optional] <p>Characters string for the random selection.</p>
     * @param string $encoding       [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function get_random_string(
        int $length,
        string $possible_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
        string $encoding = 'UTF-8'
    ): string {
        // init
        $i = 0;
        $str = '';

        //
        // add random chars
        //

        if ($encoding === 'UTF-8') {
            $max_length = (int) \mb_strlen($possible_chars);
            if ($max_length === 0) {
                return '';
            }

            while ($i < $length) {
                try {
                    $rand_int = \random_int(0, $max_length - 1);
                } catch (\Exception $e) {
                    /** @noinspection RandomApiMigrationInspection */
                    $rand_int = \mt_rand(0, $max_length - 1);
                }
                $char = \mb_substr($possible_chars, $rand_int, 1);
                if ($char !== false) {
                    $str .= $char;
                    ++$i;
                }
            }
        } else {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            $max_length = (int) self::strlen($possible_chars, $encoding);
            if ($max_length === 0) {
                return '';
            }

            while ($i < $length) {
                try {
                    $rand_int = \random_int(0, $max_length - 1);
                } catch (\Exception $e) {
                    /** @noinspection RandomApiMigrationInspection */
                    $rand_int = \mt_rand(0, $max_length - 1);
                }
                $char = self::substr($possible_chars, $rand_int, 1, $encoding);
                if ($char !== false) {
                    $str .= $char;
                    ++$i;
                }
            }
        }

        return $str;
    }

    /**
     * @param int|string $entropy_extra [optional] <p>Extra entropy via a string or int value.</p>
     * @param bool       $use_md5       [optional] <p>Return the unique identifier as md5-hash? Default: true</p>
     *
     * @return string
     */
    public static function get_unique_string($entropy_extra = '', bool $use_md5 = true): string
    {
        $unique_helper = \random_int(0, \mt_getrandmax()) .
                        \session_id() .
                        ($_SERVER['REMOTE_ADDR'] ?? '') .
                        ($_SERVER['SERVER_ADDR'] ?? '') .
                        $entropy_extra;

        $unique_string = \uniqid($unique_helper, true);

        if ($use_md5) {
            $unique_string = \md5($unique_string . $unique_helper);
        }

        return $unique_string;
    }

    /**
     * alias for "UTF8::string_has_bom()"
     *
     * @param string $str
     *
     * @return bool
     *
     * @see UTF8::string_has_bom()
     * @deprecated <p>please use "UTF8::string_has_bom()"</p>
     */
    public static function hasBom(string $str): bool
    {
        return self::string_has_bom($str);
    }

    /**
     * Returns true if the string contains a lower case char, false otherwise.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <p>Whether or not the string contains a lower case character.</p>
     */
    public static function has_lowercase(string $str): bool
    {
        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return \mb_ereg_match('.*[[:lower:]]', $str);
        }

        return self::str_matches_pattern($str, '.*[[:lower:]]');
    }

    /**
     * Returns true if the string contains an upper case char, false otherwise.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool whether or not the string contains an upper case character
     */
    public static function has_uppercase(string $str): bool
    {
        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return \mb_ereg_match('.*[[:upper:]]', $str);
        }

        return self::str_matches_pattern($str, '.*[[:upper:]]');
    }

    /**
     * Converts a hexadecimal value into a UTF-8 character.
     *
     * @param string $hexdec <p>The hexadecimal value.</p>
     *
     * @return false|string one single UTF-8 character
     */
    public static function hex_to_chr(string $hexdec)
    {
        return self::decimal_to_chr(\hexdec($hexdec));
    }

    /**
     * Converts hexadecimal U+xxxx code point representation to integer.
     *
     * INFO: opposite to UTF8::int_to_hex()
     *
     * @param string $hexdec <p>The hexadecimal code point representation.</p>
     *
     * @return false|int the code point, or false on failure
     */
    public static function hex_to_int($hexdec)
    {
        // init
        $hexdec = (string) $hexdec;

        if ($hexdec === '') {
            return false;
        }

        if (\preg_match('/^(?:\\\u|U\+|)([a-zA-Z0-9]{4,6})$/', $hexdec, $match)) {
            return \intval($match[1], 16);
        }

        return false;
    }

    /**
     * alias for "UTF8::html_entity_decode()"
     *
     * @param string $str
     * @param int    $flags
     * @param string $encoding
     *
     * @return string
     *
     * @see UTF8::html_entity_decode()
     * @deprecated <p>please use "UTF8::html_entity_decode()"</p>
     */
    public static function html_decode(
        string $str,
        int $flags = null,
        string $encoding = 'UTF-8'
    ): string {
        return self::html_entity_decode($str, $flags, $encoding);
    }

    /**
     * Converts a UTF-8 string to a series of HTML numbered entities.
     *
     * INFO: opposite to UTF8::html_decode()
     *
     * @param string $str              <p>The Unicode string to be encoded as numbered entities.</p>
     * @param bool   $keep_ascii_chars [optional] <p>Keep ASCII chars.</p>
     * @param string $encoding         [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string HTML numbered entities
     */
    public static function html_encode(
        string $str,
        bool $keep_ascii_chars = false,
        string $encoding = 'UTF-8'
    ): string {
        if ($str === '') {
            return '';
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        // INFO: http://stackoverflow.com/questions/35854535/better-explanation-of-convmap-in-mb-encode-numericentity
        if (self::$SUPPORT['mbstring'] === true) {
            $start_code = 0x00;
            if ($keep_ascii_chars === true) {
                $start_code = 0x80;
            }

            if ($encoding === 'UTF-8') {
                /** @var false|string|null $return - needed for PhpStan (stubs error) */
                $return = \mb_encode_numericentity(
                    $str,
                    [$start_code, 0xfffff, 0, 0xfffff, 0]
                );
                if ($return !== null && $return !== false) {
                    return $return;
                }
            }

            /** @var false|string|null $return - needed for PhpStan (stubs error) */
            $return = \mb_encode_numericentity(
                $str,
                [$start_code, 0xfffff, 0, 0xfffff, 0],
                $encoding
            );
            if ($return !== null && $return !== false) {
                return $return;
            }
        }

        //
        // fallback via vanilla php
        //

        return \implode(
            '',
            \array_map(
                static function (string $chr) use ($keep_ascii_chars, $encoding): string {
                    return self::single_chr_html_encode($chr, $keep_ascii_chars, $encoding);
                },
                self::str_split($str)
            )
        );
    }

    /**
     * UTF-8 version of html_entity_decode()
     *
     * The reason we are not using html_entity_decode() by itself is because
     * while it is not technically correct to leave out the semicolon
     * at the end of an entity most browsers will still interpret the entity
     * correctly. html_entity_decode() does not convert entities without
     * semicolons, so we are left with our own little solution here. Bummer.
     *
     * Convert all HTML entities to their applicable characters
     *
     * INFO: opposite to UTF8::html_encode()
     *
     * @see http://php.net/manual/en/function.html-entity-decode.php
     *
     * @param string $str      <p>
     *                         The input string.
     *                         </p>
     * @param int    $flags    [optional] <p>
     *                         A bitmask of one or more of the following flags, which specify how to handle quotes
     *                         and which document type to use. The default is ENT_COMPAT | ENT_HTML401.
     *                         <table>
     *                         Available <i>flags</i> constants
     *                         <tr valign="top">
     *                         <td>Constant Name</td>
     *                         <td>Description</td>
     *                         </tr>
     *                         <tr valign="top">
     *                         <td><b>ENT_COMPAT</b></td>
     *                         <td>Will convert double-quotes and leave single-quotes alone.</td>
     *                         </tr>
     *                         <tr valign="top">
     *                         <td><b>ENT_QUOTES</b></td>
     *                         <td>Will convert both double and single quotes.</td>
     *                         </tr>
     *                         <tr valign="top">
     *                         <td><b>ENT_NOQUOTES</b></td>
     *                         <td>Will leave both double and single quotes unconverted.</td>
     *                         </tr>
     *                         <tr valign="top">
     *                         <td><b>ENT_HTML401</b></td>
     *                         <td>
     *                         Handle code as HTML 4.01.
     *                         </td>
     *                         </tr>
     *                         <tr valign="top">
     *                         <td><b>ENT_XML1</b></td>
     *                         <td>
     *                         Handle code as XML 1.
     *                         </td>
     *                         </tr>
     *                         <tr valign="top">
     *                         <td><b>ENT_XHTML</b></td>
     *                         <td>
     *                         Handle code as XHTML.
     *                         </td>
     *                         </tr>
     *                         <tr valign="top">
     *                         <td><b>ENT_HTML5</b></td>
     *                         <td>
     *                         Handle code as HTML 5.
     *                         </td>
     *                         </tr>
     *                         </table>
     *                         </p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string the decoded string
     */
    public static function html_entity_decode(
        string $str,
        int $flags = null,
        string $encoding = 'UTF-8'
    ): string {
        if (
            !isset($str[3]) // examples: &; || &x;
            ||
            \strpos($str, '&') === false // no "&"
        ) {
            return $str;
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if ($flags === null) {
            $flags = \ENT_QUOTES | \ENT_HTML5;
        }

        if (
            $encoding !== 'UTF-8'
            &&
            $encoding !== 'ISO-8859-1'
            &&
            $encoding !== 'WINDOWS-1252'
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::html_entity_decode() without mbstring cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        do {
            $str_compare = $str;

            if (\strpos($str, '&') !== false) {
                if (\strpos($str, '&#') !== false) {
                    // decode also numeric & UTF16 two byte entities
                    $str = (string) \preg_replace(
                        '/(&#(?:x0*[0-9a-fA-F]{2,6}(?![0-9a-fA-F;])|(?:0*\d{2,6}(?![0-9;]))))/S',
                        '$1;',
                        $str
                    );
                }

                $str = \html_entity_decode(
                    $str,
                    $flags,
                    $encoding
                );
            }
        } while ($str_compare !== $str);

        return $str;
    }

    /**
     * Create a escape html version of the string via "UTF8::htmlspecialchars()".
     *
     * @param string $str
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function html_escape(string $str, string $encoding = 'UTF-8'): string
    {
        return self::htmlspecialchars(
            $str,
            \ENT_QUOTES | \ENT_SUBSTITUTE,
            $encoding
        );
    }

    /**
     * Remove empty html-tag.
     *
     * e.g.: <tag></tag>
     *
     * @param string $str
     *
     * @return string
     */
    public static function html_stripe_empty_tags(string $str): string
    {
        return (string) \preg_replace(
            '/<[^\\/>]*?>\\s*?<\\/[^>]*?>/u',
            '',
            $str
        );
    }

    /**
     * Convert all applicable characters to HTML entities: UTF-8 version of htmlentities()
     *
     * @see http://php.net/manual/en/function.htmlentities.php
     *
     * @param string $str           <p>
     *                              The input string.
     *                              </p>
     * @param int    $flags         [optional] <p>
     *                              A bitmask of one or more of the following flags, which specify how to handle
     *                              quotes, invalid code unit sequences and the used document type. The default is
     *                              ENT_COMPAT | ENT_HTML401.
     *                              <table>
     *                              Available <i>flags</i> constants
     *                              <tr valign="top">
     *                              <td>Constant Name</td>
     *                              <td>Description</td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_COMPAT</b></td>
     *                              <td>Will convert double-quotes and leave single-quotes alone.</td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_QUOTES</b></td>
     *                              <td>Will convert both double and single quotes.</td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_NOQUOTES</b></td>
     *                              <td>Will leave both double and single quotes unconverted.</td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_IGNORE</b></td>
     *                              <td>
     *                              Silently discard invalid code unit sequences instead of returning
     *                              an empty string. Using this flag is discouraged as it
     *                              may have security implications.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_SUBSTITUTE</b></td>
     *                              <td>
     *                              Replace invalid code unit sequences with a Unicode Replacement Character
     *                              U+FFFD (UTF-8) or &#38;#38;#FFFD; (otherwise) instead of returning an empty
     *                              string.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_DISALLOWED</b></td>
     *                              <td>
     *                              Replace invalid code points for the given document type with a
     *                              Unicode Replacement Character U+FFFD (UTF-8) or &#38;#38;#FFFD;
     *                              (otherwise) instead of leaving them as is. This may be useful, for
     *                              instance, to ensure the well-formedness of XML documents with
     *                              embedded external content.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_HTML401</b></td>
     *                              <td>
     *                              Handle code as HTML 4.01.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_XML1</b></td>
     *                              <td>
     *                              Handle code as XML 1.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_XHTML</b></td>
     *                              <td>
     *                              Handle code as XHTML.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_HTML5</b></td>
     *                              <td>
     *                              Handle code as HTML 5.
     *                              </td>
     *                              </tr>
     *                              </table>
     *                              </p>
     * @param string $encoding      [optional] <p>
     *                              Like <b>htmlspecialchars</b>,
     *                              <b>htmlentities</b> takes an optional third argument
     *                              <i>encoding</i> which defines encoding used in
     *                              conversion.
     *                              Although this argument is technically optional, you are highly
     *                              encouraged to specify the correct value for your code.
     *                              </p>
     * @param bool   $double_encode [optional] <p>
     *                              When <i>double_encode</i> is turned off PHP will not
     *                              encode existing html entities. The default is to convert everything.
     *                              </p>
     *
     * @return string
     *                <p>
     *                The encoded string.
     *                <br><br>
     *                If the input <i>string</i> contains an invalid code unit
     *                sequence within the given <i>encoding</i> an empty string
     *                will be returned, unless either the <b>ENT_IGNORE</b> or
     *                <b>ENT_SUBSTITUTE</b> flags are set.
     *                </p>
     */
    public static function htmlentities(
        string $str,
        int $flags = \ENT_COMPAT,
        string $encoding = 'UTF-8',
        bool $double_encode = true
    ): string {
        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        $str = \htmlentities(
            $str,
            $flags,
            $encoding,
            $double_encode
        );

        /**
         * PHP doesn't replace a backslash to its html entity since this is something
         * that's mostly used to escape characters when inserting in a database. Since
         * we're using a decent database layer, we don't need this shit and we're replacing
         * the double backslashes by its' html entity equivalent.
         *
         * https://github.com/forkcms/library/blob/master/spoon/filter/filter.php#L303
         */
        $str = \str_replace('\\', '&#92;', $str);

        return self::html_encode($str, true, $encoding);
    }

    /**
     * Convert only special characters to HTML entities: UTF-8 version of htmlspecialchars()
     *
     * INFO: Take a look at "UTF8::htmlentities()"
     *
     * @see http://php.net/manual/en/function.htmlspecialchars.php
     *
     * @param string $str           <p>
     *                              The string being converted.
     *                              </p>
     * @param int    $flags         [optional] <p>
     *                              A bitmask of one or more of the following flags, which specify how to handle
     *                              quotes, invalid code unit sequences and the used document type. The default is
     *                              ENT_COMPAT | ENT_HTML401.
     *                              <table>
     *                              Available <i>flags</i> constants
     *                              <tr valign="top">
     *                              <td>Constant Name</td>
     *                              <td>Description</td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_COMPAT</b></td>
     *                              <td>Will convert double-quotes and leave single-quotes alone.</td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_QUOTES</b></td>
     *                              <td>Will convert both double and single quotes.</td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_NOQUOTES</b></td>
     *                              <td>Will leave both double and single quotes unconverted.</td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_IGNORE</b></td>
     *                              <td>
     *                              Silently discard invalid code unit sequences instead of returning
     *                              an empty string. Using this flag is discouraged as it
     *                              may have security implications.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_SUBSTITUTE</b></td>
     *                              <td>
     *                              Replace invalid code unit sequences with a Unicode Replacement Character
     *                              U+FFFD (UTF-8) or &#38;#38;#FFFD; (otherwise) instead of returning an empty
     *                              string.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_DISALLOWED</b></td>
     *                              <td>
     *                              Replace invalid code points for the given document type with a
     *                              Unicode Replacement Character U+FFFD (UTF-8) or &#38;#38;#FFFD;
     *                              (otherwise) instead of leaving them as is. This may be useful, for
     *                              instance, to ensure the well-formedness of XML documents with
     *                              embedded external content.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_HTML401</b></td>
     *                              <td>
     *                              Handle code as HTML 4.01.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_XML1</b></td>
     *                              <td>
     *                              Handle code as XML 1.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_XHTML</b></td>
     *                              <td>
     *                              Handle code as XHTML.
     *                              </td>
     *                              </tr>
     *                              <tr valign="top">
     *                              <td><b>ENT_HTML5</b></td>
     *                              <td>
     *                              Handle code as HTML 5.
     *                              </td>
     *                              </tr>
     *                              </table>
     *                              </p>
     * @param string $encoding      [optional] <p>
     *                              Defines encoding used in conversion.
     *                              </p>
     *                              <p>
     *                              For the purposes of this function, the encodings
     *                              ISO-8859-1, ISO-8859-15,
     *                              UTF-8, cp866,
     *                              cp1251, cp1252, and
     *                              KOI8-R are effectively equivalent, provided the
     *                              <i>string</i> itself is valid for the encoding, as
     *                              the characters affected by <b>htmlspecialchars</b> occupy
     *                              the same positions in all of these encodings.
     *                              </p>
     * @param bool   $double_encode [optional] <p>
     *                              When <i>double_encode</i> is turned off PHP will not
     *                              encode existing html entities, the default is to convert everything.
     *                              </p>
     *
     * @return string the converted string.
     *                </p>
     *                <p>
     *                If the input <i>string</i> contains an invalid code unit
     *                sequence within the given <i>encoding</i> an empty string
     *                will be returned, unless either the <b>ENT_IGNORE</b> or
     *                <b>ENT_SUBSTITUTE</b> flags are set
     */
    public static function htmlspecialchars(
        string $str,
        int $flags = \ENT_COMPAT,
        string $encoding = 'UTF-8',
        bool $double_encode = true
    ): string {
        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        return \htmlspecialchars(
            $str,
            $flags,
            $encoding,
            $double_encode
        );
    }

    /**
     * Checks whether iconv is available on the server.
     *
     * @return bool
     *              <strong>true</strong> if available, <strong>false</strong> otherwise
     */
    public static function iconv_loaded(): bool
    {
        return \extension_loaded('iconv');
    }

    /**
     * alias for "UTF8::decimal_to_chr()"
     *
     * @param mixed $int
     *
     * @return string
     *
     * @see UTF8::decimal_to_chr()
     * @deprecated <p>please use "UTF8::decimal_to_chr()"</p>
     */
    public static function int_to_chr($int): string
    {
        return self::decimal_to_chr($int);
    }

    /**
     * Converts Integer to hexadecimal U+xxxx code point representation.
     *
     * INFO: opposite to UTF8::hex_to_int()
     *
     * @param int    $int    <p>The integer to be converted to hexadecimal code point.</p>
     * @param string $prefix [optional]
     *
     * @return string the code point, or empty string on failure
     */
    public static function int_to_hex(int $int, string $prefix = 'U+'): string
    {
        $hex = \dechex($int);

        $hex = (\strlen($hex) < 4 ? \substr('0000' . $hex, -4) : $hex);

        return $prefix . $hex . '';
    }

    /**
     * Checks whether intl-char is available on the server.
     *
     * @return bool
     *              <strong>true</strong> if available, <strong>false</strong> otherwise
     */
    public static function intlChar_loaded(): bool
    {
        return \class_exists('IntlChar');
    }

    /**
     * Checks whether intl is available on the server.
     *
     * @return bool
     *              <strong>true</strong> if available, <strong>false</strong> otherwise
     */
    public static function intl_loaded(): bool
    {
        return \extension_loaded('intl');
    }

    /**
     * alias for "UTF8::is_ascii()"
     *
     * @param string $str
     *
     * @return bool
     *
     * @see UTF8::is_ascii()
     * @deprecated <p>please use "UTF8::is_ascii()"</p>
     */
    public static function isAscii(string $str): bool
    {
        return ASCII::is_ascii($str);
    }

    /**
     * alias for "UTF8::is_base64()"
     *
     * @param string $str
     *
     * @return bool
     *
     * @see UTF8::is_base64()
     * @deprecated <p>please use "UTF8::is_base64()"</p>
     */
    public static function isBase64($str): bool
    {
        return self::is_base64($str);
    }

    /**
     * alias for "UTF8::is_binary()"
     *
     * @param mixed $str
     * @param bool  $strict
     *
     * @return bool
     *
     * @see UTF8::is_binary()
     * @deprecated <p>please use "UTF8::is_binary()"</p>
     */
    public static function isBinary($str, $strict = false): bool
    {
        return self::is_binary($str, $strict);
    }

    /**
     * alias for "UTF8::is_bom()"
     *
     * @param string $utf8_chr
     *
     * @return bool
     *
     * @see UTF8::is_bom()
     * @deprecated <p>please use "UTF8::is_bom()"</p>
     */
    public static function isBom(string $utf8_chr): bool
    {
        return self::is_bom($utf8_chr);
    }

    /**
     * alias for "UTF8::is_html()"
     *
     * @param string $str
     *
     * @return bool
     *
     * @see UTF8::is_html()
     * @deprecated <p>please use "UTF8::is_html()"</p>
     */
    public static function isHtml(string $str): bool
    {
        return self::is_html($str);
    }

    /**
     * alias for "UTF8::is_json()"
     *
     * @param string $str
     *
     * @return bool
     *
     * @see UTF8::is_json()
     * @deprecated <p>please use "UTF8::is_json()"</p>
     */
    public static function isJson(string $str): bool
    {
        return self::is_json($str);
    }

    /**
     * alias for "UTF8::is_utf16()"
     *
     * @param mixed $str
     *
     * @return false|int
     *                   <strong>false</strong> if is't not UTF16,<br>
     *                   <strong>1</strong> for UTF-16LE,<br>
     *                   <strong>2</strong> for UTF-16BE
     *
     * @see UTF8::is_utf16()
     * @deprecated <p>please use "UTF8::is_utf16()"</p>
     */
    public static function isUtf16($str)
    {
        return self::is_utf16($str);
    }

    /**
     * alias for "UTF8::is_utf32()"
     *
     * @param mixed $str
     *
     * @return false|int
     *                   <strong>false</strong> if is't not UTF16,
     *                   <strong>1</strong> for UTF-32LE,
     *                   <strong>2</strong> for UTF-32BE
     *
     * @see UTF8::is_utf32()
     * @deprecated <p>please use "UTF8::is_utf32()"</p>
     */
    public static function isUtf32($str)
    {
        return self::is_utf32($str);
    }

    /**
     * alias for "UTF8::is_utf8()"
     *
     * @param string $str
     * @param bool   $strict
     *
     * @return bool
     *
     * @see UTF8::is_utf8()
     * @deprecated <p>please use "UTF8::is_utf8()"</p>
     */
    public static function isUtf8($str, $strict = false): bool
    {
        return self::is_utf8($str, $strict);
    }

    /**
     * Returns true if the string contains only alphabetic chars, false otherwise.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <p>Whether or not $str contains only alphabetic chars.</p>
     */
    public static function is_alpha(string $str): bool
    {
        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return \mb_ereg_match('^[[:alpha:]]*$', $str);
        }

        return self::str_matches_pattern($str, '^[[:alpha:]]*$');
    }

    /**
     * Returns true if the string contains only alphabetic and numeric chars, false otherwise.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <p>Whether or not $str contains only alphanumeric chars.</p>
     */
    public static function is_alphanumeric(string $str): bool
    {
        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return \mb_ereg_match('^[[:alnum:]]*$', $str);
        }

        return self::str_matches_pattern($str, '^[[:alnum:]]*$');
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
        return ASCII::is_ascii($str);
    }

    /**
     * Returns true if the string is base64 encoded, false otherwise.
     *
     * @param mixed|string $str                   <p>The input string.</p>
     * @param bool         $empty_string_is_valid [optional] <p>Is an empty string valid base64 or not?</p>
     *
     * @return bool whether or not $str is base64 encoded
     */
    public static function is_base64($str, $empty_string_is_valid = false): bool
    {
        if (
            $empty_string_is_valid === false
            &&
            $str === ''
        ) {
            return false;
        }

        /**
         * @psalm-suppress RedundantConditionGivenDocblockType
         */
        if (\is_string($str) === false) {
            return false;
        }

        $base64String = \base64_decode($str, true);

        return $base64String !== false && \base64_encode($base64String) === $str;
    }

    /**
     * Check if the input is binary... (is look like a hack).
     *
     * @param mixed $input
     * @param bool  $strict
     *
     * @return bool
     */
    public static function is_binary($input, bool $strict = false): bool
    {
        $input = (string) $input;
        if ($input === '') {
            return false;
        }

        if (\preg_match('~^[01]+$~', $input)) {
            return true;
        }

        $ext = self::get_file_type($input);
        if ($ext['type'] === 'binary') {
            return true;
        }

        $test_length = \strlen($input);
        $test_null_counting = \substr_count($input, "\x0", 0, $test_length);
        if (($test_null_counting / $test_length) > 0.25) {
            return true;
        }

        if ($strict === true) {
            if (self::$SUPPORT['finfo'] === false) {
                throw new \RuntimeException('ext-fileinfo: is not installed');
            }

            /** @noinspection PhpComposerExtensionStubsInspection */
            $finfo_encoding = (new \finfo(\FILEINFO_MIME_ENCODING))->buffer($input);
            if ($finfo_encoding && $finfo_encoding === 'binary') {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the file is binary.
     *
     * @param string $file
     *
     * @return bool
     */
    public static function is_binary_file($file): bool
    {
        // init
        $block = '';

        $fp = \fopen($file, 'rb');
        if (\is_resource($fp)) {
            $block = \fread($fp, 512);
            \fclose($fp);
        }

        if ($block === '') {
            return false;
        }

        return self::is_binary($block, true);
    }

    /**
     * Returns true if the string contains only whitespace chars, false otherwise.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <p>Whether or not $str contains only whitespace characters.</p>
     */
    public static function is_blank(string $str): bool
    {
        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return \mb_ereg_match('^[[:space:]]*$', $str);
        }

        return self::str_matches_pattern($str, '^[[:space:]]*$');
    }

    /**
     * Checks if the given string is equal to any "Byte Order Mark".
     *
     * WARNING: Use "UTF8::string_has_bom()" if you will check BOM in a string.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <p><strong>true</strong> if the $utf8_chr is Byte Order Mark, <strong>false</strong> otherwise.</p>
     */
    public static function is_bom($str): bool
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach (self::$BOM as $bom_string => &$bom_byte_length) {
            if ($str === $bom_string) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the string is considered to be empty.
     *
     * A variable is considered empty if it does not exist or if its value equals FALSE.
     * empty() does not generate a warning if the variable does not exist.
     *
     * @param mixed $str
     *
     * @return bool whether or not $str is empty()
     */
    public static function is_empty($str): bool
    {
        return empty($str);
    }

    /**
     * Returns true if the string contains only hexadecimal chars, false otherwise.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <p>Whether or not $str contains only hexadecimal chars.</p>
     */
    public static function is_hexadecimal(string $str): bool
    {
        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return \mb_ereg_match('^[[:xdigit:]]*$', $str);
        }

        return self::str_matches_pattern($str, '^[[:xdigit:]]*$');
    }

    /**
     * Check if the string contains any HTML tags.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <p>Whether or not $str contains html elements.</p>
     */
    public static function is_html(string $str): bool
    {
        if ($str === '') {
            return false;
        }

        // init
        $matches = [];

        $str = self::emoji_encode($str); // hack for emoji support :/

        \preg_match("/<\\/?\\w+(?:(?:\\s+\\w+(?:\\s*=\\s*(?:\".*?\"|'.*?'|[^'\">\\s]+))?)*\\s*|\\s*)\\/?>/u", $str, $matches);

        return $matches !== [];
    }

    /**
     * Try to check if "$str" is a JSON-string.
     *
     * @param string $str                                    <p>The input string.</p>
     * @param bool   $only_array_or_object_results_are_valid [optional] <p>Only array and objects are valid json results.</p>
     *
     * @return bool
     *              <p>Whether or not the $str is in JSON format.</p>
     */
    public static function is_json(
        string $str,
        $only_array_or_object_results_are_valid = true
    ): bool {
        if ($str === '') {
            return false;
        }

        if (self::$SUPPORT['json'] === false) {
            throw new \RuntimeException('ext-json: is not installed');
        }

        $json = self::json_decode($str);
        if ($json === null && \strtoupper($str) !== 'NULL') {
            return false;
        }

        if (
            $only_array_or_object_results_are_valid === true
            &&
            \is_object($json) === false
            &&
            \is_array($json) === false
        ) {
            return false;
        }

        /** @noinspection PhpComposerExtensionStubsInspection */
        return \json_last_error() === \JSON_ERROR_NONE;
    }

    /**
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <p>Whether or not $str contains only lowercase chars.</p>
     */
    public static function is_lowercase(string $str): bool
    {
        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return \mb_ereg_match('^[[:lower:]]*$', $str);
        }

        return self::str_matches_pattern($str, '^[[:lower:]]*$');
    }

    /**
     * Returns true if the string is serialized, false otherwise.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <p>Whether or not $str is serialized.</p>
     */
    public static function is_serialized(string $str): bool
    {
        if ($str === '') {
            return false;
        }

        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        /** @noinspection UnserializeExploitsInspection */
        return $str === 'b:0;'
               ||
               @\unserialize($str) !== false;
    }

    /**
     * Returns true if the string contains only lower case chars, false
     * otherwise.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <p>Whether or not $str contains only lower case characters.</p>
     */
    public static function is_uppercase(string $str): bool
    {
        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            return \mb_ereg_match('^[[:upper:]]*$', $str);
        }

        return self::str_matches_pattern($str, '^[[:upper:]]*$');
    }

    /**
     * Check if the string is UTF-16.
     *
     * @param mixed $str                       <p>The input string.</p>
     * @param bool  $check_if_string_is_binary
     *
     * @return false|int
     *                   <strong>false</strong> if is't not UTF-16,<br>
     *                   <strong>1</strong> for UTF-16LE,<br>
     *                   <strong>2</strong> for UTF-16BE
     */
    public static function is_utf16($str, $check_if_string_is_binary = true)
    {
        // init
        $str = (string) $str;
        $str_chars = [];

        if (
            $check_if_string_is_binary === true
            &&
            self::is_binary($str, true) === false
        ) {
            return false;
        }

        if (self::$SUPPORT['mbstring'] === false) {
            \trigger_error('UTF8::is_utf16() without mbstring may did not work correctly', \E_USER_WARNING);
        }

        $str = self::remove_bom($str);

        $maybe_utf16le = 0;
        $test = \mb_convert_encoding($str, 'UTF-8', 'UTF-16LE');
        if ($test) {
            $test2 = \mb_convert_encoding($test, 'UTF-16LE', 'UTF-8');
            $test3 = \mb_convert_encoding($test2, 'UTF-8', 'UTF-16LE');
            if ($test3 === $test) {
                /**
                 * @psalm-suppress RedundantCondition
                 */
                if ($str_chars === []) {
                    $str_chars = self::count_chars($str, true, false);
                }
                foreach (self::count_chars($test3) as $test3char => &$test3charEmpty) {
                    if (\in_array($test3char, $str_chars, true) === true) {
                        ++$maybe_utf16le;
                    }
                }
                unset($test3charEmpty);
            }
        }

        $maybe_utf16be = 0;
        $test = \mb_convert_encoding($str, 'UTF-8', 'UTF-16BE');
        if ($test) {
            $test2 = \mb_convert_encoding($test, 'UTF-16BE', 'UTF-8');
            $test3 = \mb_convert_encoding($test2, 'UTF-8', 'UTF-16BE');
            if ($test3 === $test) {
                if ($str_chars === []) {
                    $str_chars = self::count_chars($str, true, false);
                }
                foreach (self::count_chars($test3) as $test3char => &$test3charEmpty) {
                    if (\in_array($test3char, $str_chars, true) === true) {
                        ++$maybe_utf16be;
                    }
                }
                unset($test3charEmpty);
            }
        }

        if ($maybe_utf16be !== $maybe_utf16le) {
            if ($maybe_utf16le > $maybe_utf16be) {
                return 1;
            }

            return 2;
        }

        return false;
    }

    /**
     * Check if the string is UTF-32.
     *
     * @param mixed $str                       <p>The input string.</p>
     * @param bool  $check_if_string_is_binary
     *
     * @return false|int
     *                   <strong>false</strong> if is't not UTF-32,<br>
     *                   <strong>1</strong> for UTF-32LE,<br>
     *                   <strong>2</strong> for UTF-32BE
     */
    public static function is_utf32($str, $check_if_string_is_binary = true)
    {
        // init
        $str = (string) $str;
        $str_chars = [];

        if (
            $check_if_string_is_binary === true
            &&
            self::is_binary($str, true) === false
        ) {
            return false;
        }

        if (self::$SUPPORT['mbstring'] === false) {
            \trigger_error('UTF8::is_utf32() without mbstring may did not work correctly', \E_USER_WARNING);
        }

        $str = self::remove_bom($str);

        $maybe_utf32le = 0;
        $test = \mb_convert_encoding($str, 'UTF-8', 'UTF-32LE');
        if ($test) {
            $test2 = \mb_convert_encoding($test, 'UTF-32LE', 'UTF-8');
            $test3 = \mb_convert_encoding($test2, 'UTF-8', 'UTF-32LE');
            if ($test3 === $test) {
                /**
                 * @psalm-suppress RedundantCondition
                 */
                if ($str_chars === []) {
                    $str_chars = self::count_chars($str, true, false);
                }
                foreach (self::count_chars($test3) as $test3char => &$test3charEmpty) {
                    if (\in_array($test3char, $str_chars, true) === true) {
                        ++$maybe_utf32le;
                    }
                }
                unset($test3charEmpty);
            }
        }

        $maybe_utf32be = 0;
        $test = \mb_convert_encoding($str, 'UTF-8', 'UTF-32BE');
        if ($test) {
            $test2 = \mb_convert_encoding($test, 'UTF-32BE', 'UTF-8');
            $test3 = \mb_convert_encoding($test2, 'UTF-8', 'UTF-32BE');
            if ($test3 === $test) {
                if ($str_chars === []) {
                    $str_chars = self::count_chars($str, true, false);
                }
                foreach (self::count_chars($test3) as $test3char => &$test3charEmpty) {
                    if (\in_array($test3char, $str_chars, true) === true) {
                        ++$maybe_utf32be;
                    }
                }
                unset($test3charEmpty);
            }
        }

        if ($maybe_utf32be !== $maybe_utf32le) {
            if ($maybe_utf32le > $maybe_utf32be) {
                return 1;
            }

            return 2;
        }

        return false;
    }

    /**
     * Checks whether the passed input contains only byte sequences that appear valid UTF-8.
     *
     * @param int|string|string[]|null $str    <p>The input to be checked.</p>
     * @param bool                     $strict <p>Check also if the string is not UTF-16 or UTF-32.</p>
     *
     * @return bool
     */
    public static function is_utf8($str, bool $strict = false): bool
    {
        if (\is_array($str) === true) {
            foreach ($str as &$v) {
                if (self::is_utf8($v, $strict) === false) {
                    return false;
                }
            }

            return true;
        }

        return self::is_utf8_string((string) $str, $strict);
    }

    /**
     * (PHP 5 &gt;= 5.2.0, PECL json &gt;= 1.2.0)<br/>
     * Decodes a JSON string
     *
     * @see http://php.net/manual/en/function.json-decode.php
     *
     * @param string $json    <p>
     *                        The <i>json</i> string being decoded.
     *                        </p>
     *                        <p>
     *                        This function only works with UTF-8 encoded strings.
     *                        </p>
     *                        <p>PHP implements a superset of
     *                        JSON - it will also encode and decode scalar types and <b>NULL</b>. The JSON standard
     *                        only supports these values when they are nested inside an array or an object.
     *                        </p>
     * @param bool   $assoc   [optional] <p>
     *                        When <b>TRUE</b>, returned objects will be converted into
     *                        associative arrays.
     *                        </p>
     * @param int    $depth   [optional] <p>
     *                        User specified recursion depth.
     *                        </p>
     * @param int    $options [optional] <p>
     *                        Bitmask of JSON decode options. Currently only
     *                        <b>JSON_BIGINT_AS_STRING</b>
     *                        is supported (default is to cast large integers as floats)
     *                        </p>
     *
     * @return mixed
     *               The value encoded in <i>json</i> in appropriate PHP type. Values true, false and
     *               null (case-insensitive) are returned as <b>TRUE</b>, <b>FALSE</b> and <b>NULL</b> respectively.
     *               <b>NULL</b> is returned if the <i>json</i> cannot be decoded or if the encoded data
     *               is deeper than the recursion limit.
     */
    public static function json_decode(
        string $json,
        bool $assoc = false,
        int $depth = 512,
        int $options = 0
    ) {
        $json = self::filter($json);

        if (self::$SUPPORT['json'] === false) {
            throw new \RuntimeException('ext-json: is not installed');
        }

        /** @noinspection PhpComposerExtensionStubsInspection */
        return \json_decode($json, $assoc, $depth, $options);
    }

    /**
     * (PHP 5 &gt;= 5.2.0, PECL json &gt;= 1.2.0)<br/>
     * Returns the JSON representation of a value.
     *
     * @see http://php.net/manual/en/function.json-encode.php
     *
     * @param mixed $value   <p>
     *                       The <i>value</i> being encoded. Can be any type except
     *                       a resource.
     *                       </p>
     *                       <p>
     *                       All string data must be UTF-8 encoded.
     *                       </p>
     *                       <p>PHP implements a superset of
     *                       JSON - it will also encode and decode scalar types and <b>NULL</b>. The JSON standard
     *                       only supports these values when they are nested inside an array or an object.
     *                       </p>
     * @param int   $options [optional] <p>
     *                       Bitmask consisting of <b>JSON_HEX_QUOT</b>,
     *                       <b>JSON_HEX_TAG</b>,
     *                       <b>JSON_HEX_AMP</b>,
     *                       <b>JSON_HEX_APOS</b>,
     *                       <b>JSON_NUMERIC_CHECK</b>,
     *                       <b>JSON_PRETTY_PRINT</b>,
     *                       <b>JSON_UNESCAPED_SLASHES</b>,
     *                       <b>JSON_FORCE_OBJECT</b>,
     *                       <b>JSON_UNESCAPED_UNICODE</b>. The behaviour of these
     *                       constants is described on
     *                       the JSON constants page.
     *                       </p>
     * @param int   $depth   [optional] <p>
     *                       Set the maximum depth. Must be greater than zero.
     *                       </p>
     *
     * @return false|string
     *                      A JSON encoded <strong>string</strong> on success or<br>
     *                      <strong>FALSE</strong> on failure
     */
    public static function json_encode($value, int $options = 0, int $depth = 512)
    {
        $value = self::filter($value);

        if (self::$SUPPORT['json'] === false) {
            throw new \RuntimeException('ext-json: is not installed');
        }

        /** @noinspection PhpComposerExtensionStubsInspection */
        return \json_encode($value, $options, $depth);
    }

    /**
     * Checks whether JSON is available on the server.
     *
     * @return bool
     *              <strong>true</strong> if available, <strong>false</strong> otherwise
     */
    public static function json_loaded(): bool
    {
        return \function_exists('json_decode');
    }

    /**
     * Makes string's first char lowercase.
     *
     * @param string      $str                           <p>The input string</p>
     * @param string      $encoding                      [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool        $clean_utf8                    [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string|null $lang                          [optional] <p>Set the language for special cases: az, el, lt, tr</p>
     * @param bool        $try_to_keep_the_string_length [optional] <p>true === try to keep the string length: e.g. ẞ -> ß</p>
     *
     * @return string the resulting string
     */
    public static function lcfirst(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        if ($clean_utf8 === true) {
            $str = self::clean($str);
        }

        $use_mb_functions = ($lang === null && $try_to_keep_the_string_length === false);

        if ($encoding === 'UTF-8') {
            $str_part_two = (string) \mb_substr($str, 1);

            if ($use_mb_functions === true) {
                $str_part_one = \mb_strtolower(
                    (string) \mb_substr($str, 0, 1)
                );
            } else {
                $str_part_one = self::strtolower(
                    (string) \mb_substr($str, 0, 1),
                    $encoding,
                    false,
                    $lang,
                    $try_to_keep_the_string_length
                );
            }
        } else {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            $str_part_two = (string) self::substr($str, 1, null, $encoding);

            $str_part_one = self::strtolower(
                (string) self::substr($str, 0, 1, $encoding),
                $encoding,
                false,
                $lang,
                $try_to_keep_the_string_length
            );
        }

        return $str_part_one . $str_part_two;
    }

    /**
     * alias for "UTF8::lcfirst()"
     *
     * @param string      $str
     * @param string      $encoding
     * @param bool        $clean_utf8
     * @param string|null $lang
     * @param bool        $try_to_keep_the_string_length
     *
     * @return string
     *
     * @see UTF8::lcfirst()
     * @deprecated <p>please use "UTF8::lcfirst()"</p>
     */
    public static function lcword(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        return self::lcfirst(
            $str,
            $encoding,
            $clean_utf8,
            $lang,
            $try_to_keep_the_string_length
        );
    }

    /**
     * Lowercase for all words in the string.
     *
     * @param string      $str                           <p>The input string.</p>
     * @param string[]    $exceptions                    [optional] <p>Exclusion for some words.</p>
     * @param string      $char_list                     [optional] <p>Additional chars that contains to words and do not start
     *                                                   a new word.</p>
     * @param string      $encoding                      [optional] <p>Set the charset.</p>
     * @param bool        $clean_utf8                    [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string|null $lang                          [optional] <p>Set the language for special cases: az, el, lt, tr</p>
     * @param bool        $try_to_keep_the_string_length [optional] <p>true === try to keep the string length: e.g. ẞ -> ß</p>
     *
     * @return string
     */
    public static function lcwords(
        string $str,
        array $exceptions = [],
        string $char_list = '',
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        if (!$str) {
            return '';
        }

        $words = self::str_to_words($str, $char_list);
        $use_exceptions = $exceptions !== [];

        $words_str = '';
        foreach ($words as &$word) {
            if (!$word) {
                continue;
            }

            if (
                $use_exceptions === false
                ||
                !\in_array($word, $exceptions, true)
            ) {
                $words_str .= self::lcfirst($word, $encoding, $clean_utf8, $lang, $try_to_keep_the_string_length);
            } else {
                $words_str .= $word;
            }
        }

        return $words_str;
    }

    /**
     * alias for "UTF8::lcfirst()"
     *
     * @param string      $str
     * @param string      $encoding
     * @param bool        $clean_utf8
     * @param string|null $lang
     * @param bool        $try_to_keep_the_string_length
     *
     * @return string
     *
     * @see UTF8::lcfirst()
     * @deprecated <p>please use "UTF8::lcfirst()"</p>
     */
    public static function lowerCaseFirst(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        return self::lcfirst(
            $str,
            $encoding,
            $clean_utf8,
            $lang,
            $try_to_keep_the_string_length
        );
    }

    /**
     * Strip whitespace or other characters from the beginning of a UTF-8 string.
     *
     * @param string      $str   <p>The string to be trimmed</p>
     * @param string|null $chars <p>Optional characters to be stripped</p>
     *
     * @return string the string with unwanted characters stripped from the left
     */
    public static function ltrim(string $str = '', string $chars = null): string
    {
        if ($str === '') {
            return '';
        }

        if (self::$SUPPORT['mbstring'] === true) {
            if ($chars) {
                /** @noinspection PregQuoteUsageInspection */
                $chars = \preg_quote($chars);
                $pattern = "^[${chars}]+";
            } else {
                $pattern = '^[\\s]+';
            }

            /** @noinspection PhpComposerExtensionStubsInspection */
            return (string) \mb_ereg_replace($pattern, '', $str);
        }

        if ($chars) {
            $chars = \preg_quote($chars, '/');
            $pattern = "^[${chars}]+";
        } else {
            $pattern = '^[\\s]+';
        }

        return self::regex_replace($str, $pattern, '', '', '/');
    }

    /**
     * Returns the UTF-8 character with the maximum code point in the given data.
     *
     * @param array<string>|string $arg <p>A UTF-8 encoded string or an array of such strings.</p>
     *
     * @return string|null the character with the highest code point than others, returns null on failure or empty input
     */
    public static function max($arg)
    {
        if (\is_array($arg) === true) {
            $arg = \implode('', $arg);
        }

        $codepoints = self::codepoints($arg, false);
        if ($codepoints === []) {
            return null;
        }

        $codepoint_max = \max($codepoints);

        return self::chr($codepoint_max);
    }

    /**
     * Calculates and returns the maximum number of bytes taken by any
     * UTF-8 encoded character in the given string.
     *
     * @param string $str <p>The original Unicode string.</p>
     *
     * @return int
     *             <p>Max byte lengths of the given chars.</p>
     */
    public static function max_chr_width(string $str): int
    {
        $bytes = self::chr_size_list($str);
        if ($bytes !== []) {
            return (int) \max($bytes);
        }

        return 0;
    }

    /**
     * Checks whether mbstring is available on the server.
     *
     * @return bool
     *              <strong>true</strong> if available, <strong>false</strong> otherwise
     */
    public static function mbstring_loaded(): bool
    {
        return \extension_loaded('mbstring');
    }

    /**
     * Returns the UTF-8 character with the minimum code point in the given data.
     *
     * @param mixed $arg <strong>A UTF-8 encoded string or an array of such strings.</strong>
     *
     * @return string|null the character with the lowest code point than others, returns null on failure or empty input
     */
    public static function min($arg)
    {
        if (\is_array($arg) === true) {
            $arg = \implode('', $arg);
        }

        $codepoints = self::codepoints($arg, false);
        if ($codepoints === []) {
            return null;
        }

        $codepoint_min = \min($codepoints);

        return self::chr($codepoint_min);
    }

    /**
     * alias for "UTF8::normalize_encoding()"
     *
     * @param mixed $encoding
     * @param mixed $fallback
     *
     * @return mixed
     *
     * @see UTF8::normalize_encoding()
     * @deprecated <p>please use "UTF8::normalize_encoding()"</p>
     */
    public static function normalizeEncoding($encoding, $fallback = '')
    {
        return self::normalize_encoding($encoding, $fallback);
    }

    /**
     * Normalize the encoding-"name" input.
     *
     * @param mixed $encoding <p>e.g.: ISO, UTF8, WINDOWS-1251 etc.</p>
     * @param mixed $fallback <p>e.g.: UTF-8</p>
     *
     * @return mixed e.g.: ISO-8859-1, UTF-8, WINDOWS-1251 etc.<br>Will return a empty string as fallback (by default)
     */
    public static function normalize_encoding($encoding, $fallback = '')
    {
        static $STATIC_NORMALIZE_ENCODING_CACHE = [];

        // init
        $encoding = (string) $encoding;

        if (!$encoding) {
            return $fallback;
        }

        if (
            $encoding === 'UTF-8'
            ||
            $encoding === 'UTF8'
        ) {
            return 'UTF-8';
        }

        if (
            $encoding === '8BIT'
            ||
            $encoding === 'BINARY'
        ) {
            return 'CP850';
        }

        if (
            $encoding === 'HTML'
            ||
            $encoding === 'HTML-ENTITIES'
        ) {
            return 'HTML-ENTITIES';
        }

        if (
            $encoding === 'ISO'
            ||
            $encoding === 'ISO-8859-1'
        ) {
            return 'ISO-8859-1';
        }

        if (
            $encoding === '1' // only a fallback, for non "strict_types" usage ...
            ||
            $encoding === '0' // only a fallback, for non "strict_types" usage ...
        ) {
            return $fallback;
        }

        if (isset($STATIC_NORMALIZE_ENCODING_CACHE[$encoding])) {
            return $STATIC_NORMALIZE_ENCODING_CACHE[$encoding];
        }

        if (self::$ENCODINGS === null) {
            self::$ENCODINGS = self::getData('encodings');
        }

        if (\in_array($encoding, self::$ENCODINGS, true)) {
            $STATIC_NORMALIZE_ENCODING_CACHE[$encoding] = $encoding;

            return $encoding;
        }

        $encoding_original = $encoding;
        $encoding = \strtoupper($encoding);
        $encoding_upper_helper = (string) \preg_replace('/[^a-zA-Z0-9]/u', '', $encoding);

        $equivalences = [
            'ISO8859'     => 'ISO-8859-1',
            'ISO88591'    => 'ISO-8859-1',
            'ISO'         => 'ISO-8859-1',
            'LATIN'       => 'ISO-8859-1',
            'LATIN1'      => 'ISO-8859-1', // Western European
            'ISO88592'    => 'ISO-8859-2',
            'LATIN2'      => 'ISO-8859-2', // Central European
            'ISO88593'    => 'ISO-8859-3',
            'LATIN3'      => 'ISO-8859-3', // Southern European
            'ISO88594'    => 'ISO-8859-4',
            'LATIN4'      => 'ISO-8859-4', // Northern European
            'ISO88595'    => 'ISO-8859-5',
            'ISO88596'    => 'ISO-8859-6', // Greek
            'ISO88597'    => 'ISO-8859-7',
            'ISO88598'    => 'ISO-8859-8', // Hebrew
            'ISO88599'    => 'ISO-8859-9',
            'LATIN5'      => 'ISO-8859-9', // Turkish
            'ISO885911'   => 'ISO-8859-11',
            'TIS620'      => 'ISO-8859-11', // Thai
            'ISO885910'   => 'ISO-8859-10',
            'LATIN6'      => 'ISO-8859-10', // Nordic
            'ISO885913'   => 'ISO-8859-13',
            'LATIN7'      => 'ISO-8859-13', // Baltic
            'ISO885914'   => 'ISO-8859-14',
            'LATIN8'      => 'ISO-8859-14', // Celtic
            'ISO885915'   => 'ISO-8859-15',
            'LATIN9'      => 'ISO-8859-15', // Western European (with some extra chars e.g. €)
            'ISO885916'   => 'ISO-8859-16',
            'LATIN10'     => 'ISO-8859-16', // Southeast European
            'CP1250'      => 'WINDOWS-1250',
            'WIN1250'     => 'WINDOWS-1250',
            'WINDOWS1250' => 'WINDOWS-1250',
            'CP1251'      => 'WINDOWS-1251',
            'WIN1251'     => 'WINDOWS-1251',
            'WINDOWS1251' => 'WINDOWS-1251',
            'CP1252'      => 'WINDOWS-1252',
            'WIN1252'     => 'WINDOWS-1252',
            'WINDOWS1252' => 'WINDOWS-1252',
            'CP1253'      => 'WINDOWS-1253',
            'WIN1253'     => 'WINDOWS-1253',
            'WINDOWS1253' => 'WINDOWS-1253',
            'CP1254'      => 'WINDOWS-1254',
            'WIN1254'     => 'WINDOWS-1254',
            'WINDOWS1254' => 'WINDOWS-1254',
            'CP1255'      => 'WINDOWS-1255',
            'WIN1255'     => 'WINDOWS-1255',
            'WINDOWS1255' => 'WINDOWS-1255',
            'CP1256'      => 'WINDOWS-1256',
            'WIN1256'     => 'WINDOWS-1256',
            'WINDOWS1256' => 'WINDOWS-1256',
            'CP1257'      => 'WINDOWS-1257',
            'WIN1257'     => 'WINDOWS-1257',
            'WINDOWS1257' => 'WINDOWS-1257',
            'CP1258'      => 'WINDOWS-1258',
            'WIN1258'     => 'WINDOWS-1258',
            'WINDOWS1258' => 'WINDOWS-1258',
            'UTF16'       => 'UTF-16',
            'UTF32'       => 'UTF-32',
            'UTF8'        => 'UTF-8',
            'UTF'         => 'UTF-8',
            'UTF7'        => 'UTF-7',
            '8BIT'        => 'CP850',
            'BINARY'      => 'CP850',
        ];

        if (!empty($equivalences[$encoding_upper_helper])) {
            $encoding = $equivalences[$encoding_upper_helper];
        }

        $STATIC_NORMALIZE_ENCODING_CACHE[$encoding_original] = $encoding;

        return $encoding;
    }

    /**
     * Standardize line ending to unix-like.
     *
     * @param string $str      <p>The input string.</p>
     * @param string $replacer <p>The replacer char e.g. "\n" (Linux) or "\r\n" (Windows). You can also use \PHP_EOL here.</p>
     *
     * @return string
     *                <p>A string with normalized line ending.</p>
     */
    public static function normalize_line_ending(string $str, $replacer = "\n"): string
    {
        return \str_replace(["\r\n", "\r", "\n"], $replacer, $str);
    }

    /**
     * Normalize some MS Word special characters.
     *
     * @param string $str <p>The string to be normalized.</p>
     *
     * @return string
     *                <p>A string with normalized characters for commonly used chars in Word documents.</p>
     */
    public static function normalize_msword(string $str): string
    {
        return ASCII::normalize_msword($str);
    }

    /**
     * Normalize the whitespace.
     *
     * @param string $str                        <p>The string to be normalized.</p>
     * @param bool   $keep_non_breaking_space    [optional] <p>Set to true, to keep non-breaking-spaces.</p>
     * @param bool   $keep_bidi_unicode_controls [optional] <p>Set to true, to keep non-printable (for the web)
     *                                           bidirectional text chars.</p>
     *
     * @return string
     *                <p>A string with normalized whitespace.</p>
     */
    public static function normalize_whitespace(
        string $str,
        bool $keep_non_breaking_space = false,
        bool $keep_bidi_unicode_controls = false
    ): string {
        return ASCII::normalize_whitespace(
            $str,
            $keep_non_breaking_space,
            $keep_bidi_unicode_controls
        );
    }

    /**
     * Calculates Unicode code point of the given UTF-8 encoded character.
     *
     * INFO: opposite to UTF8::chr()
     *
     * @param string $chr      <p>The character of which to calculate code point.<p/>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return int
     *             <p>Unicode code point of the given character,<br>
     *             0 on invalid UTF-8 byte sequence</p>
     */
    public static function ord($chr, string $encoding = 'UTF-8'): int
    {
        static $CHAR_CACHE = [];

        // init
        $chr = (string) $chr;

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        $cache_key = $chr . $encoding;
        if (isset($CHAR_CACHE[$cache_key]) === true) {
            return $CHAR_CACHE[$cache_key];
        }

        // check again, if it's still not UTF-8
        if ($encoding !== 'UTF-8') {
            $chr = self::encode($encoding, $chr);
        }

        if (self::$ORD === null) {
            self::$ORD = self::getData('ord');
        }

        if (isset(self::$ORD[$chr])) {
            return $CHAR_CACHE[$cache_key] = self::$ORD[$chr];
        }

        //
        // fallback via "IntlChar"
        //

        if (self::$SUPPORT['intlChar'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            $code = \IntlChar::ord($chr);
            if ($code) {
                return $CHAR_CACHE[$cache_key] = $code;
            }
        }

        //
        // fallback via vanilla php
        //

        /** @noinspection CallableParameterUseCaseInTypeContextInspection - FP */
        $chr = \unpack('C*', (string) \substr($chr, 0, 4));
        /** @noinspection OffsetOperationsInspection */
        $code = $chr ? $chr[1] : 0;

        /** @noinspection OffsetOperationsInspection */
        if ($code >= 0xF0 && isset($chr[4])) {
            /** @noinspection UnnecessaryCastingInspection */
            /** @noinspection OffsetOperationsInspection */
            return $CHAR_CACHE[$cache_key] = (int) ((($code - 0xF0) << 18) + (($chr[2] - 0x80) << 12) + (($chr[3] - 0x80) << 6) + $chr[4] - 0x80);
        }

        /** @noinspection OffsetOperationsInspection */
        if ($code >= 0xE0 && isset($chr[3])) {
            /** @noinspection UnnecessaryCastingInspection */
            /** @noinspection OffsetOperationsInspection */
            return $CHAR_CACHE[$cache_key] = (int) ((($code - 0xE0) << 12) + (($chr[2] - 0x80) << 6) + $chr[3] - 0x80);
        }

        /** @noinspection OffsetOperationsInspection */
        if ($code >= 0xC0 && isset($chr[2])) {
            /** @noinspection UnnecessaryCastingInspection */
            /** @noinspection OffsetOperationsInspection */
            return $CHAR_CACHE[$cache_key] = (int) ((($code - 0xC0) << 6) + $chr[2] - 0x80);
        }

        return $CHAR_CACHE[$cache_key] = $code;
    }

    /**
     * Parses the string into an array (into the the second parameter).
     *
     * WARNING: Unlike "parse_str()", this method does not (re-)place variables in the current scope,
     *          if the second parameter is not set!
     *
     * @see http://php.net/manual/en/function.parse-str.php
     *
     * @param string $str        <p>The input string.</p>
     * @param array  $result     <p>The result will be returned into this reference parameter.</p>
     * @param bool   $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return bool
     *              <p>Will return <strong>false</strong> if php can't parse the string and we haven't any $result.</p>
     */
    public static function parse_str(string $str, &$result, bool $clean_utf8 = false): bool
    {
        if ($clean_utf8 === true) {
            $str = self::clean($str);
        }

        if (self::$SUPPORT['mbstring'] === true) {
            $return = \mb_parse_str($str, $result);

            return $return !== false && $result !== [];
        }

        /** @noinspection PhpVoidFunctionResultUsedInspection */
        \parse_str($str, $result);

        return $result !== [];
    }

    /**
     * Checks if \u modifier is available that enables Unicode support in PCRE.
     *
     * @return bool
     *              <p>
     *              <strong>true</strong> if support is available,<br>
     *              <strong>false</strong> otherwise
     *              </p>
     */
    public static function pcre_utf8_support(): bool
    {
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        return (bool) @\preg_match('//u', '');
    }

    /**
     * Create an array containing a range of UTF-8 characters.
     *
     * @param mixed     $var1      <p>Numeric or hexadecimal code points, or a UTF-8 character to start from.</p>
     * @param mixed     $var2      <p>Numeric or hexadecimal code points, or a UTF-8 character to end at.</p>
     * @param bool      $use_ctype <p>use ctype to detect numeric and hexadecimal, otherwise we will use a simple "is_numeric"</p>
     * @param string    $encoding  [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param float|int $step      [optional] <p>
     *                             If a step value is given, it will be used as the
     *                             increment between elements in the sequence. step
     *                             should be given as a positive number. If not specified,
     *                             step will default to 1.
     *                             </p>
     *
     * @return string[]
     */
    public static function range(
        $var1,
        $var2,
        bool $use_ctype = true,
        string $encoding = 'UTF-8',
        $step = 1
    ): array {
        if (!$var1 || !$var2) {
            return [];
        }

        if ($step !== 1) {
            /**
             * @psalm-suppress RedundantConditionGivenDocblockType
             */
            if (!\is_numeric($step)) {
                throw new \InvalidArgumentException('$step need to be a number, type given: ' . \gettype($step));
            }

            /**
             * @psalm-suppress RedundantConditionGivenDocblockType - false-positive from psalm?
             */
            if ($step <= 0) {
                throw new \InvalidArgumentException('$step need to be a positive number, given: ' . $step);
            }
        }

        if ($use_ctype && self::$SUPPORT['ctype'] === false) {
            throw new \RuntimeException('ext-ctype: is not installed');
        }

        $is_digit = false;
        $is_xdigit = false;

        /** @noinspection PhpComposerExtensionStubsInspection */
        if ($use_ctype && \ctype_digit((string) $var1) && \ctype_digit((string) $var2)) {
            $is_digit = true;
            $start = (int) $var1;
        } /** @noinspection PhpComposerExtensionStubsInspection */ elseif ($use_ctype && \ctype_xdigit($var1) && \ctype_xdigit($var2)) {
            $is_xdigit = true;
            $start = (int) self::hex_to_int($var1);
        } elseif (!$use_ctype && \is_numeric($var1)) {
            $start = (int) $var1;
        } else {
            $start = self::ord($var1);
        }

        if (!$start) {
            return [];
        }

        if ($is_digit) {
            $end = (int) $var2;
        } elseif ($is_xdigit) {
            $end = (int) self::hex_to_int($var2);
        } elseif (!$use_ctype && \is_numeric($var2)) {
            $end = (int) $var2;
        } else {
            $end = self::ord($var2);
        }

        if (!$end) {
            return [];
        }

        $array = [];
        foreach (\range($start, $end, $step) as $i) {
            $array[] = (string) self::chr((int) $i, $encoding);
        }

        return $array;
    }

    /**
     * Multi decode HTML entity + fix urlencoded-win1252-chars.
     *
     * e.g:
     * 'test+test'                     => 'test+test'
     * 'D&#252;sseldorf'               => 'Düsseldorf'
     * 'D%FCsseldorf'                  => 'Düsseldorf'
     * 'D&#xFC;sseldorf'               => 'Düsseldorf'
     * 'D%26%23xFC%3Bsseldorf'         => 'Düsseldorf'
     * 'DÃ¼sseldorf'                   => 'Düsseldorf'
     * 'D%C3%BCsseldorf'               => 'Düsseldorf'
     * 'D%C3%83%C2%BCsseldorf'         => 'Düsseldorf'
     * 'D%25C3%2583%25C2%25BCsseldorf' => 'Düsseldorf'
     *
     * @param string $str          <p>The input string.</p>
     * @param bool   $multi_decode <p>Decode as often as possible.</p>
     *
     * @return string
     *                <p>The decoded URL, as a string.</p>
     */
    public static function rawurldecode(string $str, bool $multi_decode = true): string
    {
        if ($str === '') {
            return '';
        }

        if (
            \strpos($str, '&') === false
            &&
            \strpos($str, '%') === false
            &&
            \strpos($str, '+') === false
            &&
            \strpos($str, '\u') === false
        ) {
            return self::fix_simple_utf8($str);
        }

        $str = self::urldecode_unicode_helper($str);

        if ($multi_decode) {
            do {
                $str_compare = $str;

                /**
                 * @psalm-suppress PossiblyInvalidArgument
                 */
                $str = self::fix_simple_utf8(
                    \rawurldecode(
                        self::html_entity_decode(
                            self::to_utf8($str),
                            \ENT_QUOTES | \ENT_HTML5
                        )
                    )
                );
            } while ($str_compare !== $str);
        } else {
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $str = self::fix_simple_utf8(
                \rawurldecode(
                    self::html_entity_decode(
                        self::to_utf8($str),
                        \ENT_QUOTES | \ENT_HTML5
                    )
                )
            );
        }

        return $str;
    }

    /**
     * Replaces all occurrences of $pattern in $str by $replacement.
     *
     * @param string $str         <p>The input string.</p>
     * @param string $pattern     <p>The regular expression pattern.</p>
     * @param string $replacement <p>The string to replace with.</p>
     * @param string $options     [optional] <p>Matching conditions to be used.</p>
     * @param string $delimiter   [optional] <p>Delimiter the the regex. Default: '/'</p>
     *
     * @return string
     */
    public static function regex_replace(
        string $str,
        string $pattern,
        string $replacement,
        string $options = '',
        string $delimiter = '/'
    ): string {
        if ($options === 'msr') {
            $options = 'ms';
        }

        // fallback
        if (!$delimiter) {
            $delimiter = '/';
        }

        return (string) \preg_replace(
            $delimiter . $pattern . $delimiter . 'u' . $options,
            $replacement,
            $str
        );
    }

    /**
     * alias for "UTF8::remove_bom()"
     *
     * @param string $str
     *
     * @return string
     *
     * @see UTF8::remove_bom()
     * @deprecated <p>please use "UTF8::remove_bom()"</p>
     */
    public static function removeBOM(string $str): string
    {
        return self::remove_bom($str);
    }

    /**
     * Remove the BOM from UTF-8 / UTF-16 / UTF-32 strings.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return string
     *                <p>A string without UTF-BOM.</p>
     */
    public static function remove_bom(string $str): string
    {
        if ($str === '') {
            return '';
        }

        $str_length = \strlen($str);
        foreach (self::$BOM as $bom_string => $bom_byte_length) {
            if (\strpos($str, $bom_string, 0) === 0) {
                /** @var false|string $str_tmp - needed for PhpStan (stubs error) */
                $str_tmp = \substr($str, $bom_byte_length, $str_length);
                if ($str_tmp === false) {
                    return '';
                }

                $str_length -= (int) $bom_byte_length;

                $str = (string) $str_tmp;
            }
        }

        return $str;
    }

    /**
     * Removes duplicate occurrences of a string in another string.
     *
     * @param string          $str  <p>The base string.</p>
     * @param string|string[] $what <p>String to search for in the base string.</p>
     *
     * @return string
     *                <p>A string with removed duplicates.</p>
     */
    public static function remove_duplicates(string $str, $what = ' '): string
    {
        if (\is_string($what) === true) {
            $what = [$what];
        }

        /**
         * @psalm-suppress RedundantConditionGivenDocblockType
         */
        if (\is_array($what) === true) {
            foreach ($what as $item) {
                $str = (string) \preg_replace('/(' . \preg_quote($item, '/') . ')+/u', $item, $str);
            }
        }

        return $str;
    }

    /**
     * Remove html via "strip_tags()" from the string.
     *
     * @param string $str            <p>The input string.</p>
     * @param string $allowable_tags [optional] <p>You can use the optional second parameter to specify tags which should
     *                               not be stripped. Default: null
     *                               </p>
     *
     * @return string
     *                <p>A string with without html tags.</p>
     */
    public static function remove_html(string $str, string $allowable_tags = ''): string
    {
        return \strip_tags($str, $allowable_tags);
    }

    /**
     * Remove all breaks [<br> | \r\n | \r | \n | ...] from the string.
     *
     * @param string $str         <p>The input string.</p>
     * @param string $replacement [optional] <p>Default is a empty string.</p>
     *
     * @return string
     *                <p>A string without breaks.</p>
     */
    public static function remove_html_breaks(string $str, string $replacement = ''): string
    {
        return (string) \preg_replace("#/\r\n|\r|\n|<br.*/?>#isU", $replacement, $str);
    }

    /**
     * Remove invisible characters from a string.
     *
     * e.g.: This prevents sandwiching null characters between ascii characters, like Java\0script.
     *
     * copy&past from https://github.com/bcit-ci/CodeIgniter/blob/develop/system/core/Common.php
     *
     * @param string $str         <p>The input string.</p>
     * @param bool   $url_encoded [optional] <p>
     *                            Try to remove url encoded control character.
     *                            WARNING: maybe contains false-positives e.g. aa%0Baa -> aaaa.
     *                            <br>
     *                            Default: false
     *                            </p>
     * @param string $replacement [optional] <p>The replacement character.</p>
     *
     * @return string
     *                <p>A string without invisible chars.</p>
     */
    public static function remove_invisible_characters(
        string $str,
        bool $url_encoded = false,
        string $replacement = ''
    ): string {
        return ASCII::remove_invisible_characters(
            $str,
            $url_encoded,
            $replacement
        );
    }

    /**
     * Returns a new string with the prefix $substring removed, if present.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $substring <p>The prefix to remove.</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     *                <p>A string without the prefix $substring.</p>
     */
    public static function remove_left(
        string $str,
        string $substring,
        string $encoding = 'UTF-8'
    ): string {
        if ($substring && \strpos($str, $substring) === 0) {
            if ($encoding === 'UTF-8') {
                return (string) \mb_substr(
                    $str,
                    (int) \mb_strlen($substring)
                );
            }

            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            return (string) self::substr(
                $str,
                (int) self::strlen($substring, $encoding),
                null,
                $encoding
            );
        }

        return $str;
    }

    /**
     * Returns a new string with the suffix $substring removed, if present.
     *
     * @param string $str
     * @param string $substring <p>The suffix to remove.</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     *                <p>A string having a $str without the suffix $substring.</p>
     */
    public static function remove_right(
        string $str,
        string $substring,
        string $encoding = 'UTF-8'
    ): string {
        if ($substring && \substr($str, -\strlen($substring)) === $substring) {
            if ($encoding === 'UTF-8') {
                return (string) \mb_substr(
                    $str,
                    0,
                    (int) \mb_strlen($str) - (int) \mb_strlen($substring)
                );
            }

            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            return (string) self::substr(
                $str,
                0,
                (int) self::strlen($str, $encoding) - (int) self::strlen($substring, $encoding),
                $encoding
            );
        }

        return $str;
    }

    /**
     * Replaces all occurrences of $search in $str by $replacement.
     *
     * @param string $str            <p>The input string.</p>
     * @param string $search         <p>The needle to search for.</p>
     * @param string $replacement    <p>The string to replace with.</p>
     * @param bool   $case_sensitive [optional] <p>Whether or not to enforce case-sensitivity. Default: true</p>
     *
     * @return string
     *                <p>A string with replaced parts.</p>
     */
    public static function replace(
        string $str,
        string $search,
        string $replacement,
        bool $case_sensitive = true
    ): string {
        if ($case_sensitive) {
            return \str_replace($search, $replacement, $str);
        }

        return self::str_ireplace($search, $replacement, $str);
    }

    /**
     * Replaces all occurrences of $search in $str by $replacement.
     *
     * @param string       $str            <p>The input string.</p>
     * @param array        $search         <p>The elements to search for.</p>
     * @param array|string $replacement    <p>The string to replace with.</p>
     * @param bool         $case_sensitive [optional] <p>Whether or not to enforce case-sensitivity. Default: true</p>
     *
     * @return string
     *                <p>A string with replaced parts.</p>
     */
    public static function replace_all(
        string $str,
        array $search,
        $replacement,
        bool $case_sensitive = true
    ): string {
        if ($case_sensitive) {
            return \str_replace($search, $replacement, $str);
        }

        return self::str_ireplace($search, $replacement, $str);
    }

    /**
     * Replace the diamond question mark (�) and invalid-UTF8 chars with the replacement.
     *
     * @param string $str                        <p>The input string</p>
     * @param string $replacement_char           <p>The replacement character.</p>
     * @param bool   $process_invalid_utf8_chars <p>Convert invalid UTF-8 chars </p>
     *
     * @return string
     *                <p>A string without diamond question marks (�).</p>
     */
    public static function replace_diamond_question_mark(
        string $str,
        string $replacement_char = '',
        bool $process_invalid_utf8_chars = true
    ): string {
        if ($str === '') {
            return '';
        }

        if ($process_invalid_utf8_chars === true) {
            $replacement_char_helper = $replacement_char;
            if ($replacement_char === '') {
                $replacement_char_helper = 'none';
            }

            if (isset(self::$SUPPORT['mbstring']) === false || self::$SUPPORT['mbstring'] === false) {
                // if there is no native support for "mbstring",
                // then we need to clean the string before ...
                $str = self::clean($str);
            }

            $save = \mb_substitute_character();
            \mb_substitute_character($replacement_char_helper);
            // the polyfill maybe return false, so cast to string
            $str = (string) \mb_convert_encoding($str, 'UTF-8', 'UTF-8');
            \mb_substitute_character($save);
        }

        return \str_replace(
            [
                "\xEF\xBF\xBD",
                '�',
            ],
            [
                $replacement_char,
                $replacement_char,
            ],
            $str
        );
    }

    /**
     * Strip whitespace or other characters from the end of a UTF-8 string.
     *
     * @param string      $str   <p>The string to be trimmed.</p>
     * @param string|null $chars <p>Optional characters to be stripped.</p>
     *
     * @return string
     *                <p>A string with unwanted characters stripped from the right.</p>
     */
    public static function rtrim(string $str = '', string $chars = null): string
    {
        if ($str === '') {
            return '';
        }

        if (self::$SUPPORT['mbstring'] === true) {
            if ($chars) {
                /** @noinspection PregQuoteUsageInspection */
                $chars = \preg_quote($chars);
                $pattern = "[${chars}]+$";
            } else {
                $pattern = '[\\s]+$';
            }

            /** @noinspection PhpComposerExtensionStubsInspection */
            return (string) \mb_ereg_replace($pattern, '', $str);
        }

        if ($chars) {
            $chars = \preg_quote($chars, '/');
            $pattern = "[${chars}]+$";
        } else {
            $pattern = '[\\s]+$';
        }

        return self::regex_replace($str, $pattern, '', '', '/');
    }

    /**
     * WARNING: Print native UTF-8 support (libs), e.g. for debugging.
     *
     * @psalm-suppress MissingReturnType
     */
    public static function showSupport()
    {
        echo '<pre>';
        foreach (self::$SUPPORT as $key => &$value) {
            echo $key . ' - ' . \print_r($value, true) . "\n<br>";
        }
        unset($value);
        echo '</pre>';
    }

    /**
     * Converts a UTF-8 character to HTML Numbered Entity like "&#123;".
     *
     * @param string $char             <p>The Unicode character to be encoded as numbered entity.</p>
     * @param bool   $keep_ascii_chars <p>Set to <strong>true</strong> to keep ASCII chars.</>
     * @param string $encoding         [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     *                <p>The HTML numbered entity for the given character.</p>
     */
    public static function single_chr_html_encode(
        string $char,
        bool $keep_ascii_chars = false,
        string $encoding = 'UTF-8'
    ): string {
        if ($char === '') {
            return '';
        }

        if (
            $keep_ascii_chars === true
            &&
            ASCII::is_ascii($char) === true
        ) {
            return $char;
        }

        return '&#' . self::ord($char, $encoding) . ';';
    }

    /**
     * @param string $str
     * @param int    $tab_length
     *
     * @return string
     */
    public static function spaces_to_tabs(string $str, int $tab_length = 4): string
    {
        if ($tab_length === 4) {
            $tab = '    ';
        } elseif ($tab_length === 2) {
            $tab = '  ';
        } else {
            $tab = \str_repeat(' ', $tab_length);
        }

        return \str_replace($tab, "\t", $str);
    }

    /**
     * alias for "UTF8::str_split()"
     *
     * @param string|string[] $str
     * @param int             $length
     * @param bool            $clean_utf8
     *
     * @return string[]
     *
     * @see UTF8::str_split()
     * @deprecated <p>please use "UTF8::str_split()"</p>
     */
    public static function split(
        $str,
        int $length = 1,
        bool $clean_utf8 = false
    ): array {
        return self::str_split($str, $length, $clean_utf8);
    }

    /**
     * alias for "UTF8::str_starts_with()"
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     *
     * @see UTF8::str_starts_with()
     * @deprecated <p>please use "UTF8::str_starts_with()"</p>
     */
    public static function str_begins(string $haystack, string $needle): bool
    {
        return self::str_starts_with($haystack, $needle);
    }

    /**
     * Returns a camelCase version of the string. Trims surrounding spaces,
     * capitalizes letters following digits, spaces, dashes and underscores,
     * and removes spaces, dashes, as well as underscores.
     *
     * @param string      $str                           <p>The input string.</p>
     * @param string      $encoding                      [optional] <p>Default: 'UTF-8'</p>
     * @param bool        $clean_utf8                    [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string|null $lang                          [optional] <p>Set the language for special cases: az, el, lt, tr</p>
     * @param bool        $try_to_keep_the_string_length [optional] <p>true === try to keep the string length: e.g. ẞ -> ß</p>
     *
     * @return string
     */
    public static function str_camelize(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        if ($clean_utf8 === true) {
            $str = self::clean($str);
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        $str = self::lcfirst(
            \trim($str),
            $encoding,
            false,
            $lang,
            $try_to_keep_the_string_length
        );
        $str = (string) \preg_replace('/^[-_]+/', '', $str);

        $use_mb_functions = $lang === null && $try_to_keep_the_string_length === false;

        $str = (string) \preg_replace_callback(
            '/[-_\\s]+(.)?/u',
            /**
             * @param array $match
             *
             * @return string
             */
            static function (array $match) use ($use_mb_functions, $encoding, $lang, $try_to_keep_the_string_length): string {
                if (isset($match[1])) {
                    if ($use_mb_functions === true) {
                        if ($encoding === 'UTF-8') {
                            return \mb_strtoupper($match[1]);
                        }

                        return \mb_strtoupper($match[1], $encoding);
                    }

                    return self::strtoupper($match[1], $encoding, false, $lang, $try_to_keep_the_string_length);
                }

                return '';
            },
            $str
        );

        return (string) \preg_replace_callback(
            '/[\\p{N}]+(.)?/u',
            /**
             * @param array $match
             *
             * @return string
             */
            static function (array $match) use ($use_mb_functions, $encoding, $clean_utf8, $lang, $try_to_keep_the_string_length): string {
                if ($use_mb_functions === true) {
                    if ($encoding === 'UTF-8') {
                        return \mb_strtoupper($match[0]);
                    }

                    return \mb_strtoupper($match[0], $encoding);
                }

                return self::strtoupper($match[0], $encoding, $clean_utf8, $lang, $try_to_keep_the_string_length);
            },
            $str
        );
    }

    /**
     * Returns the string with the first letter of each word capitalized,
     * except for when the word is a name which shouldn't be capitalized.
     *
     * @param string $str
     *
     * @return string
     *                <p>A string with $str capitalized.</p>
     */
    public static function str_capitalize_name(string $str): string
    {
        return self::str_capitalize_name_helper(
            self::str_capitalize_name_helper(
                self::collapse_whitespace($str),
                ' '
            ),
            '-'
        );
    }

    /**
     * Returns true if the string contains $needle, false otherwise. By default
     * the comparison is case-sensitive, but can be made insensitive by setting
     * $case_sensitive to false.
     *
     * @param string $haystack       <p>The input string.</p>
     * @param string $needle         <p>Substring to look for.</p>
     * @param bool   $case_sensitive [optional] <p>Whether or not to enforce case-sensitivity. Default: true</p>
     *
     * @return bool whether or not $haystack contains $needle
     */
    public static function str_contains(
        string $haystack,
        string $needle,
        bool $case_sensitive = true
    ): bool {
        if ($case_sensitive) {
            return \strpos($haystack, $needle) !== false;
        }

        return \mb_stripos($haystack, $needle) !== false;
    }

    /**
     * Returns true if the string contains all $needles, false otherwise. By
     * default the comparison is case-sensitive, but can be made insensitive by
     * setting $case_sensitive to false.
     *
     * @param string $haystack       <p>The input string.</p>
     * @param array  $needles        <p>SubStrings to look for.</p>
     * @param bool   $case_sensitive [optional] <p>Whether or not to enforce case-sensitivity. Default: true</p>
     *
     * @return bool whether or not $haystack contains $needle
     */
    public static function str_contains_all(
        string $haystack,
        array $needles,
        bool $case_sensitive = true
    ): bool {
        if ($haystack === '' || $needles === []) {
            return false;
        }

        /** @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($needles as &$needle) {
            if (!$needle) {
                return false;
            }

            if ($case_sensitive) {
                return \strpos($haystack, $needle) !== false;
            }

            return \mb_stripos($haystack, $needle) !== false;
        }

        return true;
    }

    /**
     * Returns true if the string contains any $needles, false otherwise. By
     * default the comparison is case-sensitive, but can be made insensitive by
     * setting $case_sensitive to false.
     *
     * @param string $haystack       <p>The input string.</p>
     * @param array  $needles        <p>SubStrings to look for.</p>
     * @param bool   $case_sensitive [optional] <p>Whether or not to enforce case-sensitivity. Default: true</p>
     *
     * @return bool
     *              Whether or not $str contains $needle
     */
    public static function str_contains_any(
        string $haystack,
        array $needles,
        bool $case_sensitive = true
    ): bool {
        if ($haystack === '' || $needles === []) {
            return false;
        }

        /** @noinspection LoopWhichDoesNotLoopInspection */
        foreach ($needles as &$needle) {
            if (!$needle) {
                continue;
            }

            if ($case_sensitive) {
                if (\strpos($haystack, $needle) !== false) {
                    return true;
                }

                continue;
            }

            if (\mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns a lowercase and trimmed string separated by dashes. Dashes are
     * inserted before uppercase characters (with the exception of the first
     * character of the string), and in place of spaces as well as underscores.
     *
     * @param string $str      <p>The input string.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function str_dasherize(string $str, string $encoding = 'UTF-8'): string
    {
        return self::str_delimit($str, '-', $encoding);
    }

    /**
     * Returns a lowercase and trimmed string separated by the given delimiter.
     * Delimiters are inserted before uppercase characters (with the exception
     * of the first character of the string), and in place of spaces, dashes,
     * and underscores. Alpha delimiters are not converted to lowercase.
     *
     * @param string      $str                           <p>The input string.</p>
     * @param string      $delimiter                     <p>Sequence used to separate parts of the string.</p>
     * @param string      $encoding                      [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool        $clean_utf8                    [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string|null $lang                          [optional] <p>Set the language for special cases: az, el, lt,
     *                                                   tr</p>
     * @param bool        $try_to_keep_the_string_length [optional] <p>true === try to keep the string length: e.g. ẞ ->
     *                                                   ß</p>
     *
     * @return string
     */
    public static function str_delimit(
        string $str,
        string $delimiter,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            $str = (string) \mb_ereg_replace('\\B(\\p{Lu})', '-\1', \trim($str));

            $use_mb_functions = $lang === null && $try_to_keep_the_string_length === false;
            if ($use_mb_functions === true && $encoding === 'UTF-8') {
                $str = \mb_strtolower($str);
            } else {
                $str = self::strtolower($str, $encoding, $clean_utf8, $lang, $try_to_keep_the_string_length);
            }

            /** @noinspection PhpComposerExtensionStubsInspection */
            return (string) \mb_ereg_replace('[\\-_\\s]+', $delimiter, $str);
        }

        $str = (string) \preg_replace('/\\B(\\p{Lu})/u', '-\1', \trim($str));

        $use_mb_functions = $lang === null && $try_to_keep_the_string_length === false;
        if ($use_mb_functions === true && $encoding === 'UTF-8') {
            $str = \mb_strtolower($str);
        } else {
            $str = self::strtolower($str, $encoding, $clean_utf8, $lang, $try_to_keep_the_string_length);
        }

        return (string) \preg_replace('/[\\-_\\s]+/u', $delimiter, $str);
    }

    /**
     * Optimized "mb_detect_encoding()"-function -> with support for UTF-16 and UTF-32.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return false|string
     *                      The detected string-encoding e.g. UTF-8 or UTF-16BE,<br>
     *                      otherwise it will return false e.g. for BINARY or not detected encoding.
     */
    public static function str_detect_encoding($str)
    {
        // init
        $str = (string) $str;

        //
        // 1.) check binary strings (010001001...) like UTF-16 / UTF-32 / PDF / Images / ...
        //

        if (self::is_binary($str, true) === true) {
            $is_utf32 = self::is_utf32($str, false);
            if ($is_utf32 === 1) {
                return 'UTF-32LE';
            }
            if ($is_utf32 === 2) {
                return 'UTF-32BE';
            }

            $is_utf16 = self::is_utf16($str, false);
            if ($is_utf16 === 1) {
                return 'UTF-16LE';
            }
            if ($is_utf16 === 2) {
                return 'UTF-16BE';
            }

            // is binary but not "UTF-16" or "UTF-32"
            return false;
        }

        //
        // 2.) simple check for ASCII chars
        //

        if (ASCII::is_ascii($str) === true) {
            return 'ASCII';
        }

        //
        // 3.) simple check for UTF-8 chars
        //

        if (self::is_utf8_string($str) === true) {
            return 'UTF-8';
        }

        //
        // 4.) check via "mb_detect_encoding()"
        //
        // INFO: UTF-16, UTF-32, UCS2 and UCS4, encoding detection will fail always with "mb_detect_encoding()"

        $encoding_detecting_order = [
            'ISO-8859-1',
            'ISO-8859-2',
            'ISO-8859-3',
            'ISO-8859-4',
            'ISO-8859-5',
            'ISO-8859-6',
            'ISO-8859-7',
            'ISO-8859-8',
            'ISO-8859-9',
            'ISO-8859-10',
            'ISO-8859-13',
            'ISO-8859-14',
            'ISO-8859-15',
            'ISO-8859-16',
            'WINDOWS-1251',
            'WINDOWS-1252',
            'WINDOWS-1254',
            'CP932',
            'CP936',
            'CP950',
            'CP866',
            'CP850',
            'CP51932',
            'CP50220',
            'CP50221',
            'CP50222',
            'ISO-2022-JP',
            'ISO-2022-KR',
            'JIS',
            'JIS-ms',
            'EUC-CN',
            'EUC-JP',
        ];

        if (self::$SUPPORT['mbstring'] === true) {
            // info: do not use the symfony polyfill here
            $encoding = \mb_detect_encoding($str, $encoding_detecting_order, true);
            if ($encoding) {
                return $encoding;
            }
        }

        //
        // 5.) check via "iconv()"
        //

        if (self::$ENCODINGS === null) {
            self::$ENCODINGS = self::getData('encodings');
        }

        foreach (self::$ENCODINGS as $encoding_tmp) {
            // INFO: //IGNORE but still throw notice
            /** @noinspection PhpUsageOfSilenceOperatorInspection */
            if ((string) @\iconv($encoding_tmp, $encoding_tmp . '//IGNORE', $str) === $str) {
                return $encoding_tmp;
            }
        }

        return false;
    }

    /**
     * alias for "UTF8::str_ends_with()"
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     *
     * @see UTF8::str_ends_with()
     * @deprecated <p>please use "UTF8::str_ends_with()"</p>
     */
    public static function str_ends(string $haystack, string $needle): bool
    {
        return self::str_ends_with($haystack, $needle);
    }

    /**
     * Check if the string ends with the given substring.
     *
     * @param string $haystack <p>The string to search in.</p>
     * @param string $needle   <p>The substring to search for.</p>
     *
     * @return bool
     */
    public static function str_ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if ($haystack === '') {
            return false;
        }

        return \substr($haystack, -\strlen($needle)) === $needle;
    }

    /**
     * Returns true if the string ends with any of $substrings, false otherwise.
     *
     * - case-sensitive
     *
     * @param string   $str        <p>The input string.</p>
     * @param string[] $substrings <p>Substrings to look for.</p>
     *
     * @return bool whether or not $str ends with $substring
     */
    public static function str_ends_with_any(string $str, array $substrings): bool
    {
        if ($substrings === []) {
            return false;
        }

        foreach ($substrings as &$substring) {
            if (\substr($str, -\strlen($substring)) === $substring) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensures that the string begins with $substring. If it doesn't, it's
     * prepended.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $substring <p>The substring to add if not present.</p>
     *
     * @return string
     */
    public static function str_ensure_left(string $str, string $substring): string
    {
        if (
            $substring !== ''
            &&
            \strpos($str, $substring) === 0
        ) {
            return $str;
        }

        return $substring . $str;
    }

    /**
     * Ensures that the string ends with $substring. If it doesn't, it's appended.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $substring <p>The substring to add if not present.</p>
     *
     * @return string
     */
    public static function str_ensure_right(string $str, string $substring): string
    {
        if (
            $str === ''
            ||
            $substring === ''
            ||
            \substr($str, -\strlen($substring)) !== $substring
        ) {
            $str .= $substring;
        }

        return $str;
    }

    /**
     * Capitalizes the first word of the string, replaces underscores with
     * spaces, and strips '_id'.
     *
     * @param string $str
     *
     * @return string
     */
    public static function str_humanize($str): string
    {
        $str = \str_replace(
            [
                '_id',
                '_',
            ],
            [
                '',
                ' ',
            ],
            $str
        );

        return self::ucfirst(\trim($str));
    }

    /**
     * alias for "UTF8::str_istarts_with()"
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     *
     * @see UTF8::str_istarts_with()
     * @deprecated <p>please use "UTF8::str_istarts_with()"</p>
     */
    public static function str_ibegins(string $haystack, string $needle): bool
    {
        return self::str_istarts_with($haystack, $needle);
    }

    /**
     * alias for "UTF8::str_iends_with()"
     *
     * @param string $haystack
     * @param string $needle
     *
     * @return bool
     *
     * @see UTF8::str_iends_with()
     * @deprecated <p>please use "UTF8::str_iends_with()"</p>
     */
    public static function str_iends(string $haystack, string $needle): bool
    {
        return self::str_iends_with($haystack, $needle);
    }

    /**
     * Check if the string ends with the given substring, case-insensitive.
     *
     * @param string $haystack <p>The string to search in.</p>
     * @param string $needle   <p>The substring to search for.</p>
     *
     * @return bool
     */
    public static function str_iends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if ($haystack === '') {
            return false;
        }

        return self::strcasecmp(\substr($haystack, -\strlen($needle)), $needle) === 0;
    }

    /**
     * Returns true if the string ends with any of $substrings, false otherwise.
     *
     * - case-insensitive
     *
     * @param string   $str        <p>The input string.</p>
     * @param string[] $substrings <p>Substrings to look for.</p>
     *
     * @return bool
     *              <p>Whether or not $str ends with $substring.</p>
     */
    public static function str_iends_with_any(string $str, array $substrings): bool
    {
        if ($substrings === []) {
            return false;
        }

        foreach ($substrings as &$substring) {
            if (self::str_iends_with($str, $substring)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns the index of the first occurrence of $needle in the string,
     * and false if not found. Accepts an optional offset from which to begin
     * the search.
     *
     * @param string $str      <p>The input string.</p>
     * @param string $needle   <p>Substring to look for.</p>
     * @param int    $offset   [optional] <p>Offset from which to search. Default: 0</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return false|int
     *                   <p>The occurrence's <strong>index</strong> if found, otherwise <strong>false</strong>.</p>
     *
     * @see UTF8::stripos()
     * @deprecated <p>please use "UTF8::stripos()"</p>
     */
    public static function str_iindex_first(
        string $str,
        string $needle,
        int $offset = 0,
        string $encoding = 'UTF-8'
    ) {
        return self::stripos(
            $str,
            $needle,
            $offset,
            $encoding
        );
    }

    /**
     * Returns the index of the last occurrence of $needle in the string,
     * and false if not found. Accepts an optional offset from which to begin
     * the search. Offsets may be negative to count from the last character
     * in the string.
     *
     * @param string $str      <p>The input string.</p>
     * @param string $needle   <p>Substring to look for.</p>
     * @param int    $offset   [optional] <p>Offset from which to search. Default: 0</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return false|int
     *                   <p>The last occurrence's <strong>index</strong> if found, otherwise <strong>false</strong>.</p>
     *
     * @see UTF8::strripos()
     * @deprecated <p>please use "UTF8::strripos()"</p>
     */
    public static function str_iindex_last(
        string $str,
        string $needle,
        int $offset = 0,
        string $encoding = 'UTF-8'
    ) {
        return self::strripos(
            $str,
            $needle,
            $offset,
            $encoding
        );
    }

    /**
     * Returns the index of the first occurrence of $needle in the string,
     * and false if not found. Accepts an optional offset from which to begin
     * the search.
     *
     * @param string $str      <p>The input string.</p>
     * @param string $needle   <p>Substring to look for.</p>
     * @param int    $offset   [optional] <p>Offset from which to search. Default: 0</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return false|int
     *                   <p>The occurrence's <strong>index</strong> if found, otherwise <strong>false</strong>.</p>
     *
     * @see UTF8::strpos()
     * @deprecated <p>please use "UTF8::strpos()"</p>
     */
    public static function str_index_first(
        string $str,
        string $needle,
        int $offset = 0,
        string $encoding = 'UTF-8'
    ) {
        return self::strpos(
            $str,
            $needle,
            $offset,
            $encoding
        );
    }

    /**
     * Returns the index of the last occurrence of $needle in the string,
     * and false if not found. Accepts an optional offset from which to begin
     * the search. Offsets may be negative to count from the last character
     * in the string.
     *
     * @param string $str      <p>The input string.</p>
     * @param string $needle   <p>Substring to look for.</p>
     * @param int    $offset   [optional] <p>Offset from which to search. Default: 0</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return false|int
     *                   <p>The last occurrence's <strong>index</strong> if found, otherwise <strong>false</strong>.</p>
     *
     * @see UTF8::strrpos()
     * @deprecated <p>please use "UTF8::strrpos()"</p>
     */
    public static function str_index_last(
        string $str,
        string $needle,
        int $offset = 0,
        string $encoding = 'UTF-8'
    ) {
        return self::strrpos(
            $str,
            $needle,
            $offset,
            $encoding
        );
    }

    /**
     * Inserts $substring into the string at the $index provided.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $substring <p>String to be inserted.</p>
     * @param int    $index     <p>The index at which to insert the substring.</p>
     * @param string $encoding  [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function str_insert(
        string $str,
        string $substring,
        int $index,
        string $encoding = 'UTF-8'
    ): string {
        if ($encoding === 'UTF-8') {
            $len = (int) \mb_strlen($str);
            if ($index > $len) {
                return $str;
            }

            /** @noinspection UnnecessaryCastingInspection */
            return (string) \mb_substr($str, 0, $index) .
                   $substring .
                   (string) \mb_substr($str, $index, $len);
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        $len = (int) self::strlen($str, $encoding);
        if ($index > $len) {
            return $str;
        }

        return ((string) self::substr($str, 0, $index, $encoding)) .
               $substring .
               ((string) self::substr($str, $index, $len, $encoding));
    }

    /**
     * Case-insensitive and UTF-8 safe version of <function>str_replace</function>.
     *
     * @see http://php.net/manual/en/function.str-ireplace.php
     *
     * @param mixed $search  <p>
     *                       Every replacement with search array is
     *                       performed on the result of previous replacement.
     *                       </p>
     * @param mixed $replace <p>
     *                       </p>
     * @param mixed $subject <p>
     *                       If subject is an array, then the search and
     *                       replace is performed with every entry of
     *                       subject, and the return value is an array as
     *                       well.
     *                       </p>
     * @param int   $count   [optional] <p>
     *                       The number of matched and replaced needles will
     *                       be returned in count which is passed by
     *                       reference.
     *                       </p>
     *
     * @return mixed a string or an array of replacements
     */
    public static function str_ireplace($search, $replace, $subject, &$count = null)
    {
        $search = (array) $search;

        /** @noinspection AlterInForeachInspection */
        foreach ($search as &$s) {
            $s = (string) $s;
            if ($s === '') {
                $s = '/^(?<=.)$/';
            } else {
                $s = '/' . \preg_quote($s, '/') . '/ui';
            }
        }

        $subject = \preg_replace($search, $replace, $subject, -1, $replace);
        $count = $replace; // used as reference parameter

        return $subject;
    }

    /**
     * Replaces $search from the beginning of string with $replacement.
     *
     * @param string $str         <p>The input string.</p>
     * @param string $search      <p>The string to search for.</p>
     * @param string $replacement <p>The replacement.</p>
     *
     * @return string string after the replacements
     */
    public static function str_ireplace_beginning(string $str, string $search, string $replacement): string
    {
        if ($str === '') {
            if ($replacement === '') {
                return '';
            }

            if ($search === '') {
                return $replacement;
            }
        }

        if ($search === '') {
            return $str . $replacement;
        }

        if (\stripos($str, $search) === 0) {
            return $replacement . \substr($str, \strlen($search));
        }

        return $str;
    }

    /**
     * Replaces $search from the ending of string with $replacement.
     *
     * @param string $str         <p>The input string.</p>
     * @param string $search      <p>The string to search for.</p>
     * @param string $replacement <p>The replacement.</p>
     *
     * @return string
     *                <p>string after the replacements.</p>
     */
    public static function str_ireplace_ending(string $str, string $search, string $replacement): string
    {
        if ($str === '') {
            if ($replacement === '') {
                return '';
            }

            if ($search === '') {
                return $replacement;
            }
        }

        if ($search === '') {
            return $str . $replacement;
        }

        if (\stripos($str, $search, \strlen($str) - \strlen($search)) !== false) {
            $str = \substr($str, 0, -\strlen($search)) . $replacement;
        }

        return $str;
    }

    /**
     * Check if the string starts with the given substring, case-insensitive.
     *
     * @param string $haystack <p>The string to search in.</p>
     * @param string $needle   <p>The substring to search for.</p>
     *
     * @return bool
     */
    public static function str_istarts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if ($haystack === '') {
            return false;
        }

        return self::stripos($haystack, $needle) === 0;
    }

    /**
     * Returns true if the string begins with any of $substrings, false otherwise.
     *
     * - case-insensitive
     *
     * @param string $str        <p>The input string.</p>
     * @param array  $substrings <p>Substrings to look for.</p>
     *
     * @return bool whether or not $str starts with $substring
     */
    public static function str_istarts_with_any(string $str, array $substrings): bool
    {
        if ($str === '') {
            return false;
        }

        if ($substrings === []) {
            return false;
        }

        foreach ($substrings as &$substring) {
            if (self::str_istarts_with($str, $substring)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the substring after the first occurrence of a separator.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $separator <p>The string separator.</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_isubstr_after_first_separator(
        string $str,
        string $separator,
        string $encoding = 'UTF-8'
    ): string {
        if ($separator === '' || $str === '') {
            return '';
        }

        $offset = self::stripos($str, $separator);
        if ($offset === false) {
            return '';
        }

        if ($encoding === 'UTF-8') {
            return (string) \mb_substr(
                $str,
                $offset + (int) \mb_strlen($separator)
            );
        }

        return (string) self::substr(
            $str,
            $offset + (int) self::strlen($separator, $encoding),
            null,
            $encoding
        );
    }

    /**
     * Gets the substring after the last occurrence of a separator.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $separator <p>The string separator.</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_isubstr_after_last_separator(
        string $str,
        string $separator,
        string $encoding = 'UTF-8'
    ): string {
        if ($separator === '' || $str === '') {
            return '';
        }

        $offset = self::strripos($str, $separator);
        if ($offset === false) {
            return '';
        }

        if ($encoding === 'UTF-8') {
            return (string) \mb_substr(
                $str,
                $offset + (int) self::strlen($separator)
            );
        }

        return (string) self::substr(
            $str,
            $offset + (int) self::strlen($separator, $encoding),
            null,
            $encoding
        );
    }

    /**
     * Gets the substring before the first occurrence of a separator.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $separator <p>The string separator.</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_isubstr_before_first_separator(
        string $str,
        string $separator,
        string $encoding = 'UTF-8'
    ): string {
        if ($separator === '' || $str === '') {
            return '';
        }

        $offset = self::stripos($str, $separator);
        if ($offset === false) {
            return '';
        }

        if ($encoding === 'UTF-8') {
            return (string) \mb_substr($str, 0, $offset);
        }

        return (string) self::substr($str, 0, $offset, $encoding);
    }

    /**
     * Gets the substring before the last occurrence of a separator.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $separator <p>The string separator.</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_isubstr_before_last_separator(
        string $str,
        string $separator,
        string $encoding = 'UTF-8'
    ): string {
        if ($separator === '' || $str === '') {
            return '';
        }

        if ($encoding === 'UTF-8') {
            $offset = \mb_strripos($str, $separator);
            if ($offset === false) {
                return '';
            }

            return (string) \mb_substr($str, 0, $offset);
        }

        $offset = self::strripos($str, $separator, 0, $encoding);
        if ($offset === false) {
            return '';
        }

        return (string) self::substr($str, 0, $offset, $encoding);
    }

    /**
     * Gets the substring after (or before via "$before_needle") the first occurrence of the "$needle".
     *
     * @param string $str           <p>The input string.</p>
     * @param string $needle        <p>The string to look for.</p>
     * @param bool   $before_needle [optional] <p>Default: false</p>
     * @param string $encoding      [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_isubstr_first(
        string $str,
        string $needle,
        bool $before_needle = false,
        string $encoding = 'UTF-8'
    ): string {
        if (
            $needle === ''
            ||
            $str === ''
        ) {
            return '';
        }

        $part = self::stristr(
            $str,
            $needle,
            $before_needle,
            $encoding
        );
        if ($part === false) {
            return '';
        }

        return $part;
    }

    /**
     * Gets the substring after (or before via "$before_needle") the last occurrence of the "$needle".
     *
     * @param string $str           <p>The input string.</p>
     * @param string $needle        <p>The string to look for.</p>
     * @param bool   $before_needle [optional] <p>Default: false</p>
     * @param string $encoding      [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_isubstr_last(
        string $str,
        string $needle,
        bool $before_needle = false,
        string $encoding = 'UTF-8'
    ): string {
        if (
            $needle === ''
            ||
            $str === ''
        ) {
            return '';
        }

        $part = self::strrichr(
            $str,
            $needle,
            $before_needle,
            $encoding
        );
        if ($part === false) {
            return '';
        }

        return $part;
    }

    /**
     * Returns the last $n characters of the string.
     *
     * @param string $str      <p>The input string.</p>
     * @param int    $n        <p>Number of characters to retrieve from the end.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function str_last_char(
        string $str,
        int $n = 1,
        string $encoding = 'UTF-8'
    ): string {
        if ($str === '' || $n <= 0) {
            return '';
        }

        if ($encoding === 'UTF-8') {
            return (string) \mb_substr($str, -$n);
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        return (string) self::substr($str, -$n, null, $encoding);
    }

    /**
     * Limit the number of characters in a string.
     *
     * @param string $str        <p>The input string.</p>
     * @param int    $length     [optional] <p>Default: 100</p>
     * @param string $str_add_on [optional] <p>Default: …</p>
     * @param string $encoding   [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function str_limit(
        string $str,
        int $length = 100,
        string $str_add_on = '…',
        string $encoding = 'UTF-8'
    ): string {
        if ($str === '' || $length <= 0) {
            return '';
        }

        if ($encoding === 'UTF-8') {
            if ((int) \mb_strlen($str) <= $length) {
                return $str;
            }

            /** @noinspection UnnecessaryCastingInspection */
            return (string) \mb_substr($str, 0, $length - (int) self::strlen($str_add_on)) . $str_add_on;
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        if ((int) self::strlen($str, $encoding) <= $length) {
            return $str;
        }

        return ((string) self::substr($str, 0, $length - (int) self::strlen($str_add_on), $encoding)) . $str_add_on;
    }

    /**
     * Limit the number of characters in a string, but also after the next word.
     *
     * @param string $str        <p>The input string.</p>
     * @param int    $length     [optional] <p>Default: 100</p>
     * @param string $str_add_on [optional] <p>Default: …</p>
     * @param string $encoding   [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function str_limit_after_word(
        string $str,
        int $length = 100,
        string $str_add_on = '…',
        string $encoding = 'UTF-8'
    ): string {
        if ($str === '' || $length <= 0) {
            return '';
        }

        if ($encoding === 'UTF-8') {
            /** @noinspection UnnecessaryCastingInspection */
            if ((int) \mb_strlen($str) <= $length) {
                return $str;
            }

            if (\mb_substr($str, $length - 1, 1) === ' ') {
                return ((string) \mb_substr($str, 0, $length - 1)) . $str_add_on;
            }

            $str = \mb_substr($str, 0, $length);

            $array = \explode(' ', $str);
            \array_pop($array);
            $new_str = \implode(' ', $array);

            if ($new_str === '') {
                return ((string) \mb_substr($str, 0, $length - 1)) . $str_add_on;
            }
        } else {
            if ((int) self::strlen($str, $encoding) <= $length) {
                return $str;
            }

            if (self::substr($str, $length - 1, 1, $encoding) === ' ') {
                return ((string) self::substr($str, 0, $length - 1, $encoding)) . $str_add_on;
            }

            /** @noinspection CallableParameterUseCaseInTypeContextInspection - FP */
            $str = self::substr($str, 0, $length, $encoding);
            /** @noinspection CallableParameterUseCaseInTypeContextInspection - FP */
            if ($str === false) {
                return '' . $str_add_on;
            }

            $array = \explode(' ', $str);
            \array_pop($array);
            $new_str = \implode(' ', $array);

            if ($new_str === '') {
                return ((string) self::substr($str, 0, $length - 1, $encoding)) . $str_add_on;
            }
        }

        return $new_str . $str_add_on;
    }

    /**
     * Returns the longest common prefix between the $str1 and $str2.
     *
     * @param string $str1     <p>The input sting.</p>
     * @param string $str2     <p>Second string for comparison.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function str_longest_common_prefix(
        string $str1,
        string $str2,
        string $encoding = 'UTF-8'
    ): string {
        // init
        $longest_common_prefix = '';

        if ($encoding === 'UTF-8') {
            $max_length = (int) \min(
                \mb_strlen($str1),
                \mb_strlen($str2)
            );

            for ($i = 0; $i < $max_length; ++$i) {
                $char = \mb_substr($str1, $i, 1);

                if (
                    $char !== false
                    &&
                    $char === \mb_substr($str2, $i, 1)
                ) {
                    $longest_common_prefix .= $char;
                } else {
                    break;
                }
            }
        } else {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            $max_length = (int) \min(
                self::strlen($str1, $encoding),
                self::strlen($str2, $encoding)
            );

            for ($i = 0; $i < $max_length; ++$i) {
                $char = self::substr($str1, $i, 1, $encoding);

                if (
                    $char !== false
                    &&
                    $char === self::substr($str2, $i, 1, $encoding)
                ) {
                    $longest_common_prefix .= $char;
                } else {
                    break;
                }
            }
        }

        return $longest_common_prefix;
    }

    /**
     * Returns the longest common substring between the $str1 and $str2.
     * In the case of ties, it returns that which occurs first.
     *
     * @param string $str1
     * @param string $str2     <p>Second string for comparison.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     *                <p>A string with its $str being the longest common substring.</p>
     */
    public static function str_longest_common_substring(
        string $str1,
        string $str2,
        string $encoding = 'UTF-8'
    ): string {
        if ($str1 === '' || $str2 === '') {
            return '';
        }

        // Uses dynamic programming to solve
        // http://en.wikipedia.org/wiki/Longest_common_substring_problem

        if ($encoding === 'UTF-8') {
            $str_length = (int) \mb_strlen($str1);
            $other_length = (int) \mb_strlen($str2);
        } else {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            $str_length = (int) self::strlen($str1, $encoding);
            $other_length = (int) self::strlen($str2, $encoding);
        }

        // Return if either string is empty
        if ($str_length === 0 || $other_length === 0) {
            return '';
        }

        $len = 0;
        $end = 0;
        $table = \array_fill(
            0,
            $str_length + 1,
            \array_fill(0, $other_length + 1, 0)
        );

        if ($encoding === 'UTF-8') {
            for ($i = 1; $i <= $str_length; ++$i) {
                for ($j = 1; $j <= $other_length; ++$j) {
                    $str_char = \mb_substr($str1, $i - 1, 1);
                    $other_char = \mb_substr($str2, $j - 1, 1);

                    if ($str_char === $other_char) {
                        $table[$i][$j] = $table[$i - 1][$j - 1] + 1;
                        if ($table[$i][$j] > $len) {
                            $len = $table[$i][$j];
                            $end = $i;
                        }
                    } else {
                        $table[$i][$j] = 0;
                    }
                }
            }
        } else {
            for ($i = 1; $i <= $str_length; ++$i) {
                for ($j = 1; $j <= $other_length; ++$j) {
                    $str_char = self::substr($str1, $i - 1, 1, $encoding);
                    $other_char = self::substr($str2, $j - 1, 1, $encoding);

                    if ($str_char === $other_char) {
                        $table[$i][$j] = $table[$i - 1][$j - 1] + 1;
                        if ($table[$i][$j] > $len) {
                            $len = $table[$i][$j];
                            $end = $i;
                        }
                    } else {
                        $table[$i][$j] = 0;
                    }
                }
            }
        }

        if ($encoding === 'UTF-8') {
            return (string) \mb_substr($str1, $end - $len, $len);
        }

        return (string) self::substr($str1, $end - $len, $len, $encoding);
    }

    /**
     * Returns the longest common suffix between the $str1 and $str2.
     *
     * @param string $str1
     * @param string $str2     <p>Second string for comparison.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     */
    public static function str_longest_common_suffix(
        string $str1,
        string $str2,
        string $encoding = 'UTF-8'
    ): string {
        if ($str1 === '' || $str2 === '') {
            return '';
        }

        if ($encoding === 'UTF-8') {
            $max_length = (int) \min(
                \mb_strlen($str1, $encoding),
                \mb_strlen($str2, $encoding)
            );

            $longest_common_suffix = '';
            for ($i = 1; $i <= $max_length; ++$i) {
                $char = \mb_substr($str1, -$i, 1);

                if (
                    $char !== false
                    &&
                    $char === \mb_substr($str2, -$i, 1)
                ) {
                    $longest_common_suffix = $char . $longest_common_suffix;
                } else {
                    break;
                }
            }
        } else {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            $max_length = (int) \min(
                self::strlen($str1, $encoding),
                self::strlen($str2, $encoding)
            );

            $longest_common_suffix = '';
            for ($i = 1; $i <= $max_length; ++$i) {
                $char = self::substr($str1, -$i, 1, $encoding);

                if (
                    $char !== false
                    &&
                    $char === self::substr($str2, -$i, 1, $encoding)
                ) {
                    $longest_common_suffix = $char . $longest_common_suffix;
                } else {
                    break;
                }
            }
        }

        return $longest_common_suffix;
    }

    /**
     * Returns true if $str matches the supplied pattern, false otherwise.
     *
     * @param string $str     <p>The input string.</p>
     * @param string $pattern <p>Regex pattern to match against.</p>
     *
     * @return bool whether or not $str matches the pattern
     */
    public static function str_matches_pattern(string $str, string $pattern): bool
    {
        return (bool) \preg_match('/' . $pattern . '/u', $str);
    }

    /**
     * Returns whether or not a character exists at an index. Offsets may be
     * negative to count from the last character in the string. Implements
     * part of the ArrayAccess interface.
     *
     * @param string $str      <p>The input string.</p>
     * @param int    $offset   <p>The index to check.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return bool whether or not the index exists
     */
    public static function str_offset_exists(string $str, int $offset, string $encoding = 'UTF-8'): bool
    {
        // init
        $length = (int) self::strlen($str, $encoding);

        if ($offset >= 0) {
            return $length > $offset;
        }

        return $length >= \abs($offset);
    }

    /**
     * Returns the character at the given index. Offsets may be negative to
     * count from the last character in the string. Implements part of the
     * ArrayAccess interface, and throws an OutOfBoundsException if the index
     * does not exist.
     *
     * @param string $str      <p>The input string.</p>
     * @param int    $index    <p>The <strong>index</strong> from which to retrieve the char.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @throws \OutOfBoundsException if the positive or negative offset does not exist
     *
     * @return string
     *                <p>The character at the specified index.</p>
     */
    public static function str_offset_get(string $str, int $index, string $encoding = 'UTF-8'): string
    {
        // init
        $length = (int) self::strlen($str);

        if (
            ($index >= 0 && $length <= $index)
            ||
            $length < \abs($index)
        ) {
            throw new \OutOfBoundsException('No character exists at the index');
        }

        return self::char_at($str, $index, $encoding);
    }

    /**
     * Pad a UTF-8 string to a given length with another string.
     *
     * @param string     $str        <p>The input string.</p>
     * @param int        $pad_length <p>The length of return string.</p>
     * @param string     $pad_string [optional] <p>String to use for padding the input string.</p>
     * @param int|string $pad_type   [optional] <p>
     *                               Can be <strong>STR_PAD_RIGHT</strong> (default), [or string "right"]<br>
     *                               <strong>STR_PAD_LEFT</strong> [or string "left"] or<br>
     *                               <strong>STR_PAD_BOTH</strong> [or string "both"]
     *                               </p>
     * @param string     $encoding   [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     *                <p>Returns the padded string.</p>
     */
    public static function str_pad(
        string $str,
        int $pad_length,
        string $pad_string = ' ',
        $pad_type = \STR_PAD_RIGHT,
        string $encoding = 'UTF-8'
    ): string {
        if ($pad_length === 0 || $pad_string === '') {
            return $str;
        }

        if ($pad_type !== (int) $pad_type) {
            if ($pad_type === 'left') {
                $pad_type = \STR_PAD_LEFT;
            } elseif ($pad_type === 'right') {
                $pad_type = \STR_PAD_RIGHT;
            } elseif ($pad_type === 'both') {
                $pad_type = \STR_PAD_BOTH;
            } else {
                throw new \InvalidArgumentException(
                    'Pad expects $pad_type to be "STR_PAD_*" or ' . "to be one of 'left', 'right' or 'both'"
                );
            }
        }

        if ($encoding === 'UTF-8') {
            $str_length = (int) \mb_strlen($str);

            if ($pad_length >= $str_length) {
                switch ($pad_type) {
                    case \STR_PAD_LEFT:
                        $ps_length = (int) \mb_strlen($pad_string);

                        $diff = ($pad_length - $str_length);

                        $pre = (string) \mb_substr(
                            \str_repeat($pad_string, (int) \ceil($diff / $ps_length)),
                            0,
                            $diff
                        );
                        $post = '';

                        break;

                    case \STR_PAD_BOTH:
                        $diff = ($pad_length - $str_length);

                        $ps_length_left = (int) \floor($diff / 2);

                        $ps_length_right = (int) \ceil($diff / 2);

                        $pre = (string) \mb_substr(
                            \str_repeat($pad_string, $ps_length_left),
                            0,
                            $ps_length_left
                        );
                        $post = (string) \mb_substr(
                            \str_repeat($pad_string, $ps_length_right),
                            0,
                            $ps_length_right
                        );

                        break;

                    case \STR_PAD_RIGHT:
                    default:
                        $ps_length = (int) \mb_strlen($pad_string);

                        $diff = ($pad_length - $str_length);

                        $post = (string) \mb_substr(
                            \str_repeat($pad_string, (int) \ceil($diff / $ps_length)),
                            0,
                            $diff
                        );
                        $pre = '';
                }

                return $pre . $str . $post;
            }

            return $str;
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        $str_length = (int) self::strlen($str, $encoding);

        if ($pad_length >= $str_length) {
            switch ($pad_type) {
                case \STR_PAD_LEFT:
                    $ps_length = (int) self::strlen($pad_string, $encoding);

                    $diff = ($pad_length - $str_length);

                    $pre = (string) self::substr(
                        \str_repeat($pad_string, (int) \ceil($diff / $ps_length)),
                        0,
                        $diff,
                        $encoding
                    );
                    $post = '';

                    break;

                case \STR_PAD_BOTH:
                    $diff = ($pad_length - $str_length);

                    $ps_length_left = (int) \floor($diff / 2);

                    $ps_length_right = (int) \ceil($diff / 2);

                    $pre = (string) self::substr(
                        \str_repeat($pad_string, $ps_length_left),
                        0,
                        $ps_length_left,
                        $encoding
                    );
                    $post = (string) self::substr(
                        \str_repeat($pad_string, $ps_length_right),
                        0,
                        $ps_length_right,
                        $encoding
                    );

                    break;

                case \STR_PAD_RIGHT:
                default:
                    $ps_length = (int) self::strlen($pad_string, $encoding);

                    $diff = ($pad_length - $str_length);

                    $post = (string) self::substr(
                        \str_repeat($pad_string, (int) \ceil($diff / $ps_length)),
                        0,
                        $diff,
                        $encoding
                    );
                    $pre = '';
            }

            return $pre . $str . $post;
        }

        return $str;
    }

    /**
     * Returns a new string of a given length such that both sides of the
     * string are padded. Alias for "UTF8::str_pad()" with a $pad_type of 'both'.
     *
     * @param string $str
     * @param int    $length   <p>Desired string length after padding.</p>
     * @param string $pad_str  [optional] <p>String used to pad, defaults to space. Default: ' '</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     *                <p>The string with padding applied.</p>
     */
    public static function str_pad_both(
        string $str,
        int $length,
        string $pad_str = ' ',
        string $encoding = 'UTF-8'
    ): string {
        return self::str_pad(
            $str,
            $length,
            $pad_str,
            \STR_PAD_BOTH,
            $encoding
        );
    }

    /**
     * Returns a new string of a given length such that the beginning of the
     * string is padded. Alias for "UTF8::str_pad()" with a $pad_type of 'left'.
     *
     * @param string $str
     * @param int    $length   <p>Desired string length after padding.</p>
     * @param string $pad_str  [optional] <p>String used to pad, defaults to space. Default: ' '</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     *                <p>The string with left padding.</p>
     */
    public static function str_pad_left(
        string $str,
        int $length,
        string $pad_str = ' ',
        string $encoding = 'UTF-8'
    ): string {
        return self::str_pad(
            $str,
            $length,
            $pad_str,
            \STR_PAD_LEFT,
            $encoding
        );
    }

    /**
     * Returns a new string of a given length such that the end of the string
     * is padded. Alias for "UTF8::str_pad()" with a $pad_type of 'right'.
     *
     * @param string $str
     * @param int    $length   <p>Desired string length after padding.</p>
     * @param string $pad_str  [optional] <p>String used to pad, defaults to space. Default: ' '</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     *                <p>The string with right padding.</p>
     */
    public static function str_pad_right(
        string $str,
        int $length,
        string $pad_str = ' ',
        string $encoding = 'UTF-8'
    ): string {
        return self::str_pad(
            $str,
            $length,
            $pad_str,
            \STR_PAD_RIGHT,
            $encoding
        );
    }

    /**
     * Repeat a string.
     *
     * @param string $str        <p>
     *                           The string to be repeated.
     *                           </p>
     * @param int    $multiplier <p>
     *                           Number of time the input string should be
     *                           repeated.
     *                           </p>
     *                           <p>
     *                           multiplier has to be greater than or equal to 0.
     *                           If the multiplier is set to 0, the function
     *                           will return an empty string.
     *                           </p>
     *
     * @return string
     *                <p>The repeated string.</P>
     */
    public static function str_repeat(string $str, int $multiplier): string
    {
        $str = self::filter($str);

        return \str_repeat($str, $multiplier);
    }

    /**
     * INFO: This is only a wrapper for "str_replace()"  -> the original functions is already UTF-8 safe.
     *
     * Replace all occurrences of the search string with the replacement string
     *
     * @see http://php.net/manual/en/function.str-replace.php
     *
     * @param mixed $search  <p>
     *                       The value being searched for, otherwise known as the needle.
     *                       An array may be used to designate multiple needles.
     *                       </p>
     * @param mixed $replace <p>
     *                       The replacement value that replaces found search
     *                       values. An array may be used to designate multiple replacements.
     *                       </p>
     * @param mixed $subject <p>
     *                       The string or array being searched and replaced on,
     *                       otherwise known as the haystack.
     *                       </p>
     *                       <p>
     *                       If subject is an array, then the search and
     *                       replace is performed with every entry of
     *                       subject, and the return value is an array as
     *                       well.
     *                       </p>
     * @param int   $count   [optional] If passed, this will hold the number of matched and replaced needles
     *
     * @return mixed this function returns a string or an array with the replaced values
     */
    public static function str_replace(
        $search,
        $replace,
        $subject,
        int &$count = null
    ) {
        /**
         * @psalm-suppress PossiblyNullArgument
         */
        return \str_replace(
            $search,
            $replace,
            $subject,
            $count
        );
    }

    /**
     * Replaces $search from the beginning of string with $replacement.
     *
     * @param string $str         <p>The input string.</p>
     * @param string $search      <p>The string to search for.</p>
     * @param string $replacement <p>The replacement.</p>
     *
     * @return string
     *                <p>A string after the replacements.</p>
     */
    public static function str_replace_beginning(
        string $str,
        string $search,
        string $replacement
    ): string {
        if ($str === '') {
            if ($replacement === '') {
                return '';
            }

            if ($search === '') {
                return $replacement;
            }
        }

        if ($search === '') {
            return $str . $replacement;
        }

        if (\strpos($str, $search) === 0) {
            return $replacement . \substr($str, \strlen($search));
        }

        return $str;
    }

    /**
     * Replaces $search from the ending of string with $replacement.
     *
     * @param string $str         <p>The input string.</p>
     * @param string $search      <p>The string to search for.</p>
     * @param string $replacement <p>The replacement.</p>
     *
     * @return string
     *                <p>A string after the replacements.</p>
     */
    public static function str_replace_ending(
        string $str,
        string $search,
        string $replacement
    ): string {
        if ($str === '') {
            if ($replacement === '') {
                return '';
            }

            if ($search === '') {
                return $replacement;
            }
        }

        if ($search === '') {
            return $str . $replacement;
        }

        if (\strpos($str, $search, \strlen($str) - \strlen($search)) !== false) {
            $str = \substr($str, 0, -\strlen($search)) . $replacement;
        }

        return $str;
    }

    /**
     * Replace the first "$search"-term with the "$replace"-term.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     *
     * @psalm-suppress InvalidReturnType
     */
    public static function str_replace_first(
        string $search,
        string $replace,
        string $subject
    ): string {
        $pos = self::strpos($subject, $search);

        if ($pos !== false) {
            /**
             * @psalm-suppress InvalidReturnStatement
             */
            return self::substr_replace(
                $subject,
                $replace,
                $pos,
                (int) self::strlen($search)
            );
        }

        return $subject;
    }

    /**
     * Replace the last "$search"-term with the "$replace"-term.
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     *
     * @return string
     *
     * @psalm-suppress InvalidReturnType
     */
    public static function str_replace_last(
        string $search,
        string $replace,
        string $subject
    ): string {
        $pos = self::strrpos($subject, $search);
        if ($pos !== false) {
            /**
             * @psalm-suppress InvalidReturnStatement
             */
            return self::substr_replace(
                $subject,
                $replace,
                $pos,
                (int) self::strlen($search)
            );
        }

        return $subject;
    }

    /**
     * Shuffles all the characters in the string.
     *
     * PS: uses random algorithm which is weak for cryptography purposes
     *
     * @param string $str      <p>The input string</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     *                <p>The shuffled string.</p>
     */
    public static function str_shuffle(string $str, string $encoding = 'UTF-8'): string
    {
        if ($encoding === 'UTF-8') {
            $indexes = \range(0, (int) \mb_strlen($str) - 1);
            /** @noinspection NonSecureShuffleUsageInspection */
            \shuffle($indexes);

            // init
            $shuffled_str = '';

            foreach ($indexes as &$i) {
                $tmp_sub_str = \mb_substr($str, $i, 1);
                if ($tmp_sub_str !== false) {
                    $shuffled_str .= $tmp_sub_str;
                }
            }
        } else {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            $indexes = \range(0, (int) self::strlen($str, $encoding) - 1);
            /** @noinspection NonSecureShuffleUsageInspection */
            \shuffle($indexes);

            // init
            $shuffled_str = '';

            foreach ($indexes as &$i) {
                $tmp_sub_str = self::substr($str, $i, 1, $encoding);
                if ($tmp_sub_str !== false) {
                    $shuffled_str .= $tmp_sub_str;
                }
            }
        }

        return $shuffled_str;
    }

    /**
     * Returns the substring beginning at $start, and up to, but not including
     * the index specified by $end. If $end is omitted, the function extracts
     * the remaining string. If $end is negative, it is computed from the end
     * of the string.
     *
     * @param string $str
     * @param int    $start    <p>Initial index from which to begin extraction.</p>
     * @param int    $end      [optional] <p>Index at which to end extraction. Default: null</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return false|string
     *                      <p>The extracted substring.</p><p>If <i>str</i> is shorter than <i>start</i>
     *                      characters long, <b>FALSE</b> will be returned.
     */
    public static function str_slice(
        string $str,
        int $start,
        int $end = null,
        string $encoding = 'UTF-8'
    ) {
        if ($encoding === 'UTF-8') {
            if ($end === null) {
                $length = (int) \mb_strlen($str);
            } elseif ($end >= 0 && $end <= $start) {
                return '';
            } elseif ($end < 0) {
                $length = (int) \mb_strlen($str) + $end - $start;
            } else {
                $length = $end - $start;
            }

            return \mb_substr($str, $start, $length);
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        if ($end === null) {
            $length = (int) self::strlen($str, $encoding);
        } elseif ($end >= 0 && $end <= $start) {
            return '';
        } elseif ($end < 0) {
            $length = (int) self::strlen($str, $encoding) + $end - $start;
        } else {
            $length = $end - $start;
        }

        return self::substr($str, $start, $length, $encoding);
    }

    /**
     * Convert a string to e.g.: "snake_case"
     *
     * @param string $str
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     *                <p>A string in snake_case.</p>
     */
    public static function str_snakeize(string $str, string $encoding = 'UTF-8'): string
    {
        if ($str === '') {
            return '';
        }

        $str = \str_replace(
            '-',
            '_',
            self::normalize_whitespace($str)
        );

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        $str = (string) \preg_replace_callback(
            '/([\\p{N}|\\p{Lu}])/u',
            /**
             * @param string[] $matches
             *
             * @return string
             */
            static function (array $matches) use ($encoding): string {
                $match = $matches[1];
                $match_int = (int) $match;

                if ((string) $match_int === $match) {
                    return '_' . $match . '_';
                }

                if ($encoding === 'UTF-8') {
                    return '_' . \mb_strtolower($match);
                }

                return '_' . self::strtolower($match, $encoding);
            },
            $str
        );

        $str = (string) \preg_replace(
            [
                '/\\s+/u',           // convert spaces to "_"
                '/^\\s+|\\s+$/u', // trim leading & trailing spaces
                '/_+/',                 // remove double "_"
            ],
            [
                '_',
                '',
                '_',
            ],
            $str
        );

        return \trim(\trim($str, '_')); // trim leading & trailing "_" + whitespace
    }

    /**
     * Sort all characters according to code points.
     *
     * @param string $str    <p>A UTF-8 string.</p>
     * @param bool   $unique <p>Sort unique. If <strong>true</strong>, repeated characters are ignored.</p>
     * @param bool   $desc   <p>If <strong>true</strong>, will sort characters in reverse code point order.</p>
     *
     * @return string
     *                <p>A string of sorted characters.</p>
     */
    public static function str_sort(string $str, bool $unique = false, bool $desc = false): string
    {
        $array = self::codepoints($str);

        if ($unique) {
            $array = \array_flip(\array_flip($array));
        }

        if ($desc) {
            \arsort($array);
        } else {
            \asort($array);
        }

        return self::string($array);
    }

    /**
     * Convert a string to an array of Unicode characters.
     *
     * @param int|int[]|string|string[] $str                     <p>The string to split into array.</p>
     * @param int                       $length                  [optional] <p>Max character length of each array
     *                                                           element.</p>
     * @param bool                      $clean_utf8              [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param bool                      $try_to_use_mb_functions [optional] <p>Set to false, if you don't want to use
     *                                                           "mb_substr"</p>
     *
     * @return array
     *               <p>An array containing chunks of the input.</p>
     */
    public static function str_split(
        $str,
        int $length = 1,
        bool $clean_utf8 = false,
        bool $try_to_use_mb_functions = true
    ): array {
        if ($length <= 0) {
            return [];
        }

        if (\is_array($str) === true) {
            foreach ($str as $k => &$v) {
                $v = self::str_split(
                    $v,
                    $length,
                    $clean_utf8,
                    $try_to_use_mb_functions
                );
            }

            return $str;
        }

        // init
        $str = (string) $str;

        if ($str === '') {
            return [];
        }

        if ($clean_utf8 === true) {
            $str = self::clean($str);
        }

        if (
            $try_to_use_mb_functions === true
            &&
            self::$SUPPORT['mbstring'] === true
        ) {
            if (Bootup::is_php('7.4')) {
                $return = \mb_str_split($str, $length);
                if ($return !== false) {
                    return $return;
                }
            }

            $i_max = \mb_strlen($str);
            if ($i_max <= 127) {
                $ret = [];
                for ($i = 0; $i < $i_max; ++$i) {
                    $ret[] = \mb_substr($str, $i, 1);
                }
            } else {
                $return_array = [];
                \preg_match_all('/./us', $str, $return_array);
                $ret = $return_array[0] ?? [];
            }
        } elseif (self::$SUPPORT['pcre_utf8'] === true) {
            $return_array = [];
            \preg_match_all('/./us', $str, $return_array);
            $ret = $return_array[0] ?? [];
        } else {

            // fallback

            $ret = [];
            $len = \strlen($str);

            /** @noinspection ForeachInvariantsInspection */
            for ($i = 0; $i < $len; ++$i) {
                if (($str[$i] & "\x80") === "\x00") {
                    $ret[] = $str[$i];
                } elseif (
                    isset($str[$i + 1])
                    &&
                    ($str[$i] & "\xE0") === "\xC0"
                ) {
                    if (($str[$i + 1] & "\xC0") === "\x80") {
                        $ret[] = $str[$i] . $str[$i + 1];

                        ++$i;
                    }
                } elseif (
                    isset($str[$i + 2])
                    &&
                    ($str[$i] & "\xF0") === "\xE0"
                ) {
                    if (
                        ($str[$i + 1] & "\xC0") === "\x80"
                        &&
                        ($str[$i + 2] & "\xC0") === "\x80"
                    ) {
                        $ret[] = $str[$i] . $str[$i + 1] . $str[$i + 2];

                        $i += 2;
                    }
                } elseif (
                    isset($str[$i + 3])
                    &&
                    ($str[$i] & "\xF8") === "\xF0"
                ) {
                    if (
                        ($str[$i + 1] & "\xC0") === "\x80"
                        &&
                        ($str[$i + 2] & "\xC0") === "\x80"
                        &&
                        ($str[$i + 3] & "\xC0") === "\x80"
                    ) {
                        $ret[] = $str[$i] . $str[$i + 1] . $str[$i + 2] . $str[$i + 3];

                        $i += 3;
                    }
                }
            }
        }

        if ($length > 1) {
            $ret = \array_chunk($ret, $length);

            return \array_map(
                static function (array &$item): string {
                    return \implode('', $item);
                },
                $ret
            );
        }

        if (isset($ret[0]) && $ret[0] === '') {
            return [];
        }

        return $ret;
    }

    /**
     * Splits the string with the provided regular expression, returning an
     * array of strings. An optional integer $limit will truncate the
     * results.
     *
     * @param string $str
     * @param string $pattern <p>The regex with which to split the string.</p>
     * @param int    $limit   [optional] <p>Maximum number of results to return. Default: -1 === no limit</p>
     *
     * @return string[]
     *                  <p>An array of strings.</p>
     */
    public static function str_split_pattern(string $str, string $pattern, int $limit = -1): array
    {
        if ($limit === 0) {
            return [];
        }

        if ($pattern === '') {
            return [$str];
        }

        if (self::$SUPPORT['mbstring'] === true) {
            if ($limit >= 0) {
                /** @noinspection PhpComposerExtensionStubsInspection */
                $result_tmp = \mb_split($pattern, $str);

                $result = [];
                foreach ($result_tmp as $item_tmp) {
                    if ($limit === 0) {
                        break;
                    }
                    --$limit;

                    $result[] = $item_tmp;
                }

                return $result;
            }

            /** @noinspection PhpComposerExtensionStubsInspection */
            return \mb_split($pattern, $str);
        }

        if ($limit > 0) {
            ++$limit;
        } else {
            $limit = -1;
        }

        $array = \preg_split('/' . \preg_quote($pattern, '/') . '/u', $str, $limit);

        if ($array === false) {
            return [];
        }

        if ($limit > 0 && \count($array) === $limit) {
            \array_pop($array);
        }

        return $array;
    }

    /**
     * Check if the string starts with the given substring.
     *
     * @param string $haystack <p>The string to search in.</p>
     * @param string $needle   <p>The substring to search for.</p>
     *
     * @return bool
     */
    public static function str_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        if ($haystack === '') {
            return false;
        }

        return \strpos($haystack, $needle) === 0;
    }

    /**
     * Returns true if the string begins with any of $substrings, false otherwise.
     *
     * - case-sensitive
     *
     * @param string $str        <p>The input string.</p>
     * @param array  $substrings <p>Substrings to look for.</p>
     *
     * @return bool whether or not $str starts with $substring
     */
    public static function str_starts_with_any(string $str, array $substrings): bool
    {
        if ($str === '') {
            return false;
        }

        if ($substrings === []) {
            return false;
        }

        foreach ($substrings as &$substring) {
            if (self::str_starts_with($str, $substring)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets the substring after the first occurrence of a separator.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $separator <p>The string separator.</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_substr_after_first_separator(string $str, string $separator, string $encoding = 'UTF-8'): string
    {
        if ($separator === '' || $str === '') {
            return '';
        }

        if ($encoding === 'UTF-8') {
            $offset = \mb_strpos($str, $separator);
            if ($offset === false) {
                return '';
            }

            return (string) \mb_substr(
                $str,
                $offset + (int) \mb_strlen($separator)
            );
        }

        $offset = self::strpos($str, $separator, 0, $encoding);
        if ($offset === false) {
            return '';
        }

        return (string) \mb_substr(
            $str,
            $offset + (int) self::strlen($separator, $encoding),
            null,
            $encoding
        );
    }

    /**
     * Gets the substring after the last occurrence of a separator.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $separator <p>The string separator.</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_substr_after_last_separator(string $str, string $separator, string $encoding = 'UTF-8'): string
    {
        if ($separator === '' || $str === '') {
            return '';
        }

        if ($encoding === 'UTF-8') {
            $offset = \mb_strrpos($str, $separator);
            if ($offset === false) {
                return '';
            }

            return (string) \mb_substr(
                $str,
                $offset + (int) \mb_strlen($separator)
            );
        }

        $offset = self::strrpos($str, $separator, 0, $encoding);
        if ($offset === false) {
            return '';
        }

        return (string) self::substr(
            $str,
            $offset + (int) self::strlen($separator, $encoding),
            null,
            $encoding
        );
    }

    /**
     * Gets the substring before the first occurrence of a separator.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $separator <p>The string separator.</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_substr_before_first_separator(
        string $str,
        string $separator,
        string $encoding = 'UTF-8'
    ): string {
        if ($separator === '' || $str === '') {
            return '';
        }

        if ($encoding === 'UTF-8') {
            $offset = \mb_strpos($str, $separator);
            if ($offset === false) {
                return '';
            }

            return (string) \mb_substr(
                $str,
                0,
                $offset
            );
        }

        $offset = self::strpos($str, $separator, 0, $encoding);
        if ($offset === false) {
            return '';
        }

        return (string) self::substr(
            $str,
            0,
            $offset,
            $encoding
        );
    }

    /**
     * Gets the substring before the last occurrence of a separator.
     *
     * @param string $str       <p>The input string.</p>
     * @param string $separator <p>The string separator.</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_substr_before_last_separator(string $str, string $separator, string $encoding = 'UTF-8'): string
    {
        if ($separator === '' || $str === '') {
            return '';
        }

        if ($encoding === 'UTF-8') {
            $offset = \mb_strrpos($str, $separator);
            if ($offset === false) {
                return '';
            }

            return (string) \mb_substr(
                $str,
                0,
                $offset
            );
        }

        $offset = self::strrpos($str, $separator, 0, $encoding);
        if ($offset === false) {
            return '';
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        return (string) self::substr(
            $str,
            0,
            $offset,
            $encoding
        );
    }

    /**
     * Gets the substring after (or before via "$before_needle") the first occurrence of the "$needle".
     *
     * @param string $str           <p>The input string.</p>
     * @param string $needle        <p>The string to look for.</p>
     * @param bool   $before_needle [optional] <p>Default: false</p>
     * @param string $encoding      [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_substr_first(
        string $str,
        string $needle,
        bool $before_needle = false,
        string $encoding = 'UTF-8'
    ): string {
        if ($str === '' || $needle === '') {
            return '';
        }

        if ($encoding === 'UTF-8') {
            if ($before_needle === true) {
                $part = \mb_strstr(
                    $str,
                    $needle,
                    $before_needle
                );
            } else {
                $part = \mb_strstr(
                    $str,
                    $needle
                );
            }
        } else {
            $part = self::strstr(
                $str,
                $needle,
                $before_needle,
                $encoding
            );
        }

        return $part === false ? '' : $part;
    }

    /**
     * Gets the substring after (or before via "$before_needle") the last occurrence of the "$needle".
     *
     * @param string $str           <p>The input string.</p>
     * @param string $needle        <p>The string to look for.</p>
     * @param bool   $before_needle [optional] <p>Default: false</p>
     * @param string $encoding      [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     */
    public static function str_substr_last(
        string $str,
        string $needle,
        bool $before_needle = false,
        string $encoding = 'UTF-8'
    ): string {
        if ($str === '' || $needle === '') {
            return '';
        }

        if ($encoding === 'UTF-8') {
            if ($before_needle === true) {
                $part = \mb_strrchr(
                    $str,
                    $needle,
                    $before_needle
                );
            } else {
                $part = \mb_strrchr(
                    $str,
                    $needle
                );
            }
        } else {
            $part = self::strrchr(
                $str,
                $needle,
                $before_needle,
                $encoding
            );
        }

        return $part === false ? '' : $part;
    }

    /**
     * Surrounds $str with the given substring.
     *
     * @param string $str
     * @param string $substring <p>The substring to add to both sides.</P>
     *
     * @return string
     *                <p>A string with the substring both prepended and appended.</p>
     */
    public static function str_surround(string $str, string $substring): string
    {
        return $substring . $str . $substring;
    }

    /**
     * Returns a trimmed string with the first letter of each word capitalized.
     * Also accepts an array, $ignore, allowing you to list words not to be
     * capitalized.
     *
     * @param string              $str
     * @param array|string[]|null $ignore                        [optional] <p>An array of words not to capitalize or null.
     *                                                           Default: null</p>
     * @param string              $encoding                      [optional] <p>Default: 'UTF-8'</p>
     * @param bool                $clean_utf8                    [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string|null         $lang                          [optional] <p>Set the language for special cases: az, el, lt,
     *                                                           tr</p>
     * @param bool                $try_to_keep_the_string_length [optional] <p>true === try to keep the string length: e.g. ẞ ->
     *                                                           ß</p>
     * @param bool                $use_trim_first                [optional] <p>true === trim the input string, first</p>
     * @param string|null         $word_define_chars             [optional] <p>An string of chars that will be used as whitespace separator === words.</p>
     *
     * @return string
     *                <p>The titleized string.</p>
     */
    public static function str_titleize(
        string $str,
        array $ignore = null,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false,
        bool $use_trim_first = true,
        string $word_define_chars = null
    ): string {
        if ($str === '') {
            return '';
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if ($use_trim_first === true) {
            $str = \trim($str);
        }

        if ($clean_utf8 === true) {
            $str = self::clean($str);
        }

        $use_mb_functions = $lang === null && $try_to_keep_the_string_length === false;

        if ($word_define_chars) {
            $word_define_chars = \preg_quote($word_define_chars, '/');
        } else {
            $word_define_chars = '';
        }

        $str = (string) \preg_replace_callback(
            '/([^\\s' . $word_define_chars . ']+)/u',
            static function (array $match) use ($try_to_keep_the_string_length, $lang, $ignore, $use_mb_functions, $encoding): string {
                if ($ignore !== null && \in_array($match[0], $ignore, true)) {
                    return $match[0];
                }

                if ($use_mb_functions === true) {
                    if ($encoding === 'UTF-8') {
                        return \mb_strtoupper(\mb_substr($match[0], 0, 1))
                               . \mb_strtolower(\mb_substr($match[0], 1));
                    }

                    return \mb_strtoupper(\mb_substr($match[0], 0, 1, $encoding), $encoding)
                           . \mb_strtolower(\mb_substr($match[0], 1, null, $encoding), $encoding);
                }

                return self::ucfirst(
                    self::strtolower(
                        $match[0],
                        $encoding,
                        false,
                        $lang,
                        $try_to_keep_the_string_length
                    ),
                    $encoding,
                    false,
                    $lang,
                    $try_to_keep_the_string_length
                );
            },
            $str
        );

        return $str;
    }

    /**
     * Returns a trimmed string in proper title case.
     *
     * Also accepts an array, $ignore, allowing you to list words not to be
     * capitalized.
     *
     * Adapted from John Gruber's script.
     *
     * @see https://gist.github.com/gruber/9f9e8650d68b13ce4d78
     *
     * @param string $str
     * @param array  $ignore   <p>An array of words not to capitalize.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string
     *                <p>The titleized string.</p>
     */
    public static function str_titleize_for_humans(
        string $str,
        array $ignore = [],
        string $encoding = 'UTF-8'
    ): string {
        if ($str === '') {
            return '';
        }

        $small_words = [
            '(?<!q&)a',
            'an',
            'and',
            'as',
            'at(?!&t)',
            'but',
            'by',
            'en',
            'for',
            'if',
            'in',
            'of',
            'on',
            'or',
            'the',
            'to',
            'v[.]?',
            'via',
            'vs[.]?',
        ];

        if ($ignore !== []) {
            $small_words = \array_merge($small_words, $ignore);
        }

        $small_words_rx = \implode('|', $small_words);
        $apostrophe_rx = '(?x: [\'’] [[:lower:]]* )?';

        $str = \trim($str);

        if (self::has_lowercase($str) === false) {
            $str = self::strtolower($str, $encoding);
        }

        // the main substitutions
        $str = (string) \preg_replace_callback(
            '~\\b (_*) (?:                                                         # 1. Leading underscore and
                        ( (?<=[ ][/\\\\]) [[:alpha:]]+ [-_[:alpha:]/\\\\]+ |              # 2. file path or 
                          [-_[:alpha:]]+ [@.:] [-_[:alpha:]@.:/]+ ' . $apostrophe_rx . ' ) #    URL, domain, or email
                        |
                        ( (?i: ' . $small_words_rx . ' ) ' . $apostrophe_rx . ' )            # 3. or small word (case-insensitive)
                        |
                        ( [[:alpha:]] [[:lower:]\'’()\[\]{}]* ' . $apostrophe_rx . ' )     # 4. or word w/o internal caps
                        |
                        ( [[:alpha:]] [[:alpha:]\'’()\[\]{}]* ' . $apostrophe_rx . ' )     # 5. or some other word
                      ) (_*) \\b                                                          # 6. With trailing underscore
                    ~ux',
            /**
             * @param string[] $matches
             *
             * @return string
             */
            static function (array $matches) use ($encoding): string {
                // preserve leading underscore
                $str = $matches[1];
                if ($matches[2]) {
                    // preserve URLs, domains, emails and file paths
                    $str .= $matches[2];
                } elseif ($matches[3]) {
                    // lower-case small words
                    $str .= self::strtolower($matches[3], $encoding);
                } elseif ($matches[4]) {
                    // capitalize word w/o internal caps
                    $str .= static::ucfirst($matches[4], $encoding);
                } else {
                    // preserve other kinds of word (iPhone)
                    $str .= $matches[5];
                }
                // preserve trailing underscore
                $str .= $matches[6];

                return $str;
            },
            $str
        );

        // Exceptions for small words: capitalize at start of title...
        $str = (string) \preg_replace_callback(
            '~(  \\A [[:punct:]]*            # start of title...
                      |  [:.;?!][ ]+                # or of subsentence...
                      |  [ ][\'"“‘(\[][ ]* )        # or of inserted subphrase...
                      ( ' . $small_words_rx . ' ) \\b # ...followed by small word
                     ~uxi',
            /**
             * @param string[] $matches
             *
             * @return string
             */
            static function (array $matches) use ($encoding): string {
                return $matches[1] . static::ucfirst($matches[2], $encoding);
            },
            $str
        );

        // ...and end of title
        $str = (string) \preg_replace_callback(
            '~\\b ( ' . $small_words_rx . ' ) # small word...
                      (?= [[:punct:]]* \Z          # ...at the end of the title...
                      |   [\'"’”)\]] [ ] )         # ...or of an inserted subphrase?
                     ~uxi',
            /**
             * @param string[] $matches
             *
             * @return string
             */
            static function (array $matches) use ($encoding): string {
                return static::ucfirst($matches[1], $encoding);
            },
            $str
        );

        // Exceptions for small words in hyphenated compound words.
        // e.g. "in-flight" -> In-Flight
        $str = (string) \preg_replace_callback(
            '~\\b
                        (?<! -)                   # Negative lookbehind for a hyphen; we do not want to match man-in-the-middle but do want (in-flight)
                        ( ' . $small_words_rx . ' )
                        (?= -[[:alpha:]]+)        # lookahead for "-someword"
                       ~uxi',
            /**
             * @param string[] $matches
             *
             * @return string
             */
            static function (array $matches) use ($encoding): string {
                return static::ucfirst($matches[1], $encoding);
            },
            $str
        );

        // e.g. "Stand-in" -> "Stand-In" (Stand is already capped at this point)
        $str = (string) \preg_replace_callback(
            '~\\b
                      (?<!…)                    # Negative lookbehind for a hyphen; we do not want to match man-in-the-middle but do want (stand-in)
                      ( [[:alpha:]]+- )         # $1 = first word and hyphen, should already be properly capped
                      ( ' . $small_words_rx . ' ) # ...followed by small word
                      (?!	- )                 # Negative lookahead for another -
                     ~uxi',
            /**
             * @param string[] $matches
             *
             * @return string
             */
            static function (array $matches) use ($encoding): string {
                return $matches[1] . static::ucfirst($matches[2], $encoding);
            },
            $str
        );

        return $str;
    }

    /**
     * Get a binary representation of a specific string.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return false|string
     *                      <p>false on error</p>
     */
    public static function str_to_binary(string $str)
    {
        /** @var array|false $value - needed for PhpStan (stubs error) */
        $value = \unpack('H*', $str);
        if ($value === false) {
            return false;
        }

        /** @noinspection OffsetOperationsInspection */
        return \base_convert($value[1], 16, 2);
    }

    /**
     * @param string   $str
     * @param bool     $remove_empty_values <p>Remove empty values.</p>
     * @param int|null $remove_short_values <p>The min. string length or null to disable</p>
     *
     * @return string[]
     */
    public static function str_to_lines(string $str, bool $remove_empty_values = false, int $remove_short_values = null): array
    {
        if ($str === '') {
            return $remove_empty_values === true ? [] : [''];
        }

        if (self::$SUPPORT['mbstring'] === true) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            $return = \mb_split("[\r\n]{1,2}", $str);
        } else {
            $return = \preg_split("/[\r\n]{1,2}/u", $str);
        }

        if ($return === false) {
            return $remove_empty_values === true ? [] : [''];
        }

        if (
            $remove_short_values === null
            &&
            $remove_empty_values === false
        ) {
            return $return;
        }

        return self::reduce_string_array(
            $return,
            $remove_empty_values,
            $remove_short_values
        );
    }

    /**
     * Convert a string into an array of words.
     *
     * @param string   $str
     * @param string   $char_list           <p>Additional chars for the definition of "words".</p>
     * @param bool     $remove_empty_values <p>Remove empty values.</p>
     * @param int|null $remove_short_values <p>The min. string length or null to disable</p>
     *
     * @return string[]
     */
    public static function str_to_words(
        string $str,
        string $char_list = '',
        bool $remove_empty_values = false,
        int $remove_short_values = null
    ): array {
        if ($str === '') {
            return $remove_empty_values === true ? [] : [''];
        }

        $char_list = self::rxClass($char_list, '\pL');

        $return = \preg_split("/({$char_list}+(?:[\p{Pd}’']{$char_list}+)*)/u", $str, -1, \PREG_SPLIT_DELIM_CAPTURE);
        if ($return === false) {
            return $remove_empty_values === true ? [] : [''];
        }

        if (
            $remove_short_values === null
            &&
            $remove_empty_values === false
        ) {
            return $return;
        }

        $tmp_return = self::reduce_string_array(
            $return,
            $remove_empty_values,
            $remove_short_values
        );

        foreach ($tmp_return as &$item) {
            $item = (string) $item;
        }

        return $tmp_return;
    }

    /**
     * alias for "UTF8::to_ascii()"
     *
     * @param string $str
     * @param string $unknown
     * @param bool   $strict
     *
     * @return string
     *
     * @see UTF8::to_ascii()
     * @deprecated <p>please use "UTF8::to_ascii()"</p>
     */
    public static function str_transliterate(
        string $str,
        string $unknown = '?',
        bool $strict = false
    ): string {
        return self::to_ascii($str, $unknown, $strict);
    }

    /**
     * Truncates the string to a given length. If $substring is provided, and
     * truncating occurs, the string is further truncated so that the substring
     * may be appended without exceeding the desired length.
     *
     * @param string $str
     * @param int    $length    <p>Desired length of the truncated string.</p>
     * @param string $substring [optional] <p>The substring to append if it can fit. Default: ''</p>
     * @param string $encoding  [optional] <p>Default: 'UTF-8'</p>
     *
     * @return string
     *                <p>A string after truncating.</p>
     */
    public static function str_truncate(
        string $str,
        int $length,
        string $substring = '',
        string $encoding = 'UTF-8'
    ): string {
        if ($str === '') {
            return '';
        }

        if ($encoding === 'UTF-8') {
            if ($length >= (int) \mb_strlen($str)) {
                return $str;
            }

            if ($substring !== '') {
                $length -= (int) \mb_strlen($substring);

                /** @noinspection UnnecessaryCastingInspection */
                return (string) \mb_substr($str, 0, $length) . $substring;
            }

            /** @noinspection UnnecessaryCastingInspection */
            return (string) \mb_substr($str, 0, $length);
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        if ($length >= (int) self::strlen($str, $encoding)) {
            return $str;
        }

        if ($substring !== '') {
            $length -= (int) self::strlen($substring, $encoding);
        }

        return (
               (string) self::substr(
                   $str,
                   0,
                   $length,
                   $encoding
               )
               ) . $substring;
    }

    /**
     * Truncates the string to a given length, while ensuring that it does not
     * split words. If $substring is provided, and truncating occurs, the
     * string is further truncated so that the substring may be appended without
     * exceeding the desired length.
     *
     * @param string $str
     * @param int    $length                                 <p>Desired length of the truncated string.</p>
     * @param string $substring                              [optional] <p>The substring to append if it can fit. Default:
     *                                                       ''</p>
     * @param string $encoding                               [optional] <p>Default: 'UTF-8'</p>
     * @param bool   $ignore_do_not_split_words_for_one_word [optional] <p>Default: false</p>
     *
     * @return string
     *                <p>A string after truncating.</p>
     */
    public static function str_truncate_safe(
        string $str,
        int $length,
        string $substring = '',
        string $encoding = 'UTF-8',
        bool $ignore_do_not_split_words_for_one_word = false
    ): string {
        if ($str === '' || $length <= 0) {
            return $substring;
        }

        if ($encoding === 'UTF-8') {
            if ($length >= (int) \mb_strlen($str)) {
                return $str;
            }

            // need to further trim the string so we can append the substring
            $length -= (int) \mb_strlen($substring);
            if ($length <= 0) {
                return $substring;
            }

            /** @var false|string $truncated - needed for PhpStan (stubs error) */
            $truncated = \mb_substr($str, 0, $length);
            if ($truncated === false) {
                return '';
            }

            // if the last word was truncated
            $space_position = \mb_strpos($str, ' ', $length - 1);
            if ($space_position !== $length) {
                // find pos of the last occurrence of a space, get up to that
                $last_position = \mb_strrpos($truncated, ' ', 0);

                if (
                    $last_position !== false
                    ||
                    ($space_position !== false && $ignore_do_not_split_words_for_one_word === false)
                ) {
                    $truncated = (string) \mb_substr($truncated, 0, (int) $last_position);
                }
            }
        } else {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            if ($length >= (int) self::strlen($str, $encoding)) {
                return $str;
            }

            // need to further trim the string so we can append the substring
            $length -= (int) self::strlen($substring, $encoding);
            if ($length <= 0) {
                return $substring;
            }

            $truncated = self::substr($str, 0, $length, $encoding);

            if ($truncated === false) {
                return '';
            }

            // if the last word was truncated
            $space_position = self::strpos($str, ' ', $length - 1, $encoding);
            if ($space_position !== $length) {
                // find pos of the last occurrence of a space, get up to that
                $last_position = self::strrpos($truncated, ' ', 0, $encoding);

                if (
                    $last_position !== false
                    ||
                    ($space_position !== false && $ignore_do_not_split_words_for_one_word === false)
                ) {
                    $truncated = (string) self::substr($truncated, 0, (int) $last_position, $encoding);
                }
            }
        }

        return $truncated . $substring;
    }

    /**
     * Returns a lowercase and trimmed string separated by underscores.
     * Underscores are inserted before uppercase characters (with the exception
     * of the first character of the string), and in place of spaces as well as
     * dashes.
     *
     * @param string $str
     *
     * @return string
     *                <p>The underscored string.</p>
     */
    public static function str_underscored(string $str): string
    {
        return self::str_delimit($str, '_');
    }

    /**
     * Returns an UpperCamelCase version of the supplied string. It trims
     * surrounding spaces, capitalizes letters following digits, spaces, dashes
     * and underscores, and removes spaces, dashes, underscores.
     *
     * @param string      $str                           <p>The input string.</p>
     * @param string      $encoding                      [optional] <p>Default: 'UTF-8'</p>
     * @param bool        $clean_utf8                    [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string|null $lang                          [optional] <p>Set the language for special cases: az, el, lt, tr</p>
     * @param bool        $try_to_keep_the_string_length [optional] <p>true === try to keep the string length: e.g. ẞ -> ß</p>
     *
     * @return string
     *                <p>A string in UpperCamelCase.</p>
     */
    public static function str_upper_camelize(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        return self::ucfirst(self::str_camelize($str, $encoding), $encoding, $clean_utf8, $lang, $try_to_keep_the_string_length);
    }

    /**
     * alias for "UTF8::ucfirst()"
     *
     * @param string      $str
     * @param string      $encoding
     * @param bool        $clean_utf8
     * @param string|null $lang
     * @param bool        $try_to_keep_the_string_length
     *
     * @return string
     *
     * @see UTF8::ucfirst()
     * @deprecated <p>please use "UTF8::ucfirst()"</p>
     */
    public static function str_upper_first(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        return self::ucfirst(
            $str,
            $encoding,
            $clean_utf8,
            $lang,
            $try_to_keep_the_string_length
        );
    }

    /**
     * Get the number of words in a specific string.
     *
     * @param string $str       <p>The input string.</p>
     * @param int    $format    [optional] <p>
     *                          <strong>0</strong> => return a number of words (default)<br>
     *                          <strong>1</strong> => return an array of words<br>
     *                          <strong>2</strong> => return an array of words with word-offset as key
     *                          </p>
     * @param string $char_list [optional] <p>Additional chars that contains to words and do not start a new word.</p>
     *
     * @return int|string[] The number of words in the string
     */
    public static function str_word_count(string $str, int $format = 0, string $char_list = '')
    {
        $str_parts = self::str_to_words($str, $char_list);

        $len = \count($str_parts);

        if ($format === 1) {
            $number_of_words = [];
            for ($i = 1; $i < $len; $i += 2) {
                $number_of_words[] = $str_parts[$i];
            }
        } elseif ($format === 2) {
            $number_of_words = [];
            $offset = (int) self::strlen($str_parts[0]);
            for ($i = 1; $i < $len; $i += 2) {
                $number_of_words[$offset] = $str_parts[$i];
                $offset += (int) self::strlen($str_parts[$i]) + (int) self::strlen($str_parts[$i + 1]);
            }
        } else {
            $number_of_words = (int) (($len - 1) / 2);
        }

        return $number_of_words;
    }

    /**
     * Case-insensitive string comparison.
     *
     * INFO: Case-insensitive version of UTF8::strcmp()
     *
     * @param string $str1     <p>The first string.</p>
     * @param string $str2     <p>The second string.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return int
     *             <strong>&lt; 0</strong> if str1 is less than str2;<br>
     *             <strong>&gt; 0</strong> if str1 is greater than str2,<br>
     *             <strong>0</strong> if they are equal
     */
    public static function strcasecmp(
        string $str1,
        string $str2,
        string $encoding = 'UTF-8'
    ): int {
        return self::strcmp(
            self::strtocasefold(
                $str1,
                true,
                false,
                $encoding,
                null,
                false
            ),
            self::strtocasefold(
                $str2,
                true,
                false,
                $encoding,
                null,
                false
            )
        );
    }

    /**
     * alias for "UTF8::strstr()"
     *
     * @param string $haystack
     * @param string $needle
     * @param bool   $before_needle
     * @param string $encoding
     * @param bool   $clean_utf8
     *
     * @return false|string
     *
     * @see UTF8::strstr()
     * @deprecated <p>please use "UTF8::strstr()"</p>
     */
    public static function strchr(
        string $haystack,
        string $needle,
        bool $before_needle = false,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        return self::strstr(
            $haystack,
            $needle,
            $before_needle,
            $encoding,
            $clean_utf8
        );
    }

    /**
     * Case-sensitive string comparison.
     *
     * @param string $str1 <p>The first string.</p>
     * @param string $str2 <p>The second string.</p>
     *
     * @return int
     *             <strong>&lt; 0</strong> if str1 is less than str2<br>
     *             <strong>&gt; 0</strong> if str1 is greater than str2<br>
     *             <strong>0</strong> if they are equal
     */
    public static function strcmp(string $str1, string $str2): int
    {
        if ($str1 === $str2) {
            return 0;
        }

        return \strcmp(
            \Normalizer::normalize($str1, \Normalizer::NFD),
            \Normalizer::normalize($str2, \Normalizer::NFD)
        );
    }

    /**
     * Find length of initial segment not matching mask.
     *
     * @param string $str
     * @param string $char_list
     * @param int    $offset
     * @param int    $length
     * @param string $encoding  [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return int
     */
    public static function strcspn(
        string $str,
        string $char_list,
        int $offset = null,
        int $length = null,
        string $encoding = 'UTF-8'
    ): int {
        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if ($char_list === '') {
            return (int) self::strlen($str, $encoding);
        }

        if ($offset !== null || $length !== null) {
            if ($encoding === 'UTF-8') {
                if ($length === null) {
                    /** @noinspection UnnecessaryCastingInspection */
                    $str_tmp = \mb_substr($str, (int) $offset);
                } else {
                    /** @noinspection UnnecessaryCastingInspection */
                    $str_tmp = \mb_substr($str, (int) $offset, $length);
                }
            } else {
                /** @noinspection UnnecessaryCastingInspection */
                $str_tmp = self::substr($str, (int) $offset, $length, $encoding);
            }

            if ($str_tmp === false) {
                return 0;
            }

            /** @noinspection CallableParameterUseCaseInTypeContextInspection - FP */
            $str = $str_tmp;
        }

        if ($str === '') {
            return 0;
        }

        $matches = [];
        if (\preg_match('/^(.*?)' . self::rxClass($char_list) . '/us', $str, $matches)) {
            $return = self::strlen($matches[1], $encoding);
            if ($return === false) {
                return 0;
            }

            return $return;
        }

        return (int) self::strlen($str, $encoding);
    }

    /**
     * alias for "UTF8::stristr()"
     *
     * @param string $haystack
     * @param string $needle
     * @param bool   $before_needle
     * @param string $encoding
     * @param bool   $clean_utf8
     *
     * @return false|string
     *
     * @see UTF8::stristr()
     * @deprecated <p>please use "UTF8::stristr()"</p>
     */
    public static function strichr(
        string $haystack,
        string $needle,
        bool $before_needle = false,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        return self::stristr(
            $haystack,
            $needle,
            $before_needle,
            $encoding,
            $clean_utf8
        );
    }

    /**
     * Create a UTF-8 string from code points.
     *
     * INFO: opposite to UTF8::codepoints()
     *
     * @param array $array <p>Integer or Hexadecimal codepoints.</p>
     *
     * @return string
     *                <p>A UTF-8 encoded string.</p>
     */
    public static function string(array $array): string
    {
        if ($array === []) {
            return  '';
        }

        $str = '';
        foreach ($array as $strPart) {
            $str .= '&#' . (int) $strPart . ';';
        }

        return self::html_entity_decode($str, \ENT_QUOTES | \ENT_HTML5);
    }

    /**
     * Checks if string starts with "BOM" (Byte Order Mark Character) character.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return bool
     *              <strong>true</strong> if the string has BOM at the start,<br>
     *              <strong>false</strong> otherwise
     */
    public static function string_has_bom(string $str): bool
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        foreach (self::$BOM as $bom_string => &$bom_byte_length) {
            if (\strpos($str, $bom_string) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Strip HTML and PHP tags from a string + clean invalid UTF-8.
     *
     * @see http://php.net/manual/en/function.strip-tags.php
     *
     * @param string $str            <p>
     *                               The input string.
     *                               </p>
     * @param string $allowable_tags [optional] <p>
     *                               You can use the optional second parameter to specify tags which should
     *                               not be stripped.
     *                               </p>
     *                               <p>
     *                               HTML comments and PHP tags are also stripped. This is hardcoded and
     *                               can not be changed with allowable_tags.
     *                               </p>
     * @param bool   $clean_utf8     [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return string
     *                <p>The stripped string.</p>
     */
    public static function strip_tags(
        string $str,
        string $allowable_tags = null,
        bool $clean_utf8 = false
    ): string {
        if ($str === '') {
            return '';
        }

        if ($clean_utf8 === true) {
            $str = self::clean($str);
        }

        if ($allowable_tags === null) {
            return \strip_tags($str);
        }

        return \strip_tags($str, $allowable_tags);
    }

    /**
     * Strip all whitespace characters. This includes tabs and newline
     * characters, as well as multibyte whitespace such as the thin space
     * and ideographic space.
     *
     * @param string $str
     *
     * @return string
     */
    public static function strip_whitespace(string $str): string
    {
        if ($str === '') {
            return '';
        }

        return (string) \preg_replace('/[[:space:]]+/u', '', $str);
    }

    /**
     * Find the position of the first occurrence of a substring in a string, case-insensitive.
     *
     * @see http://php.net/manual/en/function.mb-stripos.php
     *
     * @param string $haystack   <p>The string from which to get the position of the first occurrence of needle.</p>
     * @param string $needle     <p>The string to find in haystack.</p>
     * @param int    $offset     [optional] <p>The position in haystack to start searching.</p>
     * @param string $encoding   [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool   $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|int
     *                   Return the <strong>(int)</strong> numeric position of the first occurrence of needle in the
     *                   haystack string,<br> or <strong>false</strong> if needle is not found
     */
    public static function stripos(
        string $haystack,
        string $needle,
        int $offset = 0,
        $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $haystack = self::clean($haystack);
            $needle = self::clean($needle);
        }

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_stripos($haystack, $needle, $offset);
            }

            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            return \mb_stripos($haystack, $needle, $offset, $encoding);
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        if (
            $encoding === 'UTF-8' // INFO: "grapheme_stripos()" can't handle other encodings
            &&
            $offset >= 0 // grapheme_stripos() can't handle negative offset
            &&
            self::$SUPPORT['intl'] === true
        ) {
            $return_tmp = \grapheme_stripos($haystack, $needle, $offset);
            if ($return_tmp !== false) {
                return $return_tmp;
            }
        }

        //
        // fallback for ascii only
        //

        if (ASCII::is_ascii($haystack . $needle)) {
            return \stripos($haystack, $needle, $offset);
        }

        //
        // fallback via vanilla php
        //

        $haystack = self::strtocasefold($haystack, true, false, $encoding, null, false);
        $needle = self::strtocasefold($needle, true, false, $encoding, null, false);

        return self::strpos($haystack, $needle, $offset, $encoding);
    }

    /**
     * Returns all of haystack starting from and including the first occurrence of needle to the end.
     *
     * @param string $haystack      <p>The input string. Must be valid UTF-8.</p>
     * @param string $needle        <p>The string to look for. Must be valid UTF-8.</p>
     * @param bool   $before_needle [optional] <p>
     *                              If <b>TRUE</b>, it returns the part of the
     *                              haystack before the first occurrence of the needle (excluding the needle).
     *                              </p>
     * @param string $encoding      [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool   $clean_utf8    [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|string
     *                      <p>A sub-string,<br>or <strong>false</strong> if needle is not found.</p>
     */
    public static function stristr(
        string $haystack,
        string $needle,
        bool $before_needle = false,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $needle = self::clean($needle);
            $haystack = self::clean($haystack);
        }

        if (!$needle) {
            return $haystack;
        }

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_stristr($haystack, $needle, $before_needle);
            }

            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            return \mb_stristr($haystack, $needle, $before_needle, $encoding);
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        if (
            $encoding !== 'UTF-8'
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::stristr() without mbstring cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        if (
            $encoding === 'UTF-8' // INFO: "grapheme_stristr()" can't handle other encodings
            &&
            self::$SUPPORT['intl'] === true
        ) {
            $return_tmp = \grapheme_stristr($haystack, $needle, $before_needle);
            if ($return_tmp !== false) {
                return $return_tmp;
            }
        }

        if (ASCII::is_ascii($needle . $haystack)) {
            return \stristr($haystack, $needle, $before_needle);
        }

        \preg_match('/^(.*?)' . \preg_quote($needle, '/') . '/usi', $haystack, $match);

        if (!isset($match[1])) {
            return false;
        }

        if ($before_needle) {
            return $match[1];
        }

        return self::substr($haystack, (int) self::strlen($match[1], $encoding), null, $encoding);
    }

    /**
     * Get the string length, not the byte-length!
     *
     * @see http://php.net/manual/en/function.mb-strlen.php
     *
     * @param string $str        <p>The string being checked for length.</p>
     * @param string $encoding   [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool   $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|int
     *                   <p>
     *                   The number <strong>(int)</strong> of characters in the string $str having character encoding
     *                   $encoding.
     *                   (One multi-byte character counted as +1).
     *                   <br>
     *                   Can return <strong>false</strong>, if e.g. mbstring is not installed and we process invalid
     *                   chars.
     *                   </p>
     */
    public static function strlen(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        if ($str === '') {
            return 0;
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if ($clean_utf8 === true) {
            // "mb_strlen" and "\iconv_strlen" returns wrong length,
            // if invalid characters are found in $str
            $str = self::clean($str);
        }

        //
        // fallback via mbstring
        //

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_strlen($str);
            }

            return \mb_strlen($str, $encoding);
        }

        //
        // fallback for binary || ascii only
        //

        if (
            $encoding === 'CP850'
            ||
            $encoding === 'ASCII'
        ) {
            return \strlen($str);
        }

        if (
            $encoding !== 'UTF-8'
            &&
            self::$SUPPORT['mbstring'] === false
            &&
            self::$SUPPORT['iconv'] === false
        ) {
            \trigger_error('UTF8::strlen() without mbstring / iconv cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        //
        // fallback via iconv
        //

        if (self::$SUPPORT['iconv'] === true) {
            $return_tmp = \iconv_strlen($str, $encoding);
            if ($return_tmp !== false) {
                return $return_tmp;
            }
        }

        //
        // fallback via intl
        //

        if (
            $encoding === 'UTF-8' // INFO: "grapheme_strlen()" can't handle other encodings
            &&
            self::$SUPPORT['intl'] === true
        ) {
            $return_tmp = \grapheme_strlen($str);
            if ($return_tmp !== null) {
                return $return_tmp;
            }
        }

        //
        // fallback for ascii only
        //

        if (ASCII::is_ascii($str)) {
            return \strlen($str);
        }

        //
        // fallback via vanilla php
        //

        \preg_match_all('/./us', $str, $parts);

        $return_tmp = \count($parts[0]);
        if ($return_tmp === 0) {
            return false;
        }

        return $return_tmp;
    }

    /**
     * Get string length in byte.
     *
     * @param string $str
     *
     * @return int
     */
    public static function strlen_in_byte(string $str): int
    {
        if ($str === '') {
            return 0;
        }

        if (self::$SUPPORT['mbstring_func_overload'] === true) {
            // "mb_" is available if overload is used, so use it ...
            return \mb_strlen($str, 'CP850'); // 8-BIT
        }

        return \strlen($str);
    }

    /**
     * Case-insensitive string comparisons using a "natural order" algorithm.
     *
     * INFO: natural order version of UTF8::strcasecmp()
     *
     * @param string $str1     <p>The first string.</p>
     * @param string $str2     <p>The second string.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return int
     *             <strong>&lt; 0</strong> if str1 is less than str2<br>
     *             <strong>&gt; 0</strong> if str1 is greater than str2<br>
     *             <strong>0</strong> if they are equal
     */
    public static function strnatcasecmp(string $str1, string $str2, string $encoding = 'UTF-8'): int
    {
        return self::strnatcmp(
            self::strtocasefold($str1, true, false, $encoding, null, false),
            self::strtocasefold($str2, true, false, $encoding, null, false)
        );
    }

    /**
     * String comparisons using a "natural order" algorithm
     *
     * INFO: natural order version of UTF8::strcmp()
     *
     * @see http://php.net/manual/en/function.strnatcmp.php
     *
     * @param string $str1 <p>The first string.</p>
     * @param string $str2 <p>The second string.</p>
     *
     * @return int
     *             <strong>&lt; 0</strong> if str1 is less than str2;<br>
     *             <strong>&gt; 0</strong> if str1 is greater than str2;<br>
     *             <strong>0</strong> if they are equal
     */
    public static function strnatcmp(string $str1, string $str2): int
    {
        if ($str1 === $str2) {
            return 0;
        }

        return \strnatcmp(
            (string) self::strtonatfold($str1),
            (string) self::strtonatfold($str2)
        );
    }

    /**
     * Case-insensitive string comparison of the first n characters.
     *
     * @see http://php.net/manual/en/function.strncasecmp.php
     *
     * @param string $str1     <p>The first string.</p>
     * @param string $str2     <p>The second string.</p>
     * @param int    $len      <p>The length of strings to be used in the comparison.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return int
     *             <strong>&lt; 0</strong> if <i>str1</i> is less than <i>str2</i>;<br>
     *             <strong>&gt; 0</strong> if <i>str1</i> is greater than <i>str2</i>;<br>
     *             <strong>0</strong> if they are equal
     */
    public static function strncasecmp(
        string $str1,
        string $str2,
        int $len,
        string $encoding = 'UTF-8'
    ): int {
        return self::strncmp(
            self::strtocasefold($str1, true, false, $encoding, null, false),
            self::strtocasefold($str2, true, false, $encoding, null, false),
            $len
        );
    }

    /**
     * String comparison of the first n characters.
     *
     * @see http://php.net/manual/en/function.strncmp.php
     *
     * @param string $str1     <p>The first string.</p>
     * @param string $str2     <p>The second string.</p>
     * @param int    $len      <p>Number of characters to use in the comparison.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return int
     *             <strong>&lt; 0</strong> if <i>str1</i> is less than <i>str2</i>;<br>
     *             <strong>&gt; 0</strong> if <i>str1</i> is greater than <i>str2</i>;<br>
     *             <strong>0</strong> if they are equal
     */
    public static function strncmp(
        string $str1,
        string $str2,
        int $len,
        string $encoding = 'UTF-8'
    ): int {
        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if ($encoding === 'UTF-8') {
            $str1 = (string) \mb_substr($str1, 0, $len);
            $str2 = (string) \mb_substr($str2, 0, $len);
        } else {
            $str1 = (string) self::substr($str1, 0, $len, $encoding);
            $str2 = (string) self::substr($str2, 0, $len, $encoding);
        }

        return self::strcmp($str1, $str2);
    }

    /**
     * Search a string for any of a set of characters.
     *
     * @see http://php.net/manual/en/function.strpbrk.php
     *
     * @param string $haystack  <p>The string where char_list is looked for.</p>
     * @param string $char_list <p>This parameter is case-sensitive.</p>
     *
     * @return false|string string starting from the character found, or false if it is not found
     */
    public static function strpbrk(string $haystack, string $char_list)
    {
        if ($haystack === '' || $char_list === '') {
            return false;
        }

        if (\preg_match('/' . self::rxClass($char_list) . '/us', $haystack, $m)) {
            return \substr($haystack, (int) \strpos($haystack, $m[0]));
        }

        return false;
    }

    /**
     * Find the position of the first occurrence of a substring in a string.
     *
     * @see http://php.net/manual/en/function.mb-strpos.php
     *
     * @param string     $haystack   <p>The string from which to get the position of the first occurrence of needle.</p>
     * @param int|string $needle     <p>The string to find in haystack.<br>Or a code point as int.</p>
     * @param int        $offset     [optional] <p>The search offset. If it is not specified, 0 is used.</p>
     * @param string     $encoding   [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool       $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|int
     *                   The <strong>(int)</strong> numeric position of the first occurrence of needle in the haystack
     *                   string.<br> If needle is not found it returns false.
     */
    public static function strpos(
        string $haystack,
        $needle,
        int $offset = 0,
        $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        if ($haystack === '') {
            return false;
        }

        // iconv and mbstring do not support integer $needle
        if ((int) $needle === $needle) {
            $needle = (string) self::chr($needle);
        }
        $needle = (string) $needle;

        if ($needle === '') {
            return false;
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $needle = self::clean($needle);
            $haystack = self::clean($haystack);
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        //
        // fallback via mbstring
        //

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_strpos($haystack, $needle, $offset);
            }

            return \mb_strpos($haystack, $needle, $offset, $encoding);
        }

        //
        // fallback for binary || ascii only
        //
        if (
            $encoding === 'CP850'
            ||
            $encoding === 'ASCII'
        ) {
            return \strpos($haystack, $needle, $offset);
        }

        if (
            $encoding !== 'UTF-8'
            &&
            self::$SUPPORT['iconv'] === false
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::strpos() without mbstring / iconv cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        //
        // fallback via intl
        //

        if (
            $encoding === 'UTF-8' // INFO: "grapheme_strpos()" can't handle other encodings
            &&
            $offset >= 0 // grapheme_strpos() can't handle negative offset
            &&
            self::$SUPPORT['intl'] === true
        ) {
            $return_tmp = \grapheme_strpos($haystack, $needle, $offset);
            if ($return_tmp !== false) {
                return $return_tmp;
            }
        }

        //
        // fallback via iconv
        //

        if (
            $offset >= 0 // iconv_strpos() can't handle negative offset
            &&
            self::$SUPPORT['iconv'] === true
        ) {
            // ignore invalid negative offset to keep compatibility
            // with php < 5.5.35, < 5.6.21, < 7.0.6
            $return_tmp = \iconv_strpos($haystack, $needle, $offset > 0 ? $offset : 0, $encoding);
            if ($return_tmp !== false) {
                return $return_tmp;
            }
        }

        //
        // fallback for ascii only
        //

        if (ASCII::is_ascii($haystack . $needle)) {
            return \strpos($haystack, $needle, $offset);
        }

        //
        // fallback via vanilla php
        //

        $haystack_tmp = self::substr($haystack, $offset, null, $encoding);
        if ($haystack_tmp === false) {
            $haystack_tmp = '';
        }
        $haystack = (string) $haystack_tmp;

        if ($offset < 0) {
            $offset = 0;
        }

        $pos = \strpos($haystack, $needle);
        if ($pos === false) {
            return false;
        }

        if ($pos) {
            return $offset + (int) self::strlen(\substr($haystack, 0, $pos), $encoding);
        }

        return $offset + 0;
    }

    /**
     * Find the position of the first occurrence of a substring in a string.
     *
     * @param string $haystack <p>
     *                         The string being checked.
     *                         </p>
     * @param string $needle   <p>
     *                         The position counted from the beginning of haystack.
     *                         </p>
     * @param int    $offset   [optional] <p>
     *                         The search offset. If it is not specified, 0 is used.
     *                         </p>
     *
     * @return false|int The numeric position of the first occurrence of needle in the
     *                   haystack string. If needle is not found, it returns false.
     */
    public static function strpos_in_byte(string $haystack, string $needle, int $offset = 0)
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if (self::$SUPPORT['mbstring_func_overload'] === true) {
            // "mb_" is available if overload is used, so use it ...
            return \mb_strpos($haystack, $needle, $offset, 'CP850'); // 8-BIT
        }

        return \strpos($haystack, $needle, $offset);
    }

    /**
     * Find the last occurrence of a character in a string within another.
     *
     * @see http://php.net/manual/en/function.mb-strrchr.php
     *
     * @param string $haystack      <p>The string from which to get the last occurrence of needle.</p>
     * @param string $needle        <p>The string to find in haystack</p>
     * @param bool   $before_needle [optional] <p>
     *                              Determines which portion of haystack
     *                              this function returns.
     *                              If set to true, it returns all of haystack
     *                              from the beginning to the last occurrence of needle.
     *                              If set to false, it returns all of haystack
     *                              from the last occurrence of needle to the end,
     *                              </p>
     * @param string $encoding      [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool   $clean_utf8    [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|string the portion of haystack or false if needle is not found
     */
    public static function strrchr(
        string $haystack,
        string $needle,
        bool $before_needle = false,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $needle = self::clean($needle);
            $haystack = self::clean($haystack);
        }

        //
        // fallback via mbstring
        //

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_strrchr($haystack, $needle, $before_needle);
            }

            return \mb_strrchr($haystack, $needle, $before_needle, $encoding);
        }

        //
        // fallback for binary || ascii only
        //

        if (
            $before_needle === false
            &&
            (
                $encoding === 'CP850'
                ||
                $encoding === 'ASCII'
            )
        ) {
            return \strrchr($haystack, $needle);
        }

        if (
            $encoding !== 'UTF-8'
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::strrchr() without mbstring cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        //
        // fallback via iconv
        //

        if (self::$SUPPORT['iconv'] === true) {
            $needle_tmp = self::substr($needle, 0, 1, $encoding);
            if ($needle_tmp === false) {
                return false;
            }
            $needle = (string) $needle_tmp;

            $pos = \iconv_strrpos($haystack, $needle, $encoding);
            if ($pos === false) {
                return false;
            }

            if ($before_needle) {
                return self::substr($haystack, 0, $pos, $encoding);
            }

            return self::substr($haystack, $pos, null, $encoding);
        }

        //
        // fallback via vanilla php
        //

        $needle_tmp = self::substr($needle, 0, 1, $encoding);
        if ($needle_tmp === false) {
            return false;
        }
        $needle = (string) $needle_tmp;

        $pos = self::strrpos($haystack, $needle, 0, $encoding);
        if ($pos === false) {
            return false;
        }

        if ($before_needle) {
            return self::substr($haystack, 0, $pos, $encoding);
        }

        return self::substr($haystack, $pos, null, $encoding);
    }

    /**
     * Reverses characters order in the string.
     *
     * @param string $str      <p>The input string.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string the string with characters in the reverse sequence
     */
    public static function strrev(string $str, string $encoding = 'UTF-8'): string
    {
        if ($str === '') {
            return '';
        }

        // init
        $reversed = '';

        $str = self::emoji_encode($str, true);

        if ($encoding === 'UTF-8') {
            if (self::$SUPPORT['intl'] === true) {
                // try "grapheme" first: https://stackoverflow.com/questions/17496493/strrev-dosent-support-utf-8
                $i = (int) \grapheme_strlen($str);
                while ($i--) {
                    $reversed_tmp = \grapheme_substr($str, $i, 1);
                    if ($reversed_tmp !== false) {
                        $reversed .= $reversed_tmp;
                    }
                }
            } else {
                $i = (int) \mb_strlen($str);
                while ($i--) {
                    $reversed_tmp = \mb_substr($str, $i, 1);
                    if ($reversed_tmp !== false) {
                        $reversed .= $reversed_tmp;
                    }
                }
            }
        } else {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            $i = (int) self::strlen($str, $encoding);
            while ($i--) {
                $reversed_tmp = self::substr($str, $i, 1, $encoding);
                if ($reversed_tmp !== false) {
                    $reversed .= $reversed_tmp;
                }
            }
        }

        return self::emoji_decode($reversed, true);
    }

    /**
     * Find the last occurrence of a character in a string within another, case-insensitive.
     *
     * @see http://php.net/manual/en/function.mb-strrichr.php
     *
     * @param string $haystack      <p>The string from which to get the last occurrence of needle.</p>
     * @param string $needle        <p>The string to find in haystack.</p>
     * @param bool   $before_needle [optional] <p>
     *                              Determines which portion of haystack
     *                              this function returns.
     *                              If set to true, it returns all of haystack
     *                              from the beginning to the last occurrence of needle.
     *                              If set to false, it returns all of haystack
     *                              from the last occurrence of needle to the end,
     *                              </p>
     * @param string $encoding      [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool   $clean_utf8    [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|string the portion of haystack or<br>false if needle is not found
     */
    public static function strrichr(
        string $haystack,
        string $needle,
        bool $before_needle = false,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $needle = self::clean($needle);
            $haystack = self::clean($haystack);
        }

        //
        // fallback via mbstring
        //

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_strrichr($haystack, $needle, $before_needle);
            }

            return \mb_strrichr($haystack, $needle, $before_needle, $encoding);
        }

        //
        // fallback via vanilla php
        //

        $needle_tmp = self::substr($needle, 0, 1, $encoding);
        if ($needle_tmp === false) {
            return false;
        }
        $needle = (string) $needle_tmp;

        $pos = self::strripos($haystack, $needle, 0, $encoding);
        if ($pos === false) {
            return false;
        }

        if ($before_needle) {
            return self::substr($haystack, 0, $pos, $encoding);
        }

        return self::substr($haystack, $pos, null, $encoding);
    }

    /**
     * Find the position of the last occurrence of a substring in a string, case-insensitive.
     *
     * @param string     $haystack   <p>The string to look in.</p>
     * @param int|string $needle     <p>The string to look for.</p>
     * @param int        $offset     [optional] <p>Number of characters to ignore in the beginning or end.</p>
     * @param string     $encoding   [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool       $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|int
     *                   <p>The <strong>(int)</strong> numeric position of the last occurrence of needle in the haystack
     *                   string.<br>If needle is not found, it returns false.</p>
     */
    public static function strripos(
        string $haystack,
        $needle,
        int $offset = 0,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        if ($haystack === '') {
            return false;
        }

        // iconv and mbstring do not support integer $needle
        if ((int) $needle === $needle && $needle >= 0) {
            $needle = (string) self::chr($needle);
        }
        $needle = (string) $needle;

        if ($needle === '') {
            return false;
        }

        if ($clean_utf8 === true) {
            // mb_strripos() && iconv_strripos() is not tolerant to invalid characters
            $needle = self::clean($needle);
            $haystack = self::clean($haystack);
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        //
        // fallback via mbstrig
        //

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_strripos($haystack, $needle, $offset);
            }

            return \mb_strripos($haystack, $needle, $offset, $encoding);
        }

        //
        // fallback for binary || ascii only
        //

        if (
            $encoding === 'CP850'
            ||
            $encoding === 'ASCII'
        ) {
            return \strripos($haystack, $needle, $offset);
        }

        if (
            $encoding !== 'UTF-8'
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::strripos() without mbstring cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        //
        // fallback via intl
        //

        if (
            $encoding === 'UTF-8' // INFO: "grapheme_strripos()" can't handle other encodings
            &&
            $offset >= 0 // grapheme_strripos() can't handle negative offset
            &&
            self::$SUPPORT['intl'] === true
        ) {
            $return_tmp = \grapheme_strripos($haystack, $needle, $offset);
            if ($return_tmp !== false) {
                return $return_tmp;
            }
        }

        //
        // fallback for ascii only
        //

        if (ASCII::is_ascii($haystack . $needle)) {
            return \strripos($haystack, $needle, $offset);
        }

        //
        // fallback via vanilla php
        //

        $haystack = self::strtocasefold($haystack, true, false, $encoding);
        $needle = self::strtocasefold($needle, true, false, $encoding);

        return self::strrpos($haystack, $needle, $offset, $encoding, $clean_utf8);
    }

    /**
     * Finds position of last occurrence of a string within another, case-insensitive.
     *
     * @param string $haystack <p>
     *                         The string from which to get the position of the last occurrence
     *                         of needle.
     *                         </p>
     * @param string $needle   <p>
     *                         The string to find in haystack.
     *                         </p>
     * @param int    $offset   [optional] <p>
     *                         The position in haystack
     *                         to start searching.
     *                         </p>
     *
     * @return false|int
     *                   <p>eturn the numeric position of the last occurrence of needle in the
     *                   haystack string, or false if needle is not found.</p>
     */
    public static function strripos_in_byte(string $haystack, string $needle, int $offset = 0)
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if (self::$SUPPORT['mbstring_func_overload'] === true) {
            // "mb_" is available if overload is used, so use it ...
            return \mb_strripos($haystack, $needle, $offset, 'CP850'); // 8-BIT
        }

        return \strripos($haystack, $needle, $offset);
    }

    /**
     * Find the position of the last occurrence of a substring in a string.
     *
     * @see http://php.net/manual/en/function.mb-strrpos.php
     *
     * @param string     $haystack   <p>The string being checked, for the last occurrence of needle</p>
     * @param int|string $needle     <p>The string to find in haystack.<br>Or a code point as int.</p>
     * @param int        $offset     [optional] <p>May be specified to begin searching an arbitrary number of characters
     *                               into the string. Negative values will stop searching at an arbitrary point prior to
     *                               the end of the string.
     *                               </p>
     * @param string     $encoding   [optional] <p>Set the charset.</p>
     * @param bool       $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|int
     *                   <p>The <strong>(int)</strong> numeric position of the last occurrence of needle in the haystack
     *                   string.<br>If needle is not found, it returns false.</p>
     */
    public static function strrpos(
        string $haystack,
        $needle,
        int $offset = 0,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        if ($haystack === '') {
            return false;
        }

        // iconv and mbstring do not support integer $needle
        if ((int) $needle === $needle && $needle >= 0) {
            $needle = (string) self::chr($needle);
        }
        $needle = (string) $needle;

        if ($needle === '') {
            return false;
        }

        if ($clean_utf8 === true) {
            // mb_strrpos && iconv_strrpos is not tolerant to invalid characters
            $needle = self::clean($needle);
            $haystack = self::clean($haystack);
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        //
        // fallback via mbstring
        //

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_strrpos($haystack, $needle, $offset);
            }

            return \mb_strrpos($haystack, $needle, $offset, $encoding);
        }

        //
        // fallback for binary || ascii only
        //

        if (
            $encoding === 'CP850'
            ||
            $encoding === 'ASCII'
        ) {
            return \strrpos($haystack, $needle, $offset);
        }

        if (
            $encoding !== 'UTF-8'
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::strrpos() without mbstring cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        //
        // fallback via intl
        //

        if (
            $offset >= 0 // grapheme_strrpos() can't handle negative offset
            &&
            $encoding === 'UTF-8' // INFO: "grapheme_strrpos()" can't handle other encodings
            &&
            self::$SUPPORT['intl'] === true
        ) {
            $return_tmp = \grapheme_strrpos($haystack, $needle, $offset);
            if ($return_tmp !== false) {
                return $return_tmp;
            }
        }

        //
        // fallback for ascii only
        //

        if (ASCII::is_ascii($haystack . $needle)) {
            return \strrpos($haystack, $needle, $offset);
        }

        //
        // fallback via vanilla php
        //

        $haystack_tmp = null;
        if ($offset > 0) {
            $haystack_tmp = self::substr($haystack, $offset);
        } elseif ($offset < 0) {
            $haystack_tmp = self::substr($haystack, 0, $offset);
            $offset = 0;
        }

        if ($haystack_tmp !== null) {
            if ($haystack_tmp === false) {
                $haystack_tmp = '';
            }
            $haystack = (string) $haystack_tmp;
        }

        $pos = \strrpos($haystack, $needle);
        if ($pos === false) {
            return false;
        }

        /** @var false|string $str_tmp - needed for PhpStan (stubs error) */
        $str_tmp = \substr($haystack, 0, $pos);
        if ($str_tmp === false) {
            return false;
        }

        return $offset + (int) self::strlen($str_tmp);
    }

    /**
     * Find the position of the last occurrence of a substring in a string.
     *
     * @param string $haystack <p>
     *                         The string being checked, for the last occurrence
     *                         of needle.
     *                         </p>
     * @param string $needle   <p>
     *                         The string to find in haystack.
     *                         </p>
     * @param int    $offset   [optional] <p>May be specified to begin searching an arbitrary number of characters into
     *                         the string. Negative values will stop searching at an arbitrary point
     *                         prior to the end of the string.
     *                         </p>
     *
     * @return false|int
     *                   <p>The numeric position of the last occurrence of needle in the
     *                   haystack string. If needle is not found, it returns false.</p>
     */
    public static function strrpos_in_byte(string $haystack, string $needle, int $offset = 0)
    {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if (self::$SUPPORT['mbstring_func_overload'] === true) {
            // "mb_" is available if overload is used, so use it ...
            return \mb_strrpos($haystack, $needle, $offset, 'CP850'); // 8-BIT
        }

        return \strrpos($haystack, $needle, $offset);
    }

    /**
     * Finds the length of the initial segment of a string consisting entirely of characters contained within a given
     * mask.
     *
     * @param string $str      <p>The input string.</p>
     * @param string $mask     <p>The mask of chars</p>
     * @param int    $offset   [optional]
     * @param int    $length   [optional]
     * @param string $encoding [optional] <p>Set the charset.</p>
     *
     * @return false|int
     */
    public static function strspn(
        string $str,
        string $mask,
        int $offset = 0,
        int $length = null,
        string $encoding = 'UTF-8'
    ) {
        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if ($offset || $length !== null) {
            if ($encoding === 'UTF-8') {
                if ($length === null) {
                    $str = (string) \mb_substr($str, $offset);
                } else {
                    $str = (string) \mb_substr($str, $offset, $length);
                }
            } else {
                $str = (string) self::substr($str, $offset, $length, $encoding);
            }
        }

        if ($str === '' || $mask === '') {
            return 0;
        }

        $matches = [];

        return \preg_match('/^' . self::rxClass($mask) . '+/u', $str, $matches) ? (int) self::strlen($matches[0], $encoding) : 0;
    }

    /**
     * Returns part of haystack string from the first occurrence of needle to the end of haystack.
     *
     * @param string $haystack      <p>The input string. Must be valid UTF-8.</p>
     * @param string $needle        <p>The string to look for. Must be valid UTF-8.</p>
     * @param bool   $before_needle [optional] <p>
     *                              If <b>TRUE</b>, strstr() returns the part of the
     *                              haystack before the first occurrence of the needle (excluding the needle).
     *                              </p>
     * @param string $encoding      [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool   $clean_utf8    [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|string
     *                      A sub-string,<br>or <strong>false</strong> if needle is not found
     */
    public static function strstr(
        string $haystack,
        string $needle,
        bool $before_needle = false,
        string $encoding = 'UTF-8',
        $clean_utf8 = false
    ) {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $needle = self::clean($needle);
            $haystack = self::clean($haystack);
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        //
        // fallback via mbstring
        //

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_strstr($haystack, $needle, $before_needle);
            }

            return \mb_strstr($haystack, $needle, $before_needle, $encoding);
        }

        //
        // fallback for binary || ascii only
        //

        if (
            $encoding === 'CP850'
            ||
            $encoding === 'ASCII'
        ) {
            return \strstr($haystack, $needle, $before_needle);
        }

        if (
            $encoding !== 'UTF-8'
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::strstr() without mbstring cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        //
        // fallback via intl
        //

        if (
            $encoding === 'UTF-8' // INFO: "grapheme_strstr()" can't handle other encodings
            &&
            self::$SUPPORT['intl'] === true
        ) {
            $return_tmp = \grapheme_strstr($haystack, $needle, $before_needle);
            if ($return_tmp !== false) {
                return $return_tmp;
            }
        }

        //
        // fallback for ascii only
        //

        if (ASCII::is_ascii($haystack . $needle)) {
            return \strstr($haystack, $needle, $before_needle);
        }

        //
        // fallback via vanilla php
        //

        \preg_match('/^(.*?)' . \preg_quote($needle, '/') . '/us', $haystack, $match);

        if (!isset($match[1])) {
            return false;
        }

        if ($before_needle) {
            return $match[1];
        }

        return self::substr($haystack, (int) self::strlen($match[1]));
    }

    /**
     *  * Finds first occurrence of a string within another.
     *
     * @param string $haystack      <p>
     *                              The string from which to get the first occurrence
     *                              of needle.
     *                              </p>
     * @param string $needle        <p>
     *                              The string to find in haystack.
     *                              </p>
     * @param bool   $before_needle [optional] <p>
     *                              Determines which portion of haystack
     *                              this function returns.
     *                              If set to true, it returns all of haystack
     *                              from the beginning to the first occurrence of needle.
     *                              If set to false, it returns all of haystack
     *                              from the first occurrence of needle to the end,
     *                              </p>
     *
     * @return false|string
     *                      <p>The portion of haystack,
     *                      or false if needle is not found.</p>
     */
    public static function strstr_in_byte(
        string $haystack,
        string $needle,
        bool $before_needle = false
    ) {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if (self::$SUPPORT['mbstring_func_overload'] === true) {
            // "mb_" is available if overload is used, so use it ...
            return \mb_strstr($haystack, $needle, $before_needle, 'CP850'); // 8-BIT
        }

        return \strstr($haystack, $needle, $before_needle);
    }

    /**
     * Unicode transformation for case-less matching.
     *
     * @see http://unicode.org/reports/tr21/tr21-5.html
     *
     * @param string      $str        <p>The input string.</p>
     * @param bool        $full       [optional] <p>
     *                                <b>true</b>, replace full case folding chars (default)<br>
     *                                <b>false</b>, use only limited static array [UTF8::$COMMON_CASE_FOLD]
     *                                </p>
     * @param bool        $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string      $encoding   [optional] <p>Set the charset.</p>
     * @param string|null $lang       [optional] <p>Set the language for special cases: az, el, lt, tr</p>
     * @param bool        $lower      [optional] <p>Use lowercase string, otherwise use uppercase string. PS: uppercase
     *                                is for some languages better ...</p>
     *
     * @return string
     */
    public static function strtocasefold(
        string $str,
        bool $full = true,
        bool $clean_utf8 = false,
        string $encoding = 'UTF-8',
        string $lang = null,
        $lower = true
    ): string {
        if ($str === '') {
            return '';
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $str = self::clean($str);
        }

        $str = self::fixStrCaseHelper($str, $lower, $full);

        if ($lang === null && $encoding === 'UTF-8') {
            if ($lower === true) {
                return \mb_strtolower($str);
            }

            return \mb_strtoupper($str);
        }

        if ($lower === true) {
            return self::strtolower($str, $encoding, false, $lang);
        }

        return self::strtoupper($str, $encoding, false, $lang);
    }

    /**
     * Make a string lowercase.
     *
     * @see http://php.net/manual/en/function.mb-strtolower.php
     *
     * @param string      $str                           <p>The string being lowercased.</p>
     * @param string      $encoding                      [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool        $clean_utf8                    [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string|null $lang                          [optional] <p>Set the language for special cases: az, el, lt, tr</p>
     * @param bool        $try_to_keep_the_string_length [optional] <p>true === try to keep the string length: e.g. ẞ -> ß</p>
     *
     * @return string
     *                <p>String with all alphabetic characters converted to lowercase.</p>
     */
    public static function strtolower(
        $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        // init
        $str = (string) $str;

        if ($str === '') {
            return '';
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $str = self::clean($str);
        }

        // hack for old php version or for the polyfill ...
        if ($try_to_keep_the_string_length === true) {
            $str = self::fixStrCaseHelper($str, true);
        }

        if ($lang === null && $encoding === 'UTF-8') {
            return \mb_strtolower($str);
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        if ($lang !== null) {
            if (self::$SUPPORT['intl'] === true) {
                if (self::$INTL_TRANSLITERATOR_LIST === null) {
                    self::$INTL_TRANSLITERATOR_LIST = self::getData('transliterator_list');
                }

                $language_code = $lang . '-Lower';
                if (!\in_array($language_code, self::$INTL_TRANSLITERATOR_LIST, true)) {
                    \trigger_error('UTF8::strtolower() cannot handle special language: ' . $lang . ' | supported: ' . \print_r(self::$INTL_TRANSLITERATOR_LIST, true), \E_USER_WARNING);

                    $language_code = 'Any-Lower';
                }

                /** @noinspection PhpComposerExtensionStubsInspection */
                /** @noinspection UnnecessaryCastingInspection */
                return (string) \transliterator_transliterate($language_code, $str);
            }

            \trigger_error('UTF8::strtolower() without intl cannot handle the "lang" parameter: ' . $lang, \E_USER_WARNING);
        }

        // always fallback via symfony polyfill
        return \mb_strtolower($str, $encoding);
    }

    /**
     * Make a string uppercase.
     *
     * @see http://php.net/manual/en/function.mb-strtoupper.php
     *
     * @param string      $str                           <p>The string being uppercased.</p>
     * @param string      $encoding                      [optional] <p>Set the charset.</p>
     * @param bool        $clean_utf8                    [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string|null $lang                          [optional] <p>Set the language for special cases: az, el, lt, tr</p>
     * @param bool        $try_to_keep_the_string_length [optional] <p>true === try to keep the string length: e.g. ẞ -> ß</p>
     *
     * @return string
     *                <p>String with all alphabetic characters converted to uppercase.</p>
     */
    public static function strtoupper(
        $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        // init
        $str = (string) $str;

        if ($str === '') {
            return '';
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $str = self::clean($str);
        }

        // hack for old php version or for the polyfill ...
        if ($try_to_keep_the_string_length === true) {
            $str = self::fixStrCaseHelper($str, false);
        }

        if ($lang === null && $encoding === 'UTF-8') {
            return \mb_strtoupper($str);
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        if ($lang !== null) {
            if (self::$SUPPORT['intl'] === true) {
                if (self::$INTL_TRANSLITERATOR_LIST === null) {
                    self::$INTL_TRANSLITERATOR_LIST = self::getData('transliterator_list');
                }

                $language_code = $lang . '-Upper';
                if (!\in_array($language_code, self::$INTL_TRANSLITERATOR_LIST, true)) {
                    \trigger_error('UTF8::strtoupper() without intl for special language: ' . $lang, \E_USER_WARNING);

                    $language_code = 'Any-Upper';
                }

                /** @noinspection PhpComposerExtensionStubsInspection */
                /** @noinspection UnnecessaryCastingInspection */
                return (string) \transliterator_transliterate($language_code, $str);
            }

            \trigger_error('UTF8::strtolower() without intl cannot handle the "lang"-parameter: ' . $lang, \E_USER_WARNING);
        }

        // always fallback via symfony polyfill
        return \mb_strtoupper($str, $encoding);
    }

    /**
     * Translate characters or replace sub-strings.
     *
     * @see http://php.net/manual/en/function.strtr.php
     *
     * @param string          $str  <p>The string being translated.</p>
     * @param string|string[] $from <p>The string replacing from.</p>
     * @param string|string[] $to   [optional] <p>The string being translated to to.</p>
     *
     * @return string
     *                <p>This function returns a copy of str, translating all occurrences of each character in "from" to the
     *                corresponding character in "to".</p>
     */
    public static function strtr(string $str, $from, $to = ''): string
    {
        if ($str === '') {
            return '';
        }

        if ($from === $to) {
            return $str;
        }

        if ($to !== '') {
            $from = self::str_split($from);
            $to = self::str_split($to);
            $count_from = \count($from);
            $count_to = \count($to);

            if ($count_from > $count_to) {
                $from = \array_slice($from, 0, $count_to);
            } elseif ($count_from < $count_to) {
                $to = \array_slice($to, 0, $count_from);
            }

            $from = \array_combine($from, $to);
            /** @noinspection CallableParameterUseCaseInTypeContextInspection - FP */
            if ($from === false) {
                throw new \InvalidArgumentException('The number of elements for each array isn\'t equal or the arrays are empty: (from: ' . \print_r($from, true) . ' | to: ' . \print_r($to, true) . ')');
            }
        }

        if (\is_string($from)) {
            return \str_replace($from, '', $str);
        }

        return \strtr($str, $from);
    }

    /**
     * Return the width of a string.
     *
     * @param string $str        <p>The input string.</p>
     * @param string $encoding   [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool   $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return int
     */
    public static function strwidth(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ): int {
        if ($str === '') {
            return 0;
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if ($clean_utf8 === true) {
            // iconv and mbstring are not tolerant to invalid encoding
            // further, their behaviour is inconsistent with that of PHP's substr
            $str = self::clean($str);
        }

        //
        // fallback via mbstring
        //

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_strwidth($str);
            }

            return \mb_strwidth($str, $encoding);
        }

        //
        // fallback via vanilla php
        //

        if ($encoding !== 'UTF-8') {
            $str = self::encode('UTF-8', $str, false, $encoding);
        }

        $wide = 0;
        $str = (string) \preg_replace('/[\x{1100}-\x{115F}\x{2329}\x{232A}\x{2E80}-\x{303E}\x{3040}-\x{A4CF}\x{AC00}-\x{D7A3}\x{F900}-\x{FAFF}\x{FE10}-\x{FE19}\x{FE30}-\x{FE6F}\x{FF00}-\x{FF60}\x{FFE0}-\x{FFE6}\x{20000}-\x{2FFFD}\x{30000}-\x{3FFFD}]/u', '', $str, -1, $wide);

        return ($wide << 1) + (int) self::strlen($str, 'UTF-8');
    }

    /**
     * Get part of a string.
     *
     * @see http://php.net/manual/en/function.mb-substr.php
     *
     * @param string $str        <p>The string being checked.</p>
     * @param int    $offset     <p>The first position used in str.</p>
     * @param int    $length     [optional] <p>The maximum length of the returned string.</p>
     * @param string $encoding   [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool   $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|string
     *                      The portion of <i>str</i> specified by the <i>offset</i> and
     *                      <i>length</i> parameters.</p><p>If <i>str</i> is shorter than <i>offset</i>
     *                      characters long, <b>FALSE</b> will be returned.
     */
    public static function substr(
        string $str,
        int $offset = 0,
        int $length = null,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        // empty string
        if ($str === '' || $length === 0) {
            return '';
        }

        if ($clean_utf8 === true) {
            // iconv and mbstring are not tolerant to invalid encoding
            // further, their behaviour is inconsistent with that of PHP's substr
            $str = self::clean($str);
        }

        // whole string
        if (!$offset && $length === null) {
            return $str;
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        //
        // fallback via mbstring
        //

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                if ($length === null) {
                    return \mb_substr($str, $offset);
                }

                return \mb_substr($str, $offset, $length);
            }

            return self::substr($str, $offset, $length, $encoding);
        }

        //
        // fallback for binary || ascii only
        //

        if (
            $encoding === 'CP850'
            ||
            $encoding === 'ASCII'
        ) {
            if ($length === null) {
                return \substr($str, $offset);
            }

            return \substr($str, $offset, $length);
        }

        // otherwise we need the string-length
        $str_length = 0;
        if ($offset || $length === null) {
            $str_length = self::strlen($str, $encoding);
        }

        // e.g.: invalid chars + mbstring not installed
        if ($str_length === false) {
            return false;
        }

        // empty string
        if ($offset === $str_length && !$length) {
            return '';
        }

        // impossible
        if ($offset && $offset > $str_length) {
            return '';
        }

        if ($length === null) {
            $length = (int) $str_length;
        } else {
            $length = (int) $length;
        }

        if (
            $encoding !== 'UTF-8'
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::substr() without mbstring cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        //
        // fallback via intl
        //

        if (
            $encoding === 'UTF-8' // INFO: "grapheme_substr()" can't handle other encodings
            &&
            $offset >= 0 // grapheme_substr() can't handle negative offset
            &&
            self::$SUPPORT['intl'] === true
        ) {
            $return_tmp = \grapheme_substr($str, $offset, $length);
            if ($return_tmp !== false) {
                return $return_tmp;
            }
        }

        //
        // fallback via iconv
        //

        if (
            $length >= 0 // "iconv_substr()" can't handle negative length
            &&
            self::$SUPPORT['iconv'] === true
        ) {
            $return_tmp = \iconv_substr($str, $offset, $length);
            if ($return_tmp !== false) {
                return $return_tmp;
            }
        }

        //
        // fallback for ascii only
        //

        if (ASCII::is_ascii($str)) {
            return \substr($str, $offset, $length);
        }

        //
        // fallback via vanilla php
        //

        // split to array, and remove invalid characters
        $array = self::str_split($str);

        // extract relevant part, and join to make sting again
        return \implode('', \array_slice($array, $offset, $length));
    }

    /**
     * Binary-safe comparison of two strings from an offset, up to a length of characters.
     *
     * @param string   $str1               <p>The main string being compared.</p>
     * @param string   $str2               <p>The secondary string being compared.</p>
     * @param int      $offset             [optional] <p>The start position for the comparison. If negative, it starts
     *                                     counting from the end of the string.</p>
     * @param int|null $length             [optional] <p>The length of the comparison. The default value is the largest
     *                                     of the length of the str compared to the length of main_str less the
     *                                     offset.</p>
     * @param bool     $case_insensitivity [optional] <p>If case_insensitivity is TRUE, comparison is case
     *                                     insensitive.</p>
     * @param string   $encoding           [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return int
     *             <strong>&lt; 0</strong> if str1 is less than str2;<br>
     *             <strong>&gt; 0</strong> if str1 is greater than str2,<br>
     *             <strong>0</strong> if they are equal
     */
    public static function substr_compare(
        string $str1,
        string $str2,
        int $offset = 0,
        int $length = null,
        bool $case_insensitivity = false,
        string $encoding = 'UTF-8'
    ): int {
        if (
            $offset !== 0
            ||
            $length !== null
        ) {
            if ($encoding === 'UTF-8') {
                if ($length === null) {
                    $str1 = (string) \mb_substr($str1, $offset);
                } else {
                    $str1 = (string) \mb_substr($str1, $offset, $length);
                }
                $str2 = (string) \mb_substr($str2, 0, (int) self::strlen($str1));
            } else {
                $encoding = self::normalize_encoding($encoding, 'UTF-8');

                $str1 = (string) self::substr($str1, $offset, $length, $encoding);
                $str2 = (string) self::substr($str2, 0, (int) self::strlen($str1), $encoding);
            }
        }

        if ($case_insensitivity === true) {
            return self::strcasecmp($str1, $str2, $encoding);
        }

        return self::strcmp($str1, $str2);
    }

    /**
     * Count the number of substring occurrences.
     *
     * @see http://php.net/manual/en/function.substr-count.php
     *
     * @param string $haystack   <p>The string to search in.</p>
     * @param string $needle     <p>The substring to search for.</p>
     * @param int    $offset     [optional] <p>The offset where to start counting.</p>
     * @param int    $length     [optional] <p>
     *                           The maximum length after the specified offset to search for the
     *                           substring. It outputs a warning if the offset plus the length is
     *                           greater than the haystack length.
     *                           </p>
     * @param string $encoding   [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool   $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return false|int this functions returns an integer or false if there isn't a string
     */
    public static function substr_count(
        string $haystack,
        string $needle,
        int $offset = 0,
        int $length = null,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ) {
        if ($haystack === '' || $needle === '') {
            return false;
        }

        if ($length === 0) {
            return 0;
        }

        if ($encoding !== 'UTF-8' && $encoding !== 'CP850') {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $needle = self::clean($needle);
            $haystack = self::clean($haystack);
        }

        if ($offset || $length > 0) {
            if ($length === null) {
                $length_tmp = self::strlen($haystack, $encoding);
                if ($length_tmp === false) {
                    return false;
                }
                $length = (int) $length_tmp;
            }

            if ($encoding === 'UTF-8') {
                $haystack = (string) \mb_substr($haystack, $offset, $length);
            } else {
                $haystack = (string) \mb_substr($haystack, $offset, $length, $encoding);
            }
        }

        if (
            $encoding !== 'UTF-8'
            &&
            self::$SUPPORT['mbstring'] === false
        ) {
            \trigger_error('UTF8::substr_count() without mbstring cannot handle "' . $encoding . '" encoding', \E_USER_WARNING);
        }

        if (self::$SUPPORT['mbstring'] === true) {
            if ($encoding === 'UTF-8') {
                return \mb_substr_count($haystack, $needle);
            }

            return \mb_substr_count($haystack, $needle, $encoding);
        }

        \preg_match_all('/' . \preg_quote($needle, '/') . '/us', $haystack, $matches, \PREG_SET_ORDER);

        return \count($matches);
    }

    /**
     * Count the number of substring occurrences.
     *
     * @param string $haystack <p>
     *                         The string being checked.
     *                         </p>
     * @param string $needle   <p>
     *                         The string being found.
     *                         </p>
     * @param int    $offset   [optional] <p>
     *                         The offset where to start counting
     *                         </p>
     * @param int    $length   [optional] <p>
     *                         The maximum length after the specified offset to search for the
     *                         substring. It outputs a warning if the offset plus the length is
     *                         greater than the haystack length.
     *                         </p>
     *
     * @return false|int the number of times the
     *                   needle substring occurs in the
     *                   haystack string
     */
    public static function substr_count_in_byte(
        string $haystack,
        string $needle,
        int $offset = 0,
        int $length = null
    ) {
        if ($haystack === '' || $needle === '') {
            return 0;
        }

        if (
            ($offset || $length !== null)
            &&
            self::$SUPPORT['mbstring_func_overload'] === true
        ) {
            if ($length === null) {
                $length_tmp = self::strlen($haystack);
                if ($length_tmp === false) {
                    return false;
                }
                $length = (int) $length_tmp;
            }

            if (
                (
                    $length !== 0
                    &&
                    $offset !== 0
                )
                &&
                ($length + $offset) <= 0
                &&
                Bootup::is_php('7.1') === false // output from "substr_count()" have changed in PHP 7.1
            ) {
                return false;
            }

            /** @var false|string $haystack_tmp - needed for PhpStan (stubs error) */
            $haystack_tmp = \substr($haystack, $offset, $length);
            if ($haystack_tmp === false) {
                $haystack_tmp = '';
            }
            $haystack = (string) $haystack_tmp;
        }

        if (self::$SUPPORT['mbstring_func_overload'] === true) {
            // "mb_" is available if overload is used, so use it ...
            return \mb_substr_count($haystack, $needle, 'CP850'); // 8-BIT
        }

        if ($length === null) {
            return \substr_count($haystack, $needle, $offset);
        }

        return \substr_count($haystack, $needle, $offset, $length);
    }

    /**
     * Returns the number of occurrences of $substring in the given string.
     * By default, the comparison is case-sensitive, but can be made insensitive
     * by setting $case_sensitive to false.
     *
     * @param string $str            <p>The input string.</p>
     * @param string $substring      <p>The substring to search for.</p>
     * @param bool   $case_sensitive [optional] <p>Whether or not to enforce case-sensitivity. Default: true</p>
     * @param string $encoding       [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return int
     */
    public static function substr_count_simple(
        string $str,
        string $substring,
        bool $case_sensitive = true,
        string $encoding = 'UTF-8'
    ): int {
        if ($str === '' || $substring === '') {
            return 0;
        }

        if ($encoding === 'UTF-8') {
            if ($case_sensitive) {
                return (int) \mb_substr_count($str, $substring);
            }

            return (int) \mb_substr_count(
                \mb_strtoupper($str),
                \mb_strtoupper($substring)
            );
        }

        $encoding = self::normalize_encoding($encoding, 'UTF-8');

        if ($case_sensitive) {
            return (int) \mb_substr_count($str, $substring, $encoding);
        }

        return (int) \mb_substr_count(
            self::strtocasefold($str, true, false, $encoding, null, false),
            self::strtocasefold($substring, true, false, $encoding, null, false),
            $encoding
        );
    }

    /**
     * Removes a prefix ($needle) from the beginning of the string ($haystack), case-insensitive.
     *
     * @param string $haystack <p>The string to search in.</p>
     * @param string $needle   <p>The substring to search for.</p>
     *
     * @return string return the sub-string
     */
    public static function substr_ileft(string $haystack, string $needle): string
    {
        if ($haystack === '') {
            return '';
        }

        if ($needle === '') {
            return $haystack;
        }

        if (self::str_istarts_with($haystack, $needle) === true) {
            $haystack = (string) \mb_substr($haystack, (int) self::strlen($needle));
        }

        return $haystack;
    }

    /**
     * Get part of a string process in bytes.
     *
     * @param string $str    <p>The string being checked.</p>
     * @param int    $offset <p>The first position used in str.</p>
     * @param int    $length [optional] <p>The maximum length of the returned string.</p>
     *
     * @return false|string
     *                      The portion of <i>str</i> specified by the <i>offset</i> and
     *                      <i>length</i> parameters.</p><p>If <i>str</i> is shorter than <i>offset</i>
     *                      characters long, <b>FALSE</b> will be returned.
     */
    public static function substr_in_byte(string $str, int $offset = 0, int $length = null)
    {
        // empty string
        if ($str === '' || $length === 0) {
            return '';
        }

        // whole string
        if (!$offset && $length === null) {
            return $str;
        }

        if (self::$SUPPORT['mbstring_func_overload'] === true) {
            // "mb_" is available if overload is used, so use it ...
            return \mb_substr($str, $offset, $length, 'CP850'); // 8-BIT
        }

        return \substr($str, $offset, $length ?? 2147483647);
    }

    /**
     * Removes a suffix ($needle) from the end of the string ($haystack), case-insensitive.
     *
     * @param string $haystack <p>The string to search in.</p>
     * @param string $needle   <p>The substring to search for.</p>
     *
     * @return string return the sub-string
     */
    public static function substr_iright(string $haystack, string $needle): string
    {
        if ($haystack === '') {
            return '';
        }

        if ($needle === '') {
            return $haystack;
        }

        if (self::str_iends_with($haystack, $needle) === true) {
            $haystack = (string) \mb_substr($haystack, 0, (int) self::strlen($haystack) - (int) self::strlen($needle));
        }

        return $haystack;
    }

    /**
     * Removes a prefix ($needle) from the beginning of the string ($haystack).
     *
     * @param string $haystack <p>The string to search in.</p>
     * @param string $needle   <p>The substring to search for.</p>
     *
     * @return string return the sub-string
     */
    public static function substr_left(string $haystack, string $needle): string
    {
        if ($haystack === '') {
            return '';
        }

        if ($needle === '') {
            return $haystack;
        }

        if (self::str_starts_with($haystack, $needle) === true) {
            $haystack = (string) \mb_substr($haystack, (int) self::strlen($needle));
        }

        return $haystack;
    }

    /**
     * Replace text within a portion of a string.
     *
     * source: https://gist.github.com/stemar/8287074
     *
     * @param string|string[] $str         <p>The input string or an array of stings.</p>
     * @param string|string[] $replacement <p>The replacement string or an array of stings.</p>
     * @param int|int[]       $offset      <p>
     *                                     If start is positive, the replacing will begin at the start'th offset
     *                                     into string.
     *                                     <br><br>
     *                                     If start is negative, the replacing will begin at the start'th character
     *                                     from the end of string.
     *                                     </p>
     * @param int|int[]|null  $length      [optional] <p>If given and is positive, it represents the length of the
     *                                     portion of string which is to be replaced. If it is negative, it
     *                                     represents the number of characters from the end of string at which to
     *                                     stop replacing. If it is not given, then it will default to strlen(
     *                                     string ); i.e. end the replacing at the end of string. Of course, if
     *                                     length is zero then this function will have the effect of inserting
     *                                     replacement into string at the given start offset.</p>
     * @param string          $encoding    [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string|string[] The result string is returned. If string is an array then array is returned.
     */
    public static function substr_replace(
        $str,
        $replacement,
        $offset,
        $length = null,
        string $encoding = 'UTF-8'
    ) {
        if (\is_array($str) === true) {
            $num = \count($str);

            // the replacement
            if (\is_array($replacement) === true) {
                $replacement = \array_slice($replacement, 0, $num);
            } else {
                $replacement = \array_pad([$replacement], $num, $replacement);
            }

            // the offset
            if (\is_array($offset) === true) {
                $offset = \array_slice($offset, 0, $num);
                foreach ($offset as &$value_tmp) {
                    $value_tmp = (int) $value_tmp === $value_tmp ? $value_tmp : 0;
                }
                unset($value_tmp);
            } else {
                $offset = \array_pad([$offset], $num, $offset);
            }

            // the length
            if ($length === null) {
                $length = \array_fill(0, $num, 0);
            } elseif (\is_array($length) === true) {
                $length = \array_slice($length, 0, $num);
                foreach ($length as &$value_tmp_V2) {
                    $value_tmp_V2 = (int) $value_tmp_V2 === $value_tmp_V2 ? $value_tmp_V2 : $num;
                }
                unset($value_tmp_V2);
            } else {
                $length = \array_pad([$length], $num, $length);
            }

            // recursive call
            return \array_map([self::class, 'substr_replace'], $str, $replacement, $offset, $length);
        }

        if (\is_array($replacement) === true) {
            if ($replacement !== []) {
                $replacement = $replacement[0];
            } else {
                $replacement = '';
            }
        }

        // init
        $str = (string) $str;
        $replacement = (string) $replacement;

        if (\is_array($length) === true) {
            throw new \InvalidArgumentException('Parameter "$length" can only be an array, if "$str" is also an array.');
        }

        if (\is_array($offset) === true) {
            throw new \InvalidArgumentException('Parameter "$offset" can only be an array, if "$str" is also an array.');
        }

        if ($str === '') {
            return $replacement;
        }

        if (self::$SUPPORT['mbstring'] === true) {
            $string_length = (int) self::strlen($str, $encoding);

            if ($offset < 0) {
                $offset = (int) \max(0, $string_length + $offset);
            } elseif ($offset > $string_length) {
                $offset = $string_length;
            }

            if ($length !== null && $length < 0) {
                $length = (int) \max(0, $string_length - $offset + $length);
            } elseif ($length === null || $length > $string_length) {
                $length = $string_length;
            }

            /** @noinspection AdditionOperationOnArraysInspection */
            if (($offset + $length) > $string_length) {
                $length = $string_length - $offset;
            }

            /** @noinspection AdditionOperationOnArraysInspection */
            return ((string) \mb_substr($str, 0, $offset, $encoding)) .
                   $replacement .
                   ((string) \mb_substr($str, $offset + $length, $string_length - $offset - $length, $encoding));
        }

        //
        // fallback for ascii only
        //

        if (ASCII::is_ascii($str)) {
            return ($length === null) ?
                \substr_replace($str, $replacement, $offset) :
                \substr_replace($str, $replacement, $offset, $length);
        }

        //
        // fallback via vanilla php
        //

        \preg_match_all('/./us', $str, $str_matches);
        \preg_match_all('/./us', $replacement, $replacement_matches);

        if ($length === null) {
            $length_tmp = self::strlen($str, $encoding);
            if ($length_tmp === false) {
                // e.g.: non mbstring support + invalid chars
                return '';
            }
            $length = (int) $length_tmp;
        }

        \array_splice($str_matches[0], $offset, $length, $replacement_matches[0]);

        return \implode('', $str_matches[0]);
    }

    /**
     * Removes a suffix ($needle) from the end of the string ($haystack).
     *
     * @param string $haystack <p>The string to search in.</p>
     * @param string $needle   <p>The substring to search for.</p>
     * @param string $encoding [optional] <p>Set the charset for e.g. "mb_" function</p>
     *
     * @return string return the sub-string
     */
    public static function substr_right(
        string $haystack,
        string $needle,
        string $encoding = 'UTF-8'
    ): string {
        if ($haystack === '') {
            return '';
        }

        if ($needle === '') {
            return $haystack;
        }

        if (
            $encoding === 'UTF-8'
            &&
            \substr($haystack, -\strlen($needle)) === $needle
        ) {
            return (string) \mb_substr($haystack, 0, (int) \mb_strlen($haystack) - (int) \mb_strlen($needle));
        }

        if (\substr($haystack, -\strlen($needle)) === $needle) {
            return (string) self::substr(
                $haystack,
                0,
                (int) self::strlen($haystack, $encoding) - (int) self::strlen($needle, $encoding),
                $encoding
            );
        }

        return $haystack;
    }

    /**
     * Returns a case swapped version of the string.
     *
     * @param string $str        <p>The input string.</p>
     * @param string $encoding   [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool   $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return string each character's case swapped
     */
    public static function swapCase(string $str, string $encoding = 'UTF-8', bool $clean_utf8 = false): string
    {
        if ($str === '') {
            return '';
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $str = self::clean($str);
        }

        if ($encoding === 'UTF-8') {
            return (string) (\mb_strtolower($str) ^ \mb_strtoupper($str) ^ $str);
        }

        return (string) (self::strtolower($str, $encoding) ^ self::strtoupper($str, $encoding) ^ $str);
    }

    /**
     * Checks whether symfony-polyfills are used.
     *
     * @return bool
     *              <strong>true</strong> if in use, <strong>false</strong> otherwise
     */
    public static function symfony_polyfill_used(): bool
    {
        // init
        $return = false;

        $return_tmp = \extension_loaded('mbstring');
        if ($return_tmp === false && \function_exists('mb_strlen')) {
            $return = true;
        }

        $return_tmp = \extension_loaded('iconv');
        if ($return_tmp === false && \function_exists('iconv')) {
            $return = true;
        }

        return $return;
    }

    /**
     * @param string $str
     * @param int    $tab_length
     *
     * @return string
     */
    public static function tabs_to_spaces(string $str, int $tab_length = 4): string
    {
        if ($tab_length === 4) {
            $spaces = '    ';
        } elseif ($tab_length === 2) {
            $spaces = '  ';
        } else {
            $spaces = \str_repeat(' ', $tab_length);
        }

        return \str_replace("\t", $spaces, $str);
    }

    /**
     * Converts the first character of each word in the string to uppercase
     * and all other chars to lowercase.
     *
     * @param string      $str                           <p>The input string.</p>
     * @param string      $encoding                      [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool        $clean_utf8                    [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string|null $lang                          [optional] <p>Set the language for special cases: az, el, lt, tr</p>
     * @param bool        $try_to_keep_the_string_length [optional] <p>true === try to keep the string length: e.g. ẞ -> ß</p>
     *
     * @return string
     *                <p>A string with all characters of $str being title-cased.</p>
     */
    public static function titlecase(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $str = self::clean($str);
        }

        if (
            $lang === null
            &&
            $try_to_keep_the_string_length === false
        ) {
            if ($encoding === 'UTF-8') {
                return \mb_convert_case($str, \MB_CASE_TITLE);
            }

            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            return \mb_convert_case($str, \MB_CASE_TITLE, $encoding);
        }

        return self::str_titleize(
            $str,
            null,
            $encoding,
            false,
            $lang,
            $try_to_keep_the_string_length,
            false
        );
    }

    /**
     * alias for "UTF8::to_ascii()"
     *
     * @param string $str
     * @param string $subst_chr
     * @param bool   $strict
     *
     * @return string
     *
     * @see UTF8::to_ascii()
     * @deprecated <p>please use "UTF8::to_ascii()"</p>
     */
    public static function toAscii(
        string $str,
        string $subst_chr = '?',
        bool $strict = false
    ): string {
        return self::to_ascii($str, $subst_chr, $strict);
    }

    /**
     * alias for "UTF8::to_iso8859()"
     *
     * @param string|string[] $str
     *
     * @return string|string[]
     *
     * @see UTF8::to_iso8859()
     * @deprecated <p>please use "UTF8::to_iso8859()"</p>
     */
    public static function toIso8859($str)
    {
        return self::to_iso8859($str);
    }

    /**
     * alias for "UTF8::to_latin1()"
     *
     * @param string|string[] $str
     *
     * @return string|string[]
     *
     * @see UTF8::to_iso8859()
     * @deprecated <p>please use "UTF8::to_iso8859()"</p>
     */
    public static function toLatin1($str)
    {
        return self::to_iso8859($str);
    }

    /**
     * alias for "UTF8::to_utf8()"
     *
     * @param string|string[] $str
     *
     * @return string|string[]
     *
     * @see UTF8::to_utf8()
     * @deprecated <p>please use "UTF8::to_utf8()"</p>
     */
    public static function toUTF8($str)
    {
        return self::to_utf8($str);
    }

    /**
     * Convert a string into ASCII.
     *
     * @param string $str     <p>The input string.</p>
     * @param string $unknown [optional] <p>Character use if character unknown. (default is ?)</p>
     * @param bool   $strict  [optional] <p>Use "transliterator_transliterate()" from PHP-Intl | WARNING: bad
     *                        performance</p>
     *
     * @return string
     */
    public static function to_ascii(
        string $str,
        string $unknown = '?',
        bool $strict = false
    ): string {
        return ASCII::to_transliterate($str, $unknown, $strict);
    }

    /**
     * @param mixed $str
     *
     * @return bool
     */
    public static function to_boolean($str): bool
    {
        // init
        $str = (string) $str;

        if ($str === '') {
            return false;
        }

        // Info: http://php.net/manual/en/filter.filters.validate.php
        $map = [
            'true'  => true,
            '1'     => true,
            'on'    => true,
            'yes'   => true,
            'false' => false,
            '0'     => false,
            'off'   => false,
            'no'    => false,
        ];

        if (isset($map[$str])) {
            return $map[$str];
        }

        $key = \strtolower($str);
        if (isset($map[$key])) {
            return $map[$key];
        }

        if (\is_numeric($str)) {
            return ((float) $str + 0) > 0;
        }

        return (bool) \trim($str);
    }

    /**
     * Convert given string to safe filename (and keep string case).
     *
     * @param string $str
     * @param bool   $use_transliterate No transliteration, conversion etc. is done by default - unsafe characters are
     *                                  simply replaced with hyphen.
     * @param string $fallback_char
     *
     * @return string
     */
    public static function to_filename(
        string $str,
        bool $use_transliterate = false,
        string $fallback_char = '-'
    ): string {
        return ASCII::to_filename(
            $str,
            $use_transliterate,
            $fallback_char
        );
    }

    /**
     * Convert a string into "ISO-8859"-encoding (Latin-1).
     *
     * @param string|string[] $str
     *
     * @return string|string[]
     */
    public static function to_iso8859($str)
    {
        if (\is_array($str) === true) {
            foreach ($str as $k => &$v) {
                $v = self::to_iso8859($v);
            }

            return $str;
        }

        $str = (string) $str;
        if ($str === '') {
            return '';
        }

        return self::utf8_decode($str);
    }

    /**
     * alias for "UTF8::to_iso8859()"
     *
     * @param string|string[] $str
     *
     * @return string|string[]
     *
     * @see UTF8::to_iso8859()
     * @deprecated <p>please use "UTF8::to_iso8859()"</p>
     */
    public static function to_latin1($str)
    {
        return self::to_iso8859($str);
    }

    /**
     * This function leaves UTF-8 characters alone, while converting almost all non-UTF8 to UTF8.
     *
     * <ul>
     * <li>It decode UTF-8 codepoints and Unicode escape sequences.</li>
     * <li>It assumes that the encoding of the original string is either WINDOWS-1252 or ISO-8859.</li>
     * <li>WARNING: It does not remove invalid UTF-8 characters, so you maybe need to use "UTF8::clean()" for this
     * case.</li>
     * </ul>
     *
     * @param string|string[] $str                        <p>Any string or array.</p>
     * @param bool            $decode_html_entity_to_utf8 <p>Set to true, if you need to decode html-entities.</p>
     *
     * @return string|string[] the UTF-8 encoded string
     */
    public static function to_utf8($str, bool $decode_html_entity_to_utf8 = false)
    {
        if (\is_array($str) === true) {
            foreach ($str as $k => &$v) {
                $v = self::to_utf8($v, $decode_html_entity_to_utf8);
            }

            return $str;
        }

        $str = (string) $str;
        if ($str === '') {
            return $str;
        }

        $max = \strlen($str);
        $buf = '';

        for ($i = 0; $i < $max; ++$i) {
            $c1 = $str[$i];

            if ($c1 >= "\xC0") { // should be converted to UTF8, if it's not UTF8 already

                if ($c1 <= "\xDF") { // looks like 2 bytes UTF8

                    $c2 = $i + 1 >= $max ? "\x00" : $str[$i + 1];

                    if ($c2 >= "\x80" && $c2 <= "\xBF") { // yeah, almost sure it's UTF8 already
                        $buf .= $c1 . $c2;
                        ++$i;
                    } else { // not valid UTF8 - convert it
                        $buf .= self::to_utf8_convert_helper($c1);
                    }
                } elseif ($c1 >= "\xE0" && $c1 <= "\xEF") { // looks like 3 bytes UTF8

                    $c2 = $i + 1 >= $max ? "\x00" : $str[$i + 1];
                    $c3 = $i + 2 >= $max ? "\x00" : $str[$i + 2];

                    if ($c2 >= "\x80" && $c2 <= "\xBF" && $c3 >= "\x80" && $c3 <= "\xBF") { // yeah, almost sure it's UTF8 already
                        $buf .= $c1 . $c2 . $c3;
                        $i += 2;
                    } else { // not valid UTF8 - convert it
                        $buf .= self::to_utf8_convert_helper($c1);
                    }
                } elseif ($c1 >= "\xF0" && $c1 <= "\xF7") { // looks like 4 bytes UTF8

                    $c2 = $i + 1 >= $max ? "\x00" : $str[$i + 1];
                    $c3 = $i + 2 >= $max ? "\x00" : $str[$i + 2];
                    $c4 = $i + 3 >= $max ? "\x00" : $str[$i + 3];

                    if ($c2 >= "\x80" && $c2 <= "\xBF" && $c3 >= "\x80" && $c3 <= "\xBF" && $c4 >= "\x80" && $c4 <= "\xBF") { // yeah, almost sure it's UTF8 already
                        $buf .= $c1 . $c2 . $c3 . $c4;
                        $i += 3;
                    } else { // not valid UTF8 - convert it
                        $buf .= self::to_utf8_convert_helper($c1);
                    }
                } else { // doesn't look like UTF8, but should be converted

                    $buf .= self::to_utf8_convert_helper($c1);
                }
            } elseif (($c1 & "\xC0") === "\x80") { // needs conversion

                $buf .= self::to_utf8_convert_helper($c1);
            } else { // it doesn't need conversion

                $buf .= $c1;
            }
        }

        // decode unicode escape sequences + unicode surrogate pairs
        $buf = \preg_replace_callback(
            '/\\\\u([dD][89abAB][0-9a-fA-F]{2})\\\\u([dD][cdefCDEF][\da-fA-F]{2})|\\\\u([0-9a-fA-F]{4})/',
            /**
             * @param array $matches
             *
             * @return string
             */
            static function (array $matches): string {
                if (isset($matches[3])) {
                    $cp = (int) \hexdec($matches[3]);
                } else {
                    // http://unicode.org/faq/utf_bom.html#utf16-4
                    $cp = ((int) \hexdec($matches[1]) << 10)
                          + (int) \hexdec($matches[2])
                          + 0x10000
                          - (0xD800 << 10)
                          - 0xDC00;
                }

                // https://github.com/php/php-src/blob/php-7.3.2/ext/standard/html.c#L471
                //
                // php_utf32_utf8(unsigned char *buf, unsigned k)

                if ($cp < 0x80) {
                    return (string) self::chr($cp);
                }

                if ($cp < 0xA0) {
                    /** @noinspection UnnecessaryCastingInspection */
                    return (string) self::chr(0xC0 | $cp >> 6) . (string) self::chr(0x80 | $cp & 0x3F);
                }

                return self::decimal_to_chr($cp);
            },
            $buf
        );

        if ($buf === null) {
            return '';
        }

        // decode UTF-8 codepoints
        if ($decode_html_entity_to_utf8 === true) {
            $buf = self::html_entity_decode($buf);
        }

        return $buf;
    }

    /**
     * Strip whitespace or other characters from the beginning and end of a UTF-8 string.
     *
     * INFO: This is slower then "trim()"
     *
     * We can only use the original-function, if we use <= 7-Bit in the string / chars
     * but the check for ASCII (7-Bit) cost more time, then we can safe here.
     *
     * @param string      $str   <p>The string to be trimmed</p>
     * @param string|null $chars [optional] <p>Optional characters to be stripped</p>
     *
     * @return string the trimmed string
     */
    public static function trim(string $str = '', string $chars = null): string
    {
        if ($str === '') {
            return '';
        }

        if (self::$SUPPORT['mbstring'] === true) {
            if ($chars) {
                /** @noinspection PregQuoteUsageInspection */
                $chars = \preg_quote($chars);
                $pattern = "^[${chars}]+|[${chars}]+\$";
            } else {
                $pattern = '^[\\s]+|[\\s]+$';
            }

            /** @noinspection PhpComposerExtensionStubsInspection */
            return (string) \mb_ereg_replace($pattern, '', $str);
        }

        if ($chars) {
            $chars = \preg_quote($chars, '/');
            $pattern = "^[${chars}]+|[${chars}]+\$";
        } else {
            $pattern = '^[\\s]+|[\\s]+$';
        }

        return self::regex_replace($str, $pattern, '', '', '/');
    }

    /**
     * Makes string's first char uppercase.
     *
     * @param string      $str                           <p>The input string.</p>
     * @param string      $encoding                      [optional] <p>Set the charset for e.g. "mb_" function</p>
     * @param bool        $clean_utf8                    [optional] <p>Remove non UTF-8 chars from the string.</p>
     * @param string|null $lang                          [optional] <p>Set the language for special cases: az, el, lt, tr</p>
     * @param bool        $try_to_keep_the_string_length [optional] <p>true === try to keep the string length: e.g. ẞ -> ß</p>
     *
     * @return string the resulting string
     */
    public static function ucfirst(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false,
        string $lang = null,
        bool $try_to_keep_the_string_length = false
    ): string {
        if ($str === '') {
            return '';
        }

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $str = self::clean($str);
        }

        $use_mb_functions = $lang === null && $try_to_keep_the_string_length === false;

        if ($encoding === 'UTF-8') {
            $str_part_two = (string) \mb_substr($str, 1);

            if ($use_mb_functions === true) {
                $str_part_one = \mb_strtoupper(
                    (string) \mb_substr($str, 0, 1)
                );
            } else {
                $str_part_one = self::strtoupper(
                    (string) \mb_substr($str, 0, 1),
                    $encoding,
                    false,
                    $lang,
                    $try_to_keep_the_string_length
                );
            }
        } else {
            $encoding = self::normalize_encoding($encoding, 'UTF-8');

            $str_part_two = (string) self::substr($str, 1, null, $encoding);

            if ($use_mb_functions === true) {
                $str_part_one = \mb_strtoupper(
                    (string) \mb_substr($str, 0, 1, $encoding),
                    $encoding
                );
            } else {
                $str_part_one = self::strtoupper(
                    (string) self::substr($str, 0, 1, $encoding),
                    $encoding,
                    false,
                    $lang,
                    $try_to_keep_the_string_length
                );
            }
        }

        return $str_part_one . $str_part_two;
    }

    /**
     * alias for "UTF8::ucfirst()"
     *
     * @param string $str
     * @param string $encoding
     * @param bool   $clean_utf8
     *
     * @return string
     *
     * @see UTF8::ucfirst()
     * @deprecated <p>please use "UTF8::ucfirst()"</p>
     */
    public static function ucword(
        string $str,
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ): string {
        return self::ucfirst($str, $encoding, $clean_utf8);
    }

    /**
     * Uppercase for all words in the string.
     *
     * @param string   $str        <p>The input string.</p>
     * @param string[] $exceptions [optional] <p>Exclusion for some words.</p>
     * @param string   $char_list  [optional] <p>Additional chars that contains to words and do not start a new
     *                             word.</p>
     * @param string   $encoding   [optional] <p>Set the charset.</p>
     * @param bool     $clean_utf8 [optional] <p>Remove non UTF-8 chars from the string.</p>
     *
     * @return string
     */
    public static function ucwords(
        string $str,
        array $exceptions = [],
        string $char_list = '',
        string $encoding = 'UTF-8',
        bool $clean_utf8 = false
    ): string {
        if (!$str) {
            return '';
        }

        // INFO: mb_convert_case($str, MB_CASE_TITLE);
        // -> MB_CASE_TITLE didn't only uppercase the first letter, it also lowercase all other letters

        if ($clean_utf8 === true) {
            // "mb_strpos()" and "iconv_strpos()" returns wrong position,
            // if invalid characters are found in $haystack before $needle
            $str = self::clean($str);
        }

        $use_php_default_functions = !(bool) ($char_list . \implode('', $exceptions));

        if (
            $use_php_default_functions === true
            &&
            ASCII::is_ascii($str) === true
        ) {
            return \ucwords($str);
        }

        $words = self::str_to_words($str, $char_list);
        $use_exceptions = $exceptions !== [];

        $words_str = '';
        foreach ($words as &$word) {
            if (!$word) {
                continue;
            }

            if (
                $use_exceptions === false
                ||
                !\in_array($word, $exceptions, true)
            ) {
                $words_str .= self::ucfirst($word, $encoding);
            } else {
                $words_str .= $word;
            }
        }

        return $words_str;
    }

    /**
     * Multi decode HTML entity + fix urlencoded-win1252-chars.
     *
     * e.g:
     * 'test+test'                     => 'test test'
     * 'D&#252;sseldorf'               => 'Düsseldorf'
     * 'D%FCsseldorf'                  => 'Düsseldorf'
     * 'D&#xFC;sseldorf'               => 'Düsseldorf'
     * 'D%26%23xFC%3Bsseldorf'         => 'Düsseldorf'
     * 'DÃ¼sseldorf'                   => 'Düsseldorf'
     * 'D%C3%BCsseldorf'               => 'Düsseldorf'
     * 'D%C3%83%C2%BCsseldorf'         => 'Düsseldorf'
     * 'D%25C3%2583%25C2%25BCsseldorf' => 'Düsseldorf'
     *
     * @param string $str          <p>The input string.</p>
     * @param bool   $multi_decode <p>Decode as often as possible.</p>
     *
     * @return string
     */
    public static function urldecode(string $str, bool $multi_decode = true): string
    {
        if ($str === '') {
            return '';
        }

        if (
            \strpos($str, '&') === false
            &&
            \strpos($str, '%') === false
            &&
            \strpos($str, '+') === false
            &&
            \strpos($str, '\u') === false
        ) {
            return self::fix_simple_utf8($str);
        }

        $str = self::urldecode_unicode_helper($str);

        if ($multi_decode === true) {
            do {
                $str_compare = $str;

                /**
                 * @psalm-suppress PossiblyInvalidArgument
                 */
                $str = self::fix_simple_utf8(
                    \urldecode(
                        self::html_entity_decode(
                            self::to_utf8($str),
                            \ENT_QUOTES | \ENT_HTML5
                        )
                    )
                );
            } while ($str_compare !== $str);
        } else {
            /**
             * @psalm-suppress PossiblyInvalidArgument
             */
            $str = self::fix_simple_utf8(
                \urldecode(
                    self::html_entity_decode(
                        self::to_utf8($str),
                        \ENT_QUOTES | \ENT_HTML5
                    )
                )
            );
        }

        return $str;
    }

    /**
     * Return a array with "urlencoded"-win1252 -> UTF-8
     *
     * @return string[]
     *
     * @deprecated <p>please use the "UTF8::urldecode()" function to decode a string</p>
     */
    public static function urldecode_fix_win1252_chars(): array
    {
        return [
            '%20' => ' ',
            '%21' => '!',
            '%22' => '"',
            '%23' => '#',
            '%24' => '$',
            '%25' => '%',
            '%26' => '&',
            '%27' => "'",
            '%28' => '(',
            '%29' => ')',
            '%2A' => '*',
            '%2B' => '+',
            '%2C' => ',',
            '%2D' => '-',
            '%2E' => '.',
            '%2F' => '/',
            '%30' => '0',
            '%31' => '1',
            '%32' => '2',
            '%33' => '3',
            '%34' => '4',
            '%35' => '5',
            '%36' => '6',
            '%37' => '7',
            '%38' => '8',
            '%39' => '9',
            '%3A' => ':',
            '%3B' => ';',
            '%3C' => '<',
            '%3D' => '=',
            '%3E' => '>',
            '%3F' => '?',
            '%40' => '@',
            '%41' => 'A',
            '%42' => 'B',
            '%43' => 'C',
            '%44' => 'D',
            '%45' => 'E',
            '%46' => 'F',
            '%47' => 'G',
            '%48' => 'H',
            '%49' => 'I',
            '%4A' => 'J',
            '%4B' => 'K',
            '%4C' => 'L',
            '%4D' => 'M',
            '%4E' => 'N',
            '%4F' => 'O',
            '%50' => 'P',
            '%51' => 'Q',
            '%52' => 'R',
            '%53' => 'S',
            '%54' => 'T',
            '%55' => 'U',
            '%56' => 'V',
            '%57' => 'W',
            '%58' => 'X',
            '%59' => 'Y',
            '%5A' => 'Z',
            '%5B' => '[',
            '%5C' => '\\',
            '%5D' => ']',
            '%5E' => '^',
            '%5F' => '_',
            '%60' => '`',
            '%61' => 'a',
            '%62' => 'b',
            '%63' => 'c',
            '%64' => 'd',
            '%65' => 'e',
            '%66' => 'f',
            '%67' => 'g',
            '%68' => 'h',
            '%69' => 'i',
            '%6A' => 'j',
            '%6B' => 'k',
            '%6C' => 'l',
            '%6D' => 'm',
            '%6E' => 'n',
            '%6F' => 'o',
            '%70' => 'p',
            '%71' => 'q',
            '%72' => 'r',
            '%73' => 's',
            '%74' => 't',
            '%75' => 'u',
            '%76' => 'v',
            '%77' => 'w',
            '%78' => 'x',
            '%79' => 'y',
            '%7A' => 'z',
            '%7B' => '{',
            '%7C' => '|',
            '%7D' => '}',
            '%7E' => '~',
            '%7F' => '',
            '%80' => '`',
            '%81' => '',
            '%82' => '‚',
            '%83' => 'ƒ',
            '%84' => '„',
            '%85' => '…',
            '%86' => '†',
            '%87' => '‡',
            '%88' => 'ˆ',
            '%89' => '‰',
            '%8A' => 'Š',
            '%8B' => '‹',
            '%8C' => 'Œ',
            '%8D' => '',
            '%8E' => 'Ž',
            '%8F' => '',
            '%90' => '',
            '%91' => '‘',
            '%92' => '’',
            '%93' => '“',
            '%94' => '”',
            '%95' => '•',
            '%96' => '–',
            '%97' => '—',
            '%98' => '˜',
            '%99' => '™',
            '%9A' => 'š',
            '%9B' => '›',
            '%9C' => 'œ',
            '%9D' => '',
            '%9E' => 'ž',
            '%9F' => 'Ÿ',
            '%A0' => '',
            '%A1' => '¡',
            '%A2' => '¢',
            '%A3' => '£',
            '%A4' => '¤',
            '%A5' => '¥',
            '%A6' => '¦',
            '%A7' => '§',
            '%A8' => '¨',
            '%A9' => '©',
            '%AA' => 'ª',
            '%AB' => '«',
            '%AC' => '¬',
            '%AD' => '',
            '%AE' => '®',
            '%AF' => '¯',
            '%B0' => '°',
            '%B1' => '±',
            '%B2' => '²',
            '%B3' => '³',
            '%B4' => '´',
            '%B5' => 'µ',
            '%B6' => '¶',
            '%B7' => '·',
            '%B8' => '¸',
            '%B9' => '¹',
            '%BA' => 'º',
            '%BB' => '»',
            '%BC' => '¼',
            '%BD' => '½',
            '%BE' => '¾',
            '%BF' => '¿',
            '%C0' => 'À',
            '%C1' => 'Á',
            '%C2' => 'Â',
            '%C3' => 'Ã',
            '%C4' => 'Ä',
            '%C5' => 'Å',
            '%C6' => 'Æ',
            '%C7' => 'Ç',
            '%C8' => 'È',
            '%C9' => 'É',
            '%CA' => 'Ê',
            '%CB' => 'Ë',
            '%CC' => 'Ì',
            '%CD' => 'Í',
            '%CE' => 'Î',
            '%CF' => 'Ï',
            '%D0' => 'Ð',
            '%D1' => 'Ñ',
            '%D2' => 'Ò',
            '%D3' => 'Ó',
            '%D4' => 'Ô',
            '%D5' => 'Õ',
            '%D6' => 'Ö',
            '%D7' => '×',
            '%D8' => 'Ø',
            '%D9' => 'Ù',
            '%DA' => 'Ú',
            '%DB' => 'Û',
            '%DC' => 'Ü',
            '%DD' => 'Ý',
            '%DE' => 'Þ',
            '%DF' => 'ß',
            '%E0' => 'à',
            '%E1' => 'á',
            '%E2' => 'â',
            '%E3' => 'ã',
            '%E4' => 'ä',
            '%E5' => 'å',
            '%E6' => 'æ',
            '%E7' => 'ç',
            '%E8' => 'è',
            '%E9' => 'é',
            '%EA' => 'ê',
            '%EB' => 'ë',
            '%EC' => 'ì',
            '%ED' => 'í',
            '%EE' => 'î',
            '%EF' => 'ï',
            '%F0' => 'ð',
            '%F1' => 'ñ',
            '%F2' => 'ò',
            '%F3' => 'ó',
            '%F4' => 'ô',
            '%F5' => 'õ',
            '%F6' => 'ö',
            '%F7' => '÷',
            '%F8' => 'ø',
            '%F9' => 'ù',
            '%FA' => 'ú',
            '%FB' => 'û',
            '%FC' => 'ü',
            '%FD' => 'ý',
            '%FE' => 'þ',
            '%FF' => 'ÿ',
        ];
    }

    /**
     * Decodes a UTF-8 string to ISO-8859-1.
     *
     * @param string $str             <p>The input string.</p>
     * @param bool   $keep_utf8_chars
     *
     * @return string
     */
    public static function utf8_decode(string $str, bool $keep_utf8_chars = false): string
    {
        if ($str === '') {
            return '';
        }

        // save for later comparision
        $str_backup = $str;
        $len = \strlen($str);

        if (self::$ORD === null) {
            self::$ORD = self::getData('ord');
        }

        if (self::$CHR === null) {
            self::$CHR = self::getData('chr');
        }

        $no_char_found = '?';
        /** @noinspection ForeachInvariantsInspection */
        for ($i = 0, $j = 0; $i < $len; ++$i, ++$j) {
            switch ($str[$i] & "\xF0") {
                case "\xC0":
                case "\xD0":
                    $c = (self::$ORD[$str[$i] & "\x1F"] << 6) | self::$ORD[$str[++$i] & "\x3F"];
                    $str[$j] = $c < 256 ? self::$CHR[$c] : $no_char_found;

                    break;

                /** @noinspection PhpMissingBreakStatementInspection */
                case "\xF0":
                    ++$i;

                // no break

                case "\xE0":
                    $str[$j] = $no_char_found;
                    $i += 2;

                    break;

                default:
                    $str[$j] = $str[$i];
            }
        }

        /** @var false|string $return - needed for PhpStan (stubs error) */
        $return = \substr($str, 0, $j);
        if ($return === false) {
            $return = '';
        }

        if (
            $keep_utf8_chars === true
            &&
            (int) self::strlen($return) >= (int) self::strlen($str_backup)
        ) {
            return $str_backup;
        }

        return $return;
    }

    /**
     * Encodes an ISO-8859-1 string to UTF-8.
     *
     * @param string $str <p>The input string.</p>
     *
     * @return string
     */
    public static function utf8_encode(string $str): string
    {
        if ($str === '') {
            return '';
        }

        /** @var false|string $str - the polyfill maybe return false */
        $str = \utf8_encode($str);

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        /** @psalm-suppress TypeDoesNotContainType */
        if ($str === false) {
            return '';
        }

        return $str;
    }

    /**
     * fix -> utf8-win1252 chars
     *
     * @param string $str <p>The input string.</p>
     *
     * @return string
     *
     * @deprecated <p>please use "UTF8::fix_simple_utf8()"</p>
     */
    public static function utf8_fix_win1252_chars(string $str): string
    {
        return self::fix_simple_utf8($str);
    }

    /**
     * Returns an array with all utf8 whitespace characters.
     *
     * @see http://www.bogofilter.org/pipermail/bogofilter/2003-March/001889.html
     *
     * @return string[]
     *                  An array with all known whitespace characters as values and the type of whitespace as keys
     *                  as defined in above URL
     */
    public static function whitespace_table(): array
    {
        return self::$WHITESPACE_TABLE;
    }

    /**
     * Limit the number of words in a string.
     *
     * @param string $str        <p>The input string.</p>
     * @param int    $limit      <p>The limit of words as integer.</p>
     * @param string $str_add_on <p>Replacement for the striped string.</p>
     *
     * @return string
     */
    public static function words_limit(
        string $str,
        int $limit = 100,
        string $str_add_on = '…'
    ): string {
        if ($str === '' || $limit < 1) {
            return '';
        }

        \preg_match('/^\\s*+(?:[^\\s]++\\s*+){1,' . $limit . '}/u', $str, $matches);

        if (
            !isset($matches[0])
            ||
            \mb_strlen($str) === (int) \mb_strlen($matches[0])
        ) {
            return $str;
        }

        return \rtrim($matches[0]) . $str_add_on;
    }

    /**
     * Wraps a string to a given number of characters
     *
     * @see http://php.net/manual/en/function.wordwrap.php
     *
     * @param string $str   <p>The input string.</p>
     * @param int    $width [optional] <p>The column width.</p>
     * @param string $break [optional] <p>The line is broken using the optional break parameter.</p>
     * @param bool   $cut   [optional] <p>
     *                      If the cut is set to true, the string is
     *                      always wrapped at or before the specified width. So if you have
     *                      a word that is larger than the given width, it is broken apart.
     *                      </p>
     *
     * @return string
     *                <p>The given string wrapped at the specified column.</p>
     */
    public static function wordwrap(
        string $str,
        int $width = 75,
        string $break = "\n",
        bool $cut = false
    ): string {
        if ($str === '' || $break === '') {
            return '';
        }

        $str_split = \explode($break, $str);
        if ($str_split === false) {
            return '';
        }

        $chars = [];
        $word_split = '';
        foreach ($str_split as $i => $i_value) {
            if ($i) {
                $chars[] = $break;
                $word_split .= '#';
            }

            foreach (self::str_split($i_value) as $c) {
                $chars[] = $c;
                if ($c === ' ') {
                    $word_split .= ' ';
                } else {
                    $word_split .= '?';
                }
            }
        }

        $str_return = '';
        $j = 0;
        $b = -1;
        $i = -1;
        $word_split = \wordwrap($word_split, $width, '#', $cut);

        $max = \mb_strlen($word_split);
        while (($b = \mb_strpos($word_split, '#', $b + 1)) !== false) {
            for (++$i; $i < $b; ++$i) {
                $str_return .= $chars[$j];
                unset($chars[$j++]);

                // prevent endless loop, e.g. if there is a error in the "mb_*" polyfill
                if ($i > $max) {
                    break 2;
                }
            }

            if (
                $break === $chars[$j]
                ||
                $chars[$j] === ' '
            ) {
                unset($chars[$j++]);
            }

            $str_return .= $break;

            // prevent endless loop, e.g. if there is a error in the "mb_*" polyfill
            if ($b > $max) {
                break;
            }
        }

        return $str_return . \implode('', $chars);
    }

    /**
     * Line-Wrap the string after $limit, but split the string by "$delimiter" before ...
     *    ... so that we wrap the per line.
     *
     * @param string      $str             <p>The input string.</p>
     * @param int         $width           [optional] <p>The column width.</p>
     * @param string      $break           [optional] <p>The line is broken using the optional break parameter.</p>
     * @param bool        $cut             [optional] <p>
     *                                     If the cut is set to true, the string is
     *                                     always wrapped at or before the specified width. So if you have
     *                                     a word that is larger than the given width, it is broken apart.
     *                                     </p>
     * @param bool        $add_final_break [optional] <p>
     *                                     If this flag is true, then the method will add a $break at the end
     *                                     of the result string.
     *                                     </p>
     * @param string|null $delimiter       [optional] <p>
     *                                     You can change the default behavior, where we split the string by newline.
     *                                     </p>
     *
     * @return string
     */
    public static function wordwrap_per_line(
        string $str,
        int $width = 75,
        string $break = "\n",
        bool $cut = false,
        bool $add_final_break = true,
        string $delimiter = null
    ): string {
        if ($delimiter === null) {
            $strings = \preg_split('/\\r\\n|\\r|\\n/', $str);
        } else {
            $strings = \explode($delimiter, $str);
        }

        $string_helper_array = [];
        if ($strings !== false) {
            foreach ($strings as $value) {
                $string_helper_array[] = self::wordwrap($value, $width, $break, $cut);
            }
        }

        if ($add_final_break) {
            $final_break = $break;
        } else {
            $final_break = '';
        }

        return \implode($delimiter ?? "\n", $string_helper_array) . $final_break;
    }

    /**
     * Returns an array of Unicode White Space characters.
     *
     * @return string[] an array with numeric code point as key and White Space Character as value
     */
    public static function ws(): array
    {
        return self::$WHITESPACE;
    }

    /**
     * Checks whether the passed string contains only byte sequences that are valid UTF-8 characters.
     *
     * @see http://hsivonen.iki.fi/php-utf8/
     *
     * @param string $str    <p>The string to be checked.</p>
     * @param bool   $strict <p>Check also if the string is not UTF-16 or UTF-32.</p>
     *
     * @return bool
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     */
    private static function is_utf8_string(string $str, bool $strict = false)
    {
        if ($str === '') {
            return true;
        }

        if ($strict === true) {
            $is_binary = self::is_binary($str, true);

            if ($is_binary && self::is_utf16($str, false) !== false) {
                return false;
            }

            if ($is_binary && self::is_utf32($str, false) !== false) {
                return false;
            }
        }

        if (self::pcre_utf8_support() !== true) {
            // If even just the first character can be matched, when the /u
            // modifier is used, then it's valid UTF-8. If the UTF-8 is somehow
            // invalid, nothing at all will match, even if the string contains
            // some valid sequences
            return \preg_match('/^./us', $str, $ar) === 1;
        }

        $mState = 0; // cached expected number of octets after the current octet
        // until the beginning of the next UTF8 character sequence
        $mUcs4 = 0; // cached Unicode character
        $mBytes = 1; // cached expected number of octets in the current sequence

        if (self::$ORD === null) {
            self::$ORD = self::getData('ord');
        }

        $len = \strlen($str);
        /** @noinspection ForeachInvariantsInspection */
        for ($i = 0; $i < $len; ++$i) {
            $in = self::$ORD[$str[$i]];

            if ($mState === 0) {
                // When mState is zero we expect either a US-ASCII character or a
                // multi-octet sequence.
                if ((0x80 & $in) === 0) {
                    // US-ASCII, pass straight through.
                    $mBytes = 1;
                } elseif ((0xE0 & $in) === 0xC0) {
                    // First octet of 2 octet sequence.
                    $mUcs4 = $in;
                    $mUcs4 = ($mUcs4 & 0x1F) << 6;
                    $mState = 1;
                    $mBytes = 2;
                } elseif ((0xF0 & $in) === 0xE0) {
                    // First octet of 3 octet sequence.
                    $mUcs4 = $in;
                    $mUcs4 = ($mUcs4 & 0x0F) << 12;
                    $mState = 2;
                    $mBytes = 3;
                } elseif ((0xF8 & $in) === 0xF0) {
                    // First octet of 4 octet sequence.
                    $mUcs4 = $in;
                    $mUcs4 = ($mUcs4 & 0x07) << 18;
                    $mState = 3;
                    $mBytes = 4;
                } elseif ((0xFC & $in) === 0xF8) {
                    /* First octet of 5 octet sequence.
                     *
                     * This is illegal because the encoded codepoint must be either
                     * (a) not the shortest form or
                     * (b) outside the Unicode range of 0-0x10FFFF.
                     * Rather than trying to resynchronize, we will carry on until the end
                     * of the sequence and let the later error handling code catch it.
                     */
                    $mUcs4 = $in;
                    $mUcs4 = ($mUcs4 & 0x03) << 24;
                    $mState = 4;
                    $mBytes = 5;
                } elseif ((0xFE & $in) === 0xFC) {
                    // First octet of 6 octet sequence, see comments for 5 octet sequence.
                    $mUcs4 = $in;
                    $mUcs4 = ($mUcs4 & 1) << 30;
                    $mState = 5;
                    $mBytes = 6;
                } else {
                    // Current octet is neither in the US-ASCII range nor a legal first
                    // octet of a multi-octet sequence.
                    return false;
                }
            } elseif ((0xC0 & $in) === 0x80) {

                // When mState is non-zero, we expect a continuation of the multi-octet
                // sequence

                // Legal continuation.
                $shift = ($mState - 1) * 6;
                $tmp = $in;
                $tmp = ($tmp & 0x0000003F) << $shift;
                $mUcs4 |= $tmp;
                // Prefix: End of the multi-octet sequence. mUcs4 now contains the final
                // Unicode code point to be output.
                if (--$mState === 0) {
                    // Check for illegal sequences and code points.
                    //
                    // From Unicode 3.1, non-shortest form is illegal
                    if (
                        ($mBytes === 2 && $mUcs4 < 0x0080)
                        ||
                        ($mBytes === 3 && $mUcs4 < 0x0800)
                        ||
                        ($mBytes === 4 && $mUcs4 < 0x10000)
                        ||
                        ($mBytes > 4)
                        ||
                        // From Unicode 3.2, surrogate characters are illegal.
                        (($mUcs4 & 0xFFFFF800) === 0xD800)
                        ||
                        // Code points outside the Unicode range are illegal.
                        ($mUcs4 > 0x10FFFF)
                    ) {
                        return false;
                    }
                    // initialize UTF8 cache
                    $mState = 0;
                    $mUcs4 = 0;
                    $mBytes = 1;
                }
            } else {
                // ((0xC0 & (*in) != 0x80) && (mState != 0))
                // Incomplete multi-octet sequence.
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $str
     * @param bool   $use_lowercase      <p>Use uppercase by default, otherwise use lowercase.</p>
     * @param bool   $use_full_case_fold <p>Convert not only common cases.</p>
     *
     * @return string
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     */
    private static function fixStrCaseHelper(
        string $str,
        $use_lowercase = false,
        $use_full_case_fold = false
    ) {
        $upper = self::$COMMON_CASE_FOLD['upper'];
        $lower = self::$COMMON_CASE_FOLD['lower'];

        if ($use_lowercase === true) {
            $str = \str_replace(
                $upper,
                $lower,
                $str
            );
        } else {
            $str = \str_replace(
                $lower,
                $upper,
                $str
            );
        }

        if ($use_full_case_fold) {
            static $FULL_CASE_FOLD = null;
            if ($FULL_CASE_FOLD === null) {
                $FULL_CASE_FOLD = self::getData('caseFolding_full');
            }

            if ($use_lowercase === true) {
                $str = \str_replace($FULL_CASE_FOLD[0], $FULL_CASE_FOLD[1], $str);
            } else {
                $str = \str_replace($FULL_CASE_FOLD[1], $FULL_CASE_FOLD[0], $str);
            }
        }

        return $str;
    }

    /**
     * get data from "/data/*.php"
     *
     * @param string $file
     *
     * @return array
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     */
    private static function getData(string $file)
    {
        /** @noinspection PhpIncludeInspection */
        /** @noinspection UsingInclusionReturnValueInspection */
        /** @psalm-suppress UnresolvableInclude */
        return include __DIR__ . '/data/' . $file . '.php';
    }

    /**
     * @return true|null
     */
    private static function initEmojiData()
    {
        if (self::$EMOJI_KEYS_CACHE === null) {
            if (self::$EMOJI === null) {
                self::$EMOJI = self::getData('emoji');
            }

            \uksort(
                self::$EMOJI,
                static function (string $a, string $b): int {
                    return \strlen($b) <=> \strlen($a);
                }
            );

            self::$EMOJI_KEYS_CACHE = \array_keys(self::$EMOJI);
            self::$EMOJI_VALUES_CACHE = \array_values(self::$EMOJI);

            foreach (self::$EMOJI_KEYS_CACHE as $key) {
                $tmp_key = \crc32($key);
                self::$EMOJI_KEYS_REVERSIBLE_CACHE[] = '_-_PORTABLE_UTF8_-_' . $tmp_key . '_-_' . \strrev((string) $tmp_key) . '_-_8FTU_ELBATROP_-_';
            }

            return true;
        }

        return null;
    }

    /**
     * Checks whether mbstring "overloaded" is active on the server.
     *
     * @return bool
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     */
    private static function mbstring_overloaded()
    {
        /**
         * INI directive 'mbstring.func_overload' is deprecated since PHP 7.2
         */

        /** @noinspection PhpComposerExtensionStubsInspection */
        /** @noinspection PhpUsageOfSilenceOperatorInspection */
        return \defined('MB_OVERLOAD_STRING')
               &&
               ((int) @\ini_get('mbstring.func_overload') & \MB_OVERLOAD_STRING);
    }

    /**
     * @param array    $strings
     * @param bool     $remove_empty_values
     * @param int|null $remove_short_values
     *
     * @return array
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     */
    private static function reduce_string_array(
        array $strings,
        bool $remove_empty_values,
        int $remove_short_values = null
    ) {
        // init
        $return = [];

        foreach ($strings as &$str) {
            if (
                $remove_short_values !== null
                &&
                \mb_strlen($str) <= $remove_short_values
            ) {
                continue;
            }

            if (
                $remove_empty_values === true
                &&
                \trim($str) === ''
            ) {
                continue;
            }

            $return[] = $str;
        }

        return $return;
    }

    /**
     * rxClass
     *
     * @param string $s
     * @param string $class
     *
     * @return string
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     */
    private static function rxClass(string $s, string $class = '')
    {
        static $RX_CLASS_CACHE = [];

        $cache_key = $s . $class;

        if (isset($RX_CLASS_CACHE[$cache_key])) {
            return $RX_CLASS_CACHE[$cache_key];
        }

        $class_array = [$class];

        /** @noinspection SuspiciousLoopInspection */
        /** @noinspection AlterInForeachInspection */
        foreach (self::str_split($s) as &$s) {
            if ($s === '-') {
                $class_array[0] = '-' . $class_array[0];
            } elseif (!isset($s[2])) {
                $class_array[0] .= \preg_quote($s, '/');
            } elseif (self::strlen($s) === 1) {
                $class_array[0] .= $s;
            } else {
                $class_array[] = $s;
            }
        }

        if ($class_array[0]) {
            $class_array[0] = '[' . $class_array[0] . ']';
        }

        if (\count($class_array) === 1) {
            $return = $class_array[0];
        } else {
            $return = '(?:' . \implode('|', $class_array) . ')';
        }

        $RX_CLASS_CACHE[$cache_key] = $return;

        return $return;
    }

    /**
     * Personal names such as "Marcus Aurelius" are sometimes typed incorrectly using lowercase ("marcus aurelius").
     *
     * @param string $names
     * @param string $delimiter
     * @param string $encoding
     *
     * @return string
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     */
    private static function str_capitalize_name_helper(
        string $names,
        string $delimiter,
        string $encoding = 'UTF-8'
    ) {
        // init
        $name_helper_array = \explode($delimiter, $names);
        if ($name_helper_array === false) {
            return '';
        }

        $special_cases = [
            'names' => [
                'ab',
                'af',
                'al',
                'and',
                'ap',
                'bint',
                'binte',
                'da',
                'de',
                'del',
                'den',
                'der',
                'di',
                'dit',
                'ibn',
                'la',
                'mac',
                'nic',
                'of',
                'ter',
                'the',
                'und',
                'van',
                'von',
                'y',
                'zu',
            ],
            'prefixes' => [
                'al-',
                "d'",
                'ff',
                "l'",
                'mac',
                'mc',
                'nic',
            ],
        ];

        foreach ($name_helper_array as &$name) {
            if (\in_array($name, $special_cases['names'], true)) {
                continue;
            }

            $continue = false;

            if ($delimiter === '-') {
                /** @noinspection AlterInForeachInspection */
                foreach ((array) $special_cases['names'] as &$beginning) {
                    if (self::strpos($name, $beginning, 0, $encoding) === 0) {
                        $continue = true;
                    }
                }
            }

            /** @noinspection AlterInForeachInspection */
            foreach ((array) $special_cases['prefixes'] as &$beginning) {
                if (self::strpos($name, $beginning, 0, $encoding) === 0) {
                    $continue = true;
                }
            }

            if ($continue === true) {
                continue;
            }

            $name = self::ucfirst($name);
        }

        return \implode($delimiter, $name_helper_array);
    }

    /**
     * Generic case-sensitive transformation for collation matching.
     *
     * @param string $str <p>The input string</p>
     *
     * @return string|null
     */
    private static function strtonatfold(string $str)
    {
        /** @noinspection PhpUndefinedClassInspection */
        return \preg_replace(
            '/\p{Mn}+/u',
            '',
            \Normalizer::normalize($str, \Normalizer::NFD)
        );
    }

    /**
     * @param int|string $input
     *
     * @return string
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     */
    private static function to_utf8_convert_helper($input)
    {
        // init
        $buf = '';

        if (self::$ORD === null) {
            self::$ORD = self::getData('ord');
        }

        if (self::$CHR === null) {
            self::$CHR = self::getData('chr');
        }

        if (self::$WIN1252_TO_UTF8 === null) {
            self::$WIN1252_TO_UTF8 = self::getData('win1252_to_utf8');
        }

        $ordC1 = self::$ORD[$input];
        if (isset(self::$WIN1252_TO_UTF8[$ordC1])) { // found in Windows-1252 special cases
            $buf .= self::$WIN1252_TO_UTF8[$ordC1];
        } else {
            /** @noinspection OffsetOperationsInspection */
            $cc1 = self::$CHR[$ordC1 / 64] | "\xC0";
            $cc2 = ((string) $input & "\x3F") | "\x80";
            $buf .= $cc1 . $cc2;
        }

        return $buf;
    }

    /**
     * @param string $str
     *
     * @return string
     *
     * @noinspection ReturnTypeCanBeDeclaredInspection
     */
    private static function urldecode_unicode_helper(string $str)
    {
        $pattern = '/%u([0-9a-fA-F]{3,4})/';
        if (\preg_match($pattern, $str)) {
            $str = (string) \preg_replace($pattern, '&#x\\1;', $str);
        }

        return $str;
    }
}
