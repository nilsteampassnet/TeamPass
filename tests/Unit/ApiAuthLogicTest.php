<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/api_auth_logic.php';

/**
 * API authentication refusals: one log label per cause, one answer for all of them.
 */
class ApiAuthLogicTest extends TestCase
{
    /**
     * Builds an account row allowed to authenticate, as getUserCompleteData() returns it.
     *
     * @param array<string, mixed> $overrides Columns to change.
     *
     * @return array<string, mixed>
     */
    private function account(array $overrides = []): array
    {
        return array_merge(
            [
                'disabled' => 0,
                'api_enabled' => 1,
                'auth_type' => 'local',
                'special' => 'none',
            ],
            $overrides
        );
    }

    /**
     * Reads a repository file.
     */
    private function source(string $path): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $path);
        self::assertIsString($content, $path . ' must be readable');

        return $content;
    }

    public function testAnAllowedAccountIsNotRefused(): void
    {
        self::assertSame('', apiAuthAccountRefusalReason($this->account(), false, false));
    }

    public function testEachAccountStateHasItsOwnLabel(): void
    {
        self::assertSame('api_user_unknown', apiAuthAccountRefusalReason(null, false, false));
        self::assertSame('api_user_disabled', apiAuthAccountRefusalReason($this->account(['disabled' => 1]), false, false));
        self::assertSame('api_access_not_enabled', apiAuthAccountRefusalReason($this->account(['api_enabled' => 0]), false, false));
    }

    public function testAMissingApiRowMeansAccessNotEnabled(): void
    {
        // LEFT JOIN on the api table: no row yields NULL, which the API refuses
        self::assertSame('api_access_not_enabled', apiAuthAccountRefusalReason($this->account(['api_enabled' => null]), false, false));
    }

    public function testDisabledAccountTakesPrecedenceOverApiAccess(): void
    {
        // Enabling API access on a disabled account would fix nothing
        self::assertSame(
            'api_user_disabled',
            apiAuthAccountRefusalReason($this->account(['disabled' => 1, 'api_enabled' => 0]), false, false)
        );
    }

    public function testTokenPathRestrictsAccountTypesUnlessAllowedForAll(): void
    {
        $ldap = $this->account(['auth_type' => 'ldap']);

        self::assertSame('api_token_auth_type_not_allowed', apiAuthAccountRefusalReason($ldap, true, false));
        self::assertSame('', apiAuthAccountRefusalReason($ldap, true, true));
        self::assertSame('', apiAuthAccountRefusalReason($this->account(['auth_type' => 'oauth2']), true, false));
        // The password path never checks the account type
        self::assertSame('', apiAuthAccountRefusalReason($ldap, false, false));
    }

    public function testAccountStateComesBeforeTheTokenAccountType(): void
    {
        self::assertSame(
            'api_access_not_enabled',
            apiAuthAccountRefusalReason($this->account(['auth_type' => 'ldap', 'api_enabled' => 0]), true, false)
        );
    }

    public function testPasswordMismatchNamesDirectoryAccounts(): void
    {
        self::assertSame('api_invalid_password', apiAuthPasswordMismatchReason('local'));
        self::assertSame('api_invalid_password', apiAuthPasswordMismatchReason(''));
        self::assertSame('api_invalid_password_ldap', apiAuthPasswordMismatchReason('ldap'));
        self::assertSame('api_invalid_password_oauth2', apiAuthPasswordMismatchReason('oauth2'));
    }

    public function testUnusablePrivateKeyNamesThePendingReEncryption(): void
    {
        self::assertSame('api_private_key_needs_recrypt', apiAuthPrivateKeyUnavailableReason('recrypt-private-key'));
        self::assertSame('api_private_key_unavailable', apiAuthPrivateKeyUnavailableReason('generate-keys'));
        self::assertSame('api_private_key_unavailable', apiAuthPrivateKeyUnavailableReason('none'));
    }

    public function testEveryLabelTheLogicReturnsIsAnApiFailureLabel(): void
    {
        $returned = [
            apiAuthAccountRefusalReason(null, false, false),
            apiAuthAccountRefusalReason($this->account(['disabled' => 1]), false, false),
            apiAuthAccountRefusalReason($this->account(['api_enabled' => 0]), false, false),
            apiAuthAccountRefusalReason($this->account(['auth_type' => 'ldap']), true, false),
            apiAuthPasswordMismatchReason('local'),
            apiAuthPasswordMismatchReason('ldap'),
            apiAuthPasswordMismatchReason('oauth2'),
            apiAuthPrivateKeyUnavailableReason('recrypt-private-key'),
            apiAuthPrivateKeyUnavailableReason('none'),
        ];

        foreach ($returned as $label) {
            self::assertContains($label, apiAuthFailureLabels());
        }
        self::assertSame(apiAuthFailureLabels(), array_values(array_unique(apiAuthFailureLabels())));
    }

    /**
     * The client answer must not depend on the cause, otherwise it becomes an enumeration oracle.
     */
    public function testAuthModelRefusesThroughASingleUniformExit(): void
    {
        $model = $this->source('app/api/Model/AuthModel.php');

        // Exactly two places may answer "Invalid credentials": the uniform exit and the token
        // format check, which runs before any lookup and logs nothing.
        self::assertSame(2, substr_count($model, '"info" => "Invalid credentials"'));
        self::assertStringContainsString('private function refuseApiAuth(', $model);

        // No refusal logs its own label any more: every failed_auth row goes through the exit,
        // except the lockout, which is not a credential verdict.
        preg_match_all("/logEvents\\(\\\$SETTINGS, 'failed_auth', '([a-z_]+)'/", $model, $literalLabels);
        self::assertSame(['bruteforce_account_locked', 'bruteforce_account_locked'], $literalLabels[1]);

        // Every label passed literally to the exit is known to the logs page
        preg_match_all("/refuseApiAuth\\('([a-z_]+)'/", $model, $exitLabels);
        self::assertNotEmpty($exitLabels[1]);
        foreach ($exitLabels[1] as $label) {
            self::assertContains($label, apiAuthFailureLabels());
        }
    }

    public function testEveryApiFailureLabelIsTranslated(): void
    {
        $english = include __DIR__ . '/../../app/includes/language/english.php';
        $french = include __DIR__ . '/../../app/includes/language/french.php';

        foreach (apiAuthFailureLabels() as $label) {
            self::assertArrayHasKey($label, $english, 'english: ' . $label);
            self::assertArrayHasKey($label, $french, 'french: ' . $label);
        }
    }
}
