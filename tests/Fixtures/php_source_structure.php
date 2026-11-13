<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

namespace TeamPass\Tests\SourceStructure;

use PhpToken;
use RuntimeException;

/** Extract a trusted production block by balanced PHP tokens, ignoring braces in comments/strings. */
function blockSource(string $source, array $tokens, int $start): string
{
    $depth = 0;
    $opened = false;
    for ($index = $start; $index < count($tokens); ++$index) {
        $token = $tokens[$index];
        if ($token->id === ord('{') || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
            ++$depth;
            $opened = true;
        } elseif ($token->id === ord('}') && $opened && --$depth === 0) {
            return substr($source, $tokens[$start]->pos, $token->pos + 1 - $tokens[$start]->pos);
        }
    }
    throw new RuntimeException('Production PHP block is incomplete.');
}

/** Extract a named production function without its HTTP/CLI bootstrap. */
function functionSource(string $source, string $name): string
{
    $tokens = PhpToken::tokenize($source);
    foreach ($tokens as $index => $token) {
        if (!$token->is(T_FUNCTION)) { continue; }
        for ($next = $index + 1; $next < count($tokens); ++$next) {
            if ($tokens[$next]->isIgnorable() || $tokens[$next]->text === '&') { continue; }
            if ($tokens[$next]->is(T_STRING) && $tokens[$next]->text === $name) {
                return blockSource($source, $tokens, $index);
            }
            break;
        }
    }
    throw new RuntimeException('Production function is missing: ' . $name);
}

/** Locate a semantic PHP variable without depending on nearby comments or whitespace. */
function variableOffset(string $source, string $name): int
{
    foreach (PhpToken::tokenize($source) as $token) {
        if ($token->is(T_VARIABLE) && $token->text === $name) { return $token->pos; }
    }
    throw new RuntimeException('Production variable is missing: ' . $name);
}

/** Extract a final catch block without depending on its exception variable name. */
function lastCatchSource(string $source): string
{
    $tokens = PhpToken::tokenize($source);
    for ($index = count($tokens) - 1; $index >= 0; --$index) {
        if ($tokens[$index]->is(T_CATCH)) { return blockSource($source, $tokens, $index); }
    }
    throw new RuntimeException('Production catch block is missing.');
}

/** Extract a variable assignment through its balanced statement terminator. */
function assignmentSource(string $source, string $name): string
{
    $start = variableOffset($source, $name);
    $depth = 0;
    foreach (PhpToken::tokenize($source) as $token) {
        if ($token->pos < $start) { continue; }
        if ($token->is(['(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) { ++$depth; }
        if ($token->is([')', ']', '}'])) { --$depth; }
        if ($token->is(';') && $depth === 0) { return substr($source, $start, $token->pos + 1 - $start); }
    }
    throw new RuntimeException('Production assignment is incomplete.');
}

/** Extract the foreach over a named variable, without anchoring on formatting comments. */
function foreachSource(string $source, string $name): string
{
    $tokens = PhpToken::tokenize($source);
    foreach ($tokens as $index => $token) {
        if (!$token->is(T_FOREACH)) { continue; }
        for ($next = $index + 1; $next < count($tokens) && !$tokens[$next]->is(T_AS); ++$next) {
            if ($tokens[$next]->is(T_VARIABLE) && $tokens[$next]->text === $name) { return blockSource($source, $tokens, $index); }
        }
    }
    throw new RuntimeException('Production foreach is missing: ' . $name);
}
