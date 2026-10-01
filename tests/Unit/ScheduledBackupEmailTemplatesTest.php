<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\EmailService\EmailService;

require_once dirname(__DIR__, 2) . '/app/sources/emails_templates_logic.php';

/**
 * Backup defaults must survive delivery sanitization and POEditor round trips.
 */
class ScheduledBackupEmailTemplatesTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function languageFilesProvider(): iterable
    {
        foreach (glob(dirname(__DIR__, 2) . '/app/includes/language/*.php') ?: [] as $file) {
            yield basename($file, '.php') => [$file];
        }
    }

    /**
     * Exercise the send-time sanitizer: the editor's normalizer alone does not
     * detect that a label such as "file(s)" makes EmailService escape all HTML.
     *
     * @dataProvider languageFilesProvider
     */
    public function testBackupReportsRetainTheirHtmlAtDelivery(string $file): void
    {
        $language = require $file;
        $samples = emailsTemplatesSampleValues([]);
        $externalized = strtr($language['email_body_scheduled_backup_externalized_report'], $samples);
        $standalone = strtr($language['email_body_scheduled_backup_report'], $samples);
        $samples['#tp_externalized_report#'] = $externalized;
        $combined = strtr($language['email_body_scheduled_backup_report'], $samples);

        foreach (['externalized' => $externalized, 'standalone' => $standalone, 'combined' => $combined] as $scenario => $body) {
            $this->assertStringNotContainsString('#tp_', $body);
            $this->assertStringContainsString('<table ', $body);
            $service = new EmailService();
            $this->assertSame(
                $body,
                $service->sanitizeEmailBody($body),
                basename($file) . ': ' . $scenario . ' report must reach the mailer as HTML'
            );
        }
    }

    /**
     * POEditor expects each translation entry on one physical source line.
     *
     * @dataProvider languageFilesProvider
     */
    public function testBackupTemplatesUseSingleLineEntries(string $file): void
    {
        $source = (string) file_get_contents($file);
        $language = require $file;
        foreach (['email_body_scheduled_backup_report', 'email_body_scheduled_backup_externalized_report'] as $key) {
            $this->assertMatchesRegularExpression(
                "/^    '" . $key . "' => '[^\r\n]*',\r?$/m",
                $source,
                basename($file) . ': ' . $key . ' must occupy one source line'
            );
            $this->assertStringNotContainsString("\r", $language[$key]);
            $this->assertStringNotContainsString("\n", $language[$key]);
        }
    }
}
