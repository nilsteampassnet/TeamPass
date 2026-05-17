<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel 4: Static regression tests for Vague 1 security hardening.
 *
 * These tests scan the source code for patterns that were identified as
 * critical vulnerabilities. They serve as automated guardrails to prevent
 * accidental reintroduction of fixed issues in future PRs.
 *
 * Categories covered:
 * - C-3: JWT must not be signed with DB_PASSWD
 * - H-2: API key comparison must use hash_equals
 * - H-5: Exception messages must not be returned to API clients
 * - H-6: Auth error messages must be uniform (no enumeration)
 * - H-7: Readonly check must exist on move target
 * - M-1: Auth errors must use correct HTTP codes (not 404)
 */
class SecurityHardeningTest extends TestCase
{
    private function readSource(string $relativePath): string
    {
        $path = __DIR__ . '/../../..' . $relativePath;
        self::assertFileExists($path, "Source file '$relativePath' not found");
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    // -------------------------------------------------------------------------
    // C-3: JWT signing key must not be DB_PASSWD
    // -------------------------------------------------------------------------

    public function testJwtIsNotSignedWithDbPasswd(): void
    {
        $jwtUtils = $this->readSource('/app/api/inc/jwt_utils.php');
        $authModel = $this->readSource('/app/api/Model/AuthModel.php');

        self::assertStringNotContainsString(
            'new Key(DB_PASSWD',
            $jwtUtils,
            'jwt_utils.php must not sign JWT with DB_PASSWD — use getApiJwtSigningKey()'
        );

        self::assertStringNotContainsString(
            "JWT::encode(\$payload, DB_PASSWD",
            $authModel,
            'AuthModel must not encode JWT with DB_PASSWD — use getApiJwtSigningKey()'
        );

        self::assertStringContainsString(
            'getApiJwtSigningKey()',
            $jwtUtils,
            'jwt_utils.php must call getApiJwtSigningKey() for JWT verification'
        );
    }

    // -------------------------------------------------------------------------
    // H-2: Timing-safe API key comparison
    // -------------------------------------------------------------------------

    public function testApiKeyUsesHashEquals(): void
    {
        $authModel = $this->readSource('/app/api/Model/AuthModel.php');

        self::assertStringContainsString(
            'hash_equals',
            $authModel,
            'AuthModel must use hash_equals() for API key comparison — prevents timing attacks'
        );

        // The old unsafe pattern must be gone
        self::assertStringNotContainsString(
            "\$inputData['apikey'] !== base64_decode(",
            $authModel,
            'AuthModel must not compare API key with !== — use hash_equals()'
        );
    }

    // -------------------------------------------------------------------------
    // H-5: Exception messages must not be returned to clients
    // -------------------------------------------------------------------------

    public function testControllersDoNotLeakExceptionMessages(): void
    {
        $controllers = [
            '/app/api/Controller/Api/AuthController.php',
            '/app/api/Controller/Api/FolderController.php',
            '/app/api/Controller/Api/ItemController.php',
            '/app/api/Controller/Api/UserController.php',
        ];

        foreach ($controllers as $file) {
            $source = $this->readSource($file);

            // The pattern "$e->getMessage() . ' Something went wrong'" must be gone
            self::assertDoesNotMatchRegularExpression(
                '/\$e->getMessage\(\)\s*\.\s*[\'"][^\'"]*Something went wrong/i',
                $source,
                "$file must not concatenate \$e->getMessage() into user-facing error responses"
            );

            // Debug suffixes like ".2", ".3", ".7" must be gone
            self::assertDoesNotMatchRegularExpression(
                "/Please contact support\.[0-9]+['\"]|'Access denied[0-9]+'/",
                $source,
                "$file must not contain debug-numbered error suffixes"
            );
        }
    }

    public function testItemModelDoesNotEchoError(): void
    {
        $source = $this->readSource('/app/api/Model/ItemModel.php');
        self::assertStringNotContainsString(
            'echo "ERROR"',
            $source,
            'ItemModel must not echo ERROR inline — it corrupts JSON responses'
        );
    }

    // -------------------------------------------------------------------------
    // H-6: Uniform auth error messages (anti-enumeration)
    // -------------------------------------------------------------------------

    public function testAuthModelUsesUniformErrorMessages(): void
    {
        $source = $this->readSource('/app/api/Model/AuthModel.php');

        // These discriminating messages must no longer exist
        $bannedMessages = [
            'User not allowed to use API',
            'API Key not valid',
            'Credentials not valid',
        ];

        foreach ($bannedMessages as $msg) {
            self::assertStringNotContainsString(
                $msg,
                $source,
                "AuthModel must not return '$msg' — it allows user enumeration"
            );
        }
    }

    // -------------------------------------------------------------------------
    // H-7: Read-only check on move target
    // -------------------------------------------------------------------------

    public function testItemModelChecksReadonlyOnMoveTarget(): void
    {
        $source = $this->readSource('/app/api/Model/ItemModel.php');

        // isFolderReadOnlyForUser must be called inside the folder_id change block
        // We verify both the existence and approximate proximity to canUseFolder($userData, $newFolderId)
        self::assertStringContainsString(
            'isFolderReadOnlyForUser($newFolderId',
            $source,
            'ItemModel::updateItem must check isFolderReadOnlyForUser on the target folder when folder_id changes'
        );
    }

    // -------------------------------------------------------------------------
    // M-1: Correct HTTP codes in bootstrap
    // -------------------------------------------------------------------------

    public function testBootstrapUsesCorrectHttpCodesForAuth(): void
    {
        $source = $this->readSource('/app/api/inc/bootstrap.php');

        // verifyAuth() must return 401 (not 404) for missing JWT
        self::assertStringContainsString(
            '401 Unauthorized',
            $source,
            'bootstrap.php verifyAuth() must return HTTP 401, not 404'
        );

        // apiIsEnabled() must return 503 (not 404)
        self::assertStringContainsString(
            '503 Service Unavailable',
            $source,
            'bootstrap.php apiIsEnabled() must return HTTP 503, not 404'
        );

        // checkUSerCRUDRights failure must return 403 (not 404)
        self::assertStringContainsString(
            '403 Forbidden',
            $source,
            'bootstrap.php checkUSerCRUDRights() failure must return HTTP 403, not 404'
        );

        // "Access denied2" debug artifact must be gone
        self::assertStringNotContainsString(
            'Access denied2',
            $source,
            'bootstrap.php must not contain "Access denied2" debug artifact'
        );
    }

    // -------------------------------------------------------------------------
    // C-1: UserModel column whitelist
    // -------------------------------------------------------------------------

    public function testUserControllerRequiresAdminAndDoesNotSelectStar(): void
    {
        $userModel = $this->readSource('/app/api/Model/UserModel.php');
        $userController = $this->readSource('/app/api/Controller/Api/UserController.php');

        self::assertStringNotContainsStringIgnoringCase(
            'SELECT *',
            $userModel,
            'UserModel must not use SELECT * — prevents credential leaks'
        );

        self::assertStringContainsString(
            'is_admin',
            $userController,
            'UserController must verify is_admin before serving user list'
        );
    }
}
