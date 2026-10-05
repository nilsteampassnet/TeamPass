<?php

declare(strict_types=1);

namespace SpomkyLabs\Pki\ASN1\Type\Primitive;

use DateTimeImmutable;
use DateTimeZone;
use SpomkyLabs\Pki\ASN1\Component\Identifier;
use SpomkyLabs\Pki\ASN1\Component\Length;
use SpomkyLabs\Pki\ASN1\Exception\DecodeException;
use SpomkyLabs\Pki\ASN1\Feature\ElementBase;
use SpomkyLabs\Pki\ASN1\Type\BaseTime;
use SpomkyLabs\Pki\ASN1\Type\PrimitiveType;
use SpomkyLabs\Pki\ASN1\Type\UniversalClass;

/**
 * Implements *UTCTime* type.
 */
final class UTCTime extends BaseTime
{
    use UniversalClass;
    use PrimitiveType;

    /**
     * Regular expression to parse date.
     *
     * DER restricts format to UTC timezone (Z suffix).
     *
     * @var string
     */
    public const REGEX = '#^' .
        '(\d\d)' . // YY
        '(\d\d)' . // MM
        '(\d\d)' . // DD
        '(\d\d)' . // hh
        '(\d\d)' . // mm
        '(\d\d)' . // ss
        'Z' . // TZ
        '$#';

    /**
     * Two digit years at or above this value belong to the twentieth century.
     *
     * @see https://tools.ietf.org/html/rfc5280#section-4.1.2.5.1
     *
     * @var int
     */
    public const CENTURY_PIVOT = 50;

    private function __construct(DateTimeImmutable $dt)
    {
        parent::__construct(self::TYPE_UTC_TIME, $dt);
    }

    public static function create(DateTimeImmutable $dt): self
    {
        return new self($dt);
    }

    public static function fromString(string $time): static
    {
        return new static(new DateTimeImmutable($time, new DateTimeZone('UTC')));
    }

    protected function encodedAsDER(): string
    {
        $dt = $this->dateTime->setTimezone(new DateTimeZone('UTC'));
        return $dt->format('ymdHis\\Z');
    }

    protected static function decodeFromDER(Identifier $identifier, string $data, int &$offset): ElementBase
    {
        $idx = $offset;
        $length = Length::expectFromDER($data, $idx)->expectIntLength();
        $str = mb_substr($data, $idx, $length, '8bit');
        $idx += $length;
        if (preg_match(self::REGEX, $str, $match) !== 1) {
            throw new DecodeException('Invalid UTCTime format.');
        }
        [, $year, $month, $day, $hour, $minute, $second] = $match;
        // RFC 5280 section 4.1.2.5.1: YY >= 50 means 19YY, YY < 50 means 20YY. PHP's own 'y' format pivots at 70
        // instead, which reads the years 50 to 69 a full century off.
        $year = ((int) $year >= self::CENTURY_PIVOT ? '19' : '20') . $year;
        $time = $year . $month . $day . $hour . $minute . $second . self::TZ_UTC;
        $dt = DateTimeImmutable::createFromFormat('!YmdHisT', $time, new DateTimeZone('UTC'));
        if ($dt === false) {
            throw new DecodeException('Failed to decode UTCTime');
        }
        // createFromFormat() silently rolls over out of range components: month 13 becomes January of the next
        // year, 25 hours becomes the next day. Only getLastErrors() reports it.
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            throw new DecodeException('Invalid UTCTime value.');
        }
        $offset = $idx;
        return self::create($dt);
    }
}
