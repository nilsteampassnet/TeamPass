<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: a user key generation ("create_user_keys") is resumed, never restarted from scratch.
 *
 * A generation re-encrypts every shared object of the vault for one account, so on a large vault
 * it outlasted the task time limit: the handler killed it and failed it, and the account created
 * at its first LDAP login stayed locked; each new sign-in regenerated the key pair and started
 * again from zero, into the same limit. The decisions are tested by UserKeysTaskLogicTest; this
 * locks their wiring.
 */
class UserKeysTaskResumeWiringTest extends TestCase
{
    private function source(string $relativePath): string
    {
        $path = __DIR__ . '/../../' . $relativePath;
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    private function functionBody(string $src, string $signature): string
    {
        $start = strpos($src, $signature);
        self::assertIsInt($start, $signature . ' not found');
        $next = strpos($src, "\n    private function ", $start + strlen($signature));
        return substr($src, $start, $next === false ? null : $next - $start);
    }

    public function testWorkerReplaysAnInterruptedBatchBeforeSelectingTheNextOnes(): void
    {
        $body = $this->functionBody(
            $this->source('app/scripts/traits/UserHandlerTrait.php'),
            'private function generateUserKeys(array $arguments): void'
        );

        $recover = strpos($body, '$this->recoverInterruptedUserKeysSubtasks();');
        $select = strpos($body, 'is_in_progress = 0 ORDER BY `task` ASC');
        self::assertIsInt($recover, 'An interrupted batch must be replayed');
        self::assertIsInt($select);
        self::assertLessThan($select, $recover, 'The interrupted batch must be requeued before the batches are selected');
    }

    public function testWorkerYieldsBeforeTheTimeLimit(): void
    {
        $src = $this->source('app/scripts/traits/UserHandlerTrait.php');
        $body = $this->functionBody($src, 'private function generateUserKeys(array $arguments): void');

        self::assertStringContainsString('userKeysTaskShouldYield(', $body);
        self::assertStringContainsString('$this->yieldUserKeysTask(', $body);

        $yield = $this->functionBody($src, 'private function yieldUserKeysTask(int $batchesInSlice): void');
        self::assertStringContainsString("'is_in_progress' => 0", $yield, 'A yielded task must go back to the queue');
        self::assertStringContainsString('$this->deferCompletion = true;', $yield, 'A yielded task must not be completed');
        self::assertStringContainsString('tpWriteBackgroundTasksTrigger();', $yield, 'The running handler must relaunch it at once');
    }

    public function testHandlerRequeuesAResumableTaskInsteadOfFailingIt(): void
    {
        $src = $this->source('app/scripts/background_tasks___handler.php');

        self::assertMatchesRegularExpression(
            "/RESUMABLE_TASK_TYPES = \\['create_user_keys'\\]/",
            $src
        );

        $poll = $this->functionBody($src, 'private function pollCompletedProcesses(): void');
        self::assertStringContainsString('$this->isResumableTask(', $poll);
        self::assertStringContainsString('$this->requeueInterruptedTask(', $poll);

        $cleanup = $this->functionBody($src, 'private function cleanupStaleTasks(): void');
        $requeue = strpos($cleanup, 'self::RESUMABLE_TASK_TYPES');
        $fail = strpos($cleanup, 'status = "failed"');
        self::assertIsInt($requeue);
        self::assertIsInt($fail);
        self::assertLessThan($fail, $requeue, 'A stale resumable task must be requeued before stale tasks are failed');
    }

    public function testSignInResumesTheAccountGenerationInsteadOfRegeneratingTheKeys(): void
    {
        $src = $this->source('app/sources/identify.php');

        self::assertStringContainsString('userKeysTaskIsResumable($keysTask, (int) $userInfo[\'id\'])', $src);
        self::assertStringContainsString('requeueUserKeysTask((int) $keysTask[\'increment_id\']);', $src);

        // The account's own task, never a LIKE on the arguments: '"new_user_id":5' matches user 50
        self::assertStringNotContainsString('checkIfUserKeyCreationFailed', $src);
        self::assertStringNotContainsString('\'%"new_user_id":\'', $src);

        // ongoing_process_id may point at a personal items migration: only a failed key
        // generation may be regenerated
        self::assertStringContainsString("(string) \$keysTask['process_type'] === 'create_user_keys'", $src);
    }
}
