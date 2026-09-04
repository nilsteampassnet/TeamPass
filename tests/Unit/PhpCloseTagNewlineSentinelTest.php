<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      PhpCloseTagNewlineSentinelTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

/**
 * PHP swallows the newline that directly follows "?>".
 *
 * Two consecutive template lines of the shape
 *
 *     const a = <?php echo json_encode(...); ?>
 *     const b = <?php echo json_encode(...); ?>
 *
 * are therefore emitted as a single output line:
 *
 *     const a = "x"    const b = "y"
 *
 * which dies with "Unexpected token 'const'" and takes the whole <script> block
 * down with it, not just the declaration that follows. The page looks intact and
 * every behaviour on it is dead, so the symptom points nowhere near the cause.
 *
 * This shipped once, in the folder search of 3.2.2.1, and the fix is a blank line
 * whose only job is to survive. That makes it exactly the kind of thing a reviewer
 * removes as stray whitespace, so it needs a sentinel rather than a comment alone.
 */
class PhpCloseTagNewlineSentinelTest extends TestCase
{
    private const SCANNED_GLOBS = [
        'app/pages/*.js.php',
        'app/core/*.js.php',
    ];

    /**
     * Placeholder standing for whatever a PHP block prints.
     *
     * It has to be a token that cannot occur in the sources being scanned, and it
     * must start and end with a non-word character. A marker ending on "_" glues
     * itself to a declaration sitting at column 0 ("__MARKER__const x"), and the
     * word boundary the rule below relies on then no longer exists: 3.2.2.2 shipped
     * that exact shape in the admin dashboard while this sentinel stayed green.
     */
    private const OUTPUT_MARKER = '<__TP_PHP_OUTPUT__>';

    /**
     * Statement keywords that can never legally follow a value on the same line.
     *
     * Restricted to declarations on purpose. A bare call such as "foo.bar()" glued
     * behind a value is also a syntax error, but a PHP block holding pure control
     * flow prints nothing at all, so the reconstruction below would report it while
     * the rendered page is fine. Declarations do not appear in that position.
     */
    private const DECLARATION_KEYWORDS = [
        'const', 'let', 'var', 'function', 'class',
    ];

    /**
     * Occurrences proven not to concatenate anything in the rendered output.
     *
     * Keyed by file, each entry being the offending output line. Empty while the
     * reconstruction reports nothing; kept so a justified case has somewhere to go
     * instead of the rule being weakened for everyone.
     *
     * @var array<string, list<string>>
     */
    private const ALLOWED = [];

    /**
     * Rebuild what a template actually prints, as far as newlines are concerned.
     *
     * Every PHP block becomes one marker, and the newline directly following its
     * "?>" is dropped exactly as the PHP engine drops it. A block closed by the end
     * of file has no close tag and therefore eats nothing.
     *
     * @param string $source Raw template content
     *
     * @return string Output skeleton, one entry per emitted line
     */
    private function buildOutputSkeleton(string $source): string
    {
        return (string) preg_replace(
            '/<\?php.*?\?>\n|<\?php.*?\?>|<\?php.*$/s',
            self::OUTPUT_MARKER,
            $source
        );
    }

    /**
     * Rule matching a declaration emitted behind the output of a PHP block.
     *
     * The word boundary is what keeps "iconst" from being read as a declaration,
     * and it only holds because the marker ends on a non-word character.
     *
     * @return string PCRE pattern applied to one output line
     */
    private function declarationPattern(): string
    {
        return '/' . self::OUTPUT_MARKER . '.*?\b(?:'
            . implode('|', self::DECLARATION_KEYWORDS)
            . ')\s+[A-Za-z_$]/';
    }

    /**
     * List every repository file matched by the scanned globs.
     *
     * @return list<string> Paths relative to the repository root
     */
    private function scannedFiles(): array
    {
        $root = dirname(__DIR__, 2) . '/';
        $files = [];

        foreach (self::SCANNED_GLOBS as $glob) {
            foreach ((array) glob($root . $glob) as $path) {
                $files[] = substr((string) $path, strlen($root));
            }
        }

        sort($files);

        return $files;
    }

    /**
     * No template may glue a declaration behind the output of a PHP block.
     *
     * @return void
     */
    public function testNoDeclarationIsGluedBehindAPhpBlockOutput(): void
    {
        $root = dirname(__DIR__, 2) . '/';
        $pattern = $this->declarationPattern();

        $violations = [];
        foreach ($this->scannedFiles() as $relative) {
            $source = (string) file_get_contents($root . $relative);

            foreach (explode("\n", $this->buildOutputSkeleton($source)) as $line) {
                if (preg_match($pattern, $line) !== 1) {
                    continue;
                }

                if (in_array($line, self::ALLOWED[$relative] ?? [], true) === true) {
                    continue;
                }

                $violations[] = $relative . ': ' . trim($line);
            }
        }

        self::assertSame(
            [],
            $violations,
            'A declaration is emitted on the same line as the output of a PHP block, because '
            . '"?>" swallows the newline that follows it. Separate them with a blank line, '
            . 'or add the case to ' . self::class . '::ALLOWED with the reason it is safe.'
        );
    }

    /**
     * The reconstruction must reproduce the bug it exists to catch.
     *
     * Without this, a change that makes buildOutputSkeleton() stop recognising PHP
     * blocks would leave the test green and silently guarding nothing.
     *
     * @return void
     */
    public function testTheReconstructionDropsTheNewlineFollowingACloseTag(): void
    {
        $broken = "    const a = <?php echo 1; ?>\n    const b = <?php echo 2; ?>\n";
        $fixed = "    const a = <?php echo 1; ?>\n\n    const b = <?php echo 2; ?>\n";

        $brokenSkeleton = $this->buildOutputSkeleton($broken);
        $fixedSkeleton = $this->buildOutputSkeleton($fixed);

        self::assertStringContainsString(
            self::OUTPUT_MARKER . '    const b',
            $brokenSkeleton,
            'The two declarations must land on one line when no blank line separates them'
        );
        self::assertStringNotContainsString(
            self::OUTPUT_MARKER . '    const b',
            $fixedSkeleton,
            'A blank line must keep the two declarations apart'
        );
    }

    /**
     * The rule must actually fire on the shape that shipped broken.
     *
     * @return void
     */
    public function testTheRuleFlagsTheShapeThatShipped(): void
    {
        $pattern = $this->declarationPattern();

        $skeleton = $this->buildOutputSkeleton(
            "    const folderResultLabel = <?php echo json_encode('a'); ?>\n"
            . "    const folderPickerPlaceholder = <?php echo json_encode('b'); ?>\n"
        );

        $flagged = false;
        foreach (explode("\n", $skeleton) as $line) {
            if (preg_match($pattern, $line) === 1) {
                $flagged = true;
            }
        }

        self::assertTrue($flagged, 'The sentinel must flag the 3.2.2.1 folder-search regression');
    }

    /**
     * The rule must fire when the glued declaration carries no indentation.
     *
     * This is the 3.2.2.2 admin-dashboard regression. It differs from the folder
     * search only by the missing indentation, which was enough to make the marker
     * and the keyword form a single word and silence the rule.
     *
     * @return void
     */
    public function testTheRuleFlagsADeclarationGluedAtColumnZero(): void
    {
        $skeleton = $this->buildOutputSkeleton(
            "const ADMIN_NOTICES_LABEL = <?php echo json_encode('a'); ?>\n"
            . "const ADMIN_NOTICES_NONE_LABEL = <?php echo json_encode('b'); ?>\n"
        );

        $flagged = false;
        foreach (explode("\n", $skeleton) as $line) {
            if (preg_match($this->declarationPattern(), $line) === 1) {
                $flagged = true;
            }
        }

        self::assertTrue($flagged, 'The sentinel must flag the 3.2.2.2 admin-dashboard regression');
    }

    /**
     * The globs must keep matching the templates they are meant to cover.
     *
     * @return void
     */
    public function testTheScanCoversTheTemplates(): void
    {
        $files = $this->scannedFiles();

        self::assertGreaterThan(40, count($files), 'The scan must cover every *.js.php template');
        self::assertContains('app/pages/search.js.php', $files);
    }
}
