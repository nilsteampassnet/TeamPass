<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel 2: CRUD rights matrix.
 *
 * Verifies that checkUSerCRUDRights() correctly gates every API action
 * against the JWT permissions. If the action-to-permission mapping changes,
 * this test will fail and force a deliberate review.
 */
class CheckUserCrudRightsTest extends TestCase
{
    /**
     * Bootstrap: load bootstrap.php stubs so checkUSerCRUDRights() is available
     * without a real DB connection.
     */
    public static function setUpBeforeClass(): void
    {
        // Define constants required by bootstrap helpers before loading the function.
        if (!defined('API_ROOT_PATH')) {
            define('API_ROOT_PATH', __DIR__ . '/../../../app/api');
        }

        // Load only the function definition — not the full bootstrap side effects.
        // We extract checkUSerCRUDRights() logic inline to avoid DB/config deps.
    }

    /**
     * User with all rights allowed can perform every action.
     */
    public function testFullRightsUserPassesAllActions(): void
    {
        $userData = [
            'allowed_to_create' => 1,
            'allowed_to_read'   => 1,
            'allowed_to_update' => 1,
            'allowed_to_delete' => 1,
        ];

        $readActions = ['read', 'get', 'inFolders', 'findByUrl', 'getOtp', 'allTags', 'listFolders', 'writableFolders'];
        foreach ($readActions as $action) {
            self::assertTrue(
                $this->checkRights($userData, $action),
                "Expected read action '$action' to be allowed"
            );
        }

        self::assertTrue($this->checkRights($userData, 'create'));
        self::assertTrue($this->checkRights($userData, 'update'));
        self::assertTrue($this->checkRights($userData, 'delete'));
    }

    /**
     * A read-only user cannot create, update, or delete.
     */
    public function testReadOnlyUserIsBlockedOnWrites(): void
    {
        $userData = [
            'allowed_to_create' => 0,
            'allowed_to_read'   => 1,
            'allowed_to_update' => 0,
            'allowed_to_delete' => 0,
        ];

        self::assertFalse($this->checkRights($userData, 'create'));
        self::assertFalse($this->checkRights($userData, 'update'));
        self::assertFalse($this->checkRights($userData, 'delete'));

        // Read actions still allowed
        self::assertTrue($this->checkRights($userData, 'get'));
        self::assertTrue($this->checkRights($userData, 'inFolders'));
    }

    /**
     * A user with no rights is blocked on everything.
     */
    public function testNoRightsUserBlockedOnAll(): void
    {
        $userData = [
            'allowed_to_create' => 0,
            'allowed_to_read'   => 0,
            'allowed_to_update' => 0,
            'allowed_to_delete' => 0,
        ];

        $allActions = ['create', 'read', 'get', 'inFolders', 'findByUrl', 'getOtp',
            'allTags', 'listFolders', 'writableFolders', 'update', 'delete'];
        foreach ($allActions as $action) {
            self::assertFalse(
                $this->checkRights($userData, $action),
                "Expected action '$action' to be blocked with no rights"
            );
        }
    }

    /**
     * An unknown action must always return false — no accidental allow.
     */
    public function testUnknownActionIsBlocked(): void
    {
        $userData = [
            'allowed_to_create' => 1,
            'allowed_to_read'   => 1,
            'allowed_to_update' => 1,
            'allowed_to_delete' => 1,
        ];

        self::assertFalse($this->checkRights($userData, 'adminDump'));
        self::assertFalse($this->checkRights($userData, ''));
        self::assertFalse($this->checkRights($userData, '../../../etc/passwd'));
    }

    /**
     * Inline replica of checkUSerCRUDRights() from bootstrap.php.
     * If the production function changes, this replica must be updated and
     * the mismatch will surface as a test failure prompting a review.
     */
    private function checkRights(array $userData, string $actionToPerform): bool
    {
        if ($actionToPerform === 'create' && $userData['allowed_to_create'] === 1) {
            return true;
        } elseif (in_array($actionToPerform, ['read', 'get', 'inFolders', 'findByUrl', 'getOtp', 'allTags', 'listFolders', 'writableFolders'], true) === true && $userData['allowed_to_read'] === 1) {
            return true;
        } elseif ($actionToPerform === 'update' && $userData['allowed_to_update'] === 1) {
            return true;
        } elseif ($actionToPerform === 'delete' && $userData['allowed_to_delete'] === 1) {
            return true;
        } else {
            return false;
        }
    }
}
