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
        if ($token->text === '{' || $token->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) {
            ++$depth;
            $opened = true;
        } elseif ($token->text === '}' && $opened && --$depth === 0) {
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
