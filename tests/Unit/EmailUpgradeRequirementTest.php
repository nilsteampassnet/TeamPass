<?php

declare(strict_types=1);

/**
 * Teampass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License, version 3 or later.
 * See <https://www.gnu.org/licenses/>.
 */

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/app/config/include.php';
require_once dirname(__DIR__, 2) . '/app/sources/upgrade_cli_logic.php';

final class EmailUpgradeRequirementTest extends TestCase
{
    private const RELEASE_3242_TIMESTAMP = 1794126505;

    /** A fresh installation of the shipped 3.2.4.2 must not bypass the email migration. */
    public function testEmailSchemaFloorIsNewerThanTheShippedRelease(): void
    {
        self::assertGreaterThan(self::RELEASE_3242_TIMESTAMP, (int) UPGRADE_MIN_DATE);
    }

    /** An already current 3.2.4.2 installation still needs the new email tables and queue columns. */
    public function testPreviouslyCurrentReleaseRequiresTheEmailSchema(): void
    {
        $state = ['db_version' => TP_VERSION, 'db_version_full' => TP_VERSION . '.' . TP_VERSION_MINOR,
            'db_schema_level' => '1792993330', 'upgrade_timestamp' => self::RELEASE_3242_TIMESTAMP + 3600,
            'files_version' => TP_VERSION, 'files_version_full' => TP_VERSION . '.' . TP_VERSION_MINOR,
            'min_date' => (int) UPGRADE_MIN_DATE];
        self::assertSame(['decision' => 'upgrade', 'reason' => 'SCHEMA_BEHIND'], tpUpgradeCliDecide($state));
        $state['db_schema_level'] = (string) UPGRADE_MIN_DATE;
        $state['upgrade_timestamp'] = (int) UPGRADE_MIN_DATE;
        self::assertSame('up_to_date', tpUpgradeCliDecide($state)['decision']);
    }
}
