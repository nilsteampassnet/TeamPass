<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\X501\StringPrep;

use const MB_CASE_FOLD;
use function preg_replace;
use SpomkyLabs\Pki\X501\StringPrep\Exception\StringPreparationException;

/**
 * Implements 'Map' step of the Internationalized String Preparation as specified by RFC 4518.
 *
 * @see https://tools.ietf.org/html/rfc4518#section-2.2
 */
final class MapStep implements PrepareStep
{
    /**
     * Characters that are mapped to a space.
     *
     * RFC 4518 section 2.2 maps the line separators and every space separator onto U+0020, so that a name written
     * with a tab and one written with a space are the same name.
     *
     * @var string
     */
    private const TO_SPACE = '/[\x{0009}\x{000A}\x{000B}\x{000C}\x{000D}\x{0085}\p{Zs}\p{Zl}\p{Zp}]/u';

    /**
     * Characters that are mapped to nothing.
     *
     * The soft hyphen, the combining grapheme joiner, the zero width characters, the byte order mark, the Mongolian
     * vowel separators, the variation selectors and the remaining control and format characters carry no identity.
     * Left in place they let a name that renders identically to another compare as a different name, which is how
     * an excluded name constraint was evaded.
     *
     * @var string
     */
    private const TO_NOTHING = '/[\x{00AD}\x{034F}\x{1806}\x{180B}-\x{180D}\x{200B}-\x{200F}'
        . '\x{202A}-\x{202E}\x{2060}-\x{2064}\x{206A}-\x{206F}\x{FE00}-\x{FE0F}\x{FEFF}'
        . '\x{FFF9}-\x{FFFB}\p{Cc}\p{Cf}]/u';

    /**
     * @param bool $fold Whether to apply case folding
     */
    private function __construct(
        private readonly bool $fold
    ) {
    }

    public static function create(bool $fold = false): self
    {
        return new self($fold);
    }

    /**
     * @param string $string UTF-8 encoded string
     */
    public function apply(string $string): string
    {
        // the space mapping runs first, so that a tab becomes a space rather than being deleted as a control
        // character
        $string = self::replace(self::TO_SPACE, ' ', $string);
        $string = self::replace(self::TO_NOTHING, '', $string);
        if ($this->fold) {
            // RFC 4518 asks for the case folding of RFC 3454 appendix B.2, which is Unicode case folding rather
            // than lower casing: it maps the sharp s onto "ss" and the final sigma onto a plain sigma
            $string = mb_convert_case($string, MB_CASE_FOLD, 'UTF-8');
        }

        return $string;
    }

    /**
     * @throws StringPreparationException If the subject cannot be scanned, which means it is not the UTF-8 the
     * transcode step is required to produce.
     */
    private static function replace(string $pattern, string $replacement, string $subject): string
    {
        $result = preg_replace($pattern, $replacement, $subject);
        if ($result === null) {
            throw new StringPreparationException('Failed to prepare a string that is not valid UTF-8.');
        }

        return $result;
    }
}
