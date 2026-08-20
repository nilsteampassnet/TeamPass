<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      NativeModalUsageTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

class NativeModalUsageTest extends TestCase
{
    /**
     * The targeted application pages must not return to alert(), confirm(), or prompt().
     */
    public function testTargetedPagesDoNotUseBrowserNativeDialogs(): void
    {
        $root = dirname(__DIR__, 2);
        $nativeDialogPattern = '/(?<![\w$.])(?:(?:window|globalThis|self)\s*\.\s*)?(?:alert|confirm|prompt)\s*\(/';

        foreach (array(
            'app/pages/profile.js.php',
            'app/pages/kb.js.php',
            'app/pages/admin.js.php',
            'app/pages/tools.js.php',
        ) as $relativePath) {
            $source = (string) file_get_contents($root . '/' . $relativePath);

            self::assertDoesNotMatchRegularExpression(
                $nativeDialogPattern,
                $source,
                $relativePath . ' must use the TeamPass modal UI'
            );
        }
    }

    public function testReplacementFlowsUseTheTeamPassModalUi(): void
    {
        $root = dirname(__DIR__, 2);
        $profile = (string) file_get_contents($root . '/app/pages/profile.js.php');
        $knowledgeBase = (string) file_get_contents($root . '/app/pages/kb.js.php');
        $admin = (string) file_get_contents($root . '/app/pages/admin.js.php');
        $tools = (string) file_get_contents($root . '/app/pages/tools.js.php');

        self::assertGreaterThanOrEqual(3, substr_count($profile, 'launchConfirmDialog('));
        self::assertGreaterThanOrEqual(3, substr_count($knowledgeBase, 'launchConfirmDialog('));
        self::assertStringContainsString('tpDeleteUnknownFiles', $admin);
        self::assertGreaterThanOrEqual(1, substr_count($tools, 'launchConfirmDialog('));
        self::assertStringNotContainsString('Are you sure you want to delete this backup file?', $tools);
    }

    public function testBackupDeletionConfirmationIsTranslatedInEnglishAndFrench(): void
    {
        $root = dirname(__DIR__, 2);
        $english = include $root . '/app/includes/language/english.php';
        $french = include $root . '/app/includes/language/french.php';

        self::assertArrayHasKey('tools_delete_backup_confirm', $english);
        self::assertNotSame('', trim((string) $english['tools_delete_backup_confirm']));
        self::assertArrayHasKey('tools_delete_backup_confirm', $french);
        self::assertNotSame('', trim((string) $french['tools_delete_backup_confirm']));
    }

    public function testDatabaseRestoreNavigationGuardRemainsUnchanged(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/backups.js.php');

        self::assertStringContainsString(
            "window.onbeforeunload = function() { return ''; };",
            $source
        );
    }
}
