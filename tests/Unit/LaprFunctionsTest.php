<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/includes/libraries/teampassclasses/lapr/src/LAPRSshService.php';
require_once __DIR__ . '/../../app/sources/lapr.functions.php';

use TeampassClasses\Lapr\LAPRSshService;

/**
 * Unit tests for the DB-free LAPR helpers in app/sources/lapr.functions.php
 * and the pure static methods of LAPRSshService.
 *
 * Security-critical coverage:
 *   - laprValidateUsername()        (R1 — command-injection guard)
 *   - laprIsPasswordSafeForLinux()  (R9 — chpasswd corruption guard)
 *   - laprValidatePolicyParams()    (Point 3 bounds)
 *   - laprComputeNextRotation()     (scheduling)
 *   - laprIsHostnameAllowed()       (R3 — SSRF allowlist)
 *   - LAPRSshService::computeFingerprint() (TOFU)
 */
class LaprFunctionsTest extends TestCase
{
    // =========================================================================
    // laprValidateUsername (R1)
    // =========================================================================

    /**
     * @dataProvider validUsernames
     */
    public function testValidUsernamesAccepted(string $username): void
    {
        $this->assertTrue(laprValidateUsername($username));
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function validUsernames(): array
    {
        return [
            ['root'],
            ['www-data'],
            ['user_1'],
            ['_svc'],
            ['postgres'],
            ['a'],
            ['deploy$'],   // trailing $ allowed (Samba machine accounts)
        ];
    }

    /**
     * @dataProvider invalidUsernames
     */
    public function testInvalidUsernamesRejected(string $username): void
    {
        $this->assertFalse(laprValidateUsername($username));
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function invalidUsernames(): array
    {
        return [
            ['root; rm -rf /'],       // shell metacharacters
            ['$(whoami)'],
            ['`id`'],
            ['user name'],            // space
            ['Root'],                 // uppercase not allowed by POSIX rule
            ['1abc'],                 // cannot start with a digit
            ['-abc'],                 // cannot start with a dash
            [''],                     // empty
            ['a:b'],                  // colon (chpasswd separator)
            ['toolongusernametoolongusernametoolong'], // > 32 chars
        ];
    }

    // =========================================================================
    // laprIsPasswordSafeForLinux (R9)
    // =========================================================================

    public function testSafePasswordsAccepted(): void
    {
        $this->assertTrue(laprIsPasswordSafeForLinux('Abc123!@#%^&*()-_=+'));
        $this->assertTrue(laprIsPasswordSafeForLinux('sTr0ngP4ssw0rd'));
    }

    /**
     * @dataProvider unsafePasswords
     */
    public function testUnsafePasswordsRejected(string $password): void
    {
        $this->assertFalse(laprIsPasswordSafeForLinux($password));
    }

    /**
     * @return array<int, array<int, string>>
     */
    public static function unsafePasswords(): array
    {
        return [
            ['has:colon'],          // chpasswd field separator
            ['has space'],
            ["has\ttab"],
            ["has\nnewline"],
            ['has\\backslash'],
            ["has'quote"],
            ['has"quote'],
            [''],                    // empty
            ['café'],                // non-ASCII
        ];
    }

    // =========================================================================
    // laprValidatePolicyParams (Point 3)
    // =========================================================================

    public function testPolicyParamsWithinBounds(): void
    {
        $this->assertTrue(laprValidatePolicyParams(30, 24, true, true, true, true));
        $this->assertTrue(laprValidatePolicyParams(1, 8, false, true, false, false));
        $this->assertTrue(laprValidatePolicyParams(3650, 128, true, false, false, false));
    }

    public function testPolicyParamsRejectOutOfBounds(): void
    {
        $this->assertFalse(laprValidatePolicyParams(0, 24, true, true, true, true));      // frequency < 1
        $this->assertFalse(laprValidatePolicyParams(3651, 24, true, true, true, true));   // frequency > 3650
        $this->assertFalse(laprValidatePolicyParams(30, 7, true, true, true, true));      // length < 8
        $this->assertFalse(laprValidatePolicyParams(30, 129, true, true, true, true));    // length > 128
        $this->assertFalse(laprValidatePolicyParams(30, 24, false, false, false, false)); // no charset
    }

    // =========================================================================
    // laprComputeNextRotation
    // =========================================================================

    public function testNextRotationFromNullUsesNowPlusFrequency(): void
    {
        $now = 1_000_000_000;
        $expected = date('Y-m-d H:i:s', $now + 30 * 86400);
        $this->assertSame($expected, laprComputeNextRotation(null, 30, $now));
    }

    public function testNextRotationFromLastRotation(): void
    {
        $now = 1_000_000_000;
        $last = date('Y-m-d H:i:s', $now);          // last rotation = now
        $expected = date('Y-m-d H:i:s', $now + 7 * 86400);
        $this->assertSame($expected, laprComputeNextRotation($last, 7, $now));
    }

    public function testNextRotationClampedToNowWhenInPast(): void
    {
        $now = 1_000_000_000;
        // last rotation far in the past + short frequency => computed date is behind now
        $last = date('Y-m-d H:i:s', $now - 100 * 86400);
        $expected = date('Y-m-d H:i:s', $now);
        $this->assertSame($expected, laprComputeNextRotation($last, 7, $now));
    }

    // =========================================================================
    // laprIsHostnameAllowed (R3)
    // =========================================================================

    public function testAllowlistDisabledAllowsEverything(): void
    {
        $settings = ['lapr_allowlist_enabled' => '0', 'lapr_allowlist' => ''];
        $this->assertTrue(laprIsHostnameAllowed('anything.internal', $settings));
    }

    public function testAllowlistEnabledButEmptyDeniesAll(): void
    {
        $settings = ['lapr_allowlist_enabled' => '1', 'lapr_allowlist' => ''];
        $this->assertFalse(laprIsHostnameAllowed('server.example.com', $settings));
    }

    public function testAllowlistExactMatch(): void
    {
        $settings = ['lapr_allowlist_enabled' => '1', 'lapr_allowlist' => "web01.example.com, 10.0.0.5"];
        $this->assertTrue(laprIsHostnameAllowed('web01.example.com', $settings));
        $this->assertTrue(laprIsHostnameAllowed('10.0.0.5', $settings));
        $this->assertFalse(laprIsHostnameAllowed('web02.example.com', $settings));
    }

    public function testAllowlistWildcardSuffix(): void
    {
        $settings = ['lapr_allowlist_enabled' => '1', 'lapr_allowlist' => '*.example.com'];
        $this->assertTrue(laprIsHostnameAllowed('web01.example.com', $settings));
        $this->assertTrue(laprIsHostnameAllowed('db.example.com', $settings));
        $this->assertFalse(laprIsHostnameAllowed('example.com', $settings));      // suffix requires a subdomain
        $this->assertFalse(laprIsHostnameAllowed('evil.example.org', $settings));
    }

    // =========================================================================
    // LAPRSshService::computeFingerprint (TOFU)
    // =========================================================================

    public function testFingerprintIsDeterministicAndPrefixed(): void
    {
        $key = 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAExampleKeyMaterial';
        $fp1 = LAPRSshService::computeFingerprint($key);
        $fp2 = LAPRSshService::computeFingerprint($key);

        $this->assertSame($fp1, $fp2);
        $this->assertStringStartsWith('SHA256:', $fp1);
    }

    public function testFingerprintDiffersForDifferentKeys(): void
    {
        $fpA = LAPRSshService::computeFingerprint('ssh-rsa AAAAB3KeyA');
        $fpB = LAPRSshService::computeFingerprint('ssh-rsa AAAAB3KeyB');
        $this->assertNotSame($fpA, $fpB);
    }
}
