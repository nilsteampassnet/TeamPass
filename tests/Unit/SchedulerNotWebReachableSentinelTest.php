<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      SchedulerNotWebReachableSentinelTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

/**
 * The background scheduler must never be reachable over HTTP.
 *
 * "app/sources/scheduler.php" is a cron entry point. It carries no session, no
 * authentication and no CSRF check, because none of that exists on a command line:
 * it loads the configuration, registers the background and maintenance jobs, and
 * dispatches whatever is due. "items_handler" runs at "*\/1 * * * *", so it is due
 * on every single invocation.
 *
 * The split into public/ (web root) and app/ (private) gave every "app/sources/*.php"
 * file a proxy wrapper under "public/sources/", the scheduler included. Nothing in
 * TeamPass ever called that wrapper -- the docs, the Docker entrypoint, the Windows
 * scheduled-task builder and every language file invoke the CLI path -- but it sat in
 * the web root, and both shipped web-server configurations serve an existing .php file
 * without routing it through the front controller. An unauthenticated GET on
 * /sources/scheduler.php therefore dispatched the privileged jobs (GHSA-fpv9-jxph-qg96).
 *
 * The mistake is easy to make a second time, because it is made by a sweep rather than
 * by a decision: adding a wrapper for every file in a directory is exactly the sort of
 * bulk edit that looks complete when it is wrong. Hence a sentinel on both halves of
 * the fix -- the absent wrapper, and the guard that makes the wrapper harmless anyway.
 */
class SchedulerNotWebReachableSentinelTest extends TestCase
{
    /**
     * Files under app/sources/ that are command-line entry points, not HTTP handlers.
     *
     * A file listed here must not be given a proxy shim in the web root.
     *
     * @var list<string>
     */
    private const CLI_ONLY_SOURCES = [
        'scheduler.php',
    ];

    /**
     * Repository root, with a trailing separator.
     *
     * @return string Absolute path
     */
    private function repositoryRoot(): string
    {
        return dirname(__DIR__, 2) . '/';
    }

    /**
     * A CLI-only source must have no counterpart in the web root.
     *
     * @return void
     */
    public function testCliOnlySourcesHaveNoWebRootShim(): void
    {
        foreach (self::CLI_ONLY_SOURCES as $name) {
            $shim = $this->repositoryRoot() . 'public/sources/' . $name;

            self::assertFileDoesNotExist(
                $shim,
                'public/sources/' . $name . ' makes a command-line entry point reachable over HTTP. '
                . 'Delete the shim: app/sources/' . $name . ' is invoked by cron, never by a request.'
            );
        }
    }

    /**
     * The scheduler refuses to run under any SAPI but the command line.
     *
     * The guard has to come before the first require: everything the scheduler loads
     * has side effects, starting with the database connection.
     *
     * @return void
     */
    public function testSchedulerRefusesNonCliInvocation(): void
    {
        $path = $this->repositoryRoot() . 'app/sources/scheduler.php';
        $source = (string) file_get_contents($path);

        $guardPosition = strpos($source, "PHP_SAPI !== 'cli'");
        self::assertNotFalse(
            $guardPosition,
            'app/sources/scheduler.php lost its CLI-only guard. Without it, any deployment '
            . 'that exposes the file -- a flat 3.1.x layout, a vhost rooted on the repository, '
            . 'a reinstated shim -- dispatches the background jobs to anonymous requests.'
        );

        $firstRequire = strpos($source, 'require_once');
        self::assertNotFalse($firstRequire, 'app/sources/scheduler.php loads nothing any more.');

        self::assertLessThan(
            $firstRequire,
            $guardPosition,
            'The CLI-only guard in app/sources/scheduler.php must come before the first require, '
            . 'otherwise the configuration and the database connection are loaded before the '
            . 'request is refused.'
        );
    }
}
