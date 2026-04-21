<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Stubs/auth_pure_functions.php';

/**
 * Unit tests for the pure utility functions defined in auth_pure_functions.php.
 *
 * These functions are extracted verbatim from sources/main.functions.php and
 * are used in the authentication flow. They carry no DB or session dependency.
 *
 * Functions covered:
 *   - isKeyExistingAndEqual()
 *   - isOneVarOfArrayEqualToValue()
 *   - teampassNormalizeLegacyPassword()
 *   - teampassDecodeJsonPayload()
 */
class PureFunctionsTest extends TestCase
{
    // =========================================================================
    // isKeyExistingAndEqual
    // =========================================================================

    public function testReturnsTrueWhenIntKeyMatchesIntValue(): void
    {
        $this->assertTrue(isKeyExistingAndEqual('count', 5, ['count' => 5]));
    }

    public function testReturnsTrueWhenStringKeyMatchesStringValue(): void
    {
        $this->assertTrue(isKeyExistingAndEqual('mode', 'ldap', ['mode' => 'ldap']));
    }

    public function testReturnsFalseWhenKeyIsMissing(): void
    {
        $this->assertFalse(isKeyExistingAndEqual('missing', 1, ['other' => 1]));
    }

    public function testReturnsFalseWhenIntValueDiffers(): void
    {
        $this->assertFalse(isKeyExistingAndEqual('count', 5, ['count' => 6]));
    }

    public function testReturnsFalseWhenStringValueDiffers(): void
    {
        $this->assertFalse(isKeyExistingAndEqual('mode', 'ldap', ['mode' => 'local']));
    }

    public function testIntValueCastsArrayValueToInt(): void
    {
        // Array contains '1' (string), comparison value is 1 (int) → cast happens
        $this->assertTrue(isKeyExistingAndEqual('flag', 1, ['flag' => '1']));
    }

    public function testStringValueCastsArrayValueToString(): void
    {
        // Array contains 42 (int), comparison value is '42' (string) → cast happens
        $this->assertTrue(isKeyExistingAndEqual('id', '42', ['id' => 42]));
    }

    public function testReturnsFalseOnEmptyArray(): void
    {
        $this->assertFalse(isKeyExistingAndEqual('key', 1, []));
    }

    public function testReturnsFalseWhenIntValueIsZeroButArrayHasOne(): void
    {
        $this->assertFalse(isKeyExistingAndEqual('flag', 0, ['flag' => 1]));
    }

    public function testReturnsTrueForZeroIntValue(): void
    {
        $this->assertTrue(isKeyExistingAndEqual('flag', 0, ['flag' => 0]));
    }

    public function testReturnsTrueForEmptyStringValue(): void
    {
        $this->assertTrue(isKeyExistingAndEqual('label', '', ['label' => '']));
    }

    // =========================================================================
    // isOneVarOfArrayEqualToValue
    // =========================================================================

    public function testReturnsTrueWhenIntValueFoundInArray(): void
    {
        $this->assertTrue(isOneVarOfArrayEqualToValue([0, 1, 0], 1));
    }

    public function testReturnsFalseWhenIntValueNotInArray(): void
    {
        $this->assertFalse(isOneVarOfArrayEqualToValue([0, 0, 0], 1));
    }

    public function testReturnsTrueWhenStringValueFoundInArray(): void
    {
        $this->assertTrue(isOneVarOfArrayEqualToValue(['a', 'b', 'c'], 'b'));
    }

    public function testReturnsFalseWhenStringValueNotInArray(): void
    {
        $this->assertFalse(isOneVarOfArrayEqualToValue(['a', 'b', 'c'], 'z'));
    }

    public function testReturnsFalseOnEmptyArrayForOneVar(): void
    {
        $this->assertFalse(isOneVarOfArrayEqualToValue([], 1));
    }

    public function testUsesStrictComparisonIntVsString(): void
    {
        // 1 (int) must NOT match '1' (string) — strict comparison
        $this->assertFalse(isOneVarOfArrayEqualToValue(['1', '0'], 1));
    }

    public function testUsesStrictComparisonStringVsInt(): void
    {
        // '1' (string) must NOT match 1 (int)
        $this->assertFalse(isOneVarOfArrayEqualToValue([1, 0], '1'));
    }

    public function testReturnsTrueForZeroIntInArray(): void
    {
        $this->assertTrue(isOneVarOfArrayEqualToValue([0, 2, 3], 0));
    }

    public function testReturnsTrueForEmptyStringInArray(): void
    {
        $this->assertTrue(isOneVarOfArrayEqualToValue(['a', '', 'b'], ''));
    }

    public function testStopsAtFirstMatch(): void
    {
        // Ensure it returns true even when match is the first element
        $this->assertTrue(isOneVarOfArrayEqualToValue([1, 0, 0], 1));
    }

    // =========================================================================
    // teampassNormalizeLegacyPassword
    // =========================================================================

    public function testDecodesLegacyEncodedPasswordWhenStoredLengthMatchesDecodedValue(): void
    {
        $this->assertSame('ab<c', teampassNormalizeLegacyPassword('ab&lt;c', 4));
    }

    public function testKeepsNewLiteralEntityPasswordWhenStoredLengthMatchesRawValue(): void
    {
        $this->assertSame('&lt;', teampassNormalizeLegacyPassword('&lt;', 4));
    }

    public function testKeepsPasswordUntouchedWhenDecodedLengthDoesNotMatchStoredLength(): void
    {
        $this->assertSame('ab&lt;c', teampassNormalizeLegacyPassword('ab&lt;c', 7));
    }

    public function testKeepsPasswordUntouchedWhenNoStoredLengthIsProvided(): void
    {
        $this->assertSame('ab&lt;c', teampassNormalizeLegacyPassword('ab&lt;c', null));
    }

    // =========================================================================
    // teampassDecodeJsonPayload
    // =========================================================================

    public function testKeepsRawJsonPayloadUntouchedWhenAlreadyValid(): void
    {
        $payload = '{"pw":"&lt;"}';

        $this->assertSame($payload, teampassDecodeJsonPayload($payload));
        $this->assertSame('&lt;', json_decode(teampassDecodeJsonPayload($payload), true)['pw']);
    }

    public function testDecodesOnceEscapedJsonPayloadWhilePreservingLiteralEntities(): void
    {
        $payload = '{&quot;pw&quot;:&quot;&amp;lt;&quot;}';

        $this->assertSame('{"pw":"&lt;"}', teampassDecodeJsonPayload($payload));
        $this->assertSame('&lt;', json_decode(teampassDecodeJsonPayload($payload), true)['pw']);
    }

    public function testDecodesTwiceEscapedJsonPayloadWhilePreservingLiteralEntities(): void
    {
        $payload = '{&amp;quot;pw&amp;quot;:&amp;quot;&amp;amp;lt;&amp;quot;}';

        $this->assertSame('{"pw":"&lt;"}', teampassDecodeJsonPayload($payload));
        $this->assertSame('&lt;', json_decode(teampassDecodeJsonPayload($payload), true)['pw']);
    }
}
