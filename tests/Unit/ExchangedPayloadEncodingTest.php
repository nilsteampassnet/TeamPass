<?php

declare(strict_types=1);

namespace TeamPass\Tests\ExchangedPayload;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the rule that a request payload decodes to the same values whether client/server
 * encryption is enabled or not.
 *
 * Encrypted, the "data" POST field is base64 that no input filter alters, so
 * prepareExchangedData() hands the handler exactly the JSON the browser built. Unencrypted,
 * the JSON travels in clear through the handler's input filter, and teampassDecodeJsonPayload()
 * keeps the first of raw / decoded once / decoded twice that parses.
 *
 * FILTER_SANITIZE_FULL_SPECIAL_CHARS behaves like htmlentities() and never re-encodes an
 * existing entity, so no decoding can invert it: with FILTER_FLAG_NO_ENCODE_QUOTES the JSON
 * stays valid and "é" was stored as "&eacute;" (folder titles, user names, role labels...);
 * without the flag a literal "&lt;" came back as "<".
 */
class ExchangedPayloadEncodingTest extends TestCase
{
    /**
     * What a user may type: accents, HTML-special characters, literal entities, non-Latin text.
     */
    private const VALUES = [
        'title' => 'Sécurité',
        'name' => 'Zoë Ångström',
        'label' => 'R&D <team> "quoted" \'single\'',
        'literal' => '&lt;kept&gt; &eacute; &nbsp;',
        'cjk' => '安全',
        'emoji' => '🔐',
        'html' => '<p>Bonjour&nbsp;à tous</p>',
    ];

    public static function setUpBeforeClass(): void
    {
        if (function_exists(__NAMESPACE__ . '\\teampassDecodeJsonPayload')) {
            return;
        }
        // Execute the production decoder, not a copy of it.
        $functions = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/sources/main.functions.php'));
        $start = strpos($functions, 'function teampassDecodeJsonPayload(');
        self::assertIsInt($start);
        $end = strpos($functions, "\n}", $start) + 2;
        eval('namespace ' . __NAMESPACE__ . ';' . substr($functions, $start, $end - $start));
    }

    public function testNoHandlerReadsThePayloadThroughFullSpecialChars(): void
    {
        $offenders = [];
        foreach (glob(__DIR__ . '/../../app/sources/*.php') ?: [] as $path) {
            $source = (string) file_get_contents($path);
            $pattern = '/(?:filter_input\s*\(\s*INPUT_POST\s*,\s*[\'"]data[\'"]|->filter\s*\(\s*[\'"]data[\'"])[^;]*FILTER_SANITIZE_FULL_SPECIAL_CHARS/';
            if (preg_match($pattern, $source) === 1) {
                $offenders[] = basename($path);
            }
        }

        self::assertSame([], $offenders, 'Read the "data" payload raw (FILTER_UNSAFE_RAW) and sanitize each field after decoding.');
    }

    /**
     * @return array<string, array{callable(string): string}>
     */
    public static function unencryptedReads(): array
    {
        return [
            'raw read' => [
                static fn (string $payload): string => (string) filter_var($payload, FILTER_UNSAFE_RAW),
            ],
            // items, import, main.queries: dataSanitizer() 'trim|escape' (its AntiXSS pass left out).
            'special chars then trim|escape' => [
                static fn (string $payload): string => htmlspecialchars(strip_tags(trim((string) filter_var($payload, FILTER_SANITIZE_SPECIAL_CHARS)))),
            ],
        ];
    }

    /**
     * @param callable(string): string $read
     */
    #[DataProvider('unencryptedReads')]
    public function testUnencryptedPayloadDecodesLikeAnEncryptedOne(callable $read): void
    {
        $payload = (string) json_encode(self::VALUES, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        self::assertSame(self::VALUES, json_decode(teampassDecodeJsonPayload($read($payload)), true));
    }

    public function testFullSpecialCharsCannotBeInverted(): void
    {
        $payload = (string) json_encode(['title' => 'Sécurité', 'literal' => '&lt;'], JSON_UNESCAPED_UNICODE);

        $quotesKept = json_decode(teampassDecodeJsonPayload((string) filter_var($payload, FILTER_SANITIZE_FULL_SPECIAL_CHARS, FILTER_FLAG_NO_ENCODE_QUOTES)), true);
        self::assertSame('S&eacute;curit&eacute;', $quotesKept['title']);

        $quotesEncoded = json_decode(teampassDecodeJsonPayload((string) filter_var($payload, FILTER_SANITIZE_FULL_SPECIAL_CHARS)), true);
        self::assertSame('<', $quotesEncoded['literal']);
    }
}
