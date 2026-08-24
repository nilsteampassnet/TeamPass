<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel guarding the client-side HTML encoding rule.
 *
 * purifyData() — which every AJAX response goes through, since
 * prepareExchangedData(…, 'decode', …) defaults to purify = true — returns PLAIN,
 * UNESCAPED TEXT. purifyServerData() strips tags but decodes HTML entities twice, so a value
 * the server stored as "&lt;img onerror=…&gt;" can come back out as live markup.
 *
 * Safety therefore lives at the sink: every interpolation of such a value into markup must
 * be wrapped in htmlEncode() / escapeHtml() / escapeText() / escapeAttribute(), or inserted
 * with .text(). Nine private XSS reports in a row landed on screens that skipped this step,
 * which is why it is enforced here rather than left to review.
 *
 * The test flags `'…' + <path>.<field> + '…'` for a field known to carry user data, `<path>`
 * being any dotted expression: `data.message` and `err.file.name` both match. The encoded
 * form `htmlEncode(value.title)` never does, because the call sits between the `+` and the
 * start of the path.
 *
 * Analysis: workReadmeFiles/client-purifier-root-cause-study.md
 */
class ClientHtmlEncodingSentinelTest extends TestCase
{
    /**
     * Object properties that carry user- or directory-controlled data.
     */
    private const WATCHED_FIELDS = [
        'title', 'label', 'name', 'login', 'email', 'folder', 'path',
        'lastname', 'description', 'message', 'reason', 'task_type',
        'changed_by', 'deleted_by', 'updated_by', 'detail',
    ];

    /**
     * Helpers that make a value safe to interpolate.
     *
     * The purifiers are deliberately absent: they strip tags but hand back plain text with
     * live quotes, which is exactly the gap this sentinel exists to close.
     */
    private const ENCODERS = [
        'htmlEncode', 'escapeHtml', 'escapeHtmlString', 'escapeText',
        'escapeAttribute', 'esc', 'tpEscapeHtml',
    ];

    private const SCANNED_GLOBS = [
        'app/pages/*.js.php',
        'app/core/*.js.php',
        'app/includes/js/*.js',
    ];

    /**
     * Occurrences that are known not to be HTML sinks, or not to carry user data.
     *
     * Keyed by file, each entry being the matched expression. Line numbers are deliberately
     * not used: they drift on every edit, whereas an expression that changes deserves a new
     * look anyway.
     *
     * Every entry is a value proven not to reach markup with user data. The status and error
     * messages that used to sit here as "pending review" are now encoded at their sinks; two
     * of them carried a <br> from the server, which moved into the page template so the
     * message could become pure data.
     */
    private const ALLOWED = [
        // safe — inside a /* … */ block, never executed
        'app/pages/export.js.php' => ['item.title'],
        // safe — builds a lowercase needle for .indexOf(), never inserted in the DOM
        'app/pages/favorites.js.php' => ['item.login', 'item.description', 'item.folder'],
        // safe — opt.title comes from a static local array of column labels
        'app/pages/utilities.logs.js.php' => ['opt.title'],
        // safe — info comes from the static client-side LEVELS map
        'app/core/item-classification.js.php' => ['info.label'],
        // safe — item.login is returned as a string by a Select2 templateSelection, which
        // Select2 passes through escapeMarkup; first.login goes to the Option constructor,
        // whose text argument becomes a text node
        'app/pages/lapr_accounts.js.php' => ['item.login', 'first.login'],
        // safe — same Select2 templateSelection string contract
        'app/pages/lapr_endpoints.js.php' => ['item.login'],
    ];

    /**
     * @return array<int, array{file: string, line: int, expression: string, snippet: string}>
     */
    private static function collectViolations(): array
    {
        $root = dirname(__DIR__, 2) . '/';
        // The path part is greedy across dots so a nested sink such as err.file.name or
        // data.corruption_notice.message is caught, not only the single-level form.
        $pattern = '/\+\s*([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z0-9_]+)*)\.('
            . implode('|', self::WATCHED_FIELDS) . ')\b/';

        $violations = [];
        foreach (self::SCANNED_GLOBS as $glob) {
            foreach ((array) glob($root . $glob) as $path) {
                $relative = substr((string) $path, strlen($root));
                $lines = file((string) $path);
                if ($lines === false) {
                    continue;
                }

                foreach ($lines as $index => $line) {
                    if (preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE) === 0) {
                        continue;
                    }

                    foreach ($matches[0] as $position => $match) {
                        // An encoder call sitting between the '+' and the identifier means
                        // this occurrence is already wrapped.
                        $before = rtrim(substr($line, 0, (int) $match[1]));
                        $wrapped = false;
                        foreach (self::ENCODERS as $encoder) {
                            if (preg_match('/' . $encoder . '\($/', $before) === 1) {
                                $wrapped = true;
                                break;
                            }
                        }

                        if ($wrapped === true) {
                            continue;
                        }

                        $expression = $matches[1][$position][0] . '.' . $matches[2][$position][0];
                        if (in_array($expression, self::ALLOWED[$relative] ?? [], true) === true) {
                            continue;
                        }

                        $violations[] = [
                            'file' => $relative,
                            'line' => $index + 1,
                            'expression' => $expression,
                            'snippet' => trim($line),
                        ];
                    }
                }
            }
        }

        return $violations;
    }

    public function testNoUserControlledValueIsInterpolatedWithoutEncoding(): void
    {
        $violations = self::collectViolations();

        $report = '';
        foreach ($violations as $violation) {
            $report .= sprintf(
                "\n  %s:%d  ->  %s\n      %s",
                $violation['file'],
                $violation['line'],
                $violation['expression'],
                mb_strimwidth($violation['snippet'], 0, 160, '…')
            );
        }

        $this->assertSame(
            [],
            $violations,
            "User-controlled values must be encoded before being interpolated into markup.\n"
            . "Wrap them in htmlEncode() (or insert with .text()), or add the occurrence to\n"
            . "ClientHtmlEncodingSentinelTest::ALLOWED with the reason it is not a sink."
            . $report
        );
    }

    /**
     * The allow-list must not outlive what it describes: an entry that no longer matches
     * anything means the code was fixed or moved, and the exemption should go with it.
     */
    public function testAllowListHasNoStaleEntries(): void
    {
        $root = dirname(__DIR__, 2) . '/';

        foreach (self::ALLOWED as $relative => $expressions) {
            $this->assertFileExists($root . $relative);
            $source = (string) file_get_contents($root . $relative);

            foreach ($expressions as $expression) {
                $this->assertMatchesRegularExpression(
                    '/\+\s*' . preg_quote($expression, '/') . '\b/',
                    $source,
                    sprintf(
                        'Stale exemption: "%s" no longer appears unencoded in %s — remove it '
                        . 'from ClientHtmlEncodingSentinelTest::ALLOWED.',
                        $expression,
                        $relative
                    )
                );
            }
        }
    }

    /**
     * The rule only holds while purifyData() keeps returning plain text. If that contract
     * ever changes, the sinks must be revisited before this sentinel is trusted again.
     */
    public function testPurifyDataContractIsDocumented(): void
    {
        $root = dirname(__DIR__, 2) . '/';

        foreach (['app/includes/js/functions.js', 'public/assets/js/functions.js'] as $relative) {
            $source = (string) file_get_contents($root . $relative);

            $this->assertStringContainsString(
                'CONTRACT',
                $source,
                $relative . ' must keep the purifyData() contract comment.'
            );
            $this->assertStringContainsString(
                'PLAIN, UNESCAPED TEXT',
                $source,
                $relative . ' must keep stating that purifyData() returns unescaped text.'
            );
        }
    }

    /**
     * The inbound and outbound purifiers must stay two distinct entry points.
     *
     * They were a single simplePurifier() serving both directions, which is what made the
     * function impossible to reason about: the same code had to reject what the user typed
     * AND clean what the server returned, two contracts that do not follow the same rules.
     * Re-merging them, or reviving the old name in a page script, would undo that.
     */
    public function testInboundAndOutboundPurifiersStaySeparate(): void
    {
        $root = dirname(__DIR__, 2) . '/';

        foreach (['app/includes/js/functions.js', 'public/assets/js/functions.js'] as $relative) {
            $source = (string) file_get_contents($root . $relative);

            $this->assertStringContainsString(
                'return purifyServerData(obj, bHtml, bSvg, bSvgFilters);',
                $source,
                $relative . ': purifyData() must route AJAX responses through purifyServerData().'
            );
            $this->assertStringContainsString(
                'string = purifyUserInput(text, bHtml, bSvg, bSvgFilters);',
                $source,
                $relative . ': fieldDomPurifier() must route form values through purifyUserInput().'
            );
        }

        $stale = [];
        foreach (self::SCANNED_GLOBS as $glob) {
            foreach ((array) glob($root . $glob) as $path) {
                if (str_contains((string) file_get_contents((string) $path), 'simplePurifier') === true) {
                    $stale[] = substr((string) $path, strlen($root));
                }
            }
        }

        $this->assertSame(
            [],
            $stale,
            "simplePurifier() was split into purifyServerData() (AJAX responses) and\n"
            . "purifyUserInput() (form values). Pick the one that matches the direction of\n"
            . 'the data instead of reviving the merged name.'
        );
    }
}
