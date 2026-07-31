<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * @file      BackupListInteractionTest.php
 * @author    Teampass Community
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 */

use PHPUnit\Framework\TestCase;

class BackupListInteractionTest extends TestCase
{
    private string $javascript;
    private string $stylesheet;

    protected function setUp(): void
    {
        $javascript = file_get_contents(__DIR__ . '/../../app/pages/backups.js.php');
        $stylesheet = file_get_contents(__DIR__ . '/../../public/assets/css/teampass.css');

        $this->assertNotFalse($javascript);
        $this->assertNotFalse($stylesheet);

        $this->javascript = (string) $javascript;
        $this->stylesheet = (string) $stylesheet;
    }

    public function testDynamicBackupTooltipsUseOnlyTheDelegatedInitializer(): void
    {
        $this->assertStringContainsString(
            "selector: '[data-toggle=\"tooltip\"]'",
            $this->javascript
        );

        foreach ([
            "#onthefly-server-backups-tbody [data-toggle=\"tooltip\"]",
            "#scheduled-backups-tbody [data-toggle=\"tooltip\"]",
            "#externalized-backups-tbody [data-toggle=\"tooltip\"]",
        ] as $duplicateInitializer) {
            $this->assertStringNotContainsString(
                $duplicateInitializer,
                $this->javascript,
                'Dynamic backup tooltips must not be initialized both directly and by delegation.'
            );
        }
    }

    public function testTooltipsCannotCaptureThePointer(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.tooltip\s*\{[^}]*pointer-events\s*:\s*none\s*;/s',
            $this->stylesheet
        );
    }

    public function testOnTheFlyActionsDoNotBubbleToTheSelectableRow(): void
    {
        foreach ([
            '.onthefly-server-backup-delete',
            '.onthefly-server-backup-edit-comment',
        ] as $selector) {
            $handlerStart = strpos($this->javascript, "$(document).on('click', '" . $selector);
            $this->assertNotFalse($handlerStart);

            $handler = substr($this->javascript, (int) $handlerStart, 300);
            $this->assertStringContainsString('function (e)', $handler);
            $this->assertStringContainsString('e.preventDefault();', $handler);
            $this->assertStringContainsString('e.stopPropagation();', $handler);
        }
    }
}
