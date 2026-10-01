<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/user_keys_task_logic.php';

/**
 * Behavioural tests of the resumable user key generation decisions.
 */
class UserKeysTaskLogicTest extends TestCase
{
    public function testSliceDeadlineKeepsAMarginBeforeTheTimeLimit(): void
    {
        self::assertSame(270, userKeysTaskSliceDeadline(300));
        self::assertSame(3240, userKeysTaskSliceDeadline(3600));
        // The margin never drops below 10 seconds
        self::assertSame(80, userKeysTaskSliceDeadline(90));
    }

    public function testSliceDeadlineIsComputedFromAtLeastOneMinute(): void
    {
        self::assertSame(50, userKeysTaskSliceDeadline(30));
        self::assertSame(50, userKeysTaskSliceDeadline(1));
    }

    public function testNoTimeLimitMeansASingleSlice(): void
    {
        // The documentation defines 0 as "any time is allowed"
        self::assertSame(PHP_INT_MAX, userKeysTaskSliceDeadline(0));
        self::assertSame(PHP_INT_MAX, userKeysTaskSliceDeadline(-5));
        self::assertFalse(userKeysTaskShouldYield(86400, 3600, userKeysTaskSliceDeadline(0), 500));
    }

    public function testFirstBatchOfASliceAlwaysRuns(): void
    {
        // Even past the deadline: a slice that processed nothing would never end the task
        self::assertFalse(userKeysTaskShouldYield(500, 400, 270, 0));
    }

    public function testSliceStopsWhenTheSlowestBatchNoLongerFits(): void
    {
        self::assertFalse(userKeysTaskShouldYield(200, 70, 270, 3));
        self::assertTrue(userKeysTaskShouldYield(201, 70, 270, 3));
        self::assertTrue(userKeysTaskShouldYield(271, 0, 270, 1));
    }

    public function testInterruptedBatchIsReplayedUntilItsBudgetIsSpent(): void
    {
        self::assertSame('requeue', userKeysTaskInterruptedBatchDecision(0, 3));
        self::assertSame('requeue', userKeysTaskInterruptedBatchDecision(2, 3));
        self::assertSame('fail', userKeysTaskInterruptedBatchDecision(3, 3));
        self::assertSame('fail', userKeysTaskInterruptedBatchDecision(7, 3));
    }

    public function testInterruptedBatchIsReplayedAtLeastOnce(): void
    {
        self::assertSame('requeue', userKeysTaskInterruptedBatchDecision(0, 0));
        self::assertSame('fail', userKeysTaskInterruptedBatchDecision(1, 0));
    }

    public function testFailedGenerationOfTheAccountIsResumable(): void
    {
        self::assertTrue(userKeysTaskIsResumable($this->task('failed', $this->arguments(42)), 42));
    }

    public function testOnlyAFailedGenerationIsResumed(): void
    {
        foreach (['new', 'queued', 'in_progress', 'completed', ''] as $status) {
            self::assertFalse(userKeysTaskIsResumable($this->task($status, $this->arguments(42)), 42), $status);
        }
    }

    public function testGenerationOfAnotherAccountIsNotResumable(): void
    {
        // A LIKE '%"new_user_id":5%' lookup used to match account 50 as well
        self::assertFalse(userKeysTaskIsResumable($this->task('failed', $this->arguments(50)), 5));
        self::assertFalse(userKeysTaskIsResumable($this->task('failed', $this->arguments(42)), 0));
    }

    public function testTaskWithoutUsableArgumentsIsNotResumable(): void
    {
        self::assertFalse(userKeysTaskIsResumable(null, 42));
        // completeTask() keeps only the account id
        self::assertFalse(userKeysTaskIsResumable($this->task('failed', '{"user_id":42}'), 42));
        self::assertFalse(userKeysTaskIsResumable($this->task('failed', 'not json'), 42));

        $withoutOwnerKey = json_decode($this->arguments(42), true);
        unset($withoutOwnerKey['creator_pwd']);
        self::assertFalse(userKeysTaskIsResumable($this->task('failed', (string) json_encode($withoutOwnerKey)), 42));
    }

    public function testOtherTaskTypesAreNotResumable(): void
    {
        $task = $this->task('failed', $this->arguments(42));
        $task['process_type'] = 'migrate_user_personal_items';

        self::assertFalse(userKeysTaskIsResumable($task, 42));
    }

    private function task(string $status, string $arguments): array
    {
        return [
            'process_type' => 'create_user_keys',
            'status' => $status,
            'arguments' => $arguments,
        ];
    }

    private function arguments(int $userId): string
    {
        return (string) json_encode([
            'new_user_id' => $userId,
            'new_user_pwd' => 'encrypted-password',
            'new_user_code' => 'encrypted-code',
            'owner_id' => 9999997,
            'creator_pwd' => 'encrypted-owner-password',
            'send_email' => 1,
        ]);
    }
}
