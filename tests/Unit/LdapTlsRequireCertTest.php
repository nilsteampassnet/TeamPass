<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TeampassClasses\LdapExtra\LdapExtra;

require_once __DIR__ . '/../../app/includes/libraries/teampassclasses/ldapextra/src/LdapExtra.php';

/**
 * Unit tests for the LDAP TLS certificate check setting conversion.
 *
 * The admin form stores the constant NAME as a string (e.g. 'LDAP_OPT_X_TLS_NEVER')
 * while ldap_set_option() requires the matching INTEGER value. Passing the raw string
 * raised a TypeError on PHP 8 (issue #5289).
 */
class LdapTlsRequireCertTest extends TestCase
{
    protected function setUp(): void
    {
        if (defined('LDAP_OPT_X_TLS_NEVER') === false) {
            $this->markTestSkipped('The ldap extension is not loaded.');
        }
    }

    /**
     * Call the private mapping method on a LdapExtra instance built with $settings.
     *
     * @param array $settings Teampass settings
     *
     * @return mixed
     */
    private function resolve(array $settings)
    {
        $instance = new LdapExtra($settings);
        $method = new ReflectionMethod(LdapExtra::class, 'getTlsRequireCertValue');
        $method->setAccessible(true);

        return $method->invoke($instance);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function constantNameProvider(): array
    {
        return [
            'never'  => ['LDAP_OPT_X_TLS_NEVER', LDAP_OPT_X_TLS_NEVER],
            'hard'   => ['LDAP_OPT_X_TLS_HARD', LDAP_OPT_X_TLS_HARD],
            'demand' => ['LDAP_OPT_X_TLS_DEMAND', LDAP_OPT_X_TLS_DEMAND],
            'allow'  => ['LDAP_OPT_X_TLS_ALLOW', LDAP_OPT_X_TLS_ALLOW],
            'try'    => ['LDAP_OPT_X_TLS_TRY', LDAP_OPT_X_TLS_TRY],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('constantNameProvider')]
    public function testStoredConstantNameIsConvertedToItsIntegerValue(string $name, int $expected): void
    {
        $value = $this->resolve(['ldap_tls_certificate_check' => $name]);

        $this->assertIsInt($value, 'ldap_set_option() requires an integer, not a string');
        $this->assertSame($expected, $value);
    }

    public function testLegacyIntegerValueIsPreserved(): void
    {
        $this->assertSame(LDAP_OPT_X_TLS_NEVER, $this->resolve(['ldap_tls_certificate_check' => 0]));
        $this->assertSame(LDAP_OPT_X_TLS_DEMAND, $this->resolve(['ldap_tls_certificate_check' => '2']));
    }

    public function testUnknownOrMissingValueFallsBackToHard(): void
    {
        // Fallback must be the safest option, and matches the only behaviour that
        // was actually in effect before the fix (LdapExtra always used HARD).
        $this->assertSame(LDAP_OPT_X_TLS_HARD, $this->resolve([]));
        $this->assertSame(LDAP_OPT_X_TLS_HARD, $this->resolve(['ldap_tls_certificate_check' => '']));
        $this->assertSame(LDAP_OPT_X_TLS_HARD, $this->resolve(['ldap_tls_certificate_check' => 'nonsense']));
        $this->assertSame(LDAP_OPT_X_TLS_HARD, $this->resolve(['ldap_tls_certificate_check' => 99]));
    }

    /**
     * Sentinel: the two LdapExtra copies must stay byte-identical.
     *
     * LdapExtra exists in two locations (project rule — edit both):
     *   - app/vendor/teampassclasses/ldapextra/src/LdapExtra.php            (Composer / autoloaded)
     *   - app/includes/libraries/teampassclasses/ldapextra/src/LdapExtra.php
     */
    public function testLdapExtraCopiesAreByteIdentical(): void
    {
        $root     = dirname(__DIR__, 2);
        $vendor   = $root . '/app/vendor/teampassclasses/ldapextra/src/LdapExtra.php';
        $includes = $root . '/app/includes/libraries/teampassclasses/ldapextra/src/LdapExtra.php';

        $this->assertFileExists($vendor, 'Composer copy of LdapExtra is missing');
        $this->assertFileExists($includes, 'includes/libraries copy of LdapExtra is missing');

        $this->assertSame(
            hash_file('sha256', $vendor),
            hash_file('sha256', $includes),
            'The two LdapExtra copies have diverged — edit both identically (see CLAUDE.md).'
        );
    }
}
