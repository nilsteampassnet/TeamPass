<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 * TeamPass is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See <https://www.gnu.org/licenses/> for the GNU General Public License.
 * @copyright 2009-2026 Teampass.net
 * @license GPL-3.0
 */

use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../../app/sources/log_display_logic.php';

/** Exercise the DB-free formatter and the existing safe display boundary. */
class SecureSendRetentionLogDisplayTest extends TestCase
{
    public function testBothCatalogsDescribeTheBatchUsingTheInstanceDateFormat(): void
    {
        $previousTimezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
        try {
            $summary = ['retention_days' => 30, 'cutoff' => 1791720000, 'deleted_count' => 1000, 'batch_limit' => 1000];
            $json = json_encode($summary, JSON_THROW_ON_ERROR);
            foreach (['english', 'french'] as $language) {
                $lang = new Language($language, __DIR__ . '/../../app/includes/language');
                foreach ([[], ['date_format' => 'd/m/Y', 'time_format' => 'H:i'],
                    ['date_format' => 'm/d/Y', 'time_format' => 'g:i a']] as $settings) {
                    $text = formatSecureSendRetentionSummary($json, $lang, $settings);
                    self::assertStringContainsString('1000', $text);
                    self::assertStringContainsString('30', $text);
                    self::assertStringContainsString(date(($settings['date_format'] ?? 'Y-m-d') . ' '
                        . ($settings['time_format'] ?? 'H:i:s'), $summary['cutoff']), $text);
                    self::assertStringNotContainsString('{', $text);
                    self::assertStringNotContainsString('retention_days', $text);
                    self::assertStringContainsString($language === 'english' ? 'audit event(s) deleted' : 'événement(s) d’audit supprimé(s)', $text);
                }
            }
        } finally {
            date_default_timezone_set($previousTimezone);
        }
    }

    public function testMalformedAndUnrecognizedSummariesRemainVisibleAndEscaped(): void
    {
        $lang = new Language('english', __DIR__ . '/../../app/includes/language');
        $summary = ['retention_days' => 30, 'cutoff' => 100, 'deleted_count' => 1, 'batch_limit' => 1000];
        $invalid = ['', '{broken', 'null', '[]', '"old summary"', '{"retention_days":30}'];
        foreach (['retention_days' => [0, 36501, '30'], 'cutoff' => [-1, [], null],
            'deleted_count' => [0, 1001, true], 'batch_limit' => [0, 1001, 1.5]] as $field => $values) {
            foreach ($values as $value) {
                $invalid[] = json_encode(array_replace($summary, [$field => $value]), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
            }
        }
        $invalid[] = '<img src=x onerror=alert(1)> R&amp;D O&#039;Brien &quot;name&quot;';
        foreach ($invalid as $details) {
            $text = formatSecureSendRetentionSummary($details, $lang, []);
            self::assertSame($details, $text);
            self::assertStringNotContainsString('<img', normalizeLogDisplayValue($text));
        }
    }

    public function testAdministrationHandlerFormatsOnlyTheRetentionLabelBeforeEscaping(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../../app/sources/logs.datatables.php');
        self::assertStringContainsString("if (\$label === 'secure_send_audit_retention_purge')", $source);
        self::assertStringContainsString('normalizeLogDisplayValue(formatSecureSendRetentionSummary(', $source);
        self::assertStringContainsString("(string) (\$record['field_1'] ?? ''), \$lang, \$SETTINGS", $source);
        self::assertStringContainsString("\$target = normalizeLogDisplayValue(\$field1);", $source);
    }
}
