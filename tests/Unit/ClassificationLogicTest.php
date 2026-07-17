<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared with classification.queries.php and
// the reports.queries.php coverage report (F4 — Data Classification).
require_once __DIR__ . '/../../app/sources/classification.functions.php';

/**
 * Unit tests for the Data Classification logic (F4).
 *
 * Covers:
 *   - classificationLevels()     — the fixed scale
 *   - classificationValidLevel() — level validation (0 = clear)
 *   - classificationLabelKey()   — language key resolution
 *   - classificationCoverage()   — coverage report rows
 */
class ClassificationLogicTest extends TestCase
{
    // -------------------------------------------------------------------
    // classificationLevels() / classificationValidLevel()
    // -------------------------------------------------------------------

    public function testScaleHasFiveFixedLevels(): void
    {
        $this->assertSame(
            [0 => 'unclassified', 1 => 'public', 2 => 'internal', 3 => 'confidential', 4 => 'restricted'],
            classificationLevels()
        );
    }

    public function testValidLevelsAreZeroToFour(): void
    {
        foreach ([0, 1, 2, 3, 4] as $level) {
            $this->assertTrue(classificationValidLevel($level));
        }
        $this->assertFalse(classificationValidLevel(-1));
        $this->assertFalse(classificationValidLevel(5));
        $this->assertFalse(classificationValidLevel(99));
    }

    // -------------------------------------------------------------------
    // classificationLabelKey()
    // -------------------------------------------------------------------

    public function testLabelKeysMatchTheScale(): void
    {
        $this->assertSame('classification_level_restricted', classificationLabelKey(4));
        $this->assertSame('classification_level_public', classificationLabelKey(1));
        $this->assertSame('classification_level_unclassified', classificationLabelKey(0));
    }

    public function testUnknownLevelFallsBackToUnclassified(): void
    {
        $this->assertSame('classification_level_unclassified', classificationLabelKey(42));
    }

    // -------------------------------------------------------------------
    // classificationCoverage()
    // -------------------------------------------------------------------

    public function testCoverageComputesUnclassifiedRemainder(): void
    {
        $rows = classificationCoverage([1 => 10, 3 => 5, 4 => 5], 100);

        // First row is always the unclassified remainder
        $this->assertSame(0, $rows[0]['level']);
        $this->assertSame(80, $rows[0]['items']);
        $this->assertSame(80.0, $rows[0]['percent']);

        // Then one row per level 1..4, in order
        $this->assertSame(10, $rows[1]['items']);   // public
        $this->assertSame(0, $rows[2]['items']);    // internal
        $this->assertSame(5, $rows[3]['items']);    // confidential
        $this->assertSame(5, $rows[4]['items']);    // restricted
        $this->assertSame(5.0, $rows[4]['percent']);
        $this->assertCount(5, $rows);
    }

    public function testCoverageNeverGoesNegativeOnInconsistentInput(): void
    {
        // More classified rows than counted items (e.g. stale rows on deleted items)
        $rows = classificationCoverage([4 => 50], 10);

        $this->assertSame(0, $rows[0]['items']);
    }

    public function testCoverageWithEmptyVault(): void
    {
        $rows = classificationCoverage([], 0);

        $this->assertSame(0, $rows[0]['items']);
        $this->assertSame(0.0, $rows[0]['percent']);
        $this->assertCount(5, $rows);
    }

    public function testCoverageIgnoresNegativeCounts(): void
    {
        $rows = classificationCoverage([2 => -5], 10);

        $this->assertSame(0, $rows[2]['items']);
        $this->assertSame(10, $rows[0]['items']);
    }
}
