<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;
use function TeamPass\Tests\SourceStructure\assignmentSource;
use function TeamPass\Tests\SourceStructure\foreachSource;
use function TeamPass\Tests\SourceStructure\functionSource;
use function TeamPass\Tests\SourceStructure\lastCatchSource;

require_once __DIR__ . '/../Fixtures/php_source_structure.php';

final class PhpSourceStructureTest extends TestCase
{
    /** Comments, interpolation and changed catch variable names do not change extraction boundaries. */
    public function testBoundariesFollowPhpTokensRatherThanCommentText(): void
    {
        $source = <<<'PHP'
<?php
// function sample() { fake }
function sample(string $value): string {
    /* } comments are not blocks */
    $label = "{$value}}";
    return $label;
}
$list /* arbitrary explanation */ = [';', ['{', '}']];
foreach /* changed comment */ ($list as $entry) { echo '{'; }
try { sample('x'); } catch (Throwable $renamedError) { echo '}'; }
PHP;
        $function = functionSource($source, 'sample');
        self::assertStringContainsString('return $label;', $function);
        self::assertStringEndsWith('}', $function);
        self::assertStringNotContainsString('$list', $function);
        self::assertSame('$list /* arbitrary explanation */ = [\';\', [\'{\', \'}\']];', assignmentSource($source, '$list'));
        self::assertStringContainsString("echo '{';", foreachSource($source, '$list'));
        self::assertStringContainsString('Throwable $renamedError', lastCatchSource($source));
    }
}
