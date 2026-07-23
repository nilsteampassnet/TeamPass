<?php

declare(strict_types=1);

use ParagonIE\ConstantTime\Base32;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/otp.functions.php';

/**
 * Regression coverage for the RFC 6238 item TOTP profiles.
 */
final class ItemTotpConfigurationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int, string, string}>
     */
    public static function rfc6238Vectors(): iterable
    {
        $timestamps = [59, 1111111109, 1111111111, 1234567890, 2000000000, 20000000000];
        $vectors = [
            'sha1' => [
                'secret' => '12345678901234567890',
                'codes' => ['94287082', '07081804', '14050471', '89005924', '69279037', '65353130'],
            ],
            'sha256' => [
                'secret' => '12345678901234567890123456789012',
                'codes' => ['46119246', '68084774', '67062674', '91819424', '90698825', '77737706'],
            ],
            'sha512' => [
                'secret' => '1234567890123456789012345678901234567890123456789012345678901234',
                'codes' => ['90693936', '25091201', '99943326', '93441116', '38618901', '47863826'],
            ],
        ];

        foreach ($vectors as $algorithm => $vector) {
            $secret = Base32::encodeUpper($vector['secret']);
            foreach ($timestamps as $index => $timestamp) {
                yield $algorithm . '-' . $timestamp => [
                    $algorithm,
                    $timestamp,
                    $vector['codes'][$index],
                    $secret,
                ];
            }
        }
    }

    #[DataProvider('rfc6238Vectors')]
    public function testRfc6238Vectors(
        string $algorithm,
        int $timestamp,
        string $expectedCode,
        string $secret
    ): void {
        $totp = createItemTotp($secret, $algorithm, 8, 30);

        self::assertSame($expectedCode, $totp->at($timestamp));
    }

    public function testBareSecretKeepsHistoricalProfileByDefault(): void
    {
        $configuration = normalizeItemTotpConfiguration('jbswy3dpehpk3pxp====');

        self::assertSame('JBSWY3DPEHPK3PXP', $configuration['secret']);
        self::assertSame('sha1', $configuration['algorithm']);
        self::assertSame(6, $configuration['digits']);
        self::assertSame(30, $configuration['period']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function separatedSecrets(): iterable
    {
        yield 'spaced groups' => ['JBSW Y3DP EHPK 3PXP'];
        yield 'hyphenated groups' => ['JBSWY3DP-EHPK-3PXP'];
        yield 'lowercase spaced groups' => ['jbsw y3dp ehpk 3pxp'];
        yield 'padded and spaced' => ['JBSW Y3DP EHPK 3PXP===='];
    }

    /**
     * Services display the secret in readable groups. Those separators carry no
     * meaning and must not make an otherwise valid secret unusable.
     */
    #[DataProvider('separatedSecrets')]
    public function testDisplaySeparatorsAreStrippedFromTheSecret(string $secret): void
    {
        $configuration = normalizeItemTotpConfiguration($secret);

        self::assertSame('JBSWY3DPEHPK3PXP', $configuration['secret']);
    }

    /**
     * A separated secret must generate exactly the same codes as its compact form,
     * so an item stored before the normalization keeps working untouched.
     */
    public function testSeparatedSecretGeneratesTheSameCodeAsTheCompactForm(): void
    {
        $compact = createItemTotp('JBSWY3DPEHPK3PXP');
        $spaced = createItemTotp('JBSW Y3DP EHPK 3PXP');

        self::assertSame($compact->at(1111111111), $spaced->at(1111111111));
    }

    /**
     * Stripping separators must not turn an invalid secret into a valid one.
     */
    public function testSeparatorStrippingDoesNotRescueInvalidSecrets(): void
    {
        $this->expectException(InvalidArgumentException::class);

        normalizeItemTotpConfiguration('NOT-BASE32!');
    }

    public function testNormalizeItemTotpSecretIsIdempotent(): void
    {
        $once = normalizeItemTotpSecret('jbsw y3dp-ehpk 3pxp====');

        self::assertSame('JBSWY3DPEHPK3PXP', $once);
        self::assertSame($once, normalizeItemTotpSecret($once));
    }

    /**
     * The read path skips provisioning URI parsing, but it must keep rejecting a
     * stored profile that could silently produce wrong codes.
     */
    public function testCreateItemTotpRejectsAnUnsupportedStoredProfile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        createItemTotp('JBSWY3DPEHPK3PXP', 'md5', 6, 30);
    }

    /**
     * The read path no longer forces a throw-away decoding, so an unusable stored
     * secret must still surface when the code is actually generated.
     */
    public function testCreateItemTotpSurfacesAnUnusableStoredSecretOnGeneration(): void
    {
        $totp = createItemTotp('NOTBASE32!');

        $this->expectException(Throwable::class);

        $totp->now();
    }

    public function testValidateItemTotpProfileReturnsTheNormalizedProfile(): void
    {
        self::assertSame(
            [
                'algorithm' => 'sha512',
                'digits' => 8,
                'period' => 60,
            ],
            validateItemTotpProfile(' SHA512 ', 8, 60)
        );
    }

    public function testProvisioningUriSuppliesTheCompleteProfile(): void
    {
        $configuration = normalizeItemTotpConfiguration(
            'otpauth://totp/Example:user?secret=JBSWY3DPEHPK3PXP&issuer=Example&algorithm=SHA512&digits=8&period=45'
        );

        self::assertSame('JBSWY3DPEHPK3PXP', $configuration['secret']);
        self::assertSame('sha512', $configuration['algorithm']);
        self::assertSame(8, $configuration['digits']);
        self::assertSame(45, $configuration['period']);
    }

    public function testProvisioningUriDefaultsMatchLegacyProfile(): void
    {
        $configuration = normalizeItemTotpConfiguration(
            'otpauth://totp/Example:user?secret=JBSWY3DPEHPK3PXP&issuer=Example'
        );

        self::assertSame('sha1', $configuration['algorithm']);
        self::assertSame(6, $configuration['digits']);
        self::assertSame(30, $configuration['period']);
    }

    public function testHotpProvisioningUriIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        normalizeItemTotpConfiguration(
            'otpauth://hotp/Example:user?secret=JBSWY3DPEHPK3PXP&counter=0'
        );
    }

    /**
     * @return iterable<string, array{string, string, int, int}>
     */
    public static function invalidProfiles(): iterable
    {
        yield 'unsupported algorithm' => ['JBSWY3DPEHPK3PXP', 'md5', 6, 30];
        yield 'unsupported digits' => ['JBSWY3DPEHPK3PXP', 'sha1', 7, 30];
        yield 'zero period' => ['JBSWY3DPEHPK3PXP', 'sha1', 6, 0];
        yield 'excessive period' => ['JBSWY3DPEHPK3PXP', 'sha1', 6, 86401];
        yield 'invalid Base32' => ['NOT-BASE32!', 'sha1', 6, 30];
    }

    #[DataProvider('invalidProfiles')]
    public function testInvalidProfilesAreRejected(
        string $secret,
        string $algorithm,
        int $digits,
        int $period
    ): void {
        $this->expectException(InvalidArgumentException::class);

        normalizeItemTotpConfiguration($secret, $algorithm, $digits, $period);
    }

    public function testInstallerAndUpgradePreserveLegacyDefaults(): void
    {
        $installer = file_get_contents(
            __DIR__ . '/../../public/install/install-steps/run.step5.php'
        );
        $upgrade = file_get_contents(
            __DIR__ . '/../../public/install/upgrade_run_3.2.1.php'
        );

        self::assertIsString($installer);
        self::assertIsString($upgrade);
        foreach ([
            "DEFAULT 'sha1'",
            'DEFAULT 6',
            'DEFAULT 30',
        ] as $defaultDefinition) {
            self::assertStringContainsString($defaultDefinition, $installer);
            self::assertStringContainsString($defaultDefinition, $upgrade);
        }
    }

    /**
     * The separator tolerance is user-visible behaviour: keep it documented.
     */
    public function testSecretSeparatorToleranceIsDocumented(): void
    {
        $featureDoc = file_get_contents(__DIR__ . '/../../docs/features/items.md');
        $apiDoc = file_get_contents(__DIR__ . '/../../docs/api/api-basic.md');

        self::assertIsString($featureDoc);
        self::assertIsString($apiDoc);
        self::assertStringContainsString('Spaces and hyphens are separators only', $featureDoc);
        self::assertStringContainsString('Spaces and hyphens are stripped', $apiDoc);
    }

    public function testItemPageKeepsTotpPeriodLimitOutsideGeneratedJavascript(): void
    {
        $itemPage = file_get_contents(__DIR__ . '/../../app/pages/items.php');
        $itemScript = file_get_contents(__DIR__ . '/../../app/pages/items.js.php');

        self::assertIsString($itemPage);
        self::assertIsString($itemScript);
        self::assertStringContainsString(
            'max="<?php echo ITEM_TOTP_MAX_PERIOD; ?>"',
            $itemPage
        );
        self::assertStringContainsString(
            "const maxPeriod = Number($('#form-item-otpPeriod').attr('max'))",
            $itemScript
        );
        self::assertStringNotContainsString('const maxPeriod = <?php', $itemScript);
    }
}
