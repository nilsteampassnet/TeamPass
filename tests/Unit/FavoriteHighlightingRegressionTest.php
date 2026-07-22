<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel tests for favorite highlighting on the initial item-list render.
 */
class FavoriteHighlightingRegressionTest extends TestCase
{
    /**
     * Path of every functions.js copy shipping browserSession().
     *
     * @var string[]
     */
    private const BROWSER_SESSION_COPIES = [
        '/../../public/assets/js/functions.js',
        '/../../app/includes/js/functions.js',
    ];

    private static function readSource(string $relativePath): string
    {
        $path = __DIR__ . $relativePath;
        $content = file_get_contents($path);
        self::assertNotFalse($content, $path . ' must be readable');

        return (string) $content;
    }

    private static function itemsJavaScriptSource(): string
    {
        return self::readSource('/../../app/pages/items.js.php');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function browserSessionCopyProvider(): array
    {
        $cases = [];
        foreach (self::BROWSER_SESSION_COPIES as $relativePath) {
            $cases[basename(dirname($relativePath, 2)) . '/' . basename($relativePath)] = [$relativePath];
        }

        return $cases;
    }

    public function testServerHighlightSettingsAreForcedIntoExistingBrowserStore(): void
    {
        $source = self::itemsJavaScriptSource();
        $favoritesUpdate = "app.highlightFavorites = parseInt(<?php echo (int) (\$SETTINGS['highlight_favorites'] ?? 0); ?>)";
        $selectedUpdate = "app.highlightSelected = parseInt(<?php echo (int) (\$SETTINGS['highlight_selected'] ?? 0); ?>)";

        $this->assertStringContainsString($favoritesUpdate, $source);
        $this->assertStringContainsString(
            $selectedUpdate,
            $source,
            'highlight_selected suffers the same stale-store bug and must be refreshed too.'
        );

        $treePosition = strpos($source, "\$('#jstree').jstree");
        $this->assertNotFalse($treePosition);

        foreach ([$favoritesUpdate, $selectedUpdate] as $settingUpdate) {
            $settingPosition = strpos($source, $settingUpdate);
            $this->assertNotFalse($settingPosition);
            $this->assertLessThan(
                $treePosition,
                $settingPosition,
                'The highlight settings must be applied before the first item-list trigger.'
            );
        }
    }

    public function testDeepLinkNavigationMergesIntoTheStoreInsteadOfReplacingIt(): void
    {
        $source = self::itemsJavaScriptSource();

        $this->assertStringNotContainsString(
            "store.set(\n            'teampassApplication', {",
            $source,
            'The group/id deep-link must not replace the whole teampassApplication store.'
        );
        $this->assertStringContainsString(
            "app.selectedItem = parseInt(queryDict['id'])",
            $source
        );
    }

    public function testFavoriteFlagAliasesAreNormalizedBeforeRendering(): void
    {
        $source = self::itemsJavaScriptSource();

        $this->assertStringContainsString(
            'value.is_favourited = parseInt(value.is_favourited ?? value.is_favorite ?? 0);',
            $source
        );
        $this->assertStringContainsString(
            "value.is_favourited === 1) ? ' bg-yellow' : ''",
            $source
        );
    }

    /**
     * browserSession('init') used to write a literal "key" property through a
     * jQuery .each() over a plain object, so the merge branch never created any
     * entry. Both shipped copies must keep the fixed implementation.
     *
     * @dataProvider browserSessionCopyProvider
     */
    public function testBrowserSessionMergeBranchCreatesMissingEntries(string $relativePath): void
    {
        $source = self::readSource($relativePath);

        $this->assertStringNotContainsString(
            'bSession.key = value;',
            $source,
            'The broken browserSession() merge branch must not come back.'
        );
        $this->assertStringContainsString(
            'Object.keys(data).forEach(function(key) {',
            $source
        );
        $this->assertStringContainsString(
            'if (bSession[key] === undefined) {',
            $source,
            'Existing store values must never be overwritten by browserSession().'
        );
    }

    /**
     * An empty foldersList means "not loaded yet" now that browserSession()
     * really seeds it, so a bare "!== undefined" check is no longer enough.
     */
    public function testFoldersListGuardsRejectAnEmptyCache(): void
    {
        $source = self::itemsJavaScriptSource();

        $this->assertSame(
            0,
            substr_count($source, "store.get('teampassApplication').foldersList === undefined"),
            'foldersList emptiness must be checked, not only its absence.'
        );
        $this->assertGreaterThanOrEqual(
            2,
            substr_count($source, 'cachedFolders.length > 0'),
            'Both displaySubfolders() guards must reject an empty cached folder list.'
        );
    }
}
