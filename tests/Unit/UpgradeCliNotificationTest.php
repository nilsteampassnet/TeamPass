<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../../app/sources/log_display_logic.php';

/**
 * How the command-line upgrade reports its outcome: administration log labels and e-mail.
 */
final class UpgradeCliNotificationTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return list<string> Outcomes the command line writes to the administration log */
    private static function auditedOutcomes(): array
    {
        $source = (string) file_get_contents(self::root() . '/app/scripts/upgrade-cli.php');
        preg_match_all("/(?:upgradeCliReport|tpUpgradeCliAuditLog)\(\s*'([a-z_]+)'/", $source, $matches);

        return array_values(array_unique($matches[1]));
    }

    public function testEveryOutcomeHasALogLabelInBothLanguages(): void
    {
        $outcomes = self::auditedOutcomes();
        $this->assertContains('reverted', $outcomes);
        $this->assertContains('completed', $outcomes);

        foreach (['english', 'french'] as $language) {
            $strings = require self::root() . '/app/includes/language/' . $language . '.php';
            foreach ($outcomes as $outcome) {
                $this->assertArrayHasKey('upgrade_cli_log_' . $outcome, $strings, $language . ' lacks the label of ' . $outcome);
            }
        }
    }

    public function testLogLabelShowsTheOutcomeAndBothReleases(): void
    {
        $lang = new Language('english', self::root() . '/app/includes/language');

        $this->assertSame(
            'Command-line upgrade reverted (3.2.3.3 → 3.2.3.4)',
            formatAdminLogLabel('at_upgrade_cli_reverted:3.2.3.3:3.2.3.4', $lang)
        );
        // Anything else after the prefix stays as stored.
        $this->assertSame('at_upgrade_cli_reverted:<b>', formatAdminLogLabel('at_upgrade_cli_reverted:<b>', $lang));
    }

    public function testShippedBodiesCarryEveryToken(): void
    {
        $catalog = require self::root() . '/app/config/emails_templates.php';
        $template = $catalog['upgrade_cli_report'];

        foreach (['english', 'french'] as $language) {
            $strings = require self::root() . '/app/includes/language/' . $language . '.php';
            foreach ($template['tokens'] as $token) {
                $this->assertStringContainsString($token, $strings[$template['body_key']], $language . ' body lacks ' . $token);
            }
            $this->assertStringContainsString('#tp_status#', $strings[$template['subject_key']]);
        }
    }

    public function testMailIsSentSilently(): void
    {
        // An SMTP debug transcript would print the AUTH exchange on the operator's terminal.
        $functions = (string) file_get_contents(self::root() . '/app/sources/upgrade_cli.functions.php');

        $this->assertStringContainsString("\$emailService->sendMail(\$subject, \$body, (string) \$admin['email'], \$emailSettings, '', true, true);", $functions);
    }
}
