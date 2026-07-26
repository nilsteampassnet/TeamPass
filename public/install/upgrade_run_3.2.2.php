<?php
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
 * @file      upgrade_run_3.2.2.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use TeampassClasses\SuperGlobal\SuperGlobal;
use TeampassClasses\Language\Language;
use TeampassClasses\ConfigManager\ConfigManager;

// Load functions
require_once __DIR__.'/../sources/main.functions.php';

// init
loadClasses('DB');
$superGlobal = new SuperGlobal();
$lang = new Language();
error_reporting(E_ERROR | E_PARSE);
set_time_limit(600);

// Load config
$configManager = new ConfigManager();
$SETTINGS = $configManager->getAllSettings();

//include librairies
require_once TEAMPASS_ROOT . '/app/includes/language/english.php';
require_once TEAMPASS_ROOT . '/app/config/include.php';
require_once TEAMPASS_ROOT . '/app/config/settings.php';
require_once 'tp.functions.php';
require_once 'libs/aesctr.php';
require_once TEAMPASS_ROOT . '/app/scripts/ldap_group_guid_logic.php';

// DataBase
// Test DB connexion
$pass = defuse_return_decrypted(DB_PASSWD);
$server = (string) DB_HOST;
$pre = (string) DB_PREFIX;
$database = (string) DB_NAME;
$port = (int) DB_PORT;
$user = (string) DB_USER;

$db_link = mysqli_connect(
    $server,
    $user,
    $pass,
    $database,
    $port
);
if ($db_link) {
    $db_link->set_charset(DB_ENCODING);
} else {
    echo '[{"finish":"1", "msg":"", "error":"Impossible to get connected to server. Error is: ' . addslashes(mysqli_connect_error()) . '!"}]';
    exit();
}

// Load libraries
$superGlobal = new SuperGlobal();
$lang = new Language();


//---------------------------------------------------------------------

//--->BEGIN 3.2.2

// Realign the AD group identifiers stored by the admin mapping.
//
// Until now getADGroups() built the objectGUID string by unpacking its 16 bytes in wire
// order, while getUserADGroups() used LdapRecord's Guid class, which applies the Windows
// mixed-endian byte order of the first three fields. The two never matched, so every row
// of ldap_groups_roles written from the admin screen was compared at login against a
// different string and no AD group could be mapped to a role.
//
// Both sides now produce the canonical GUID, so the stored values have to be converted
// once. The conversion is its own inverse, hence the one-shot marker below: running it
// twice would put the rows back into the broken format.
$guidMigrationDone = mysqli_fetch_array(mysqli_query(
    $db_link,
    "SELECT `valeur` FROM `" . $pre . "misc`
     WHERE `type` = 'admin' AND `intitule` = 'ldap_group_guid_byteorder_fixed'"
));

if (empty($guidMigrationDone[0]) === true) {
    // Only Active Directory installs using objectGUID are concerned. OpenLDAP identifiers
    // (gidNumber, entryUUID) are plain strings that were never byte-swapped.
    $ldapTypeRow = mysqli_fetch_array(mysqli_query(
        $db_link,
        "SELECT `valeur` FROM `" . $pre . "misc`
         WHERE `type` = 'admin' AND `intitule` = 'ldap_type'"
    ));
    $guidAttributeRow = mysqli_fetch_array(mysqli_query(
        $db_link,
        "SELECT `valeur` FROM `" . $pre . "misc`
         WHERE `type` = 'admin' AND `intitule` = 'ldap_guid_attibute'"
    ));

    $ldapType = (string) ($ldapTypeRow[0] ?? '');
    $guidAttribute = strtolower(trim((string) ($guidAttributeRow[0] ?? '')));

    if ($ldapType === 'ActiveDirectory'
        && ($guidAttribute === '' || $guidAttribute === 'objectguid')
    ) {
        $mappingRows = mysqli_query(
            $db_link,
            "SELECT `increment_id`, `ldap_group_id` FROM `" . $pre . "ldap_groups_roles`"
        );

        if ($mappingRows !== false) {
            while ($mappingRow = mysqli_fetch_assoc($mappingRows)) {
                $storedGuid = (string) $mappingRow['ldap_group_id'];

                // Leave anything that is not a canonical GUID untouched (invalid_guid_*,
                // missing_*, numeric gidNumber values inherited from an older setup).
                if (ldapIsCanonicalGuidFormat($storedGuid) === false) {
                    continue;
                }

                mysqli_query(
                    $db_link,
                    "UPDATE `" . $pre . "ldap_groups_roles`
                     SET `ldap_group_id` = '"
                     . mysqli_real_escape_string($db_link, ldapLegacyGuidToCanonical($storedGuid)) . "'
                     WHERE `increment_id` = " . (int) $mappingRow['increment_id']
                );
            }
        }
    }

    mysqli_query(
        $db_link,
        "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`)
         VALUES ('admin', 'ldap_group_guid_byteorder_fixed', '1')
         ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)"
    );
}

// Save upgrade timestamp (upsert: always update if exists)
mysqli_query(
    $db_link,
    "INSERT INTO `" . $pre . "misc` (`type`, `intitule`, `valeur`) VALUES ('admin', 'upgrade_timestamp', " . time() . ")
     ON DUPLICATE KEY UPDATE `valeur` = VALUES(`valeur`)"
);

// Close connection
mysqli_close($db_link);

// Finished
echo '[{"finish":"1" , "next":"", "error":""}]';


//---< FUNCTIONS

// ldapIsCanonicalGuidFormat() and ldapLegacyGuidToCanonical() live in the DB-free module
// app/scripts/ldap_group_guid_logic.php so they can be unit-tested in isolation
// (tests/Unit/LdapGroupGuidLogicTest.php).
