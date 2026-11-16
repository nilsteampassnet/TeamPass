<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/setting_value_logic.php';

/**
 * Behavioural tests for the text values of the admin settings.
 *
 * The generic option save stored accented letters as named entities ("Société" became
 * "Soci&eacute;t&eacute;"), which then reached the e-mail sender name and authenticator apps. The save
 * must keep escaping exactly what it escaped before, and the upgrade must repair the stored
 * values without making any of them less safe to print.
 */
class SettingValueLogicTest extends TestCase
{
    private static function legacy(mixed $value): string|false
    {
        return filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    }

    public function testAsciiIsEscapedExactlyAsBefore(): void
    {
        for ($byte = 0; $byte < 128; ++$byte) {
            $character = chr($byte);
            foreach (['a' . $character . 'b', '&amp;' . $character, '&#039;' . $character . '&quot;', '&eacute' . $character . ';'] as $value) {
                $this->assertSame(self::legacy($value), tpEncodeSettingValue($value), 'Byte ' . $byte);
            }
        }
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function scalarProvider(): array
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'zero' => [0],
            'integer' => [12],
            'float' => [1.5],
            'array' => [['a']],
            'object' => [new stdClass()],
            'invalid UTF-8' => ["ab\xC3(cd"],
            'markup' => ["O'Brien & <b>\"x\"</b>"],
            'entity already present' => ['R&amp;D'],
        ];
    }

    #[DataProvider('scalarProvider')]
    public function testOtherInputsAreHandledAsBefore(mixed $value): void
    {
        $this->assertSame(self::legacy($value), tpEncodeSettingValue($value));
    }

    public function testAccentedLettersAreStoredAsTyped(): void
    {
        $this->assertSame('Société Passwords', tpEncodeSettingValue('Société Passwords'));
        $this->assertSame('Inicio de sesión', tpEncodeSettingValue('Inicio de sesión'));
        $this->assertSame('€ ñ Ü 日本 😀 &amp; &lt;', tpEncodeSettingValue('€ ñ Ü 日本 😀 & <'));
    }

    public function testDecodingIsTheExactInverseOfTheLegacySave(): void
    {
        $failures = [];
        for ($codePoint = 0x20; $codePoint < 0x30000; ++$codePoint) {
            if ($codePoint >= 0xD800 && $codePoint <= 0xDFFF) {
                continue;
            }
            $value = 'x' . mb_chr($codePoint, 'UTF-8') . "y<&'\"&amp;";
            if (tpDecodeLegacySettingEntities((string) self::legacy($value)) !== tpEncodeSettingValue($value)) {
                $failures[] = sprintf('U+%04X', $codePoint);
            }
        }

        $this->assertSame([], $failures);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function storedValueProvider(): array
    {
        return [
            // Values found on a real installation upgraded from 3.0.x.
            'sender name' => ['Soci&eacute;t&eacute; Passwords', 'Société Passwords'],
            'login text' => ['Inicio de sesi&oacute;n', 'Inicio de sesión'],
            'numeric entities' => ['&#228;&#xE4;', 'ää'],
            'astral character' => ['&#x1F600;', '😀'],
            'no-break space' => ['a&nbsp;b', "a\u{00A0}b"],
            'special characters stay encoded' => ['R&amp;D &lt;b&gt; &quot;x&quot; &#039;y&#039;', 'R&amp;D &lt;b&gt; &quot;x&quot; &#039;y&#039;'],
            'typed entity stays literal' => ['&amp;auml;', '&amp;auml;'],
            'control character stays encoded' => ['a&#10;b&#0;c&#150;d', 'a&#10;b&#0;c&#150;d'],
            'unknown entity' => ['&bogus;', '&bogus;'],
            'already clean' => ['Société', 'Société'],
            'url' => ['https://example.com/?a=1&b=2', 'https://example.com/?a=1&b=2'],
            'json' => ['{"name":"x"}', '{"name":"x"}'],
            'empty' => ['', ''],
        ];
    }

    #[DataProvider('storedValueProvider')]
    public function testStoredValuesAreRepaired(string $stored, string $expected): void
    {
        $this->assertSame($expected, tpDecodeLegacySettingEntities($stored));
    }

    #[DataProvider('storedValueProvider')]
    public function testRepairIsIdempotentAndNeverAddsMarkup(string $stored): void
    {
        $repaired = tpDecodeLegacySettingEntities($stored);

        $this->assertSame($repaired, tpDecodeLegacySettingEntities($repaired));
        foreach (['<', '>', '"', "'"] as $character) {
            $this->assertSame(substr_count($stored, $character), substr_count($repaired, $character), $character);
        }
        $this->assertSame(0, preg_match('/[\x00-\x1F\x7F]/', $repaired) - preg_match('/[\x00-\x1F\x7F]/', $stored));
        $this->assertLessThanOrEqual(strlen($stored), strlen($repaired));
    }

    public function testTheGenericOptionSaveUsesTheNewEncoding(): void
    {
        $handler = (string) file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');
        $start = strpos($handler, "case 'save_option_change':");
        $this->assertNotFalse($start);
        $block = substr($handler, $start, 20000);

        $this->assertStringContainsString("\$post_value = tpEncodeSettingValue(\$dataReceived['value']);", $block);
        $this->assertStringNotContainsString("filter_var(\$dataReceived['value'], FILTER_SANITIZE_FULL_SPECIAL_CHARS)", $block);
    }
}
