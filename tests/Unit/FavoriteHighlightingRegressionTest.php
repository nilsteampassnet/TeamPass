<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Sentinel tests for favorite highlighting on the initial item-list render.
 */
class FavoriteHighlightingRegressionTest extends TestCase
{
    private static function itemsJavaScriptSource(): string
    {
        $path = __DIR__ . '/../../app/pages/items.js.php';
        $content = file_get_contents($path);
        self::assertNotFalse($content, $path . ' must be readable');

        return (string) $content;
    }

    public function testServerHighlightSettingIsForcedIntoExistingBrowserStore(): void
    {
        $source = self::itemsJavaScriptSource();
        $settingUpdate = "app.highlightFavorites = parseInt(<?php echo \$SETTINGS['highlight_favorites']; ?>)";

        $this->assertStringContainsString(
            $settingUpdate,
            $source
        );

        $settingPosition = strpos($source, $settingUpdate);
        $treePosition = strpos($source, "$('#jstree').jstree");
        $this->assertNotFalse($settingPosition);
        $this->assertNotFalse($treePosition);
        $this->assertLessThan(
            $treePosition,
            $settingPosition,
            'The favorite highlight setting must be applied before the first item-list trigger.'
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
}
