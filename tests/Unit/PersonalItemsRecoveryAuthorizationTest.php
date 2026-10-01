<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guard for the authorization of user_only_personal_items_encryption (GHSA-6rq2-hf9c-cxh2).
 *
 * keyHandler() only authorizes a target sent as "user_id", while this action read its
 * target from a browser-supplied "userId" that no guard inspected: any user could reset
 * (skip branch) or re-key the personal items of another account. The action must only
 * ever act on the user resolved by keyHandler().
 */
class PersonalItemsRecoveryAuthorizationTest extends TestCase
{
    /**
     * Returns the user_only_personal_items_encryption case, from its label to the default case.
     */
    private function caseBlock(): string
    {
        $content = file_get_contents(__DIR__ . '/../../app/sources/main.queries.php');
        self::assertIsString($content);

        $start = strpos($content, "case 'user_only_personal_items_encryption':");
        self::assertNotFalse($start, 'The user_only_personal_items_encryption case must exist.');
        $end = strpos($content, 'default :', $start);
        self::assertNotFalse($end, 'The case following user_only_personal_items_encryption must exist.');

        return substr($content, $start, $end - $start);
    }

    public function testHandlerOnlyActsOnTheResolvedUser(): void
    {
        $block = $this->caseBlock();

        self::assertStringNotContainsString(
            "\$dataReceived['userId']",
            $block,
            'The target must never come from the payload: keyHandler() does not check "userId".'
        );
        self::assertStringContainsString('$filtered_user_id', $block);
    }
}
