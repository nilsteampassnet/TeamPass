<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net.
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;
use TeampassClasses\Language\Language;

require_once __DIR__ . '/../../app/vendor/sergeytsalkov/meekrodb/db.class.php';
require_once __DIR__ . '/../../app/sources/user_logs_logic.php';

final class UserLogsLogicTest extends TestCase
{
    public function testPasswordAccessIncludesItemExports(): void
    {
        self::assertContains('at_export', userLogsItemActionGroups()['passwords']);
    }

    public function testCalendarPresetsIncludeTodayAcrossDaylightSaving(): void
    {
        $zone = new DateTimeZone('Europe/Paris');
        $today = new DateTimeImmutable('2026-03-30', $zone);
        $nextDay = (new DateTimeImmutable('2026-03-31', $zone))->getTimestamp();

        self::assertSame([null, null], userLogsDateRange(['period' => 'all'], $today));
        self::assertSame([
            (new DateTimeImmutable('2026-03-24', $zone))->getTimestamp(), $nextDay,
        ], userLogsDateRange(['period' => '7'], $today));
        self::assertSame([
            (new DateTimeImmutable('2026-03-01', $zone))->getTimestamp(), $nextDay,
        ], userLogsDateRange(['period' => '30'], $today));
    }

    public function testCustomRangeIncludesTheWholeEndDateAndRejectsInvalidDates(): void
    {
        $original = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
        try {
            $today = new DateTimeImmutable('2026-03-29');
            $start = $today->getTimestamp();
            self::assertSame([$start, $start + 23 * 3600], userLogsDateRange([
                'period' => 'custom', 'date_from' => '2026-03-29', 'date_to' => '2026-03-29',
            ], $today));
            self::assertNull(userLogsDateRange([
                'period' => 'custom', 'date_from' => '2026-03-30', 'date_to' => '2026-03-29',
            ], $today));
            self::assertNull(userLogsDateRange([
                'period' => 'custom', 'date_from' => '2026-02-30', 'date_to' => '2026-03-29',
            ], $today));
        } finally {
            date_default_timezone_set($original);
        }
    }

    public function testSystemConnectionsAndItemActionsHaveDistinctDisplayFields(): void
    {
        $lang = new Language('french');
        $connection = ['is_item' => 0, 'type' => 'user_connection', 'label' => 'user_connection'];
        self::assertSame('Connexion', userLogsActivityText($connection, $lang));
        self::assertSame('', userLogsTargetText($connection));
        $connection['label'] = 'disconnect';
        self::assertSame('Déconnexion', userLogsActivityText($connection, $lang));

        $item = ['is_item' => 1, 'action' => 'at_shown', 'label' => 'Portail'];
        self::assertSame('Élément consulté', userLogsActivityText($item, $lang));
        $item['action'] = 'at_password_shown';
        self::assertSame('Mot de passe affiché', userLogsActivityText($item, $lang));
    }

    public function testOnlyKnownUserManagementPayloadsIdentifyAnAccount(): void
    {
        $row = ['is_item' => 0, 'type' => 'user_mngt', 'label' => 'at_user_pwd_changed', 'log_detail' => '42'];
        self::assertSame(42, userLogsAdminTargetId($row));
        self::assertSame(42, userLogsAdminTargetId(['label' => 'at_user_email_changed:old@example.test'] + $row));
        self::assertSame(42, userLogsAdminTargetId(['label' => 'at_restored'] + $row));
        self::assertNull(userLogsAdminTargetId(['type' => 'admin_action'] + $row));
        self::assertNull(userLogsAdminTargetId(['is_item' => 1] + $row));

        foreach (['at_api_session_revoked', 'at_extension_token_revoked', 'at_review_closed', 'at_user_updated', 'user_purged'] as $action) {
            self::assertNull(userLogsAdminTargetId(['label' => $action] + $row), $action);
        }
        foreach (['0', '-42', '42.5', '42 seconds', str_repeat('9', 40), true, [], null] as $detail) {
            self::assertNull(userLogsAdminTargetId(['log_detail' => $detail] + $row));
        }

        $credential = ['label' => 'at_device_credential_revoked'] + $row;
        self::assertSame(7, userLogsAdminTargetId(['log_detail' => '{"credential_id":42,"user_id":7}'] + $credential));
        self::assertNull(userLogsAdminTargetId(['log_detail' => '{"credential_id":42}'] + $credential));
        self::assertNull(userLogsAdminTargetId(['log_detail' => '{"user_id":true}'] + $credential));
        self::assertNull(userLogsAdminTargetId($credential));
    }

    public function testOnlyManageableTargetsCanBeResolvedAndOnlyOncePerPage(): void
    {
        $allowed = ['is_item' => 0, 'type' => 'user_mngt', 'label' => 'at_user_pwd_changed', 'log_detail' => '42'];
        $outsideScope = ['log_detail' => '43'] + $allowed;
        $missing = ['log_detail' => '44'] + $allowed;
        $otherNamespace = ['label' => 'at_api_session_revoked'] + $allowed;
        $checked = [];
        $ids = userLogsManageableAdminTargetIds(
            [$allowed, $outsideScope, $allowed, $otherNamespace, $missing],
            static function (int $id) use (&$checked): bool {
                $checked[] = $id;

                return $id !== 43;
            }
        );
        self::assertSame([42, 43, 44], $checked);
        self::assertSame([42, 44], $ids);

        $logins = [42 => userLogsAccountTargetText('R&amp;D O&#039;Brien &quot;QA&quot; &lt;test&gt;', 0, new Language('english'))];
        self::assertSame('R&D O\'Brien "QA" <test>', userLogsTargetText($allowed, $logins));
        self::assertSame('', userLogsTargetText($outsideScope, $logins));
        self::assertSame('', userLogsTargetText($missing, $logins));
        self::assertSame('', userLogsTargetText($otherNamespace, $logins));
        self::assertSame('', userLogsTargetText($allowed));
    }

    public function testSoftDeletedTargetShowsOriginalLoginAndLocalizedMarker(): void
    {
        $english = new Language('english');
        $french = new Language('french');
        $storedLogin = 'R&amp;D O&#039;Brien &quot;QA&quot; &lt;test&gt;_deleted_1700000000';
        self::assertSame('R&D O\'Brien "QA" <test> (deleted)', userLogsAccountTargetText($storedLogin, 1700000000, $english));
        self::assertSame('R&D O\'Brien "QA" <test> (supprimé)', userLogsAccountTargetText($storedLogin, 1700000000, $french));
        self::assertSame('R&D O\'Brien "QA" <test>_deleted_1700000000', userLogsAccountTargetText($storedLogin, 0, $english));
        self::assertSame('test_deleted_1700000000 (deleted)', userLogsAccountTargetText('test_deleted_1700000000_deleted_1700000000', 1700000000, $english));
        self::assertSame('legacy_deleted_1600000000 (deleted)', userLogsAccountTargetText('legacy_deleted_1600000000', 1700000000, $english));
    }

    /** A translated marker stored with entities reads as text, like the other activity labels. */
    public function testDeletedMarkerStoredWithEntitiesIsDecoded(): void
    {
        $lang = $this->createMock(Language::class);
        $lang->method('getShipped')->willReturn('{login} (supprim&eacute;)');

        self::assertSame(
            'R&D (supprimé)',
            userLogsAccountTargetText('R&amp;D_deleted_1700000000', 1700000000, $lang)
        );
    }

    public function testUserManagementActivityLabelsOmitPlaceholdersAndOldEmailAddresses(): void
    {
        $english = new Language('english');
        $french = new Language('french');
        $row = ['is_item' => 0, 'type' => 'user_mngt', 'label' => 'at_user_pwd_changed', 'log_detail' => '42'];
        self::assertSame('User password changed', userLogsActivityText($row, $english));
        self::assertSame('Mot de passe de l’utilisateur modifié', userLogsActivityText($row, $french));
        $email = ['label' => 'at_user_email_changed:old@example.test'] + $row;
        self::assertSame('User email changed', userLogsActivityText($email, $english));
        self::assertSame('Adresse e-mail de l’utilisateur modifiée', userLogsActivityText($email, $french));
        self::assertSame('User locked', userLogsActivityText(['label' => 'at_user_locked'] + $row, $english));
        self::assertSame(
            'User password recovery requested',
            userLogsActivityText(['label' => 'at_user_forgot_local_password_requested'] + $row, $english)
        );
    }

    public function testAdministrationEventsUseLocalizedLabelsWithoutLosingTheirDetails(): void
    {
        $events = [
            'dataBase backup' => ['Database backed up', 'Base de données sauvegardée'],
            'dataBase restore' => ['Database restored', 'Base de données restaurée'],
            'folder_deletion_protection_enabled' => [
                'Folder deletion protection enabled',
                'Protection contre la suppression du dossier activée',
            ],
            'folder_deletion_protection_disabled' => [
                'Folder deletion protection disabled',
                'Protection contre la suppression du dossier désactivée',
            ],
            'at_email_template_updated:send_pw:en' => ['Email template updated send_pw (en)', 'Modèle d’e-mail modifié send_pw (en)'],
            'restore_missing_sharekeys: items: 3 key(s) created/rebuilt' => [
                'Sharekey repair completed: items: 3 key(s) created/rebuilt',
                'Réparation des clés de partage terminée: items: 3 key(s) created/rebuilt',
            ],
            'historical_event: unknown' => ['historical_event: unknown', 'historical_event: unknown'],
        ];
        foreach (['english', 'french'] as $index => $locale) {
            $lang = new Language($locale);
            foreach ($events as $label => $expected) {
                $row = ['is_item' => 0, 'type' => 'admin_action', 'label' => $label];
                self::assertSame($expected[$index], formatAdminLogLabel($label, $lang));
                self::assertSame($expected[$index], userLogsActivityText($row, $lang));
            }
        }

        $row = ['is_item' => 0, 'type' => 'user_mngt', 'label' => 'at_extension_token_revoked', 'log_detail' => '42'];
        self::assertSame('Jeton de l’extension de navigateur révoqué', userLogsActivityText($row, new Language('french')));
        self::assertSame('', userLogsTargetText($row, [42 => 'fixture-account']));
    }

    public function testSearchMatchesTheLocalizedAdministrationActivity(): void
    {
        $lang = new Language('french');
        $rows = [
            ['is_item' => 0, 'type' => 'admin_action', 'label' => 'dataBase restore'],
            ['is_item' => 0, 'type' => 'admin_action', 'label' => 'folder_deletion_protection_enabled'],
        ];
        $normalizers = [
            'activity' => static fn (string $value, array $entry): string => userLogsActivityText($entry, $lang),
        ];
        $page = storedTextSearchPage($rows, ['données restaurée'], ['activity'], 0, 25, null, $normalizers);
        self::assertSame(1, $page['total']);
        self::assertSame([$rows[0]], $page['rows']);
        $page = storedTextSearchPage($rows, ['folder_deletion_protection_enabled'], ['activity'], 0, 25, null, $normalizers);
        self::assertSame(0, $page['total']);
    }

    public function testSearchUsesDisplayedTextAndCountsBeforePaging(): void
    {
        $lang = new Language('french');
        $row = [
            'is_item' => 1, 'action' => 'at_shown', 'label' => 'R&amp;D O&#039;Brien &quot;test&quot; &lt;QA&gt;',
        ];
        $normalizers = [
            'activity' => static fn (string $value, array $entry): string => userLogsActivityText($entry, $lang),
            'target' => static fn (string $value, array $entry): string => userLogsTargetText($entry),
        ];
        self::assertSame('R&D O\'Brien "test" <QA>', userLogsTargetText($row));
        $rows = [$row + ['id' => 3], $row + ['id' => 2], $row + ['id' => 1]];
        foreach (['R&D', "O'Brien", '"test"', '<QA>', 'élément consulté'] as $term) {
            $page = storedTextSearchPage($rows, [$term], ['activity', 'target'], 1, 1, null, $normalizers);
            self::assertSame(3, $page['total']);
            self::assertSame([2], array_column($page['rows'], 'id'));
        }
        foreach (['amp', 'quot', 'at_shown'] as $term) {
            $page = storedTextSearchPage($rows, [$term], ['activity', 'target'], 0, 25, null, $normalizers);
            self::assertSame(0, $page['total']);
        }
    }
}
