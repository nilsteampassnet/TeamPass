<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** TeamPass - GPL-3.0-or-later. Copyright 2009-2026 Teampass.net. */

/**
 * Sentinel: the web upgrade wizard really puts TeamPass in maintenance, then restores it.
 *
 * The opt-out checkbox was never posted and the query matched `intitule = ''`, so the wizard
 * never enabled maintenance: sign-in reopened after the first 3.2.x script of a multi-version
 * chain, since each one writes upgrade_timestamp.
 */
final class WizardMaintenanceModeTest extends TestCase
{
    private static function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    public function testStepTwoPostsTheOptOutCheckbox(): void
    {
        $wizard = self::read('public/install/upgrade.php');
        $stepTwo = substr($wizard, (int) strpos($wizard, "} else if (currentStep === 'step2') {"), 400);

        $this->assertStringContainsString("no_maintenance_mode : \$('#no_maintenance_mode').prop('checked') === true ? 1 : 0", $stepTwo);
    }

    public function testMaintenanceIsRestoredOnlyAfterTheFinalPermissionApproval(): void
    {
        $ajax = self::read('public/install/upgrade_ajax.php');
        $stepTwo = substr($ajax, (int) strpos($ajax, "case 'step2':"), (int) strpos($ajax, "case 'step3':") - (int) strpos($ajax, "case 'step2':"));
        $stepFive = substr($ajax, (int) strpos($ajax, "case 'step5':"));

        $this->assertStringNotContainsString("SET `valeur` = 'maintenance_mode'", $ajax);
        $this->assertStringContainsString("if ((int) \$post_no_maintenance_mode !== 1) {", $stepTwo);
        $this->assertStringContainsString("SET `valeur` = '1' WHERE type = 'admin' AND intitule = 'maintenance_mode'", $stepTwo);
        $this->assertStringContainsString("\$superGlobal->put('upgrade_previous_maintenance'", $stepTwo);

        $this->assertStringNotContainsString("\$superGlobal->get('upgrade_previous_maintenance', 'SESSION')", $stepFive);
        $finalization = self::read('public/install/permissions.php');
        $audit = strpos($finalization, 'tpSetupPermissionsAudit(TEAMPASS_ROOT)');
        $approval = strpos($finalization, 'tpSetupPermissionsApprove(');
        $restore = strpos($finalization, "DB::update(prefixTable('misc')");
        $this->assertIsInt($audit);
        $this->assertIsInt($approval);
        $this->assertIsInt($restore);
        $this->assertGreaterThan($audit, $approval);
        $this->assertGreaterThan($approval, $restore);
        $this->assertStringContainsString("\$previous === '0' || \$previous === '1'", $finalization);
    }
}
