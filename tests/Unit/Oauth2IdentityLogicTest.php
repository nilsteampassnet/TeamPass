<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/oauth2_identity_logic.php';

/**
 * Binding of TeamPass accounts to the identity an OAuth2 provider asserts (issuer key + subject),
 * instead of to the name claim the login is derived from.
 */
final class Oauth2IdentityLogicTest extends TestCase
{
    private const ENTRA_OBJECT_ID = '0f8e1c2a-5b6d-4e7f-8a9b-0c1d2e3f4a5b';

    public function testHashIsTheSha256OfIssuerNulSubject(): void
    {
        self::assertSame(
            hash('sha256', "entra\0" . self::ENTRA_OBJECT_ID),
            oauth2IdentityHash('entra', self::ENTRA_OBJECT_ID)
        );
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', oauth2IdentityHash('entra', self::ENTRA_OBJECT_ID));
    }

    public function testSeparatorKeepsIssuerAndSubjectApart(): void
    {
        self::assertNotSame(oauth2IdentityHash('https://a', 'bc'), oauth2IdentityHash('https://ab', 'c'));
    }

    public function testSameSubjectAtTwoIssuersIsTwoIdentities(): void
    {
        self::assertNotSame(
            oauth2IdentityHash('https://idp-a.example', '42'),
            oauth2IdentityHash('https://idp-b.example', '42')
        );
    }

    public function testEmptyPartGivesNoIdentity(): void
    {
        self::assertSame('', oauth2IdentityHash('', self::ENTRA_OBJECT_ID));
        self::assertSame('', oauth2IdentityHash('entra', ''));
    }

    public function testSessionIdentityComesFromTheControllerKeys(): void
    {
        $info = [
            'id' => self::ENTRA_OBJECT_ID,
            'userPrincipalName' => 'jdoe@a.example',
            'identityIssuer' => 'entra',
            'identitySubject' => self::ENTRA_OBJECT_ID,
        ];

        self::assertSame(oauth2IdentityHash('entra', self::ENTRA_OBJECT_ID), oauth2IdentityFromSessionInfo($info));
    }

    /**
     * @dataProvider unusableSessionInfoProvider
     */
    public function testUnusableSessionInfoGivesNoIdentity(mixed $info): void
    {
        self::assertSame('', oauth2IdentityFromSessionInfo($info));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unusableSessionInfoProvider(): iterable
    {
        yield 'cleared session value' => [''];
        yield 'no session value' => [null];
        // A provider answer stored before the binding existed: the name alone must not suffice
        yield 'answer without identity keys' => [['id' => self::ENTRA_OBJECT_ID, 'userPrincipalName' => 'jdoe@a.example']];
        yield 'empty subject' => [['identityIssuer' => 'entra', 'identitySubject' => '']];
        yield 'non-string subject' => [['identityIssuer' => 'entra', 'identitySubject' => 42]];
    }

    /**
     * @dataProvider decisionProvider
     *
     * @param array<string, mixed> $account
     */
    public function testDecision(array $account, string $sessionIdentity, bool $link, string $expected): void
    {
        self::assertSame($expected, oauth2IdentityDecision($account, $sessionIdentity, $link));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, bool, string}>
     */
    public static function decisionProvider(): iterable
    {
        $mine = oauth2IdentityHash('entra', self::ENTRA_OBJECT_ID);
        $other = oauth2IdentityHash('entra', '11111111-2222-3333-4444-555555555555');

        yield 'oauth2 account bound to this identity' => [['auth_type' => 'oauth2', 'oauth2_identity' => $mine], $mine, false, 'accept'];
        yield 'oauth2 account bound to another identity' => [['auth_type' => 'oauth2', 'oauth2_identity' => $other], $mine, true, 'refuse_mismatch'];
        yield 'oauth2 account not bound yet (NULL)' => [['auth_type' => 'oauth2', 'oauth2_identity' => null], $mine, false, 'bind'];
        yield 'oauth2 account not bound yet (empty)' => [['auth_type' => 'oauth2', 'oauth2_identity' => ''], $mine, false, 'bind'];
        yield 'oauth2 account, column absent from the row' => [['auth_type' => 'oauth2'], $mine, false, 'bind'];
        yield 'local account, linking on' => [['auth_type' => 'local', 'oauth2_identity' => null], $mine, true, 'bind'];
        yield 'local account, linking off' => [['auth_type' => 'local', 'oauth2_identity' => null], $mine, false, 'refuse_link_disabled'];
        yield 'ldap account, linking on' => [['auth_type' => 'ldap', 'oauth2_identity' => null], $mine, true, 'bind'];
        yield 'ldap account, linking off' => [['auth_type' => 'ldap', 'oauth2_identity' => null], $mine, false, 'refuse_link_disabled'];
        yield 'local account still bound to another identity' => [['auth_type' => 'local', 'oauth2_identity' => $other], $mine, true, 'refuse_mismatch'];
        yield 'no identity in the session' => [['auth_type' => 'oauth2', 'oauth2_identity' => null], '', true, 'refuse_no_identity'];
        yield 'no identity in the session, bound account' => [['auth_type' => 'oauth2', 'oauth2_identity' => $mine], '', true, 'refuse_no_identity'];
    }

    public function testEveryRefusalHasAKeyAndNothingElseDoes(): void
    {
        self::assertSame('oauth2_identity_mismatch', oauth2IdentityRefusalKey('refuse_mismatch'));
        self::assertSame('oauth2_link_disabled', oauth2IdentityRefusalKey('refuse_link_disabled'));
        self::assertSame('oauth2_identity_missing', oauth2IdentityRefusalKey('refuse_no_identity'));
        self::assertSame('', oauth2IdentityRefusalKey('accept'));
        self::assertSame('', oauth2IdentityRefusalKey('bind'));
    }
}
