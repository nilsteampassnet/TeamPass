<?php
/**
 * BIP-39 wordlist loader.
 * Maps TeamPass user language to the closest available BIP-39 wordlist.
 * Falls back to English when no matching wordlist is found.
 */

declare(strict_types=1);

/**
 * Returns the BIP-39 wordlist array for the given TeamPass language name.
 *
 * @param string $teampassLanguage  Value from $session->get('user-language'), e.g. 'english', 'french'.
 * @return array<int, string>       Array of lowercase words (at least 1911 entries).
 */
function loadBip39Wordlist(string $teampassLanguage): array
{
    // Map TeamPass language names to BIP-39 wordlist file names.
    // Only languages that have an official BIP-39 wordlist are mapped;
    // all others fall back to English.
    $languageMap = [
        'english'        => 'en',
        'french'         => 'fr',
        'spanish'        => 'es',
        'italian'        => 'it',
        'czech'          => 'cs',
        'portuguese'     => 'pt',
        'portuguese_br'  => 'pt',
        'japanese'       => 'ja',
        'chinese'        => 'zh',
    ];

    $lang = strtolower(trim($teampassLanguage));
    $code = $languageMap[$lang] ?? 'en';

    $file = __DIR__ . '/wordlists/' . $code . '.php';

    // Fall back to English if the file for the mapped language does not exist.
    if (!file_exists($file)) {
        $file = __DIR__ . '/wordlists/en.php';
    }

    /** @var array<int, string> $wordlist */
    $wordlist = require $file;

    return $wordlist;
}
