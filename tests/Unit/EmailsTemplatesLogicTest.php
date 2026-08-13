<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use voku\helper\AntiXSS;

require_once dirname(__DIR__, 2) . '/app/sources/emails_templates_logic.php';

/**
 * Save contract of the email templates administration page.
 *
 * The blocking rule is the required-token check: a body that lost #password#, #reset_url# or
 * #enc_code# produces an email nobody can act upon, and the shipped default would then be the
 * only way back. These tests pin that behaviour, plus the normalization applied before storage.
 */
class EmailsTemplatesLogicTest extends TestCase
{
    private AntiXSS $antiXss;

    protected function setUp(): void
    {
        $this->antiXss = new AntiXSS();
    }

    public function testSubjectLosesMarkupAndNewlines(): void
    {
        $this->assertSame(
            'Your account is ready',
            emailsTemplatesNormalizeSubject("  <b>Your account</b>\r\nis ready  ")
        );
    }

    public function testSubjectHeaderInjectionAttemptIsFlattened(): void
    {
        $subject = emailsTemplatesNormalizeSubject("Hello\r\nBcc: attacker@example.com");

        $this->assertStringNotContainsString("\r", $subject);
        $this->assertStringNotContainsString("\n", $subject);
    }

    public function testBodyKeepsTheMarkupEmailsRelyOn(): void
    {
        $body = emailsTemplatesNormalizeBody(
            '<b>Hello</b><br><ul><li>login: #login#</li></ul><a href="#reset_url#">Reset</a>',
            $this->antiXss
        );

        $this->assertStringContainsString('<b>Hello</b>', $body);
        $this->assertStringContainsString('<li>login: #login#</li>', $body);
        $this->assertStringContainsString('#reset_url#', $body);
    }

    /**
     * What the rich text editor produces must reach the recipient.
     *
     * Every button left in the toolbar emits one of these, so a change in the sanitizer
     * that swallows one of them turns into "the formatting disappears when I save".
     */
    public function testBodyKeepsWhatTheEditorToolbarProduces(): void
    {
        $body = emailsTemplatesNormalizeBody(
            "<p>Hello <b>bold</b> <i>italic</i> <u>underline</u></p>\n"
            . '<p>Second line<br>after a break</p>'
            . '<ol><li>one</li></ol><ul><li>two</li></ul>'
            . '<a href="#tp_link#">open</a>',
            $this->antiXss
        );

        foreach (
            [
                '<p>', '<b>bold</b>', '<i>italic</i>', '<u>underline</u>', '<br>',
                '<ol><li>one</li></ol>', '<ul><li>two</li></ul>', '<a href="#tp_link#">open</a>',
            ] as $markup
        ) {
            $this->assertStringContainsString(
                $markup,
                $body,
                sprintf('The save no longer keeps %s, which the editor can produce', $markup)
            );
        }
    }

    /**
     * Inline styles are dropped, which is why the toolbar offers no colour, no
     * highlighting and no alignment: the same sanitizing runs when the email is sent, so
     * offering them would only let the administrator format what nobody receives.
     */
    public function testBodyDropsInlineStyles(): void
    {
        $body = emailsTemplatesNormalizeBody(
            '<p style="text-align: center;"><span style="color: rgb(255, 0, 0);">red</span></p>',
            $this->antiXss
        );

        $this->assertStringNotContainsString('style=', $body);
        $this->assertStringContainsString('red', $body);
    }

    public function testBodyDropsScriptAndNewlines(): void
    {
        $body = emailsTemplatesNormalizeBody(
            "Hello\n<script>alert(1)</script>\r\n#password#",
            $this->antiXss
        );

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString("\n", $body);
        $this->assertStringNotContainsString("\r", $body);
        $this->assertStringContainsString('#password#', $body);
    }

    public function testShippedTemplatesSurviveTheNormalizationRoundTrip(): void
    {
        $root = dirname(__DIR__, 2);
        $catalog = require $root . '/app/config/emails_templates.php';
        $english = require $root . '/app/includes/language/english.php';

        foreach ($catalog as $id => $template) {
            $body = emailsTemplatesNormalizeBody($english[$template['body_key']], $this->antiXss);

            // Saving a template untouched must never make it invalid.
            $this->assertSame(
                [],
                emailsTemplatesMissingTokens($body, $template['required_tokens']),
                sprintf('Template "%s" loses a required token when saved unchanged', $id)
            );
        }
    }

    public function testMissingTokensAreReportedInDeclarationOrder(): void
    {
        $this->assertSame(
            ['#password#', '#enc_code#'],
            emailsTemplatesMissingTokens(
                'Hello #login#, welcome',
                ['#password#', '#login#', '#enc_code#']
            )
        );
    }

    public function testNoMissingTokenWhenAllArePresent(): void
    {
        $this->assertSame(
            [],
            emailsTemplatesMissingTokens('login #login# password #password#', ['#login#', '#password#'])
        );
    }

    public function testUnusedTokensIgnoreTheRequiredOnes(): void
    {
        // #password# is required and absent: it is the blocking check's business, not this one.
        $this->assertSame(
            ['#login#'],
            emailsTemplatesUnusedTokens(
                'Hello there',
                ['#login#', '#password#'],
                ['#password#']
            )
        );
    }

    public function testEveryCatalogTokenHasASampleValue(): void
    {
        $catalog = require dirname(__DIR__, 2) . '/app/config/emails_templates.php';
        $samples = emailsTemplatesSampleValues([]);

        foreach ($catalog as $id => $template) {
            $tokens = array_merge($template['tokens'], $template['subject_tokens'] ?? []);
            foreach ($tokens as $token) {
                $this->assertArrayHasKey(
                    $token,
                    $samples,
                    sprintf('Template "%s": token "%s" has no preview sample value', $id, $token)
                );
            }
        }
    }

    public function testPreviewLeavesNoDeclaredTokenBehind(): void
    {
        $root = dirname(__DIR__, 2);
        $catalog = require $root . '/app/config/emails_templates.php';
        $english = require $root . '/app/includes/language/english.php';
        $samples = emailsTemplatesSampleValues([]);

        foreach ($catalog as $id => $template) {
            $rendered = emailsTemplatesRenderPreview(
                $english[$template['body_key']],
                $template['tokens'],
                $samples
            );

            foreach ($template['tokens'] as $token) {
                $this->assertStringNotContainsString(
                    $token,
                    $rendered,
                    sprintf('Template "%s": token "%s" survived the preview rendering', $id, $token)
                );
            }
        }
    }

    public function testPreviewReplacesTheLongestTokenFirst(): void
    {
        // A short marker that is the prefix of a longer one must not consume it.
        $this->assertSame(
            'A=alpha B=beta',
            emailsTemplatesRenderPreview(
                'A=#tp# B=#tp_long#',
                ['#tp#', '#tp_long#'],
                ['#tp#' => 'alpha', '#tp_long#' => 'beta']
            )
        );
    }

    public function testPreviewMarksASampleLessTokenInsteadOfLeavingItRaw(): void
    {
        $this->assertSame(
            'Hello [brand_new]',
            emailsTemplatesRenderPreview('Hello #brand_new#', ['#brand_new#'], [])
        );
    }

    public function testPreviewDoesNotDiscloseSecretsResolvedAtSendTime(): void
    {
        $samples = emailsTemplatesSampleValues(['secret_placeholder' => '[later]']);

        $this->assertSame('[later]', $samples['#password#']);
        $this->assertSame('[later]', $samples['#enc_code#']);
    }

    public function testPreviewUsesTheProvidedContext(): void
    {
        $samples = emailsTemplatesSampleValues([
            'url' => 'https://vault.example.org',
            'login' => 'alice',
        ]);

        $this->assertSame('alice', $samples['#login#']);
        $this->assertSame('https://vault.example.org', $samples['#url#']);
        $this->assertStringStartsWith('https://vault.example.org/reset-password.php', $samples['#reset_url#']);
    }

    public function testTokenDetectionIsNotFooledByASubstring(): void
    {
        // '#tp_externalized_retention_days#' contains '#tp_externalized_retention_' but the
        // catalog only declares whole markers, so a partial match must not count as present.
        $this->assertSame(
            ['#url#'],
            emailsTemplatesMissingTokens('See #url_extra# for details', ['#url#'])
        );
    }
}
