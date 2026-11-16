<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See https://www.gnu.org/licenses/ and the licenses directory.
 */

use PHPUnit\Framework\TestCase;

class SecureSendPassphrasePresentationTest extends TestCase
{
    /** Render the shipped confirmation form without a database or link credentials. */
    private function form(bool $protected, string $label): DOMXPath
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/core/otv.php');
        $start = strpos($source, '<form method="post"');
        self::assertIsInt($start);
        $end = strpos($source, '</form>', $start);
        self::assertIsInt($end);
        $template = substr($source, $start, $end + strlen('</form>') - $start);
        $parameters = [];
        $token = 'fixture-confirmation';
        $link = ['has_passphrase' => (int) $protected];
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $lang = new class($label) {
            /** Keep the fixture independent of language services and installation settings. */
            public function __construct(private string $label)
            {
            }

            /** Return the fixture translation for each rendered label. */
            public function get(string $key): string
            {
                return $this->label;
            }
        };
        ob_start();
        try {
            eval('?>' . $template);
            $html = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        return new DOMXPath($document);
    }

    /** The toggle must never submit the reveal form, including keyboard activation. */
    public function testProtectedFormStartsMaskedAndHasANonSubmittingToggle(): void
    {
        foreach (['english', 'french'] as $locale) {
            $messages = require __DIR__ . '/../../app/includes/language/' . $locale . '.php';
            $label = $messages['secure_send_show_passphrase'];
            $xpath = $this->form(true, $label);
            self::assertSame(1, $xpath->query('//input[@id="passphrase" and @name="passphrase" and @type="password" and @required]')->length);
            self::assertSame(1, $xpath->query('//button[@id="toggle-passphrase" and @type="button" and @aria-controls="passphrase" and @aria-pressed="false" and @hidden]')->length);
            self::assertSame($label, $xpath->evaluate('string(//button[@id="toggle-passphrase"]/@aria-label)'));
            self::assertSame(1, $xpath->query('//button[@type="submit"]')->length);
        }
    }

    /** Unprotected sends retain their direct confirmation form. */
    public function testUnprotectedFormHasNoPassphraseToggle(): void
    {
        $xpath = $this->form(false, 'Show passphrase');
        self::assertSame(0, $xpath->query('//input[@name="passphrase"] | //button[@id="toggle-passphrase"]')->length);
        self::assertSame(1, $xpath->query('//button[@type="submit"]')->length);
    }

    /** The CSP authorizes the toggle by its digest alone, so no other script can run on the page. */
    public function testOnlyTheToggleScriptIsAuthorizedByItsDigest(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/core/otv.php');
        self::assertSame(1, preg_match('/header\("Content-Security-Policy: ([^"]+)"\);/', $source, $csp));
        $directives = array_map('trim', explode(';', $csp[1]));
        self::assertContains("default-src 'none'", $directives);
        self::assertContains("script-src 'sha256-\$passphraseToggleHash'", $directives);
        self::assertStringNotContainsString('nonce-', $csp[1]);
        self::assertStringNotContainsString('unsafe-eval', $csp[1]);
        // Any character added around the echoed text would change what the browser hashes.
        self::assertSame(1, substr_count($source, '<script'));
        self::assertSame(1, substr_count($source, '<script><?php echo $passphraseToggleScript; ?></script>'));
    }

    /** A CRLF checkout must emit and hash the LF text browsers parse, or the toggle would be blocked. */
    public function testToggleDigestDoesNotDependOnCheckoutLineEndings(): void
    {
        $source = str_replace("\r\n", "\n", (string) file_get_contents(__DIR__ . '/../../app/core/otv.php'));
        $results = [];
        foreach (["\n", "\r\n"] as $eol) {
            $code = str_replace("\n", $eol, $source);
            $start = strpos($code, '$passphraseToggleScript = ');
            self::assertIsInt($start);
            $hashStart = strpos($code, '$passphraseToggleHash = ', $start);
            self::assertIsInt($hashStart);
            $end = strpos($code, ';', $hashStart);
            self::assertIsInt($end);
            // Evaluates only the two assignments cut from the shipped otv.php, as form() does.
            $results[] = (static function (string $snippet): array {
                eval($snippet);
                return [$passphraseToggleScript, $passphraseToggleHash];
            })(substr($code, $start, $end + 1 - $start));
        }
        [[$script, $hash], [$crlfScript, $crlfHash]] = $results;
        self::assertStringNotContainsString("\r", $crlfScript);
        self::assertSame($script, $crlfScript);
        self::assertSame($hash, $crlfHash);
        self::assertSame(base64_encode(hash('sha256', $script, true)), $hash);
        self::assertStringStartsWith('(() => {', $script);
        self::assertStringEndsWith('})()', $script);
    }

    /** Quotes and markup in translations must stay within the accessible label. */
    public function testToggleLabelCannotInjectMarkupOrAttributes(): void
    {
        $label = 'R&D O\'Brien "<img src=x onerror=alert(1)>"';
        $xpath = $this->form(true, $label);
        self::assertSame($label, $xpath->evaluate('string(//button[@id="toggle-passphrase"]/@aria-label)'));
        self::assertSame($label, $xpath->evaluate('string(//button[@id="toggle-passphrase"]/@title)'));
        self::assertSame(0, $xpath->query('//img | //@onerror')->length);
    }
}
