<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel: every action of admin.queries.php refuses a request that does not carry the
 * session key — static regression guard for GHSA-7wvw-wxqx-v364.
 *
 * The file-level gate only proves that an administrator session exists. The session key the
 * page sends with each request is what proves the request comes from TeamPass itself: the
 * CSRF protector runs in the front controller only, never on sources/*.queries.php.
 * save_api_status, clean_old_logs, websocket_start and websocket_stop (and six other cases)
 * skipped that check, so a request carrying only the administrator's cookie went through.
 *
 * Locked invariant: the first statement of each case (after an optional require_once) is an
 * if that refuses a missing or wrong key and breaks out of the switch.
 */
class AdminQueriesSessionKeyTest extends TestCase
{
    /**
     * Returns the source of each case of the admin.queries.php switch, keyed by action name.
     *
     * @return array<string, string>
     */
    private function cases(): array
    {
        $src = file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');
        self::assertIsString($src);

        $switch = strpos($src, 'switch ($post_type) {');
        self::assertIsInt($switch, 'The action switch was not found: review this test');
        $src = substr($src, $switch);

        preg_match_all("/^[ \\t]*case '([A-Za-z0-9_-]+)':/m", $src, $labels, PREG_OFFSET_CAPTURE);
        $cases = [];
        $count = count($labels[0]);
        for ($i = 0; $i < $count; $i++) {
            $start = $labels[0][$i][1] + strlen($labels[0][$i][0]);
            $end = $i + 1 < $count ? $labels[0][$i + 1][1] : strlen($src);
            $name = $labels[1][$i][0];
            self::assertArrayNotHasKey($name, $cases, "case '$name' is declared twice");
            $cases[$name] = substr($src, $start, $end - $start);
        }

        return $cases;
    }

    /**
     * Returns the meaningful tokens of a code fragment: no whitespace, no comment.
     *
     * @return list<array{0: int|null, 1: string}>
     */
    private function tokens(string $code): array
    {
        $tokens = [];
        foreach (token_get_all('<?php ' . $code) as $token) {
            $id = is_array($token) ? $token[0] : null;
            if (in_array($id, [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $tokens[] = [$id, is_array($token) ? $token[1] : $token];
        }

        return $tokens;
    }

    /**
     * Returns the index just past the parenthesis or brace group that opens at $open.
     *
     * @param list<array{0: int|null, 1: string}> $tokens
     */
    private function groupEnd(array $tokens, int $open, string $opening, string $closing): int
    {
        $depth = 0;
        $total = count($tokens);
        for ($i = $open; $i < $total; $i++) {
            $text = $tokens[$i][1];
            // T_CURLY_OPEN / T_DOLLAR_OPEN_CURLY_BRACES also close on a plain '}'.
            if ($text === $opening || ($opening === '{' && in_array($tokens[$i][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif ($text === $closing) {
                $depth--;
                if ($depth === 0) {
                    return $i + 1;
                }
            }
        }
        self::fail('Unbalanced ' . $opening . $closing . ' group: review this test');
    }

    public function testTheSwitchIsParsed(): void
    {
        $cases = $this->cases();

        self::assertGreaterThan(30, count($cases), 'Too few cases found: review this test');
        foreach (['save_api_status', 'clean_old_logs', 'websocket_start', 'websocket_stop'] as $name) {
            self::assertArrayHasKey($name, $cases, "case '$name' no longer exists: review this test");
        }
    }

    public function testEveryActionRefusesARequestWithoutTheSessionKey(): void
    {
        foreach ($this->cases() as $name => $code) {
            $tokens = $this->tokens($code);
            $i = 0;

            // A library load may come first (run_duo_config_check).
            while (isset($tokens[$i]) && in_array($tokens[$i][0], [T_REQUIRE_ONCE, T_REQUIRE, T_INCLUDE_ONCE, T_INCLUDE], true)) {
                while (isset($tokens[$i]) && $tokens[$i][1] !== ';') {
                    $i++;
                }
                $i++;
            }

            self::assertSame(T_IF, $tokens[$i][0] ?? null, "case '$name' must start with the session key check");
            self::assertSame('(', $tokens[$i + 1][1] ?? null, "case '$name': unexpected if syntax");

            $conditionEnd = $this->groupEnd($tokens, $i + 1, '(', ')');
            $condition = implode('', array_column(array_slice($tokens, $i + 1, $conditionEnd - $i - 1), 1));
            self::assertMatchesRegularExpression(
                "/\\\$post_key!==\\\$session->get\\('key'\\)|!hash_equals\\(\\(string\\)\\\$session->get\\('key'\\),\\\$post_key\\)/",
                $condition,
                "case '$name' must refuse a request whose key differs from the session key"
            );

            self::assertSame('{', $tokens[$conditionEnd][1] ?? null, "case '$name': the key check must be a block");
            $blockEnd = $this->groupEnd($tokens, $conditionEnd, '{', '}');
            $block = array_column(array_slice($tokens, $conditionEnd, $blockEnd - $conditionEnd), 0);
            self::assertContains(T_BREAK, $block, "case '$name' must stop when the key is wrong");
        }
    }
}
