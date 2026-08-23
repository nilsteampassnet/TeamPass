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
     * Read one repository file as a string.
     *
     * @param string $relativePath Path relative to the repository root
     *
     * @return string File content
     */
    private function readRepositoryFile(string $relativePath): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
    }

    /**
     * The targeted application pages must not return to alert(), confirm(), or prompt().
     *
     * @return void
     */
    public function testTargetedPagesDoNotUseBrowserNativeDialogs(): void
    {
        $nativeDialogPattern = '/(?<![\w$.])(?:(?:window|globalThis|self)\s*\.\s*)?(?:alert|confirm|prompt)\s*\(/';

        foreach (array(
            'app/pages/profile.js.php',
            'app/pages/kb.js.php',
            'app/pages/admin.js.php',
            'app/pages/tools.js.php',
        ) as $relativePath) {
            self::assertDoesNotMatchRegularExpression(
                $nativeDialogPattern,
                $this->readRepositoryFile($relativePath),
                $relativePath . ' must use the TeamPass modal UI'
            );
        }
    }

    /**
     * Each page that lost a native dialog must confirm through the TeamPass modal instead.
     *
     * @return void
     */
    public function testReplacementFlowsUseTheTeamPassModalUi(): void
    {
        $profile = $this->readRepositoryFile('app/pages/profile.js.php');
        $knowledgeBase = $this->readRepositoryFile('app/pages/kb.js.php');
        $admin = $this->readRepositoryFile('app/pages/admin.js.php');
        $tools = $this->readRepositoryFile('app/pages/tools.js.php');

        self::assertGreaterThanOrEqual(3, substr_count($profile, 'launchConfirmDialog('));
        self::assertGreaterThanOrEqual(3, substr_count($knowledgeBase, 'launchConfirmDialog('));
        self::assertStringContainsString('tpDeleteUnknownFiles', $admin);
        self::assertGreaterThanOrEqual(1, substr_count($tools, 'launchConfirmDialog('));
    }

    /**
     * The confirmation modal must release its callback when it closes.
     *
     * Cancelling used to leave the handler bound on the shared action button, so the next
     * flow reusing that modal fired both callbacks.
     *
     * @return void
     */
    public function testConfirmDialogReleasesItsCallbackOnClose(): void
    {
        foreach (array(
            'app/includes/js/functions.js',
            'public/assets/js/functions.js',
        ) as $relativePath) {
            $source = $this->readRepositoryFile($relativePath);

            self::assertStringContainsString(
                "click.tpConfirmDialog",
                $source,
                $relativePath . ' must namespace the confirm callback'
            );
            self::assertStringContainsString(
                "hidden.bs.modal.tpConfirmDialog",
                $source,
                $relativePath . ' must unbind the confirm callback when the modal closes'
            );
        }
    }

    /**
     * The backup selection guard must test emptiness, not the number zero.
     *
     * An empty <select> yields null and a populated one a 32-char operation code, so the
     * former comparison against 0 could never be true and the error was unreachable.
     *
     * @return void
     */
    public function testBackupSelectionGuardIsReachable(): void
    {
        $tools = $this->readRepositoryFile('app/pages/tools.js.php');

        self::assertStringNotContainsString(
            "\$('#restore_items_master_keys_id').val() === 0",
            $tools
        );
        self::assertSame(
            3,
            substr_count($tools, "if (!\$('#restore_items_master_keys_id').val()) {")
        );
    }

    /**
     * Both messages the tools page shows for backups must come from the language catalog.
     *
     * @return void
     */
    public function testBackupMessagesAreTranslatedInEnglishAndFrench(): void
    {
        $root = dirname(__DIR__, 2);
        $english = include $root . '/app/includes/language/english.php';
        $french = include $root . '/app/includes/language/french.php';

        foreach (array('tools_delete_backup_confirm', 'tools_no_backup_selected') as $key) {
            self::assertArrayHasKey($key, $english);
            self::assertNotSame('', trim((string) $english[$key]));
            self::assertArrayHasKey($key, $french);
            self::assertNotSame('', trim((string) $french[$key]));
        }

        $tools = $this->readRepositoryFile('app/pages/tools.js.php');
        self::assertStringNotContainsString('Are you sure you want to delete this backup file?', $tools);
        self::assertStringNotContainsString('You need to select a backup file', $tools);
    }

    /**
     * The database restore page must keep its beforeunload navigation guard.
     *
     * It is not a dialog the sweep should have removed, so assert the guard is still
     * installed without pinning the exact spacing of the statement.
     *
     * @return void
     */
    public function testDatabaseRestoreNavigationGuardRemainsUnchanged(): void
    {
        self::assertMatchesRegularExpression(
            '/window\s*\.\s*onbeforeunload\s*=\s*function\s*\(\s*\)\s*\{\s*return\s+\'\'\s*;\s*\}/',
            $this->readRepositoryFile('app/pages/backups.js.php')
        );
    }
}
