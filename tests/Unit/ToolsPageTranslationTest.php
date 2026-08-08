<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      ToolsPageTranslationTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

class ToolsPageTranslationTest extends TestCase
{
    public function testEveryStaticToolsTranslationExistsInEnglishAndFrench(): void
    {
        $root = __DIR__ . '/../..';
        $source = (string) file_get_contents($root . '/app/pages/tools.php')
            . (string) file_get_contents($root . '/app/pages/tools.js.php');
        $english = include $root . '/app/includes/language/english.php';
        $french = include $root . '/app/includes/language/french.php';

        preg_match_all('/\$lang->get\(\x27([^\x27]+)\x27\)/', $source, $matches);
        $keys = array_values(array_unique($matches[1] ?? array()));

        self::assertNotEmpty($keys);
        foreach ($keys as $key) {
            self::assertArrayHasKey($key, $english, 'english: ' . $key);
            self::assertArrayHasKey($key, $french, 'french: ' . $key);
            self::assertNotSame('', trim((string) $french[$key]), 'french: ' . $key);
        }
    }

    public function testOtpToolsDoNotContainHardcodedEnglishLabels(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/pages/tools.php');

        foreach (array(
            'No user PSK exists in DB',
            'Personal Folder disabled for user',
            'Fix items are empty after user OTP change',
            'Select username that has access to all items',
            'Backup date:',
            '>Restore keys<',
            '>Delete backup<',
        ) as $hardcodedText) {
            self::assertStringNotContainsString($hardcodedText, $source);
        }
    }
}
