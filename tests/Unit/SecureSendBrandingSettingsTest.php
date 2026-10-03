<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This file is part of the TeamPass project.
 *
 * TeamPass is free software: you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by
 * the Free Software Foundation, version 3 of the License.
 *
 * TeamPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * Certain components of this file may be under different licenses. For
 * details, see the `licenses` directory or individual file headers.
 * ---
 * @file      SecureSendBrandingSettingsTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

class SecureSendBrandingSettingsTest extends TestCase
{
    /** Fresh and upgraded installations seed public identity with privacy-safe upgrade defaults. */
    public function testPublicIdentitySettingsAreSeededForEveryInstallationPath(): void
    {
        $fresh = (string) file_get_contents(__DIR__ . '/../../public/install/install-steps/run.step5.php');
        $upgrade = (string) file_get_contents(__DIR__ . '/../../public/install/upgrade_run_3.2.2.php');
        $previousUpgrade = (string) file_get_contents(__DIR__ . '/../../public/install/upgrade_run_3.2.1.php');

        self::assertStringContainsString("array('admin', 'public_entity_name', '')", $fresh);
        self::assertStringContainsString("array('admin', 'secure_send_show_sender_name', '1')", $fresh);
        self::assertStringContainsString("'public_entity_name', '')", $upgrade);
        self::assertStringContainsString("'secure_send_show_sender_name', '0')", $upgrade);
        self::assertStringNotContainsString("'public_entity_name'", $previousUpgrade);
    }

    /** The setting stays a single bounded plain-text field next to the existing branding. */
    public function testOptionsExposeOneBoundedPublicEntityField(): void
    {
        $options = (string) file_get_contents(__DIR__ . '/../../app/pages/options.php');
        self::assertSame(1, substr_count($options, "id='public_entity_name'"));
        self::assertStringContainsString("maxlength='100'", $options);
        self::assertLessThan(strpos($options, "id='custom_logo'"), strpos($options, "id='public_entity_name'"));
        self::assertSame(1, substr_count($options, "id='secure_send_show_sender_name'"));
        self::assertStringContainsString("id='secure_send_show_sender_name_input'", $options);
    }

    /** Saving the public name passes through the shared normalizer before storage. */
    public function testAdminSaveNormalizesThePublicEntityName(): void
    {
        $admin = (string) file_get_contents(__DIR__ . '/../../app/sources/admin.queries.php');
        self::assertStringContainsString("\$post_field === 'public_entity_name'", $admin);
        self::assertStringContainsString('brandingPublicEntityName((string) $post_value)', $admin);
    }

    /** The recipient view uses safe branding and never renders authentication identifiers. */
    public function testRecipientPageUsesPublicBrandingWithoutLoginOrEmail(): void
    {
        $page = (string) file_get_contents(__DIR__ . '/../../app/core/otv.php');
        self::assertStringContainsString('brandingSecureSendLogoUrl(', $page);
        self::assertStringContainsString("\$sender['display_name']", $page);
        self::assertStringNotContainsString("\$sender['login']", $page);
        self::assertStringNotContainsString("\$sender['email']", $page);
        self::assertStringContainsString("\$escape(\$lang->get('secure_send_powered_by'))", $page);
        self::assertStringContainsString("'#TEAMPASS#'", $page);
        self::assertStringContainsString("'<strong>TeamPass</strong>'", $page);
        self::assertStringContainsString("\$lang->get('secure_send_remaining_views_count')", $page);
        self::assertStringContainsString("if (\$token !== '' && \$parameters !== null)", $page);
        self::assertStringContainsString('fa-solid fa-user', $page);
        self::assertStringNotContainsString('fa-user-check', $page);
        self::assertStringNotContainsString('secure_send_authenticated_account', $page);
        self::assertStringContainsString('rel="noopener noreferrer"', $page);
    }

    /** The creation modal tells the sender exactly which non-sensitive identity is public. */
    public function testCreationModalPreviewsThePublicDisplayNameWithoutLoginFallback(): void
    {
        $items = (string) file_get_contents(__DIR__ . '/../../app/pages/items.php');
        self::assertStringContainsString("\$session->get('user-name')", $items);
        self::assertStringContainsString("\$session->get('user-lastname')", $items);
        self::assertStringContainsString("secure_send_public_identity_notice", $items);
        self::assertStringContainsString("secure_send_public_identity_notice_entity", $items);
        self::assertStringContainsString("secure_send_public_identity_disabled", $items);
        self::assertStringContainsString('html_entity_decode(', $items);
        self::assertStringNotContainsString("\$session->get('user-login')", $items);
    }
}
