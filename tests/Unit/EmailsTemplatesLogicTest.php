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
