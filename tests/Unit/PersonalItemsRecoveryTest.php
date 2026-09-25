<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards for the recovery of personal items after a key generation (#5392).
 *
 * The recovery task was queued without an owner while the worker only read the owner's
 * sharekey: every sub-task completed and nothing was re-keyed. The recovery must read the
 * user's own sharekey with the previous private key and never overwrite a sharekey that key
 * cannot open. The target user is guarded by PersonalItemsRecoveryAuthorizationTest.
 */
class PersonalItemsRecoveryTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertIsString($content);

        return str_replace("\r\n", "\n", $content);
    }

    /**
     * Returns the body of a function or method, from its signature to its closing brace.
     */
    private function functionBody(string $source, string $signature): string
    {
        $start = strpos($source, $signature);
        self::assertNotFalse($start, $signature . ' must exist.');
        // A method closes at the trait indentation, a function at column 0
        $closing = str_starts_with($signature, 'private function') ? "\n    }\n" : "\n}\n";
        $end = strpos($source, $closing, $start);
        self::assertNotFalse($end);

        return substr($source, $start, $end - $start);
    }

    private function caseBlock(): string
    {
        $source = $this->source('app/sources/main.queries.php');
        $start = strpos($source, "case 'user_only_personal_items_encryption':");
        self::assertNotFalse($start);
        $end = strpos($source, 'default :', $start);
        self::assertNotFalse($end);

        return substr($source, $start, $end - $start);
    }

    public function testPasswordsAreNotSanitized(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/FILTER_SANITIZE[A-Z_]*[^;]*user(?:Previous|Current)Pwd|user(?:Previous|Current)Pwd[^;\n]*FILTER_SANITIZE/',
            $this->caseBlock(),
            'A password holding & < > " \' must reach decryptPrivateKey() unchanged.'
        );
    }

    public function testRecoveryTaskIsQueuedInOnePlace(): void
    {
        $source = $this->source('app/sources/main.queries.php');
        self::assertSame(1, substr_count($source, "'only_personal_items' => 1"));

        $body = $this->functionBody($source, 'function queuePersonalItemsRecoveryTask(');
        self::assertStringContainsString("'only_personal_items' => 1", $body);
        self::assertStringContainsString('triggerBackgroundHandler();', $body);

        foreach (['function changeUserLDAPAuthenticationPassword(', 'function setUserOnlyPersonalItemsEncryption('] as $caller) {
            self::assertStringContainsString('queuePersonalItemsRecoveryTask(', $this->functionBody($source, $caller));
        }
    }

    public function testPreviousKeyIsNeverTheCurrentKeyPair(): void
    {
        $body = $this->functionBody($this->source('app/sources/main.queries.php'), 'function findValidPreviousPrivateKey(');

        self::assertStringContainsString('COALESCE(is_current, 0) = 0', $body);
        self::assertStringContainsString('hash_equals($currentPrivateKey, $privateKey)', $body);
        self::assertStringNotContainsString('RAND()', $body, 'The key choice must not depend on a random pick.');
    }

    public function testEveryPersonalStepReKeysFromTheUsersOwnSharekey(): void
    {
        $source = $this->source('app/scripts/traits/UserHandlerTrait.php');
        $steps = [
            'private function generateNewUserStep20(' => 'sharekeys_items',
            'private function generateNewUserStep30(' => 'sharekeys_logs',
            'private function generateNewUserStep40(' => 'sharekeys_fields',
            'private function generateNewUserStep60(' => 'sharekeys_files',
        ];
        foreach ($steps as $signature => $table) {
            self::assertStringContainsString(
                "\$this->rekeyPersonalSharekeyFromPreviousKey('" . $table . "'",
                $this->functionBody($source, $signature)
            );
        }
    }

    public function testUnopenableSharekeyIsNeverOverwritten(): void
    {
        $body = $this->functionBody(
            $this->source('app/scripts/traits/UserHandlerTrait.php'),
            'private function rekeyPersonalSharekeyFromPreviousKey('
        );

        $guard = strpos($body, "if (empty(\$objectKey) === true) {\n            return;");
        $firstWrite = strpos($body, 'insertOrUpdateSharekey(');
        self::assertNotFalse($guard);
        self::assertNotFalse($firstWrite);
        self::assertLessThan($firstWrite, $guard, 'A sharekey on the current key pair would be replaced by an empty one.');
    }

    public function testPersonalSharekeysAreMatchedOnTheirOwnObjectIds(): void
    {
        // sharekeys_files.object_id is a files id, sharekeys_fields.object_id a categories_items
        // id and sharekeys_logs.object_id a log_items id: none of them is an items id.
        $source = $this->source('app/sources/main.queries.php');
        $skip = $this->functionBody($source, 'function setUserOnlyPersonalItemsEncryption(');
        self::assertStringContainsString("prefixTable('files') . ' AS f ON skf.object_id = f.id", $skip);
        self::assertStringContainsString("prefixTable('categories_items') . ' AS c ON skf.object_id = c.id", $skip);

        $delete = $this->functionBody($this->source('app/sources/main.functions.php'), 'function deleteUserObjetsKeys(');
        self::assertStringContainsString("SELECT c.id FROM ' . prefixTable('categories_items')", $delete);
        self::assertStringContainsString("SELECT l.increment_id FROM ' . prefixTable('log_items')", $delete);
    }
}
