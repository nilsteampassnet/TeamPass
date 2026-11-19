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
 * @file      backgroundTaskLock.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

require_once __DIR__ . '/../sources/runtime_files.functions.php';

/**
 * Own the handler's advisory lock on a trusted local runtime file.
 *
 * File presence and an old PID do not indicate activity: only flock does.
 * Validate the inode after locking and unlink it before unlocking on release.
 * A contender holding an orphaned descriptor must never start background work.
 */
final class BackgroundTaskLock
{
    /** @var resource|null */
    private $handle = null;
    private ?string $failureReason = null;

    /** @param string $path Trusted configured local path to the handler lock. */
    public function __construct(private string $path)
    {
    }

    /** Acquire exclusively without waiting or changing another owner's PID. */
    public function acquire(): bool
    {
        if (is_resource($this->handle)) {
            return true;
        }
        $this->failureReason = null;

        $handle = tpOpenRuntimeFile($this->path);
        if ($handle === false) {
            $this->failureReason = 'open_failed';
            error_log(
                'Teampass Background Tasks: cannot open a valid lock file "' . $this->path
                . '" - check that the web server user can write to this directory.'
            );
            return false;
        }
        return $this->acquireHandle($handle);
    }

    /**
     * Take ownership of an opened descriptor, closing it on any failed attempt.
     *
     * @param resource $handle The descriptor opened for this acquisition.
     */
    private function acquireHandle($handle): bool
    {
        $wouldBlock = 0;
        if (@flock($handle, LOCK_EX | LOCK_NB, $wouldBlock) === false) {
            $this->failureReason = $wouldBlock === 1 ? 'busy' : 'lock_failed';
            fclose($handle);
            return false;
        }

        // A finishing handler may have unlinked this inode after our open.
        $stat = @fstat($handle);
        if ($stat === false || tpRuntimeFileMatchesPath($this->path, $stat) === false) {
            $this->failureReason = 'path_changed';
            fclose($handle);
            return false;
        }

        $pid = (string) getmypid();
        if (@ftruncate($handle, 0) === false || @fwrite($handle, $pid) !== strlen($pid) || @fflush($handle) === false) {
            $this->failureReason = 'write_failed';
            fclose($handle);
            error_log('Teampass Background Tasks: cannot write lock file "' . $this->path . '".');
            return false;
        }
        $this->handle = $handle;
        return true;
    }

    /** Return the last acquisition diagnostic without treating file presence as activity. */
    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    /** Remove only our own inode while locked, then release the descriptor. */
    public function release(): void
    {
        if (is_resource($this->handle)) {
            $stat = @fstat($this->handle);
            if ($stat !== false && tpRuntimeFileMatchesPath($this->path, $stat)) {
                unlink($this->path);
            }
            // Keep explicit unlocking even if a child inherited the descriptor.
            // Closing is also the fallback if the explicit unlock fails.
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
        $this->handle = null;
    }

    /** Also release when the owner goes out of scope; abrupt exit is handled by the OS. */
    public function __destruct()
    {
        $this->release();
    }
}
