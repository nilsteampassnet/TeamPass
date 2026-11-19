<?php

declare(strict_types=1);

/**
 * TeamPass - a collaborative passwords manager.
 * Copyright (c) 2009-2026 Teampass.net
 * Licensed under the GNU General Public License v3.0.
 * See <https://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../../public/install/setting_entities_migration.php';

$database = (string) getenv('TEAMPASS_TEST_DB');
if (!preg_match('/^teampass_test_[a-z0-9_]+$/', $database)) {
    throw new RuntimeException('A disposable teampass_test_* database is required.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$connection = new mysqli(getenv('TEAMPASS_TEST_HOST') ?: '127.0.0.1',
    getenv('TEAMPASS_TEST_USER') ?: 'root', (string) getenv('TEAMPASS_TEST_PASSWORD'),
    $database, (int) (getenv('TEAMPASS_TEST_PORT') ?: 3306));
$connection->set_charset('utf8mb4');
$prefix = 'setting_entities_' . bin2hex(random_bytes(4)) . '_';
$miscTable = $prefix . 'misc';

/** Fail explicitly even when PHP's assertions are disabled. */
function settingEntitiesExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return array<string, string> Stored values, by "type/intitule" */
function settingEntitiesValues(mysqli $connection, string $miscTable): array
{
    $values = [];
    foreach ($connection->query('SELECT type, intitule, valeur FROM `' . $miscTable . '`')->fetch_all(MYSQLI_ASSOC) as $row) {
        $values[$row['type'] . '/' . $row['intitule']] = $row['valeur'];
    }

    return $values;
}

try {
    // Execute the real fresh-install SQL expression of the misc table, without booting the installer.
    $installer = (string) file_get_contents(__DIR__ . '/../../public/install/install-steps/run.step5.php');
    settingEntitiesExpect(preg_match('/private function misc\(\)[\s\S]*?DB::query\(\s*("[\s\S]*?")\s*\);/', $installer, $match) === 1,
        'Missing fresh-install misc schema');
    $fixture = new class ($prefix) {
        public array $inputData;
        /** Supply only the table-prefix input used by the real installer expression. */
        public function __construct(string $prefix) { $this->inputData = ['tablePrefix' => $prefix]; }
    };
    $sql = (function () use ($match): string { return eval('return ' . $match[1] . ';'); })->call($fixture);
    $connection->query($sql);

    $rows = [
        // Stored by the generic option save before the fix.
        ['admin', 'custom_login_text', 'Inicio de sesi&oacute;n', 0],
        ['admin', 'email_from_name', 'Soci&eacute;t&eacute; Passwords', 0],
        ['admin', 'ga_website_name', 'Soci&eacute;t&eacute; Passwords', 0],
        // Must not change.
        ['admin', 'cpassman_url', 'https://example.com/?a=1&amp;b=2', 0],
        ['admin', 'custom_logo', 'logo &lt;new&gt;.png', 0],
        ['admin', 'already_clean', 'Société', 0],
        ['admin', 'email_auth_pwd', 'p&auml;ss', 0],
        ['admin', 'encrypted_value', 'def50200&auml;', 1],
        ['complex', '12', 'x&auml;', 0],
    ];
    $insert = $connection->prepare('INSERT INTO `' . $miscTable . '` (type, intitule, valeur, is_encrypted) VALUES (?, ?, ?, ?)');
    foreach ($rows as [$type, $name, $value, $encrypted]) {
        $insert->bind_param('sssi', $type, $name, $value, $encrypted);
        $insert->execute();
    }
    $insert->close();
    $before = settingEntitiesValues($connection, $miscTable);

    $repaired = tpRepairLegacySettingEntities($connection, $miscTable);
    $after = settingEntitiesValues($connection, $miscTable);

    settingEntitiesExpect($repaired === 3, 'Exactly the three legacy values must be repaired, got ' . $repaired);
    settingEntitiesExpect($after['admin/custom_login_text'] === 'Inicio de sesión', 'Login text not repaired');
    settingEntitiesExpect($after['admin/email_from_name'] === 'Société Passwords', 'Sender name not repaired');
    settingEntitiesExpect($after['admin/ga_website_name'] === 'Société Passwords', 'Authenticator issuer not repaired');
    foreach (['admin/cpassman_url', 'admin/custom_logo', 'admin/already_clean', 'admin/email_auth_pwd', 'admin/encrypted_value', 'complex/12'] as $key) {
        settingEntitiesExpect($after[$key] === $before[$key], 'Must stay unchanged: ' . $key);
    }

    // A second run is a no-op.
    settingEntitiesExpect(tpRepairLegacySettingEntities($connection, $miscTable) === 0, 'The repair must be idempotent');
    settingEntitiesExpect(settingEntitiesValues($connection, $miscTable) === $after, 'A second run must not change anything');

    echo "Setting entities repair: OK\n";
} finally {
    $connection->query('DROP TABLE IF EXISTS `' . $miscTable . '`');
    $connection->close();
}
