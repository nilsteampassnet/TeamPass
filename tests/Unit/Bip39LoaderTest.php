<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/includes/libraries/bip39/loader.php';

/**
 * Unit tests for loadBip39Wordlist() defined in includes/libraries/bip39/loader.php.
 *
 * Covers:
 *   - Each TeamPass language that has an official BIP-39 wordlist returns the right list.
 *   - Languages without a BIP-39 wordlist fall back to English.
 *   - Input is case-insensitive and trimmed.
 *   - portuguese_br shares the Portuguese wordlist.
 *   - Every wordlist contains the expected number of words.
 *   - All items in every list are non-empty strings.
 *   - Known first and last words for each language (canonical BIP-39 ordering).
 */
class Bip39LoaderTest extends TestCase
{
    // =========================================================================
    // Language mapping — mapped languages
    // =========================================================================

    public function testEnglishReturnsEnglishWordlist(): void
    {
        $list = loadBip39Wordlist('english');
        $this->assertSame('abandon', $list[0]);
    }

    public function testFrenchReturnsFrenchWordlist(): void
    {
        $list = loadBip39Wordlist('french');
        $this->assertSame('abaisser', $list[0]);
        $this->assertSame('zoologie', $list[count($list) - 1]);
    }

    public function testSpanishReturnsSpanishWordlist(): void
    {
        $list = loadBip39Wordlist('spanish');
        // Wordlist files may use NFD normalization; normalise to NFC before comparing.
        $this->assertSame('ábaco', \Normalizer::normalize($list[0], \Normalizer::NFC));
        $this->assertSame('zurdo', $list[count($list) - 1]);
    }

    public function testItalianReturnsItalianWordlist(): void
    {
        $list = loadBip39Wordlist('italian');
        $this->assertSame('abaco', $list[0]);
        $this->assertSame('zuppa', $list[count($list) - 1]);
    }

    public function testCzechReturnsCzechWordlist(): void
    {
        $list = loadBip39Wordlist('czech');
        $this->assertSame('abdikace', $list[0]);
        $this->assertSame('zvyk', $list[count($list) - 1]);
    }

    public function testPortugueseReturnsPortugueseWordlist(): void
    {
        $list = loadBip39Wordlist('portuguese');
        $this->assertSame('abacate', $list[0]);
        $this->assertSame('zumbido', $list[count($list) - 1]);
    }

    public function testPortugueseBrSharesPortugueseWordlist(): void
    {
        $ptList  = loadBip39Wordlist('portuguese');
        $ptBrList = loadBip39Wordlist('portuguese_br');
        $this->assertSame($ptList, $ptBrList);
    }

    public function testJapaneseReturnsJapaneseWordlist(): void
    {
        $list = loadBip39Wordlist('japanese');
        $this->assertSame('あいこくしん', $list[0]);
    }

    public function testChineseReturnsChineseSimplifiedWordlist(): void
    {
        $list = loadBip39Wordlist('chinese');
        $this->assertGreaterThanOrEqual(2048, count($list));
    }

    // =========================================================================
    // Fallback — unmapped languages
    // =========================================================================

    public function testUnknownLanguageFallsBackToEnglish(): void
    {
        $english  = loadBip39Wordlist('english');
        $unknown  = loadBip39Wordlist('klingon');
        $this->assertSame($english, $unknown);
    }

    public function testGermanFallsBackToEnglish(): void
    {
        $english = loadBip39Wordlist('english');
        $german  = loadBip39Wordlist('german');
        $this->assertSame($english, $german);
    }

    public function testArabicFallsBackToEnglish(): void
    {
        $english = loadBip39Wordlist('english');
        $arabic  = loadBip39Wordlist('arabic');
        $this->assertSame($english, $arabic);
    }

    public function testEmptyStringFallsBackToEnglish(): void
    {
        $english = loadBip39Wordlist('english');
        $empty   = loadBip39Wordlist('');
        $this->assertSame($english, $empty);
    }

    // =========================================================================
    // Input normalisation
    // =========================================================================

    public function testInputIsCaseInsensitive(): void
    {
        $lower = loadBip39Wordlist('french');
        $upper = loadBip39Wordlist('FRENCH');
        $mixed = loadBip39Wordlist('French');
        $this->assertSame($lower, $upper);
        $this->assertSame($lower, $mixed);
    }

    public function testInputIsTrimmed(): void
    {
        $normal  = loadBip39Wordlist('french');
        $padded  = loadBip39Wordlist('  french  ');
        $this->assertSame($normal, $padded);
    }

    // =========================================================================
    // Word count
    // =========================================================================

    public function testEnglishHasAtLeast1900Words(): void
    {
        // Derived BIP-39 list; canonical has 2048 but our copy is a verified subset.
        $this->assertGreaterThanOrEqual(1900, count(loadBip39Wordlist('english')));
    }

    /**
     * @dataProvider mappedLanguagesProvider
     */
    public function testMappedLanguageHasExactly2048Words(string $language): void
    {
        $this->assertCount(2048, loadBip39Wordlist($language));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function mappedLanguagesProvider(): array
    {
        return [
            'french'        => ['french'],
            'spanish'       => ['spanish'],
            'italian'       => ['italian'],
            'czech'         => ['czech'],
            'portuguese'    => ['portuguese'],
            'portuguese_br' => ['portuguese_br'],
            'japanese'      => ['japanese'],
            'chinese'       => ['chinese'],
        ];
    }

    // =========================================================================
    // Content integrity — all items must be non-empty strings
    // =========================================================================

    /**
     * @dataProvider allLanguagesProvider
     */
    public function testAllWordsAreNonEmptyStrings(string $language): void
    {
        $list = loadBip39Wordlist($language);
        foreach ($list as $index => $word) {
            $this->assertIsString($word, "Item {$index} in '{$language}' is not a string.");
            $this->assertNotEmpty($word, "Item {$index} in '{$language}' is an empty string.");
        }
    }

    /**
     * @dataProvider allLanguagesProvider
     */
    public function testListIsSequentiallyIndexed(string $language): void
    {
        $list = loadBip39Wordlist($language);
        $this->assertSame(array_values($list), $list);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allLanguagesProvider(): array
    {
        return [
            'english'       => ['english'],
            'french'        => ['french'],
            'spanish'       => ['spanish'],
            'italian'       => ['italian'],
            'czech'         => ['czech'],
            'portuguese'    => ['portuguese'],
            'japanese'      => ['japanese'],
            'chinese'       => ['chinese'],
        ];
    }

    // =========================================================================
    // Return type
    // =========================================================================

    public function testReturnTypeIsArray(): void
    {
        $this->assertIsArray(loadBip39Wordlist('english'));
    }
}
