<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      LogQueryParameterTypesTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

/**
 * Sentinels for issue #5466.
 *
 * log_system.qui and log_system.date are VARCHAR columns: comparing them with an integer
 * placeholder makes MySQL cast every row, which disables idx_log_api_user_connection and turns
 * the per-authorize duplicate check into a full table scan.
 *
 * MeekroDB's %ss already wraps its argument in '%...%' and escapes the '%' it contains, so a
 * '%word%' argument searches for literal percent signs and never matches.
 */
class LogQueryParameterTypesTest extends TestCase
{
    /** Directories holding PHP code that issues MeekroDB queries. */
    private const SCANNED_DIRECTORIES = [
        'app/api',
        'app/core',
        'app/pages',
        'app/scripts',
        'app/sources',
        'public/install',
    ];

    public function testApiConnectionDuplicateCheckComparesVarcharColumnsAsStrings(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/api/Model/AuthModel.php');
        $this->assertNotFalse($source);

        $this->assertStringContainsString(
            'WHERE type = %s AND label = %s AND qui = %s AND field_1 LIKE %ss AND date > %s',
            (string) $source
        );
        $this->assertStringContainsString("(string) \$userInfo['id'],", (string) $source);
        $this->assertStringContainsString('(string) (time() - 2)', (string) $source);
        $this->assertStringNotContainsString("'%tp_src=api%'", (string) $source);
    }

    public function testLogSystemQuiIsNeverComparedWithAnIntegerPlaceholder(): void
    {
        $offenders = [];
        foreach ($this->databaseCalls() as $call) {
            if (!in_array('log_system', $call['strings'], true)) {
                continue;
            }
            if (preg_match('/\bqui\s*(=|IN)\s*%(i|li)\b/i', $call['literals']) === 1) {
                $offenders[] = $call['location'];
            }
        }

        $this->assertSame([], $offenders, 'log_system.qui is a VARCHAR: use %s / %ls with string values');
    }

    public function testSearchStringPlaceholderIsNeverFedItsOwnWildcards(): void
    {
        $offenders = [];
        foreach ($this->databaseCalls() as $call) {
            if (strpos($call['literals'], '%ss') === false) {
                continue;
            }
            foreach ($call['strings'] as $value) {
                if (preg_match('/^%[^%]+%$/', $value) === 1) {
                    $offenders[] = $call['location'] . ' ' . $value;
                }
            }
        }

        $this->assertSame([], $offenders, '%ss adds the wildcards itself: pass the bare search term');
    }

    /**
     * Collects every DB::<method>(...) call of the scanned directories.
     *
     * @return list<array{location: string, literals: string, strings: list<string>}>
     */
    private function databaseCalls(): array
    {
        $root = dirname(__DIR__, 2);
        $calls = [];

        foreach (self::SCANNED_DIRECTORIES as $directory) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $code = (string) file_get_contents($file->getPathname());
                if (strpos($code, 'DB::') === false) {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($root) + 1);
                foreach ($this->databaseCallsInCode($code) as $call) {
                    $calls[] = [
                        'location' => $relative . ':' . $call['line'],
                        'literals' => implode("\n", $call['strings']),
                        'strings' => $call['strings'],
                    ];
                }
            }
        }

        $this->assertNotEmpty($calls, 'No DB:: call found: the scanned directories moved');

        return $calls;
    }

    /**
     * Returns the string literals of each DB::<method>(...) call, with the call line.
     *
     * @return list<array{line: int, strings: list<string>}>
     */
    private function databaseCallsInCode(string $code): array
    {
        $tokens = token_get_all($code);
        $count = count($tokens);
        $calls = [];

        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_STRING || $tokens[$i][1] !== 'DB'
                || !isset($tokens[$i + 1]) || !is_array($tokens[$i + 1]) || $tokens[$i + 1][0] !== T_DOUBLE_COLON
            ) {
                continue;
            }

            // Move to the opening parenthesis of the call.
            $j = $i + 2;
            while ($j < $count && $tokens[$j] !== '(') {
                $j++;
            }

            $depth = 0;
            $strings = [];
            for (; $j < $count; $j++) {
                $token = $tokens[$j];
                if ($token === '(') {
                    $depth++;
                } elseif ($token === ')') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                } elseif (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                    $strings[] = substr($token[1], 1, -1);
                }
            }

            $calls[] = ['line' => (int) $tokens[$i][2], 'strings' => $strings];
            $i = $j;
        }

        return $calls;
    }
}
