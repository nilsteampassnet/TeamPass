<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sentinel: the issuer name shown by authenticator apps (setting ga_website_name).
 *
 * The MFA page printed the stored value into its input unescaped, which was safe only as long as
 * every writer stored it HTML-escaped. The legacy save_google_options action, left without a
 * caller since 3.0, stored it through htmlspecialchars_decode(): an administrator could break out
 * of the attribute. The action is gone and the page escapes the value itself.
 */
final class TotpIssuerSettingTest extends TestCase
{
    public function testTheLegacyActionThatStoredTheValueUnescapedIsGone(): void
    {
        $handler = (string) file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');

        $this->assertStringNotContainsString('save_google_options', $handler);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function storedValueProvider(): array
    {
        return [
            'attribute breakout' => [['ga_website_name' => '"><script>alert(1)</script>'], '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;'],
            'stored by save_option_change' => [['ga_website_name' => 'R&amp;D &quot;x&quot;'], 'R&amp;D &quot;x&quot;'],
            'accented letters as typed' => [['ga_website_name' => 'Société Passwords'], 'Société Passwords'],
            'legacy named entities' => [['ga_website_name' => 'Soci&eacute;t&eacute; Passwords'], 'Société Passwords'],
            'not set' => [[], ''],
        ];
    }

    /**
     * @param array<string, string> $SETTINGS Settings the page reads
     */
    #[DataProvider('storedValueProvider')]
    public function testTheMfaPageEscapesTheIssuerName(array $SETTINGS, string $expected): void
    {
        $page = (string) file_get_contents(__DIR__ . '/../../app/pages/2fa.php');
        $this->assertSame(1, preg_match('/id="ga_website_name" value="<\?php echo (.+?); \?>"/', $page, $match));

        // Evaluate the real attribute expression of the page against the stored value. The code
        // comes from the repository's own 2fa.php, never from outside input.
        $value = eval('return ' . $match[1] . ';');

        $this->assertSame($expected, $value);
        $this->assertStringNotContainsString('"', (string) $value);
        $this->assertStringNotContainsString('<', (string) $value);
    }
}
