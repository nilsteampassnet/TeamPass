<?php

/** @noinspection ReturnTypeCanBeDeclaredInspection */

declare(strict_types=1);

namespace voku\helper;

use const ENT_DISALLOWED;
use const ENT_HTML5;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;
use const HTML_ENTITIES;

/**
 * AntiXSS - ported from "CodeIgniter"
 *
 * ## 🇷🇺 Русским гражданам
 * В Украине сейчас идет война. Силами РФ наносятся удары по гражданской инфраструктуре в [Харькове][1], [Киеве][2], [Чернигове][3], [Сумах][4], [Ирпене][5] и десятках других городов. Гибнут люди - и гражданское население, и военные, в том числе российские призывники, которых бросили воевать. Чтобы лишить собственный народ доступа к информации, правительство РФ запретило называть войну войной, закрыло независимые СМИ и принимает сейчас ряд диктаторских законов. Эти законы призваны заткнуть рот всем, кто против войны. За обычный призыв к миру сейчас можно получить несколько лет тюрьмы.
 * Не молчите! Молчание - знак вашего согласия с политикой российского правительства.
 *  **Вы можете сделать выбор НЕ МОЛЧАТЬ.**
 * ---
 * ## 🇺🇸 To people of Russia
 * There is a war in Ukraine right now. The forces of the Russian Federation are attacking civilian infrastructure in [Kharkiv][1], [Kyiv][2], [Chernihiv][3], [Sumy][4], [Irpin][5] and dozens of other cities. People are dying – both civilians and military servicemen, including Russian conscripts who were thrown into the fighting. In order to deprive its own people of access to information, the government of the Russian Federation has forbidden calling a war a war, shut down independent media and is passing a number of dictatorial laws. These laws are meant to silence all those who are against war. You can be jailed for multiple years for simply calling for peace.
 * Do not be silent! Silence is a sign that you accept the Russian government's policy.
 * **You can choose NOT TO BE SILENT.**
 * ---
 * - [1] https://cloudfront-us-east-2.images.arcpublishing.com/reuters/P7K2MSZDGFMIJPDD7CI2GIROJI.jpg "Kharkiv under attack"
 * - [2] https://gdb.voanews.com/01bd0000-0aff-0242-fad0-08d9fc92c5b3_cx0_cy5_cw0_w1023_r1_s.jpg "Kyiv under attack"
 * - [3] https://ichef.bbci.co.uk/news/976/cpsprodpb/163DD/production/_123510119_hi074310744.jpg "Chernihiv under attack"
 * - [4] https://www.youtube.com/watch?v=8K-bkqKKf2A "Sumy under attack"
 * - [5] https://cloudfront-us-east-2.images.arcpublishing.com/reuters/K4MTMLEHTRKGFK3GSKAT4GR3NE.jpg "Irpin under attack"
 *
 * @copyright   Copyright (c) 2008 - 2014, EllisLab, Inc. (http://ellislab.com/)
 * @copyright   Copyright (c) 2014 - 2015, British Columbia Institute of Technology (http://bcit.ca/)
 * @copyright   Copyright (c) 2015 - 2020, Lars Moelleken (https://moelleken.org/)
 * @license     http://opensource.org/licenses/MIT	MIT License
 */
final class AntiXSS
{
    const VOKU_ANTI_XSS_GT = 'voku::anti-xss::gt';

    const VOKU_ANTI_XSS_LT = 'voku::anti-xss::lt';

    /**
     * @deprecated will be removed in future versions
     */
    const VOKU_ANTI_XSS_STYLE = 'voku::anti-xss::STYLE';

    /**
     * List of never allowed regex replacements.
     *
     * @var string[]
     */
    private $_never_allowed_regex = [];

    /**
     * List of html tags that will not close automatically.
     *
     * @var string[]
     */
    private $_do_not_close_html_tags = [];

    /**
     * List of never allowed call statements.
     *
     * @var string[]
     */
    private $_never_allowed_js_callback_regex = [
        '\(?window\)?\.',
        '\(?history\)?\.',
        '\(?location\)?\.',
        '\(?document\)?\.',
        '\(?cookie\)?\.',
        '\(?ScriptElement\)?\.',
        'd\s*a\s*t\s*a\s*:',
    ];
    
    /**
     * List of simple never allowed call statements.
     *
     * @var string[]
     */
    private $_never_allowed_call_strings = [
        // default javascript
        'javascript',
        // Java: jar-protocol is an XSS hazard
        'jar',
        // Mac (will not run the script, but open it in AppleScript Editor)
        'applescript',
        // IE: https://www.owasp.org/index.php/XSS_Filter_Evasion_Cheat_Sheet#VBscript_in_an_image
        'vbscript',
        'vbs',
        // IE, surprise!
        'wscript',
        // IE
        'jscript',
        // https://html5sec.org/#behavior
        'behavior',
        // old Netscape
        'mocha',
        // old Netscape
        'livescript',
        // default view source
        'view-source',
    ];

    /**
     * @var string[]
     */
    private $_never_allowed_str_afterwards = [
        '&lt;script&gt;',
        '&lt;/script&gt;',
    ];

    /**
     * List of never allowed strings, afterwards.
     *
     * @var string[]
     */
    private $_never_allowed_on_events_afterwards = [
        'onAbort',
        'onActivate',
        'onAttribute',
        'onAfterPrint',
        'onAfterScriptExecute',
        'onAfterUpdate',
        'onAnimationCancel',
        'onAnimationEnd',
        'onAnimationIteration',
        'onAnimationStart',
        'onAriaRequest',
        'onAutoComplete',
        'onAutoCompleteError',
        'onAuxClick',
        'onBeforeActivate',
        'onBeforeCopy',
        'onBeforeCut',
        'onBeforeInput',
        'onBeforePrint',
        'onBeforeDeactivate',
        'onBeforeEditFocus',
        'onBeforePaste',
        'onBeforePrint',
        'onBeforeScriptExecute',
        'onBeforeToggle',
        'onBeforeUnload',
        'onBeforeUpdate',
        'onBegin',
        'onBlur',
        'onBounce',
        'onCancel',
        'onCanPlay',
        'onCanPlayThrough',
        'onCellChange',
        'onChange',
        'onClick',
        'onClose',
        'onCommand',
        'onCompassNeedsCalibration',
        'onContextMenu',
        'onControlSelect',
        'onCopy',
        'onCueChange',
        'onCut',
        'onDataAvailable',
        'onDataSetChanged',
        'onDataSetComplete',
        'onDblClick',
        'onDeactivate',
        'onDeviceLight',
        'onDeviceMotion',
        'onDeviceOrientation',
        'onDeviceProximity',
        'onDrag',
        'onDragDrop',
        'onDragEnd',
        'onDragExit',
        'onDragEnter',
        'onDragLeave',
        'onDragOver',
        'onDragStart',
        'onDrop',
        'onDurationChange',
        'onEmptied',
        'onEnd',
        'onEnded',
        'onError',
        'onErrorUpdate',
        'onExit',
        'onFilterChange',
        'onFinish',
        'onFocus',
        'onFocusIn',
        'onFocusOut',
        'onFormChange',
        'onFormInput',
        'onFullScreenChange',
        'onFullScreenError',
        'onGotPointerCapture',
        'onHashChange',
        'onHelp',
        'onInput',
        'onInvalid',
        'onKeyDown',
        'onKeyPress',
        'onKeyUp',
        'onLanguageChange',
        'onLayoutComplete',
        'onLoad',
        'onLoadEnd',
        'onLoadedData',
        'onLoadedMetaData',
        'onLoadStart',
        'onLoseCapture',
        'onLostPointerCapture',
        'onMediaComplete',
        'onMediaError',
        'onMessage',
        'onMouseDown',
        'onMouseEnter',
        'onMouseLeave',
        'onMouseMove',
        'onMouseOut',
        'onMouseOver',
        'onMouseUp',
        'onMouseWheel',
        'onMove',
        'onMoveEnd',
        'onMoveStart',
        'onMozFullScreenChange',
        'onMozFullScreenError',
        'onMozPointerLockChange',
        'onMozPointerLockError',
        'onMsContentZoom',
        'onMsFullScreenChange',
        'onMsFullScreenError',
        'onMsGestureChange',
        'onMsGestureDoubleTap',
        'onMsGestureEnd',
        'onMsGestureHold',
        'onMsGestureStart',
        'onMsGestureTap',
        'onMsGotPointerCapture',
        'onMsInertiaStart',
        'onMsLostPointerCapture',
        'onMsManipulationStateChanged',
        'onMsPointerCancel',
        'onMsPointerDown',
        'onMsPointerEnter',
        'onMsPointerLeave',
        'onMsPointerMove',
        'onMsPointerOut',
        'onMsPointerOver',
        'onMsPointerUp',
        'onMsSiteModeJumpListItemRemoved',
        'onMsThumbnailClick',
        'onOffline',
        'onOnline',
        'onOutOfSync',
        'onPage',
        'onPageHide',
        'onPageShow',
        'onPaste',
        'onPause',
        'onPlay',
        'onPlaying',
        'onPointerCancel',
        'onPointerDown',
        'onPointerEnter',
        'onPointerLeave',
        'onPointerLockChange',
        'onPointerLockError',
        'onPointerMove',
        'onPointerOut',
        'onPointerOver',
        'onPointerRawUpdate',
        'onPointerUp',
        'onPopState',
        'onProgress',
        'onPropertyChange',
        'onqt_error',
        'onRateChange',
        'onReadyStateChange',
        'onReceived',
        'onRepeat',
        'onReset',
        'onResize',
        'onResizeEnd',
        'onResizeStart',
        'onResume',
        'onReverse',
        'onRowDelete',
        'onRowEnter',
        'onRowExit',
        'onRowInserted',
        'onRowsDelete',
        'onRowsEnter',
        'onRowsExit',
        'onRowsInserted',
        'onScroll',
        'onSearch',
        'onSeek',
        'onSeeked',
        'onSeeking',
        'onSelect',
        'onSelectionChange',
        'onSelectStart',
        'onStalled',
        'onStorage',
        'onStorageCommit',
        'onStart',
        'onStop',
        'onShow',
        'onSyncRestored',
        'onSubmit',
        'onSuspend',
        'onSynchRestored',
        'onTimeError',
        'onTimeUpdate',
        'onTimer',
        'onTrackChange',
        'onTransitionEnd',
        'onTransitionRun',
        'onTransitionStart',
        'onToggle',
        'onTouchCancel',
        'onTouchEnd',
        'onTouchLeave',
        'onTouchMove',
        'onTouchStart',
        'onTransitionCancel',
        'onTransitionEnd',
        'onUnload',
        'onUnhandledRejection',
        'onURLFlip',
        'onUserProximity',
        'onVolumeChange',
        'onWaiting',
        'onWebKitAnimationEnd',
        'onWebKitAnimationIteration',
        'onWebKitAnimationStart',
        'onWebKitFullScreenChange',
        'onWebKitFullScreenError',
        'onWebKitTransitionEnd',
        'onWheel',
    ];

    /**
     * https://www.owasp.org/index.php/XSS_Filter_Evasion_Cheat_Sheet#Event_Handlers
     *
     * @var string[]
     */
    private $_evil_attributes_regex = [
        'style',
        'xmlns:xdp',
        'formaction',
        'form',
        'xlink:href',
        'seekSegmentTime',
        'FSCommand',
    ];

    /**
     * @var string[]
     */
    private $_evil_html_tags = [
        'applet',
        'audio',
        'basefont',
        'base',
        'behavior',
        'bgsound',
        'blink',
        'body',
        'embed',
        'eval',
        'expression',
        'form',
        'frameset',
        'frame',
        'head',
        'html',
        'ilayer',
        'iframe',
        'input',
        'button',
        'select',
        'isindex',
        'layer',
        'link',
        'meta',
        'keygen',
        'object',
        'plaintext',
        'style',
        'script',
        'textarea',
        'title',
        'math',
        'noscript',
        'event-source',
        'vmlframe',
        'video',
        'source',
        'svg',
        'xml',
    ];

    /**
     * JavaScript-specific keywords that are essentially never used in natural-language prose before a
     * parenthesis. These are matched even when whitespace appears between the keyword and "(" so that
     * patterns like "eval (variable)" are still caught.
     *
     * @var string[]
     */
    private $_naughty_javascript_patterns = [
        'eval',
        'execScript',
        'setTimeout',
        'setInterval',
        'setImmediate',
        'cmd',
        'passthru',
        'exec',
        'fopen',
        'fsockopen',
        'file_get_contents',
        'readfile',
        'unlink',
    ];

    /**
     * Keywords that are also common English words, making them prone to false positives when whitespace
     * precedes the opening parenthesis (e.g. "operating system (Linux)"). These are only matched when
     * the keyword is followed immediately by "(" with no intervening whitespace.
     *
     * @var string[]
     */
    private $_naughty_javascript_patterns_strict = [
        'alert',
        'prompt',
        'confirm',
        'expression',
        'system',
        'file',
    ];

    /**
     * @var string
     */
    private $_spacing_regex = '(?:\s|"|\'|\+|&#x0[9A-F];|%0[9a-f])*?';

    /**
     * The replacement-string for not allowed strings.
     *
     * @var string
     */
    private $_replacement = '';

    /**
     * List of never allowed strings.
     *
     * @var string[]
     */
    private $_never_allowed_str = [];

    /**
     * If your DB (MySQL) encoding is "utf8" and not "utf8mb4", then
     * you can't save 4-Bytes chars from UTF-8 and someone can create stored XSS-attacks.
     *
     * @var bool
     */
    private $_stripe_4byte_chars = false;

    /**
     * @var bool|null
     */
    private $_xss_found;

    /**
     * @var bool
     */
    private $_keep_pre_and_code_tag_content = false;

    /**
     * @var string
     */
    private $_cache_evil_attributes_regex_string = '';

    /**
     * @var string
     */
    private $_cache_never_allowed_regex_string = '';

    /**
     * @var string
     */
    private $_cache__evil_html_tags_str = '';

    /**
     * @var array<string, array{chunk: string, split: string[], regex: string}>
     */
    private $_cache_compact_exploded_words = [];

    /**
     * @var array<string, list<string>>|null
     */
    private $_cache_never_allowed_on_events_afterwards_chunks;

    /**
     * @var array<string, string>
     */
    private $_cache_never_allowed_on_events_afterwards_regex = [];

    /**
     * @var array<string, string>
     */
    private $_cache_never_allowed_regex_replacements = [];

    /**
     * @var string[]
     */
    private $_cache_evil_attributes_quick_checks = [];

    /**
     * @var bool
     */
    private $_cache_can_short_circuit_evil_attributes = true;

    public function __construct()
    {
        $this->_initNeverAllowedStr();
        $this->_initNeverAllowedRegex();
        
        $this->_rebuildEvilAttributesQuickChecksCache();
    }

    /**
     * Compact any exploded words.
     *
     * <p>
     * <br />
     * INFO: This corrects words like:  j a v a s c r i p t
     * <br />
     * These words are compacted back to their correct state.
     * </p>
     *
     * @param string $str
     *
     * @return string
     */
    private function _compact_exploded_javascript($str)
    {
        $words = [
            'javascript',
            '<script',
            '</script>',
            'base64',
            'document',
            'eval',
        ];

        // check if we need to perform the regex-stuff
        if (\strlen($str) <= 30) {
            $useStrPos = true;
        } else {
            $useStrPos = false;
        }

        foreach ($words as $word) {
            if (!isset($this->_cache_compact_exploded_words[$word])) {
                $splitWord = \str_split($word);
                $chunkWord = \implode(
                    $this->_spacing_regex,
                    \array_map(
                        static function ($char) {
                            return \preg_quote($char, '~');
                        },
                        $splitWord
                    )
                );

                $this->_cache_compact_exploded_words[$word] = [
                    'chunk' => $chunkWord,
                    'split' => $splitWord,
                    'regex' => '~(?<before>[^\p{L}]|^)(?<word>' . $chunkWord . ')(?<after>[^\p{L}@.!?\' ]|$)~ius',
                ];
            }

            if ($useStrPos) {
                foreach ($this->_cache_compact_exploded_words[$word]['split'] as $charTmp) {
                    if (\stripos($str, $charTmp) === false) {
                        continue 2;
                    }
                }
            }

            // We only want to do this when it is followed by a non-word character.
            // And if there are no char at the start of the string.
            //
            // That way valid stuff like "dealer to!" does not become "dealerto".

            $tmp = \preg_replace_callback(
                $this->_cache_compact_exploded_words[$word]['regex'],
                [$this, '_compact_exploded_words_callback'],
                $str
            );
            $str = $tmp ?? $str;
        }

        return $str;
    }

    /**
     * Compact exploded words.
     *
     * <p>
     * <br />
     * INFO: Callback method for xss_clean() to remove whitespace from things like 'j a v a s c r i p t'.
     * </p>
     *
     * @param string[] $matches
     *
     * @return  string
     */
    private function _compact_exploded_words_callback($matches)
    {
        return $matches['before'] . \preg_replace(
            '/' . $this->_spacing_regex . '/ius',
            '',
            $matches['word']
        ) . $matches['after'];
    }

    /**
     * HTML-Entity decode callback.
     *
     * @param string[] $match
     *
     * @return string
     */
    private function _decode_entity($match)
    {
        // init
        $str = $match[0];

        // protect GET variables without XSS in URLs
        $needProtection = true;
        if (\strpos($str, '=') !== false) {
            $strCopy = $str;
            $matchesTmp = [];
            while (\preg_match("/[?|&]?[\p{L}\d_\-\[\]]+\s*=\s*([\"'])(?<attr>[^\1]*?)\\1/u", $strCopy, $matches)) {
                $matchesTmp[] = $matches;
                $strCopy = \str_replace($matches[0], '', $strCopy);

                if (\substr_count($strCopy, '"') <= 1 && \substr_count($strCopy, '\'') <= 1) {
                    break;
                }
            }

            if ($strCopy !== $str) {
                $needProtection = false;
                foreach ($matchesTmp as $matches) {
                    if ($matches['attr'] !== '') {
                        $tmpAntiXss = clone $this;

                        $urlPartClean = $tmpAntiXss->xss_clean((string) $matches['attr']);

                        if ($tmpAntiXss->isXssFound() === true) {
                            $this->_xss_found = true;

                            $urlPartClean = \str_replace(['&lt;', '&gt;'], [self::VOKU_ANTI_XSS_LT, self::VOKU_ANTI_XSS_GT], $urlPartClean);
                            $urlPartClean = UTF8::rawurldecode($urlPartClean);
                            $urlPartClean = \str_replace([self::VOKU_ANTI_XSS_LT, self::VOKU_ANTI_XSS_GT], ['&lt;', '&gt;'], $urlPartClean);

                            $str = \str_ireplace($matches['attr'], $urlPartClean, $str);
                        }
                    }
                }
            }
        }

        if ($needProtection) {
            $str = \str_replace(['&lt;', '&gt;'], [self::VOKU_ANTI_XSS_LT, self::VOKU_ANTI_XSS_GT], $str);
            $str = $this->_entity_decode(UTF8::rawurldecode($str));
            $str = \str_replace([self::VOKU_ANTI_XSS_LT, self::VOKU_ANTI_XSS_GT], ['&lt;', '&gt;'], $str);
        }

        return $str;
    }

    /**
     * Decode the html-tags but keep links without XSS.
     *
     * @param string $str
     *
     * @return string
     */
    private function _decode_string($str)
    {
        static $REGEX_FOR_HTML_TAGS = '/<\p{L}+(?:[^>"\']|(["\']).*\1)*>/usU';

        if (
            \strpos($str, '<') !== false
            &&
            \preg_match($REGEX_FOR_HTML_TAGS, $str)
        ) {
            $tmp = \preg_replace_callback(
                $REGEX_FOR_HTML_TAGS,
                [$this, '_decode_entity'],
                $str
            );
            $str = $tmp ?? $str;
        } else {
            $str = UTF8::rawurldecode($str);
        }

        return $str;
    }

    /**
     * @param string $str
     *
     * @return string
     */
    private function _do($str)
    {
        $str = (string) $str;
        if (
            !$str
            ||
            (\is_numeric($str) && ((string) (int) $str === $str || (string) (float) $str === $str))
        ) {
            // no xss found
            if ($this->_xss_found !== true) {
                $this->_xss_found = false;
            }

            return $str;
        }

        // remove the BOM from UTF-8 / UTF-16 / UTF-32 strings
        $str = UTF8::remove_bom($str);

        // replace the diamond question mark (�) and invalid-UTF8 chars
        $str = UTF8::replace_diamond_question_mark($str, '');

        // replace invisible characters with one single space
        $str = UTF8::remove_invisible_characters($str, true, '', false);

        // decode UTF-7 characters
        $str = $this->_repack_utf7($str);

        // decode the string
        $str = $this->_decode_string($str);

        // remove all >= 4-Byte chars if needed
        if ($this->_stripe_4byte_chars) {
            $tmp = \preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $str);
            $str = $tmp ?? $str;
        }

        // backup the string (for later comparison)
        $str_backup = $str;
        
        // process
        do {
            // backup the string (for the loop)
            $str_backup_loop = $str;

            // correct words before the browser will do it
            $str = $this->_compact_exploded_javascript($str);
    
            // remove disallowed javascript calls in links, images etc.
            $str = $this->_remove_disallowed_javascript($str);
    
            // remove strings that are never allowed
            $str = $this->_do_callback_outside_of_pre_and_code_tags($str, [$this, '_do_never_allowed']);
    
            // remove evil attributes such as style, onclick and xmlns
            $str = $this->_remove_evil_attributes($str);
    
            // sanitize naughty JavaScript elements
            $str = $this->_do_callback_outside_of_pre_and_code_tags($str, [$this, '_sanitize_naughty_javascript']);
    
            // sanitize naughty HTML elements
            $str = $this->_sanitize_naughty_html($str);
    
            // final clean up
            //
            // -> This adds a bit of extra precaution in case something got through the above filters.
            $str = $this->_do_callback_outside_of_pre_and_code_tags($str, [$this, '_do_never_allowed_afterwards']);
        } while ($str_backup_loop !== $str);

        // check for xss
        if ($this->_xss_found !== true) {
            $this->_xss_found = !($str_backup === $str);
        }
        
        return $str;
    }

    /**
     * @param string   $str
     * @param callable $callback
     *
     * @return string
     */
    private function _do_callback_outside_of_pre_and_code_tags($str, callable $callback)
    {
        static $REGEX_FOR_PRE_CODE_HTML = '/<(pre|code)\b(?:[^>"\']+|"[^"]*"|\'[^\']*\')*>.*?<\/\1>/is';

        if ($this->_keep_pre_and_code_tag_content !== true) {
            return $callback($str);
        }

        if (
            \stripos($str, '<pre') === false
            &&
            \stripos($str, '<code') === false
        ) {
            return $callback($str);
        }

        $result = '';
        $offset = 0;

        while (
            \preg_match($REGEX_FOR_PRE_CODE_HTML, $str, $matches, PREG_OFFSET_CAPTURE, $offset) === 1
        ) {
            $match = $matches[0][0];
            $start = $matches[0][1];

            $result .= $callback((string) \substr($str, $offset, $start - $offset));
            $result .= $match;
            $offset = $start + \strlen($match);
        }

        if ($offset === 0) {
            return $callback($str);
        }

        $remaining = (string) \substr($str, $offset);

        if ($remaining === '') {
            return $result;
        }

        return $result . $callback($remaining);
    }

    /**
     * Remove never allowed strings.
     *
     * @param string $str
     *
     * @return string
     */
    private function _do_never_allowed($str)
    {
        static $NEVER_ALLOWED_CACHE = [];

        if (!isset($NEVER_ALLOWED_CACHE['keys'])) {
            $NEVER_ALLOWED_CACHE['keys'] = \array_keys($this->_never_allowed_str);
        }

        $str = \str_ireplace(
            $NEVER_ALLOWED_CACHE['keys'],
            $this->_never_allowed_str,
            $str
        );

        // ---

        $replaceNeverAllowedCall = [];
        foreach ($this->_never_allowed_call_strings as $call) {
            if (\stripos($str, $call) !== false) {
                $replaceNeverAllowedCall[] = $call;
            }
        }
        if (\count($replaceNeverAllowedCall) > 0) {
            $tmp = \preg_replace(
                '#([^\p{L}]|^)(?:' . \implode('|', $replaceNeverAllowedCall) . ')\s*:(?!\s[\p{L}\p{N}\s]*$)(?:.*?([/\\\;()\'">]|$))#ius',
                '$1' . $this->_replacement . '$2',
                $str
            );
            $str = $tmp ?? $str;
        }

        // ---

        foreach ($this->_cache_never_allowed_regex_replacements as $regex => $replacement) {
            $tmp = \preg_replace(
                '#' . $regex . '#iUus',
                $replacement,
                $str
            );
            $str = $tmp ?? $str;
        }

        if ($this->_cache_never_allowed_regex_string) {
            $tmp = \preg_replace(
                '#' . $this->_cache_never_allowed_regex_string . '#ius',
                $this->_replacement,
                $str
            );
            $str = $tmp ?? $str;
        }

        return $str;
    }

    /**
     * @return array
     *
     * @phpstan-return array<string, list<string>>
     */
    private function _get_never_allowed_on_events_afterwards_chunks()
    {
        if ($this->_cache_never_allowed_on_events_afterwards_chunks !== null) {
            return $this->_cache_never_allowed_on_events_afterwards_chunks;
        }

        // init
        $array = [];

        foreach ($this->_never_allowed_on_events_afterwards as $event) {
            $array[$event[0] . $event[1] . $event[2]][] = $event;
        }

        foreach ($array as $eventNameBeginning => $events) {
            $this->_cache_never_allowed_on_events_afterwards_regex[$eventNameBeginning] =
                '(?<before>[^\p{L}@.!?>]|^)(?:' . \implode('|', $events) . ')(?<after>\(.*?\)|.*?>|(?:\s|\[.*?\])*?=(?:\s|\[.*?\])*?|(?:\s|\[.*?\])*?&equals;(?:\s|\[.*?\])*?|[^\p{L}]*?=[^\p{L}]*?|[^\p{L}]*?&equals;[^\p{L}]*?|$|\s*?>*?$)';
        }

        $this->_cache_never_allowed_on_events_afterwards_chunks = $array;

        return $this->_cache_never_allowed_on_events_afterwards_chunks;
    }

    /**
     * Remove never allowed string, afterwards.
     *
     * <p>
     * <br />
     * INFO: clean-up also some string, if there is no html-tag
     * </p>
     *
     * @param string $str
     *
     * @return  string
     */
    private function _do_never_allowed_afterwards($str)
    {
        if (\stripos($str, 'on') !== false) {
            foreach ($this->_get_never_allowed_on_events_afterwards_chunks() as $eventNameBeginning => $events) {
                if (\stripos($str, $eventNameBeginning) === false) {
                    continue;
                }

                foreach ($events as $event) {
                    if (\stripos($str, $event) === false) {
                        continue;
                    }

                    do {
                        $count = $temp_count = 0;

                        $tmp = \preg_replace(
                            '#' . $this->_cache_never_allowed_on_events_afterwards_regex[$eventNameBeginning] . '#ius',
                            '$1' . $this->_replacement . '$2',
                            $str,
                            -1,
                            $temp_count
                        );
                        $str = $tmp ?? $str;
                        $count += $temp_count;
                    } while ($count);

                    break;
                }
            }
        }

        return (string) \str_ireplace(
            $this->_never_allowed_str_afterwards,
            $this->_replacement,
            $str
        );
    }

    /**
     * Entity-decoding.
     *
     * @param string $str
     *
     * @return string
     */
    private function _entity_decode($str)
    {
        static $HTML_ENTITIES_CACHE;

        $flags = ENT_QUOTES | ENT_HTML5 | ENT_DISALLOWED | ENT_SUBSTITUTE;

        // decode-again, for e.g. HHVM or miss configured applications ...
        if (
            \strpos($str, '&') !== false
            &&
            \preg_match_all('/(?<html_entity>&[A-Za-z]{2,};{0})/', $str, $matches)
        ) {
            if ($HTML_ENTITIES_CACHE === null) {
                // links:
                // - http://dev.w3.org/html5/html-author/charref
                // - http://www.w3schools.com/charsets/ref_html_entities_n.asp
                $entitiesSecurity = [
                    '&#x00000;'          => '',
                    '&#0;'               => '',
                    '&#x00001;'          => '',
                    '&#1;'               => '',
                    '&nvgt;'             => '',
                    '&#61253;'           => '',
                    '&#x0EF45;'          => '',
                    '&shy;'              => '',
                    '&#x000AD;'          => '',
                    '&#173;'             => '',
                    '&colon;'            => ':',
                    '&#x0003A;'          => ':',
                    '&#58;'              => ':',
                    '&lpar;'             => '(',
                    '&#x00028;'          => '(',
                    '&#40;'              => '(',
                    '&rpar;'             => ')',
                    '&#x00029;'          => ')',
                    '&#41;'              => ')',
                    '&quest;'            => '?',
                    '&#x0003F;'          => '?',
                    '&#63;'              => '?',
                    '&sol;'              => '/',
                    '&#x0002F;'          => '/',
                    '&#47;'              => '/',
                    '&apos;'             => '\'',
                    '&#x00027;'          => '\'',
                    '&#039;'             => '\'',
                    '&#39;'              => '\'',
                    '&#x27;'             => '\'',
                    '&bsol;'             => '\'',
                    '&#x0005C;'          => '\\',
                    '&#92;'              => '\\',
                    '&comma;'            => ',',
                    '&#x0002C;'          => ',',
                    '&#44;'              => ',',
                    '&period;'           => '.',
                    '&#x0002E;'          => '.',
                    '&quot;'             => '"',
                    '&QUOT;'             => '"',
                    '&#x00022;'          => '"',
                    '&#34;'              => '"',
                    '&grave;'            => '`',
                    '&DiacriticalGrave;' => '`',
                    '&#x00060;'          => '`',
                    '&#96;'              => '`',
                    '&#46;'              => '.',
                    '&equals;'           => '=',
                    '&#x0003D;'          => '=',
                    '&#61;'              => '=',
                    '&newline;'          => "\n",
                    '&#x0000A;'          => "\n",
                    '&#10;'              => "\n",
                    '&tab;'              => "\t",
                    '&#x00009;'          => "\t",
                    '&#9;'               => "\t",
                ];

                $HTML_ENTITIES_CACHE = \array_merge(
                    $entitiesSecurity,
                    \array_flip(\get_html_translation_table(HTML_ENTITIES, $flags)),
                    \array_flip(self::_get_data('entities_fallback'))
                );
            }

            $search = [];
            $replace = [];
            foreach ($matches['html_entity'] as $match) {
                $match .= ';';
                if (isset($HTML_ENTITIES_CACHE[$match])) {
                    $search[$match] = $match;
                    $replace[$match] = $HTML_ENTITIES_CACHE[$match];
                }
            }

            if (\count($replace) > 0) {
                $str = \str_ireplace($search, $replace, $str);
            }
        }

        return $str;
    }

    /**
     * Filters tag attributes for consistency and safety.
     *
     * @param string $str
     *
     * @return string
     */
    private function _filter_attributes($str)
    {
        if ($str === '') {
            return '';
        }

        if (\strpos($str, '=') !== false) {
            $matchesTmp = [];
            while (\preg_match('#\s*[\p{L}\d_\-\[\]]+\s*=\s*(?:(["\'])(?:[^\1]*?)\\1|\\\"(?:[^"]*?)\\\")#u', $str, $matches)) {
                $matchesTmp[] = $matches[0];
                $str = \str_replace($matches[0], '', $str);

                if (\substr_count($str, '"') <= 1 && \substr_count($str, '\'') <= 1) {
                    break;
                }
            }
            $out = \implode('', $matchesTmp);
        } else {
            $out = $str;
        }

        return $out;
    }

    /**
     * get data from "/data/*.php"
     *
     * @param string $file
     *
     * @return string[]
     *
     * @phpstan-return array<string, string>
     */
    private static function _get_data($file)
    {
        return include __DIR__ . '/data/' . $file . '.php';
    }

    /**
     * initialize "$this->_never_allowed_str"
     *
     * @return void
     */
    private function _initNeverAllowedStr()
    {
        $this->_never_allowed_str = [
            'document.cookie'   => $this->_replacement,
            '(document).cookie' => $this->_replacement,
            'document.write'    => $this->_replacement,
            '(document).write'  => $this->_replacement,
            '.parentNode'       => $this->_replacement,
            '.innerHTML'        => $this->_replacement,
            '.appendChild'      => $this->_replacement,
            '-moz-binding'      => $this->_replacement,
            '<?'                => '&lt;?',
            '?>'                => '?&gt;',
            '<![CDATA['         => '&lt;![CDATA[',
            '<!ENTITY'          => '&lt;!ENTITY',
            '<!DOCTYPE'         => '&lt;!DOCTYPE',
            '<!ATTLIST'         => '&lt;!ATTLIST',
        ];
    }

    /**
     * initialize "$this->_never_allowed_regex"
     *
     * @return void
     */
    private function _initNeverAllowedRegex()
    {
        $this->_never_allowed_regex = [
            // default javascript
            '(\(?:?document\)?|\(?:?window\)?(?:\.document)?)\.(?:location|on\w*)' => $this->_replacement,
            // data-attribute + base64
            "([\"'])?data\s*:\s*(?!image\s*\/\s*(?!svg.*?))[^\1]*?base64[^\1]*?,[^\1]*?\1?" => $this->_replacement,
            // old IE, old Netscape
            'expres(?:\\\\|\s)*sion\s*(?:\(|&\#40;)' => $this->_replacement,
            // src="js"
            'src\=(?<wrapper>[\'|"]).*\.js(?:\g{wrapper})' => $this->_replacement,
            // comments
            '<!--(.*)-->' => '&lt;!--$1--&gt;',
            '<!--'        => '&lt;!--',
        ];

        $this->_rebuildNeverAllowedRegexCache();
    }

    /**
     * @return void
     */
    private function _rebuildNeverAllowedRegexCache()
    {
        // init
        $regexCombined = [];
        $regexReplacements = [];

        foreach ($this->_never_allowed_regex as $regex => $replacement) {
            if ($replacement === $this->_replacement) {
                $regexCombined[] = $regex;

                continue;
            }

            $regexReplacements[$regex] = $replacement;
        }

        $this->_cache_never_allowed_regex_string = \implode('|', $regexCombined);
        $this->_cache_never_allowed_regex_replacements = $regexReplacements;
    }

    /**
     * @return void
     */
    private function _resetNeverAllowedOnEventsAfterwardsCache()
    {
        // reset
        $this->_cache_never_allowed_on_events_afterwards_chunks = null;
        $this->_cache_never_allowed_on_events_afterwards_regex = [];
    }

    /**
     * @return void
     */
    private function _rebuildEvilAttributesQuickChecksCache()
    {
        // init
        $quickChecks = [];
        $canShortCircuit = true;
        $eventQuickChecks = [];
        $allEventsStartWithOn = true;

        foreach ($this->_evil_attributes_regex as $attribute) {
            if (\preg_match('/^[\p{L}\d:_-]+$/u', $attribute) !== 1) {
                $canShortCircuit = false;

                break;
            }

            $quickChecks[] = $attribute;
        }

        foreach ($this->_never_allowed_on_events_afterwards as $event) {
            if (\stripos($event, 'on') !== 0) {
                $allEventsStartWithOn = false;
            }

            $eventQuickChecks[] = (string) \substr($event, 0, \min(3, \strlen($event)));
        }

        if (
            $canShortCircuit 
            &&
            $eventQuickChecks !== []
        ) {
            if ($allEventsStartWithOn) {
                $quickChecks[] = 'on';
            } else {
                $quickChecks = \array_merge($quickChecks, $eventQuickChecks);
            }
        }

        $this->_cache_can_short_circuit_evil_attributes = $canShortCircuit;
        $this->_cache_evil_attributes_quick_checks = \array_values(\array_unique($quickChecks));
    }

    /**
     * Callback method for xss_clean() to sanitize links.
     *
     * <p>
     * <br />
     * INFO: This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on link-heavy strings.
     * </p>
     *
     * @param string[] $match
     *
     * @return string
     */
    private function _js_link_removal_callback($match)
    {
        return $this->_js_removal_callback($match, 'href');
    }

    /**
     * Check whether an href/src value is URL-like enough to skip JS callback stripping.
     *
     * @param string $value  The extracted attribute value
     *
     * @return bool True for URL-like values that should bypass callback filtering, false otherwise
     */
    private function _isValidUrlForCallbackBypass($value)
    {
        $value = \str_replace(' ', '%20', $value);

        if (\filter_var($value, \FILTER_VALIDATE_URL) !== false) {
            return true;
        }

        if (\stripos($value, 'script') !== false || \strpos($value, ':') !== false) {
            return false;
        }

        return \strpbrk($value, '/?#') !== false;
    }

    /**
     * Callback method for xss_clean() to sanitize tags.
     *
     * <p>
     * <br />
     * INFO: This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on image tag heavy strings.
     * </p>
     *
     * @param string[]  $match
     * @param string $search
     *
     * @return string
     */
    private function _js_removal_callback($match, $search)
    {
        if (!$match[0]) {
            return '';
        }

        $replacer = $this->_filter_attributes($match[1]);

        // filter for "$search"-attributes
        if (\preg_match('#(?:^|[ \t])' . \preg_quote($search, '#') . '[ \t]*=#iu', $match[1]) === 1) {
            // 1: whitespace before "=", 2: whitespace after "=", 3: quote wrapper, 4: attribute value
            $pattern = '#' . \preg_quote($search, '#') . '([ \t]*)=([ \t]*)([\'"])(.*)(?:\3)#isU';
            $matchInner = [];
            $foundSomethingBad = false;
            $isValidAttributeUrl = false;
            if (\preg_match($pattern, $match[1], $matchInner)) {
                $isValidAttributeUrl = $this->_isValidUrlForCallbackBypass($matchInner[4]);
                $needProtection = !$isValidAttributeUrl;

                if ($needProtection) {
                    $tmpAntiXss = clone $this;

                    $tmpAntiXss->xss_clean((string) $matchInner[0]);

                    if ($tmpAntiXss->isXssFound() === true) {
                        $foundSomethingBad = true;
                        $this->_xss_found = true;

                        $tmp = \preg_replace(
                            $pattern,
                            $search . '${1}=${2}"' . $this->_replacement . '"',
                            $replacer
                        );
                        $replacer = $tmp ?? $replacer;
                    }
                }
            }

            $shouldFilterJsCallbacks = !$foundSomethingBad && !$isValidAttributeUrl;

            if ($shouldFilterJsCallbacks) {
                // filter for javascript
                $patternTmp = '';
                foreach ($this->_never_allowed_call_strings as $callTmp) {
                    if (\stripos($match[0], $callTmp) !== false) {
                        $patternTmp .= $callTmp . ':|';
                    }
                }
                $pattern = '#' . $search . '=.*(?:' . $patternTmp . \implode('|', $this->_never_allowed_js_callback_regex) . ')#ius';
                $matchInner = [];
                if (\preg_match($pattern, $match[1], $matchInner)) {
                    $tmp = \preg_replace(
                        $pattern,
                        $search . '="' . $this->_replacement . '"',
                        $replacer
                    );
                    $replacer = $tmp ?? $replacer;
                }
            }
        }
        
        if (
            \substr($match[0], -3) === ' />'
            &&
            \substr($match[1], -2) === ' /'
            &&
            \substr($replacer, -2) !== ' /'
        ) {
            $replacer .= ' /';
        } elseif (
            \substr($match[0], -2) === '/>'
            &&
            \substr($match[1], -1) === '/'
            &&
            \substr($replacer, -1) !== '/'
        ) {
            $replacer .= '/';
        }
        
        
        return \str_ireplace($match[1], $replacer, (string) $match[0]);
    }

    /**
     * Callback method for xss_clean() to sanitize image tags.
     *
     * <p>
     * <br />
     * INFO: This limits the PCRE backtracks, making it more performance friendly
     * and prevents PREG_BACKTRACK_LIMIT_ERROR from being triggered in
     * PHP 5.2+ on image tag heavy strings.
     * </p>
     *
     * @param string[] $match
     *
     * @return string
     */
    private function _js_src_removal_callback(array $match)
    {
        return $this->_js_removal_callback($match, 'src');
    }

    /**
     * Remove disallowed Javascript in links or img tags
     *
     * <p>
     * <br />
     * We used to do some version comparisons and use of stripos(),
     * but it is dog slow compared to these simplified non-capturing
     * preg_match(), especially if the pattern exists in the string
     * </p>
     *
     * <p>
     * <br />
     * Note: It was reported that not only space characters, but all in
     * the following pattern can be parsed as separators between a tag name
     * and its attributes: [\d\s"\'`;,\/\=\(\x00\x0B\x09\x0C]
     * ... however, UTF8::clean() above already strips the
     * hex-encoded ones, so we'll skip them below.
     * </p>
     *
     * @param string $str
     *
     * @return string
     */
    private function _remove_disallowed_javascript($str)
    {
        do {
            $original = $str;

            if (\stripos($str, '<a') !== false) {
                $strTmp = \preg_replace_callback(
                    '#<a[^\p{L}@>]+([^>]*?)(?:>|$)#iu',
                    [$this, '_js_link_removal_callback'],
                    $str
                );
                if ($strTmp === null) {
                    $strTmp = \preg_replace_callback(
                        '#<a[^\p{L}@>]+([^>]*)(?:>|$)#iu',
                        [$this, '_js_link_removal_callback'],
                        $str
                    );
                }
                $str = (string)$strTmp;
            }

            if (\stripos($str, '<img') !== false) {
                $strTmp = \preg_replace_callback(
                    '#<img[^\p{L}@]+([^>]*?)(?:\s?/?>|$)#iu',
                    function ($matches) {
                        if (
                            \strpos($matches[1], 'base64') !== false
                            &&
                            \preg_match("/([\"'])?data\s*:\s*(?:image\s*\/.*)[^\1]*base64[^\1]*,[^\1]*\1?/iUus", $matches[1])
                        ) {
                            return $matches[0];
                        }

                        return $this->_js_src_removal_callback($matches);
                    },
                    $str
                );
                if ($strTmp === null) {
                    $strTmp = (string) \preg_replace_callback(
                        '#<img[^\p{L}@]+([^>]*)(?:\s?/?>|$)#iu',
                        function ($matches) {
                            if (
                                \strpos($matches[1], 'base64') !== false
                                &&
                                \preg_match("/([\"'])?data\s*:\s*(?:image\s*\/.*)[^\1]*base64[^\1]*,[^\1]*\1?/iUus", $matches[1])
                            ) {
                                return $matches[0];
                            }

                            return $this->_js_src_removal_callback($matches);
                        },
                        $str
                    );
                }
                $str = (string)$strTmp;
            }

            if (\stripos($str, '<audio') !== false) {
                $strTmp = \preg_replace_callback(
                    '#<audio[^\p{L}@]+([^>]*?)(?:\s?/?>|$)#iu',
                    [$this, '_js_src_removal_callback'],
                    $str
                );
                if ($strTmp === null) {
                    $strTmp = (string) \preg_replace_callback(
                        '#<audio[^\p{L}@]+([^>]*)(?:\s?/?>|$)#iu',
                        [$this, '_js_src_removal_callback'],
                        $str
                    );
                }
                $str = (string)$strTmp;
            }

            if (\stripos($str, '<video') !== false) {
                $strTmp = \preg_replace_callback(
                    '#<video[^\p{L}@]+([^>]*?)(?:\s?/?>|$)#iu',
                    [$this, '_js_src_removal_callback'],
                    $str
                );
                if ($strTmp === null) {
                    $strTmp = \preg_replace_callback(
                        '#<video[^\p{L}@]+([^>]*)(?:\s?/?>|$)#iu',
                        [$this, '_js_src_removal_callback'],
                        $str
                    );
                }
                $str = (string)$strTmp;
            }

            if (\stripos($str, '<source') !== false) {
                $str = (string) \preg_replace_callback(
                    '#<source[^\p{L}@]+([^>]*)(?:\s?/?>|$)#iu',
                    [$this, '_js_src_removal_callback'],
                    $str
                );
            }

            if (\stripos($str, 'script') !== false) {
                // INFO: US-ASCII: ¼ === <
                $str = (string) \preg_replace(
                    '#(?:%3C|¼|<)\s*script[^\p{L}@]+(?:[^>]*)(?:\s?/?(?:%3E|¾|>)|$)#iu',
                    $this->_replacement,
                    $str
                );
            }

            if (\stripos($str, 'script') !== false) {
                // INFO: US-ASCII: ¼ === <
                $str = (string) \preg_replace(
                    '#(?:%3C|¼|<)[^\p{L}@]*/*[^\p{L}@]*(?:script[^\p{L}@]+).*(?:%3E|¾|>)?#iUus',
                    $this->_replacement,
                    $str
                );
            }
        } while ($original !== $str);

        return (string) $str;
    }

    /**
     * Remove Evil HTML Attributes (like event handlers and style).
     *
     * It removes the evil attribute and either:
     *
     *  - Everything up until a space. For example, everything between the pipes:
     *
     * <code>
     *   <a |style=document.write('hello');alert('world');| class=link>
     * </code>
     *
     *  - Everything inside the quotes. For example, everything between the pipes:
     *
     * <code>
     *   <a |style="document.write('hello'); alert('world');"| class="link">
     * </code>
     *
     * @param string $str <p>The string to check.</p>
     *
     * @return string
     *                <p>The string with the evil attributes removed.</p>
     */
    private function _remove_evil_attributes($str)
    {
        if (
            \strpos($str, '<') === false
            ||
            \strpos($str, '=') === false
        ) {
            return $str;
        }

        if ($this->_cache_can_short_circuit_evil_attributes) {
            $hasPossibleEvilAttribute = false;
            foreach ($this->_cache_evil_attributes_quick_checks as $quickCheck) {
                if (\stripos($str, $quickCheck) !== false) {
                    $hasPossibleEvilAttribute = true;

                    break;
                }
            }

            if ($hasPossibleEvilAttribute === false) {
                return $str;
            }
        }

        // replace style-attribute, first (if needed)
        if (
            \stripos($str, 'style') !== false
            &&
            \in_array('style', $this->_evil_attributes_regex, true)
        ) {
            do {
                $count = $temp_count = 0;

                $tmp = \preg_replace(
                    '/(<[^>]+)(?<!\p{L})(style\s*=\s*"(?:[^"]*?)"|style\s*=\s*\'(?:[^\']*?)\')/iu',
                    '$1' . $this->_replacement,
                    $str,
                    -1,
                    $temp_count
                );
                $str = $tmp ?? $str;
                $count += $temp_count;
            } while ($count);
        }

        if (!$this->_cache_evil_attributes_regex_string) {
            $this->_cache_evil_attributes_regex_string = \implode('|', $this->_evil_attributes_regex);
            $this->_cache_evil_attributes_regex_string .= '|' . \implode('\w*|', $this->_never_allowed_on_events_afterwards);
        }

        do {
            $count = $temp_count = 0;

            // find occurrences of illegal attribute strings with and without quotes (" and ' are octal quotes)
            $strTmp = \preg_replace(
                '/(.*)((?:<[^>]+)(?<!\p{L}))(?:' . $this->_cache_evil_attributes_regex_string . ')(?:\s*=\s*)(?:\'(?:.*?)\'|"(?:.*?)")(.*)/ius',
                '$1$2' . $this->_replacement . '$3',
                $str,
                -1,
                $temp_count
            );
            if ($strTmp === null) {
                $strTmp = \preg_replace(
                    '/(?:' . $this->_cache_evil_attributes_regex_string . ')(?:\s*=\s*)(?:\'(?:.*?)\'|"(?:.*?)")/ius',
                    $this->_replacement,
                    $str,
                    -1,
                    $temp_count
                );
            }
            $str = (string)$strTmp;
            $count += $temp_count;

            $strTmp = \preg_replace(
                '/(.*?)(<[^>]+)(?<!\p{L})(?:' . $this->_cache_evil_attributes_regex_string . ')\s*=\s*(?:[^\s>]*)/ius',
                '$1$2' . $this->_replacement,
                $str,
                -1,
                $temp_count
            );
            if ($strTmp === null) {
                $strTmp = \preg_replace(
                    '/(?<![\p{L}=])(?:' . $this->_cache_evil_attributes_regex_string . ')\s*=\s*(?:[^\s>]*)/ius',
                    $this->_replacement,
                    $str,
                    -1,
                    $temp_count
                );
            }
            $str = (string)$strTmp;
            $count += $temp_count;
        } while ($count);

        return (string) $str;
    }

    /**
     * UTF-7 decoding function.
     *
     * @param string $str <p>HTML document for recode ASCII part of UTF-7 back to ASCII.</p>
     *
     * @return string
     */
    private function _repack_utf7($str)
    {
        static $REGEX_FOR_CONTENT = '#\+([\p{L}\d]+)-#iu';

        if (\strpos($str, '-') === false) {
            return $str;
        }

        return (string) \preg_replace_callback(
            $REGEX_FOR_CONTENT,
            [$this, '_repack_utf7_callback'],
            $str
        );
    }

    /**
     * Additional UTF-7 decoding function.
     *
     * @param string[] $strings <p>Array of strings for recode ASCII part of UTF-7 back to ASCII.</p>
     *
     * @return string
     */
    private function _repack_utf7_callback($strings)
    {
        $strTmp = \base64_decode($strings[1], true);

        if ($strTmp === false) {
            return $strings[0];
        }

        if (\rtrim(\base64_encode($strTmp), '=') !== \rtrim($strings[1], '=')) {
            return $strings[0];
        }

        $string = (string) \preg_replace_callback(
            '/^((?:\x00.)*?)((?:[^\x00].)+)/us',
            [$this, '_repack_utf7_callback_back'],
            $strTmp
        );

        return (string) \preg_replace(
            '/\x00(.)/us',
            '$1',
            $string
        );
    }

    /**
     * Additional UTF-7 encoding function.
     *
     * @param string[] $matches <p>Matches for recode ASCII part of UTF-7 back to ASCII.</p>
     *
     * @return string
     */
    private function _repack_utf7_callback_back($matches)
    {
        return $matches[1] . '+' . \rtrim(\base64_encode($matches[2]), '=') . '-';
    }

    /**
     * Sanitize naughty HTML elements.
     *
     * <p>
     * <br />
     *
     * If a tag containing any of the words in the list
     * below is found, the tag gets converted to entities.
     *
     * <br /><br />
     *
     * So this: <blink>
     * <br />
     * Becomes: &lt;blink&gt;
     * </p>
     *
     * @param string $str
     *
     * @return string
     */
    private function _sanitize_naughty_html($str)
    {
        // init
        $strEnd = '';

        do {
            $original = $str;

            if (
                \strpos($str, '<') === false
                &&
                \strpos($str, '>') === false
            ) {
                return $str;
            }

            if (!$this->_cache__evil_html_tags_str) {
                $this->_cache__evil_html_tags_str = \implode('|', $this->_evil_html_tags);
            }

            $str = (string) \preg_replace_callback(
                '#<(?<start>/*\s*)(?<tagName>' . $this->_cache__evil_html_tags_str . ')(?<end>[^><]*)(?<rest>[><]*)#ius',
                [$this, '_sanitize_naughty_html_callback'],
                $str
            );

            if (\strpos($str, '<') === false) {
                return $str;
            }

            if (
                $this->_xss_found
                &&
                \trim($str) === '<'
            ) {
                return '';
            }

            $str = (string) \preg_replace_callback(
                '#<(?!!--|!\[)((?<start>/*\s*)((?<tagName>[\p{L}:]+)(?=[^\p{L}]|$|)|.+)[^\s"\'\p{L}>/=]*[^>]*)(?<closeTag>>)?#iusS', // tags without comments
                function ($matches) {
                    if (
                        $this->_do_not_close_html_tags !== []
                        &&
                        isset($matches['tagName'])
                        &&
                        \in_array($matches['tagName'], $this->_do_not_close_html_tags, true)
                    ) {
                        return $matches[0];
                    }

                    return $this->_close_html_callback($matches);
                },
                $str
            );

            if ($str === $strEnd) {
                return (string) $str;
            }

            $strEnd = $str;
        } while ($original !== $str);

        return (string) $str;
    }

    /**
     * @param string[] $matches
     *
     * @return string
     */
    private function _close_html_callback($matches)
    {
        if (empty($matches['closeTag'])) {
            // allow e.g. "< $2.20" and e.g. "< 1 year" and e.g. "< 35kg Gross" and e.g. "< subject1"
            if (\preg_match('/^[ .,\d=%€$₢₣£₤₶ℳ₥₦₧₨රුரூ௹रू₹૱₩₪₸₫֏₭₺₼₮₯₰₷₱﷼₲₾₳₴₽₵₡¢¥円৳元៛₠¤฿؋]*$|^[ .,\d=%€$₢₣£₤₶ℳ₥₦₧₨රුரூ௹रू₹૱₩₪₸₫֏₭₺₼₮₯₰₷₱﷼₲₾₳₴₽₵₡¢¥円৳元៛₠¤฿؋]+[\p{L}\d\s]*$/u', $matches[1])) {
                return '<' . \str_replace(['>', '<'], ['&gt;', '&lt;'], $matches[1]);
            }

            return '&lt;' . \str_replace(['>', '<'], ['&gt;', '&lt;'], $matches[1]);
        }

        return '<' . \str_replace(['>', '<'], ['&gt;', '&lt;'], $matches[1]) . '>';
    }

    /**
     * Sanitize naughty HTML.
     *
     * <p>
     * <br />
     * Callback method for AntiXSS->sanitize_naughty_html() to remove naughty HTML elements.
     * </p>
     *
     * @param string[] $matches
     *
     * @return string
     */
    private function _sanitize_naughty_html_callback($matches)
    {
        // init
        $fullMatch = $matches[0];

        // skip some edge-cases
        /** @noinspection NotOptimalIfConditionsInspection */
        if (
            (
                \strpos($fullMatch, '=') === false
                &&
                \strpos($fullMatch, ' ') === false
                &&
                \strpos($fullMatch, ':') === false
                &&
                \strpos($fullMatch, '/') === false
                &&
                \strpos($fullMatch, '\\') === false
                &&
                \stripos($fullMatch, '<' . $matches['tagName'] . '>') !== 0
                &&
                \stripos($fullMatch, '</' . $matches['tagName'] . '>') !== 0
                &&
                \stripos($fullMatch, '<' . $matches['tagName'] . '<') !== 0
            )
            ||
            \preg_match('/<\/?' . $matches['tagName'] . '\p{L}+>/ius', $fullMatch) === 1
        ) {
            return $fullMatch;
        }

        return '&lt;' . $matches['start'] . $matches['tagName'] . $matches['end'] // encode opening brace
               // encode captured opening or closing brace to prevent recursive vectors
               . \str_replace(
                   [
                       '>',
                   ],
                   [
                       '&gt;',
                   ],
                   $matches['rest']
               );
    }

    /**
     * Sanitize naughty scripting elements
     *
     * <p>
     * <br />
     *
     * Similar to above, only instead of looking for
     * tags it looks for PHP and JavaScript commands
     * that are disallowed. Rather than removing the
     * code, it simply converts the parenthesis to entities
     * rendering the code un-executable.
     *
     * <br /><br />
     *
     * For example:  <pre>eval('some code')</pre>
     * <br />
     * Becomes:      <pre>eval&#40;'some code'&#41;</pre>
     * </p>
     *
     * @param string $str
     *
     * @return string
     */
    private function _sanitize_naughty_javascript($str)
    {
        if (\strpos($str, '(') !== false) {
            // Pass 1 – JS-specific keywords: match even when whitespace separates the keyword from "(".
            $found = false;
            foreach ($this->_naughty_javascript_patterns as $pattern) {
                if (\strpos($str, $pattern) !== false) {
                    $found = true;

                    break;
                }
            }

            if ($found === true) {
                $str = (string) \preg_replace(
                    '#(?<!\p{L})(' . \implode('|', $this->_naughty_javascript_patterns) . ')(\s*)\((.*)\)#uisU',
                    '\\1\\2&#40;\\3&#41;',
                    $str
                );
            }

            // Pass 2 – English-word keywords: only match when "(" follows immediately (no whitespace),
            // to avoid false positives such as "operating system (Linux)" or "file (attachment)".
            $found = false;
            foreach ($this->_naughty_javascript_patterns_strict as $pattern) {
                if (\strpos($str, $pattern) !== false) {
                    $found = true;

                    break;
                }
            }

            if ($found === true) {
                $str = (string) \preg_replace(
                    '#(?<!\p{L})(' . \implode('|', $this->_naughty_javascript_patterns_strict) . ')\((.*)\)#uisU',
                    '\\1&#40;\\2&#41;',
                    $str
                );
            }
        }

        return (string) $str;
    }

    /**
     * Add some strings to the "_evil_attributes"-array.
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function addEvilAttributes(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        // reset
        $this->_cache_evil_attributes_regex_string = '';

        $this->_evil_attributes_regex = \array_merge(
            $strings,
            $this->_evil_attributes_regex
        );

        $this->_rebuildEvilAttributesQuickChecksCache();

        return $this;
    }

    /**
     * Add some strings to the "_evil_html_tags"-array.
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function addEvilHtmlTags(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        // reset
        $this->_cache__evil_html_tags_str = '';

        $this->_evil_html_tags = \array_merge(
            $strings,
            $this->_evil_html_tags
        );

        return $this;
    }

    /**
     * Add some strings to the "_never_allowed_regex"-array.
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function addNeverAllowedRegex(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_never_allowed_regex = \array_merge(
            $strings,
            $this->_never_allowed_regex
        );

        $this->_rebuildNeverAllowedRegexCache();

        return $this;
    }

    /**
     * Remove some strings from the "_never_allowed_regex"-array.
     *
     * <p>
     * <br />
     * WARNING: Use this method only if you have a really good reason.
     * </p>
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function removeNeverAllowedRegex(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_never_allowed_regex = \array_diff(
            $this->_never_allowed_regex,
            \array_intersect($strings, $this->_never_allowed_regex)
        );

        $this->_rebuildNeverAllowedRegexCache();

        return $this;
    }

    /**
     * Add some strings to the "_never_allowed_on_events_afterwards"-array.
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function addNeverAllowedOnEventsAfterwards(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        // reset
        $this->_cache_evil_attributes_regex_string = '';
        $this->_resetNeverAllowedOnEventsAfterwardsCache();

        $this->_never_allowed_on_events_afterwards = \array_merge(
            $strings,
            $this->_never_allowed_on_events_afterwards
        );

        $this->_rebuildEvilAttributesQuickChecksCache();

        return $this;
    }

    /**
     * Add some strings to the "_never_allowed_str_afterwards"-array.
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function addNeverAllowedStrAfterwards(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_never_allowed_str_afterwards = \array_merge(
            $strings,
            $this->_never_allowed_str_afterwards
        );

        return $this;
    }

    /**
     * Add some strings to the "_do_not_close_html_tags"-array.
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function addDoNotCloseHtmlTags(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_do_not_close_html_tags = \array_merge(
            $strings,
            $this->_do_not_close_html_tags
        );

        return $this;
    }

    /**
     * Add some strings to the "_never_allowed_js_callback_regex"-array.
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function addNeverAllowedJsCallbackRegex(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_never_allowed_js_callback_regex = \array_merge(
            $strings,
            $this->_never_allowed_js_callback_regex
        );

        return $this;
    }
    
    /**
     * Add some strings to the "_never_allowed_call_strings"-array.
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function addNeverAllowedCallStrings(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_never_allowed_call_strings = \array_merge(
            $strings,
            $this->_never_allowed_call_strings
        );

        return $this;
    }

    /**
     * Remove some strings from the "_do_not_close_html_tags"-array.
     *
     * <p>
     * <br />
     * WARNING: Use this method only if you have a really good reason.
     * </p>
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function removeDoNotCloseHtmlTags(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_do_not_close_html_tags = \array_diff(
            $this->_do_not_close_html_tags,
            \array_intersect($strings, $this->_do_not_close_html_tags)
        );

        return $this;
    }

    /**
     * Add some strings to the "_naughty_javascript_patterns"-array.
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function addNaughtyJavascriptPatterns(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_naughty_javascript_patterns = \array_merge(
            $strings,
            $this->_naughty_javascript_patterns
        );

        return $this;
    }

    /**
     * Check if the "AntiXSS->xss_clean()"-method found an XSS attack in the last run.
     *
     * @return bool|null
     *                   <p>Will return null if the "xss_clean()" wasn't running at all.</p>
     */
    public function isXssFound()
    {
        return $this->_xss_found;
    }

    /**
     * Remove some strings from the "_evil_attributes"-array.
     *
     * <p>
     * <br />
     * WARNING: Use this method only if you have a really good reason.
     * </p>
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function removeEvilAttributes(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        // reset
        $this->_cache_evil_attributes_regex_string = '';

        $this->_evil_attributes_regex = \array_diff(
            $this->_evil_attributes_regex,
            \array_intersect($strings, $this->_evil_attributes_regex)
        );

        $this->_rebuildEvilAttributesQuickChecksCache();

        return $this;
    }

    /**
     * Remove some strings from the "_evil_html_tags"-array.
     *
     * <p>
     * <br />
     * WARNING: Use this method only if you have a really good reason.
     * </p>
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function removeEvilHtmlTags(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        // reset
        $this->_cache__evil_html_tags_str = '';

        $this->_evil_html_tags = \array_diff(
            $this->_evil_html_tags,
            \array_intersect($strings, $this->_evil_html_tags)
        );

        return $this;
    }

    /**
     * Remove some strings from the "_never_allowed_on_events_afterwards"-array.
     *
     * <p>
     * <br />
     * WARNING: Use this method only if you have a really good reason.
     * </p>
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function removeNeverAllowedOnEventsAfterwards(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        // reset
        $this->_cache_evil_attributes_regex_string = '';
        $this->_resetNeverAllowedOnEventsAfterwardsCache();

        $this->_never_allowed_on_events_afterwards = \array_diff(
            $this->_never_allowed_on_events_afterwards,
            \array_intersect($strings, $this->_never_allowed_on_events_afterwards)
        );

        $this->_rebuildEvilAttributesQuickChecksCache();

        return $this;
    }

    /**
     * Remove some strings from the "_never_allowed_str_afterwards"-array.
     *
     * <p>
     * <br />
     * WARNING: Use this method only if you have a really good reason.
     * </p>
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function removeNeverAllowedStrAfterwards(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_never_allowed_str_afterwards = \array_diff(
            $this->_never_allowed_str_afterwards,
            \array_intersect($strings, $this->_never_allowed_str_afterwards)
        );

        return $this;
    }

    /**
     * Remove some strings from the "_never_allowed_call_strings"-array.
     *
     * <p>
     * <br />
     * WARNING: Use this method only if you have a really good reason.
     * </p>
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function removeNeverAllowedCallStrings(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_never_allowed_call_strings = \array_diff(
            $this->_never_allowed_call_strings,
            \array_intersect($strings, $this->_never_allowed_call_strings)
        );

        return $this;
    }

    /**
     * Remove some strings from the "_never_allowed_js_callback_regex"-array.
     *
     * <p>
     * <br />
     * WARNING: Use this method only if you have a really good reason.
     * </p>
     *
     * @param string[] $strings
     *
     * @return $this
     */
    public function removeNeverAllowedJsCallbackRegex(array $strings): self
    {
        if ($strings === []) {
            return $this;
        }

        $this->_never_allowed_js_callback_regex = \array_diff(
            $this->_never_allowed_js_callback_regex,
            \array_intersect($strings, $this->_never_allowed_js_callback_regex)
        );

        return $this;
    }

    /**
     * Set the replacement-string for not allowed strings.
     *
     * @param string $string
     *
     * @return $this
     */
    public function setReplacement($string): self
    {
        $this->_replacement = (string) $string;

        $this->_initNeverAllowedStr();
        $this->_initNeverAllowedRegex();

        return $this;
    }

    /**
     * Set the option to preserve content inside "pre" and "code" tags.
     *
     * <p>
     * <br />
     * WARNING: Enable this only if you explicitly want literal code-like text in "pre" / "code" blocks to remain untouched.
     * </p>
     *
     * @param bool $bool
     *
     * @return $this
     */
    public function setKeepPreAndCodeTagContent($bool): self
    {
        $this->_keep_pre_and_code_tag_content = (bool) $bool;

        return $this;
    }

    /**
     * Set the option to stripe 4-Byte chars.
     *
     * <p>
     * <br />
     * INFO: use it if your DB (MySQL) can't use "utf8mb4" -> preventing stored XSS-attacks
     * </p>
     *
     * @param bool $bool
     *
     * @return $this
     */
    public function setStripe4byteChars($bool): self
    {
        $this->_stripe_4byte_chars = (bool) $bool;

        return $this;
    }

    /**
     * XSS Clean
     *
     * <p>
     * <br />
     * Sanitizes data so that "Cross Site Scripting" hacks can be
     * prevented. This method does a fair amount of work but
     * it is extremely thorough, designed to prevent even the
     * most obscure XSS attempts. But keep in mind that nothing
     * is ever 100% foolproof...
     * </p>
     *
     * <p>
     * <br />
     * <strong>Note:</strong> Should only be used to deal with data upon submission.
     *   It's not something that should be used for general
     *   runtime processing.
     * </p>
     *
     * @see http://channel.bitflux.ch/wiki/XSS_Prevention
     *    Based in part on some code and ideas from Bitflux.
     * @see http://ha.ckers.org/xss.html
     *    To help develop this script I used this great list of
     *    vulnerabilities along with a few other hacks I've
     *    harvested from examining vulnerabilities in other programs.
     *
     * @param string|string[] $str
     *                             <p>input data e.g. string or array of strings</p>
     *
     * @return string|string[]
     *
     * @phpstan-param string|string[] $str
     * @phpstan-return ($str is string ? string : string[])
     */
    public function xss_clean($str)
    {
        // reset
        $this->_xss_found = null;

        // check for an array of strings
        if (\is_array($str)) {
            foreach ($str as &$value) {
                /* @phpstan-ignore-next-line | _xss_found is maybe changed via "xss_clean" */
                if ($this->_xss_found === true) {
                    $alreadyFoundXss = true;
                } else {
                    $alreadyFoundXss = false;
                }
                
                $value = $this->xss_clean($value);

                /* @phpstan-ignore-next-line | _xss_found is maybe changed via "xss_clean" */
                if ($alreadyFoundXss === true) {
                    $this->_xss_found = true;
                }
            }

            return $str;
        }

        $old_str_backup = $str;

        // process
        do {
            $old_str = $str;
            $str = $this->_do($str);
        } while ($old_str !== $str);

        // keep the old value, if there wasn't any XSS attack
        if ($this->isXssFound() !== true) {
            $str = $old_str_backup;
        }

        return $str;
    }
}
