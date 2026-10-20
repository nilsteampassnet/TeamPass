<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\EmailService\EmailService;
use TeampassClasses\EmailService\EmailSettings;

require_once __DIR__ . '/../../app/sources/admin_notices_logic.php';

/**
 * SMTP certificate verification (email_smtp_verify_certificate).
 *
 * EmailService used to disable the verification unconditionally, so anyone on the network
 * path to the relay could read the SMTP credentials and every email. New installations now
 * verify; upgraded ones keep the previous behaviour until the administrator opts in.
 */
class SmtpCertificateVerificationTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function settingsProvider(): array
    {
        return [
            'missing setting keeps the previous behaviour' => [[], false],
            'disabled' => [['email_smtp_verify_certificate' => '0'], false],
            'enabled' => [['email_smtp_verify_certificate' => '1'], true],
            'enabled as an integer' => [['email_smtp_verify_certificate' => 1], true],
            'empty value' => [['email_smtp_verify_certificate' => ''], false],
        ];
    }

    /**
     * @dataProvider settingsProvider
     *
     * @param array<string, mixed> $settings
     */
    public function testEmailSettingsReadsTheVerificationSetting(array $settings, bool $expected): void
    {
        $this->assertSame($expected, (new EmailSettings($settings))->verifyCertificate);
    }

    public function testTlsOptionsVerifyChainAndHostName(): void
    {
        $this->assertSame(
            ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed' => false]],
            EmailService::tlsOptions(true)
        );
    }

    public function testTlsOptionsWithoutVerification(): void
    {
        $this->assertSame(
            ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]],
            EmailService::tlsOptions(false)
        );
    }

    /**
     * @dataProvider settingsProvider
     *
     * @param array<string, mixed> $settings
     */
    public function testConfigureMailerAppliesTheSetting(array $settings, bool $expected): void
    {
        $service = new EmailService();
        $service->configureMailer(new EmailSettings($settings + ['email_security' => 'tls']), true, true);

        $mailer = new ReflectionProperty(EmailService::class, 'mailer');
        $mailer->setAccessible(true);

        $this->assertSame(EmailService::tlsOptions($expected), $mailer->getValue($service)->SMTPOptions);
    }

    public function testDashboardRecommendsVerificationOnlyForAnUnverifiedTlsRelay(): void
    {
        $relay = ['email_smtp_server' => 'smtp.example.com', 'email_security' => 'tls'];

        $this->assertTrue(adminNoticesSmtpCertificateUnverified($relay));
        $this->assertTrue(adminNoticesSmtpCertificateUnverified(['email_security' => 'ssl'] + $relay));
        $this->assertTrue(adminNoticesSmtpCertificateUnverified($relay + ['email_smtp_verify_certificate' => '0']));

        $this->assertFalse(adminNoticesSmtpCertificateUnverified($relay + ['email_smtp_verify_certificate' => '1']));
        $this->assertFalse(adminNoticesSmtpCertificateUnverified(['email_security' => 'none'] + $relay));
        $this->assertFalse(adminNoticesSmtpCertificateUnverified(['email_security' => 'tls']));
        $this->assertFalse(adminNoticesSmtpCertificateUnverified(['email_smtp_server' => '  '] + $relay));
    }

    public function testNewInstallationsVerifyAndUpgradesKeepThePreviousBehaviour(): void
    {
        $install = (string) file_get_contents(self::ROOT . 'public/install/install-steps/run.step5.php');
        $this->assertStringContainsString("array('admin', 'email_smtp_verify_certificate', '1')", $install);

        $upgrade = (string) file_get_contents(self::ROOT . 'public/install/upgrade_run_3.2.3.php');
        $this->assertMatchesRegularExpression(
            "/INSERT IGNORE INTO `\" \\. \\\$pre \\. \"misc` \\(`type`, `intitule`, `valeur`\\)\\s+VALUES \\('admin', 'email_smtp_verify_certificate', '0'\\)/",
            $upgrade
        );
    }

    public function testNoEmailServiceCopyDisablesTheVerificationUnconditionally(): void
    {
        foreach (['app/vendor/', 'app/includes/libraries/'] as $base) {
            $source = (string) file_get_contents(self::ROOT . $base . 'teampassclasses/emailservice/src/EmailService.php');
            $this->assertStringNotContainsString("'verify_peer' => false", $source, $base . ' copy');
            $this->assertStringContainsString('self::tlsOptions($emailSettings->verifyCertificate === true)', $source, $base . ' copy');
        }
    }
}
