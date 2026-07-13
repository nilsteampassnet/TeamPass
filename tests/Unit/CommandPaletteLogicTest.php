<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Real production logic (DB-free) shared with the palette.queries.php
// handler (F15 — Universal Search / Command Palette).
require_once __DIR__ . '/../../app/sources/palette.functions.php';

/**
 * Unit tests for the Command Palette logic (F15).
 *
 * Covers:
 *   - paletteNormalizeTerm()  — trimming, min length, hard cap
 *   - paletteEscapeLikeTerm() — LIKE-injection hardening
 *   - paletteRankRows()       — relevance ordering
 */
class CommandPaletteLogicTest extends TestCase
{
    // -------------------------------------------------------------------
    // paletteNormalizeTerm()
    // -------------------------------------------------------------------

    public function testNormalizeTrimsAndKeepsUsableTerms(): void
    {
        $this->assertSame('git', paletteNormalizeTerm('  git  '));
    }

    public function testNormalizeRejectsTooShortTerms(): void
    {
        // Avoids a full LIKE scan on every first keystroke.
        $this->assertSame('', paletteNormalizeTerm('g'));
        $this->assertSame('', paletteNormalizeTerm('   '));
        $this->assertSame('', paletteNormalizeTerm(''));
    }

    public function testNormalizeCapsLength(): void
    {
        $this->assertSame(100, mb_strlen(paletteNormalizeTerm(str_repeat('a', 500))));
    }

    // -------------------------------------------------------------------
    // paletteEscapeLikeTerm()
    // -------------------------------------------------------------------

    public function testEscapeNeutralisesLikeWildcards(): void
    {
        // A raw % or _ would widen the match (LIKE injection).
        $this->assertSame('100\%', paletteEscapeLikeTerm('100%'));
        $this->assertSame('a\_b', paletteEscapeLikeTerm('a_b'));
        $this->assertSame('a\\\\b', paletteEscapeLikeTerm('a\\b'));
    }

    public function testEscapeLeavesNormalTextAlone(): void
    {
        $this->assertSame('gitlab prod', paletteEscapeLikeTerm('gitlab prod'));
    }

    // -------------------------------------------------------------------
    // paletteRankRows()
    // -------------------------------------------------------------------

    private function rows(array $labels): array
    {
        return array_map(static fn (string $label): array => ['label' => $label], $labels);
    }

    public function testRankExactBeatsPrefixBeatsSubstring(): void
    {
        $ranked = paletteRankRows(
            $this->rows(['my gitlab', 'gitlab prod', 'gitlab']),
            'gitlab'
        );

        $this->assertSame(['gitlab', 'gitlab prod', 'my gitlab'], array_column($ranked, 'label'));
    }

    public function testRankEarlierSubstringWins(): void
    {
        $ranked = paletteRankRows(
            $this->rows(['zzz git', 'a git', 'the git server']),
            'git'
        );

        // 'a git' (pos 2) ranks before 'the git server' / 'zzz git' (pos 4).
        $this->assertSame('a git', $ranked[0]['label']);
    }

    public function testRankIsCaseInsensitiveAndFallsBackAlpha(): void
    {
        $ranked = paletteRankRows(
            $this->rows(['GitLab', 'gitface', 'GITHUB']),
            'git'
        );

        // All prefix matches -> alphabetical tie-break.
        $this->assertSame(['gitface', 'GITHUB', 'GitLab'], array_column($ranked, 'label'));
    }

    public function testRankKeepsNonMatchingRowsLast(): void
    {
        // A row matched on login/url only (label does not contain the term)
        // must stay listed, after every direct label match.
        $ranked = paletteRankRows(
            $this->rows(['admin account', 'git server']),
            'git'
        );

        $this->assertSame(['git server', 'admin account'], array_column($ranked, 'label'));
    }

    public function testRankStripsInternalScoreKey(): void
    {
        $ranked = paletteRankRows($this->rows(['abc']), 'ab');

        $this->assertArrayNotHasKey('_score', $ranked[0]);
    }
}
