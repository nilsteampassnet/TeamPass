<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: the user-logs datatable must stay scoped to the caller's administration
 * perimeter — static regression guards for GHSA-qhff-v9qj-75wc.
 *
 * The advisory covered an IDOR: the endpoint was gated on the 'items' page, which every
 * authenticated user holds, and 'userId' - supplied by the client - was the only predicate
 * scoping the log queries. Iterating it disclosed any account's audit trail, administrators
 * included.
 *
 * Locked invariants:
 * - the page gate names the page that actually offers the datatable ('users'), never 'items';
 * - the requested target is authorization-checked before any log query runs;
 * - the target id reaches SQL through a placeholder bound to the validated integer.
 */
class UserLogsAccessAuthorizationTest extends TestCase
{
    private function datatableSource(): string
    {
        $path = __DIR__ . '/../../app/sources/users.logs.datatable.php';
        self::assertFileExists($path, 'users.logs.datatable.php not found');
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    /**
     * 'items' is held by every authenticated user, so it could never gate an audit trail.
     */
    public function testPageGateIsTheUsersAdministrationPage(): void
    {
        $src = $this->datatableSource();

        self::assertStringContainsString(
            "\$checkUserAccess->userAccessPage('users') === false",
            $src,
            'The datatable must be gated on the Users administration page'
        );

        self::assertStringNotContainsString(
            "\$checkUserAccess->userAccessPage('items')",
            $src,
            "Gating on 'items' exposes the audit trail to every authenticated user"
        );
    }

    /**
     * The page gate alone still lets a manager reach out-of-scope accounts: the target itself
     * must be checked.
     */
    public function testRequestedTargetIsAuthorizationChecked(): void
    {
        $src = $this->datatableSource();

        self::assertStringContainsString(
            'callerMayManageUser($targetUserId) === false',
            $src,
            'The requested account must be checked against the caller administration scope'
        );

        self::assertStringContainsString(
            "\$targetUserId !== (int) \$session->get('user-id')",
            $src,
            'Reading one\'s own logs must stay allowed without an administration right'
        );

        // The check must precede the queries, otherwise it guards nothing.
        $checkPosition = strpos($src, 'callerMayManageUser($targetUserId)');
        $queryPosition = strpos($src, 'prefixTable(\'log_items\')');
        self::assertIsInt($checkPosition);
        self::assertIsInt($queryPosition);
        self::assertLessThan(
            $queryPosition,
            $checkPosition,
            'The authorization check must run before any log query'
        );
    }

    /**
     * The scoping predicate must be the validated integer, never the raw request value.
     */
    public function testLogQueriesBindTheValidatedTarget(): void
    {
        $src = $this->datatableSource();

        self::assertStringContainsString('WHERE u.id = %i', $src, 'Item logs must bind the target id');
        self::assertStringContainsString('WHERE s.qui = %i', $src, 'System logs must bind the target id');

        self::assertStringNotContainsString(
            "WHERE u.id = '.\$inputData['userId']",
            $src,
            'The raw request value must not be concatenated into the query'
        );
        self::assertStringNotContainsString(
            "WHERE s.qui = '.\$inputData['userId']",
            $src,
            'The raw request value must not be concatenated into the query'
        );
    }
}
