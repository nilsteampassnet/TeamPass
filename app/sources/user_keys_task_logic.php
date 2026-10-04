<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      user_keys_task_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

/**
 * Decisions of the resumable user key generation ("create_user_keys" task).
 *
 * A key generation re-encrypts every shared object of the vault for one account, so its
 * duration grows with the vault. It runs in time slices bounded by the task time limit: a slice
 * stops before the limit and hands the task back to the handler, and a batch interrupted by a
 * killed slice is replayed a bounded number of times. DB-free, unit-tested by
 * tests/Unit/UserKeysTaskLogicTest.php.
 */

/** Smallest time limit a slice is computed from, in seconds. */
const USER_KEYS_TASK_MIN_RUN_TIME = 60;

/**
 * Latest elapsed time, in seconds, at which a slice may still start a batch.
 *
 * The margin keeps room for the process start-up and for the handler, which polls the worker
 * and kills it at the limit.
 *
 * @param int $taskMaximumRunTime Setting task_maximum_run_time, in seconds; 0 means no limit
 *
 * @return int PHP_INT_MAX when there is no limit: the generation then runs in a single slice
 */
function userKeysTaskSliceDeadline(int $taskMaximumRunTime): int
{
    if ($taskMaximumRunTime <= 0) {
        return PHP_INT_MAX;
    }

    $limit = max(USER_KEYS_TASK_MIN_RUN_TIME, $taskMaximumRunTime);

    return $limit - max(10, intdiv($limit, 10));
}

/**
 * Must the slice stop before starting its next batch?
 *
 * The first batch of a slice always runs, so every slice makes progress. The next one is only
 * started when the slowest batch seen so far still fits before the deadline.
 *
 * @param int $elapsed        Seconds since the slice started
 * @param int $slowestBatch   Duration of the slowest batch of this slice, in seconds
 * @param int $deadline       userKeysTaskSliceDeadline()
 * @param int $batchesInSlice Batches already processed by this slice
 *
 * @return bool
 */
function userKeysTaskShouldYield(int $elapsed, int $slowestBatch, int $deadline, int $batchesInSlice): bool
{
    return $batchesInSlice > 0 && $elapsed + $slowestBatch > $deadline;
}

/**
 * What to do with a batch left "in progress" by a slice that was killed.
 *
 * Replaying is safe, every batch upserts its sharekeys; the bound stops a batch that can never
 * fit in the time limit from being replayed for ever.
 *
 * @param int $retryCount Replays already done for this batch
 * @param int $maxRetries Replays allowed (at least one is always granted)
 *
 * @return string 'requeue' or 'fail'
 */
function userKeysTaskInterruptedBatchDecision(int $retryCount, int $maxRetries): string
{
    return $retryCount < max(1, $maxRetries) ? 'requeue' : 'fail';
}

/**
 * Can a failed key generation be resumed for this account, instead of being started again
 * from a new key pair?
 *
 * A completed task has its arguments anonymized and cannot be resumed; neither can a task of
 * another account.
 *
 * @param array|null $task   Row of background_tasks (process_type, status, arguments)
 * @param int        $userId Account whose keys are being generated
 *
 * @return bool
 */
function userKeysTaskIsResumable(?array $task, int $userId): bool
{
    if ($task === null || (string) ($task['process_type'] ?? '') !== 'create_user_keys') {
        return false;
    }
    if ((string) ($task['status'] ?? '') !== 'failed') {
        return false;
    }

    $arguments = json_decode((string) ($task['arguments'] ?? ''), true);
    if (is_array($arguments) === false || $userId <= 0) {
        return false;
    }

    // The worker opens the owner's keys with these two values
    return (int) ($arguments['new_user_id'] ?? 0) === $userId
        && empty($arguments['owner_id']) === false
        && empty($arguments['creator_pwd']) === false;
}
