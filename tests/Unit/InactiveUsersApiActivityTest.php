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
 * @file      InactiveUsersApiActivityTest.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use PHPUnit\Framework\TestCase;

/**
 * Static regression guards for inactive-user management and API activity.
 */
class InactiveUsersApiActivityTest extends TestCase
{
    private function readSource(string $relativePath): string
    {
        $path = __DIR__ . '/../..' . $relativePath;
        self::assertFileExists($path, "Source file '$relativePath' not found");
        $content = file_get_contents($path);
        self::assertIsString($content);
        return $content;
    }

    public function testFunctionalApiActivityIsMarkedFromItemLogs(): void
    {
        $src = $this->readSource('/app/sources/main.functions.php');

        self::assertStringContainsString('function markUserFunctionalActivity(', $src);
        self::assertStringContainsString('function teampassApiFunctionalActivityActions(): array', $src);
        self::assertStringContainsString("'at_shown', 'at_creation', 'at_modification', 'at_delete', 'at_import'", $src);
        self::assertStringContainsString("markUserFunctionalActivity(\$id_user, \$eventTime);", $src);
        self::assertStringContainsString("\$updateData['last_connexion'] = (string) \$timestamp;", $src);
        self::assertStringContainsString("\$updateData['inactivity_warned_at'] = null;", $src);
    }

    public function testTechnicalApiSessionEventsDoNotMarkFunctionalActivity(): void
    {
        $authModel = $this->readSource('/app/api/Model/AuthModel.php');
        $apiIndex = $this->readSource('/app/api/index.php');
        $miscController = $this->readSource('/app/api/Controller/Api/MiscController.php');

        self::assertStringNotContainsString('markUserFunctionalActivity', $authModel);
        self::assertStringNotContainsString('markApiFunctionalActivity', $authModel);
        self::assertStringNotContainsString('markApiFunctionalActivity', $apiIndex);
        self::assertStringNotContainsString('markApiFunctionalActivity', $miscController);
    }

    public function testSearchAndOtpEndpointsMarkOnlyFunctionalActivity(): void
    {
        $baseController = $this->readSource('/app/api/Controller/Api/BaseController.php');
        $itemController = $this->readSource('/app/api/Controller/Api/ItemController.php');

        self::assertStringContainsString('protected function markApiFunctionalActivity(array $userData): void', $baseController);
        self::assertStringContainsString('if (count($ret) > 0) {', $itemController);
        self::assertStringContainsString('$this->markApiFunctionalActivity($userData);', $itemController);
        self::assertStringContainsString("'otp_code' => \$otpCode", $itemController);
    }

    public function testInactiveWorkerUsesFunctionalApiLogsBeforeWarningOrAction(): void
    {
        $worker = $this->readSource('/app/scripts/background_tasks___worker.php');

        self::assertStringContainsString('getApiFunctionalActivityByUser(', $worker);
        self::assertStringContainsString('teampassApiFunctionalActivityActions()', $worker);
        self::assertStringContainsString('raison LIKE %ss', $worker);
        self::assertStringContainsString('$lastActivityTs = max($lastConnexionTs, $apiActivityTs);', $worker);
        self::assertStringContainsString('api_activity_backfilled', $worker);
    }

    public function testInactiveUsersListingsUseFunctionalApiLogs(): void
    {
        $queries = $this->readSource('/app/sources/users.queries.php');
        $page = $this->readSource('/app/pages/users.php');

        self::assertStringContainsString('last_api_activity_ts', $queries);
        self::assertStringContainsString('teampassApiFunctionalActivityActions()', $queries);
        self::assertStringContainsString('GREATEST(IFNULL(($tsExpr), 0), IFNULL(api_activity.last_api_activity_ts, 0))', $queries);
        self::assertStringContainsString('last_api_activity_ts', $page);
    }
}
