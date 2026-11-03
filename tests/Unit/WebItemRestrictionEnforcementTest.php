<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Static guards for the per-item restriction on the web paths that hand out item secrets:
 * GHSA-75qw-h7p8-ppr3 (history, TOTP code), GHSA-jxj9-mcx4-h34j (attachment preview) and
 * GHSA-h66m-8w9q-86r2 (role-only restrictions: details step 2, exports, item creation).
 *
 * accessToItemIsGranted() only checks that the item's folder is visible: it ignores the item's
 * own restriction (restricted_to + restriction_to_roles) and lets administrators through on every
 * shared folder. A path that releases a secret must require the same read access as the password
 * itself — getCurrentAccessRights() — or apply the canonical restriction decision.
 */
class WebItemRestrictionEnforcementTest extends TestCase
{
    private function source(string $file): string
    {
        $path = __DIR__ . '/../../app/sources/' . $file;
        self::assertFileExists($path);
        $content = file_get_contents($path);
        self::assertIsString($content);

        return $content;
    }

    /**
     * Returns the part of $file between two markers.
     */
    private function block(string $file, string $startMarker, string $endMarker): string
    {
        $content = $this->source($file);
        $start = strpos($content, $startMarker);
        self::assertNotFalse($start, 'Marker not found: ' . $startMarker);
        $end = strpos($content, $endMarker, $start + strlen($startMarker));
        self::assertNotFalse($end, 'Marker not found: ' . $endMarker);

        return substr($content, $start, $end - $start);
    }

    public function testItemHistoryRequiresReadAccessToTheItem(): void
    {
        $block = $this->block('items.queries.php', "case 'load_item_history':", '// get item history');

        self::assertMatchesRegularExpression(
            '/getCurrentAccessRights\(\s*\(int\) \$session->get\(\'user-id\'\),\s*\(int\) \$inputData\[\'itemId\'\],'
            . '\s*\(int\) \$dataItem\[\'id_tree\'\]\s*\)\[\'access\'\] !== true/',
            $block,
            'Previous passwords are decrypted with the instance key: the restriction must be checked first.'
        );
        self::assertStringNotContainsString(
            'accessToItemIsGranted(',
            $block,
            'Folder visibility ignores the item restriction and admits administrators.'
        );
    }

    public function testOtpCodeRequiresReadAccessToTheItem(): void
    {
        $block = $this->block('items.queries.php', "case 'show_opt_code':", 'cryption(');

        self::assertMatchesRegularExpression(
            '/getCurrentAccessRights\(\s*\(int\) \$session->get\(\'user-id\'\),\s*\(int\) \$inputData\[\'id\'\],'
            . '\s*\(int\) \$otpItemTreeId\s*\)\[\'access\'\] !== true/',
            $block
        );
        self::assertStringNotContainsString('accessToItemIsGranted(', $block);
        self::assertStringContainsString(
            "(int) \$dataItem['enabled'] === 1",
            $block,
            'A disabled OTP must not be served (same rule as the REST API).'
        );
    }

    public function testItemDetailsStepTwoAppliesRoleOnlyRestrictions(): void
    {
        $block = $this->block('items.queries.php', "case 'showDetailsStep2':", '// check user is admin');

        self::assertStringContainsString(
            "\$restrictionActive = getItemRestrictedUsersList((int) \$inputData['id'], (int) \$session->get('user-id')) === false;",
            $block,
            'The step-2 payload carries the TOTP seed: it must use the canonical restriction decision.'
        );
        self::assertStringNotContainsString(
            "if (empty(\$dataItem['restricted_to'])) {",
            $block,
            'Clearing the restriction whenever restricted_to is empty ignores role-only restrictions.'
        );
    }

    public function testImagePreviewRequiresAccessToTheFile(): void
    {
        $block = $this->block('items.queries.php', "case 'image_preview_preparation':", 'decryptFile(');

        self::assertMatchesRegularExpression(
            '/if \(userHasAccessToFile\(\(int\) \$session->get\(\'user-id\'\), \(int\) \$inputData\[\'id\'\]\) === false\) \{'
            . '.*?break;/s',
            $block,
            'A sharekey is not an authorization: shared attachments are keyed for every user.'
        );
    }

    public function testNewItemAttachesRoleRestrictionsToTheCreatedItem(): void
    {
        $block = $this->block('items.queries.php', "case 'new_item':", "case 'update_item':");
        $roles = $this->block('items.queries.php', '// Manage retriction_to_roles', '// log');
        self::assertStringContainsString($roles, $block, 'The first role block must belong to new_item.');

        self::assertStringContainsString("'item_id' => (int) \$newID,", $roles);
        self::assertStringNotContainsString(
            "\$inputData['itemId']",
            $roles,
            'A client-supplied item id lets a user add a role restriction to any item.'
        );
        self::assertStringNotContainsString(
            'if (count($role) > 1) {',
            $roles,
            'The form posts plain role ids: count() on a string is a TypeError.'
        );
    }

    public function testEveryExportAppliesTheItemRestrictionWithRoles(): void
    {
        $cases = [
            "case 'export_to_xml_format':" => "case 'export_to_csv_format':",
            "case 'export_to_csv_format':" => "case 'clean_export_table':",
            "case 'export_prepare_data':" => "case 'finalize_export_pdf':",
            "case 'export_to_html_format':" => 'function exportItemsRestrictedRoles(',
        ];
        foreach ($cases as $start => $end) {
            $block = $this->block('export.queries.php', $start, $end);
            self::assertStringContainsString(
                'exportItemsRestrictedRoles(array_column($rows, \'id\'))',
                $block,
                $start . ' must load the role restrictions of the exported items.'
            );
            self::assertStringContainsString(
                'exportItemRestrictionAllows($record, $itemsRestrictedRoles)',
                $block,
                $start . ' must apply the restriction with its roles.'
            );
            self::assertStringNotContainsString(
                "explode(';', \$record['restricted_to'])",
                $block,
                $start . ' must not decide on restricted_to alone.'
            );
        }

        self::assertStringContainsString(
            'return itemRestrictionAllows(',
            $this->block('export.queries.php', 'function exportItemRestrictionAllows(', "\n}\n")
        );
    }

    public function testExportHandlerEnforcesTheExportPermission(): void
    {
        $preamble = $this->block('export.queries.php', 'require_once \'main.functions.php\';', 'switch ($post_type) {');

        self::assertMatchesRegularExpression(
            '/if \(exportAllowedForUser\(\s*\$SETTINGS,\s*\(string\) \$session->get\(\'user-roles\'\),'
            . '\s*\(int\) \$session->get\(\'user-admin\'\) === 1\s*\) === false\) \{.*?exit;/s',
            $preamble,
            'The page check alone is bypassed by posting to the handler.'
        );
    }
}
