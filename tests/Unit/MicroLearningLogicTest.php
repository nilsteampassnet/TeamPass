<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared with app/core/micro-learning.js.php
// (F11 — In-Context Micro-Learning).
require_once __DIR__ . '/../../app/sources/learning.functions.php';

/**
 * Unit tests for the Micro-Learning logic (F11).
 *
 * Covers:
 *   - microLearningTipCatalogue() — structural integrity + i18n sentinel
 *     (every tip's lang key must exist in the English AND French files)
 *   - microLearningDailyTip()     — deterministic rotation, dismissal, exhaustion
 */
class MicroLearningLogicTest extends TestCase
{
    private const VALID_CONTEXTS = ['item_form', 'password_focus', 'otv_share', 'daily'];

    // -------------------------------------------------------------------
    // Catalogue integrity
    // -------------------------------------------------------------------

    public function testCatalogueIdsAreUniqueAndContextsValid(): void
    {
        $catalogue = microLearningTipCatalogue();
        $ids = array_column($catalogue, 'id');

        $this->assertSame($ids, array_unique($ids), 'Tip ids must be unique');
        foreach ($catalogue as $tip) {
            $this->assertContains($tip['context'], self::VALID_CONTEXTS, $tip['id']);
            $this->assertNotSame('', $tip['lang'], $tip['id']);
        }
    }

    public function testCatalogueHasDailyRotationMaterial(): void
    {
        $daily = array_filter(microLearningTipCatalogue(), static fn (array $t): bool => $t['context'] === 'daily');

        // The "first week" rotation needs material for several days.
        $this->assertGreaterThanOrEqual(5, count($daily));
    }

    /**
     * Sentinel: every tip lang key must exist in every shipped translation
     * used by the feature — a missing key silently renders an empty toast.
     */
    public function testEveryTipLangKeyExistsInEnglishAndFrench(): void
    {
        $english = include __DIR__ . '/../../app/includes/language/english.php';
        $french = include __DIR__ . '/../../app/includes/language/french.php';

        foreach (microLearningTipCatalogue() as $tip) {
            $this->assertArrayHasKey($tip['lang'], $english, 'english: ' . $tip['lang']);
            $this->assertNotSame('', trim((string) $english[$tip['lang']]), 'english: ' . $tip['lang']);
            $this->assertArrayHasKey($tip['lang'], $french, 'french: ' . $tip['lang']);
            $this->assertNotSame('', trim((string) $french[$tip['lang']]), 'french: ' . $tip['lang']);
        }
    }

    // -------------------------------------------------------------------
    // microLearningDailyTip()
    // -------------------------------------------------------------------

    public function testDailyTipIsDeterministicAndRotates(): void
    {
        $catalogue = microLearningTipCatalogue();
        $dailyCount = count(array_filter($catalogue, static fn (array $t): bool => $t['context'] === 'daily'));

        $day1 = microLearningDailyTip($catalogue, 100);
        $day1Again = microLearningDailyTip($catalogue, 100);
        $day2 = microLearningDailyTip($catalogue, 101);
        $fullCycle = microLearningDailyTip($catalogue, 100 + $dailyCount);

        $this->assertSame($day1['id'], $day1Again['id'], 'Same day, same tip');
        $this->assertNotSame($day1['id'], $day2['id'], 'Next day, next tip');
        $this->assertSame($day1['id'], $fullCycle['id'], 'Rotation wraps around');
    }

    public function testDailyTipNeverReturnsContextualTips(): void
    {
        $catalogue = microLearningTipCatalogue();
        $dailyCount = count(array_filter($catalogue, static fn (array $t): bool => $t['context'] === 'daily'));

        for ($day = 0; $day < $dailyCount * 2; $day++) {
            $this->assertSame('daily', microLearningDailyTip($catalogue, $day)['context']);
        }
    }

    public function testDailyTipSkipsDismissedTips(): void
    {
        $catalogue = microLearningTipCatalogue();
        $first = microLearningDailyTip($catalogue, 0);

        $next = microLearningDailyTip($catalogue, 0, [$first['id']]);

        $this->assertNotSame($first['id'], $next['id']);
    }

    public function testDailyTipReturnsNullWhenAllDismissed(): void
    {
        $catalogue = microLearningTipCatalogue();
        $allDaily = array_column(
            array_filter($catalogue, static fn (array $t): bool => $t['context'] === 'daily'),
            'id'
        );

        $this->assertNull(microLearningDailyTip($catalogue, 3, $allDaily));
    }
}
