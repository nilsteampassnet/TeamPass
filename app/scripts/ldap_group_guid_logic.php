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
 * ---
 * Pure conversion logic for Active Directory group objectGUID identifiers.
 *
 * These functions have NO DB / session / filesystem dependency, so they can be unit-tested in
 * isolation. They are included by both:
 *   - public/install/upgrade_run_3.2.2.php    (one-shot migration of ldap_groups_roles)
 *   - tests/Unit/LdapGroupGuidLogicTest.php   (unit tests)
 *
 * Context: until 3.2.2, ActiveDirectoryExtra::getADGroups() serialised the 16 objectGUID bytes
 * in wire order while getUserADGroups() used LdapRecord\Models\Attributes\Guid, which applies
 * the Windows mixed-endian layout of the first three fields. The identifiers stored by the
 * admin mapping could therefore never match the ones resolved at login.
 *
 * @file      ldap_group_guid_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

declare(strict_types=1);

if (function_exists('ldapIsCanonicalGuidFormat') === false) {
    /**
     * Tell whether a stored group identifier looks like a lowercase canonical GUID.
     *
     * Anything else must be left untouched by the migration: 'invalid_guid_*' and 'missing_*'
     * placeholders, numeric gidNumber values, or an OpenLDAP entryUUID kept as a plain string.
     *
     * @param string $value Value read from teampass_ldap_groups_roles.ldap_group_id
     * @return bool True when the value is a lowercase 8-4-4-4-12 hexadecimal GUID
     */
    function ldapIsCanonicalGuidFormat(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $value
        ) === 1;
    }
}

if (function_exists('ldapLegacyGuidToCanonical') === false) {
    /**
     * Convert a byte-sequential objectGUID string into its canonical mixed-endian form.
     *
     * Windows stores the first three fields of an objectGUID little-endian. Reversing the byte
     * order of those three fields turns the legacy value produced by the old vsprintf() code
     * into the value LdapRecord\Models\Attributes\Guid returns.
     *
     * WARNING: the transformation is its own inverse. Applying it twice restores the broken
     * value, hence the one-shot marker 'ldap_group_guid_byteorder_fixed' guarding the caller.
     *
     * @param string $guid GUID in the legacy byte order
     * @return string The same GUID with fields 1 to 3 byte-reversed, unchanged when malformed
     */
    function ldapLegacyGuidToCanonical(string $guid): string
    {
        $fields = explode('-', $guid);
        if (count($fields) !== 5) {
            return $guid;
        }

        foreach ([0, 1, 2] as $fieldIndex) {
            $bytes = str_split($fields[$fieldIndex], 2);
            $fields[$fieldIndex] = implode('', array_reverse($bytes));
        }

        return implode('-', $fields);
    }
}
