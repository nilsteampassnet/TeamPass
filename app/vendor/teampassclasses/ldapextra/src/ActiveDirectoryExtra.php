<?php

namespace TeampassClasses\LdapExtra;

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
 * @file      ActiveDirectoryExtra.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 */

use LdapRecord\Models\ActiveDirectory\Group as BaseGroup;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\User;

class ActiveDirectoryExtra extends BaseGroup 
{
    public function getADGroups(Connection $connection, array $settings): array
    {
        if (isset($settings['ldap_group_objectclasses_attibute'])) {
            static::$objectClasses = explode(",", $settings['ldap_group_objectclasses_attibute']);
        }

        if (!$connection || !$connection->isConnected()) {
            return [
                'error' => true,
                'message' => 'No valid LDAP connection is available for the query.',
                'userGroups' => [],
            ];
        }

        $query = $connection->query();

        // Determine which GUID attribute is used.
        // The default must stay aligned with getUserADGroups(), otherwise the identifiers
        // stored by the admin mapping never match the ones resolved at login time.
        $guidAttr = strtolower(
            (isset($settings['ldap_guid_attibute']) && !empty($settings['ldap_guid_attibute']))
                ? $settings['ldap_guid_attibute']
                : 'objectguid'
        );

        // Always select CN, the chosen GUID attribute, and member attributes for group membership mapping
        $selectAttrs = ['cn', $guidAttr, 'member', 'uniquemember', 'memberuid'];
        $query->select($selectAttrs);

        foreach (static::$objectClasses as $objectClass) {
            $query->where('objectclass', '=', $objectClass);
        }

        try {
            $groups = $query->paginate();

            $groupsArr = [];
            foreach ($groups as $group) {
                if (isset($group[$guidAttr][0])) {
                    // Convert binary AD objectGUID to standard GUID string format
                    if ($guidAttr === 'objectguid') {
                        try {
                            $bin = $group[$guidAttr][0];
                            // Use ldaprecord's Guid class which correctly handles
                            // Windows mixed-endian byte order for objectGUID.
                            // Direct byte-sequential unpacking produces a byte-swapped
                            // UUID for the first three segments, which never matches
                            // the UUID shown in the Azure AD / AD portal, nor the one
                            // getUserADGroups() resolves at login time.
                            $adGroupId = strtolower(
                                (new \LdapRecord\Models\Attributes\Guid($bin))->getValue()
                            );
                        } catch (\Throwable $e) {
                            // Deterministic fallback: keeps the same id across scans so a group
                            // with an unreadable GUID does not show up as a new entry every time.
                            $adGroupId = 'invalid_guid_' . md5((string) ($group['cn'][0] ?? ''));
                        }
                    } else {
                        // Otherwise treat attribute as plain string (e.g. gidNumber)
                        $adGroupId = strtolower((string) $group[$guidAttr][0]);
                    }
                } else {
                    // Handle groups missing the expected GUID attribute
                    $adGroupId = 'missing_' . uniqid();
                }

                $groupsArr[$adGroupId] = [
                    'ad_group_id' => $adGroupId,
                    'ad_group_title' => $group['cn'][0] ?? 'Unknown',
                    'role_id' => -1,
                    'id' => -1,
                    'role_title' => '',
                    'members' => $this->extractGroupMembers($group),
                ];
            }

            return [
                'error' => false,
                'message' => 'Groups fetched successfully.',
                'userGroups' => $groupsArr,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'LDAP Error: ' . $e->getMessage(),
                'userGroups' => [],
            ];
        }
    }

    /**
     * Extract group members from LDAP group entry
     * Handles Active Directory member attribute and also posixGroup/groupOfNames for compatibility
     *
     * @param array $group The LDAP group entry
     * @return array Array of members with type (uid or dn) and value
     */
    private function extractGroupMembers(array $group): array
    {
        $members = [];

        // Active Directory and groupOfNames: member contains DN of members
        if (isset($group['member']) === true) {
            foreach ($group['member'] as $key => $member) {
                if ($key !== 'count' && !empty($member)) {
                    $members[] = ['type' => 'dn', 'value' => $member];
                }
            }
        }

        // groupOfUniqueNames: uniqueMember contains DN of members
        if (isset($group['uniquemember']) === true) {
            foreach ($group['uniquemember'] as $key => $member) {
                if ($key !== 'count' && !empty($member)) {
                    $members[] = ['type' => 'dn', 'value' => $member];
                }
            }
        }

        // posixGroup: memberUid contains uid/login of members
        if (isset($group['memberuid']) === true) {
            foreach ($group['memberuid'] as $key => $member) {
                if ($key !== 'count' && !empty($member)) {
                    $members[] = ['type' => 'uid', 'value' => $member];
                }
            }
        }

        return $members;
    }

    /**
     * Get the Active Directory groups a user belongs to, nested memberships included.
     *
     * The returned identifiers use the very same format as getADGroups(), which is what
     * teampass_ldap_groups_roles.ldap_group_id stores: both sides must stay aligned or no
     * AD group can ever be mapped to a Teampass role.
     *
     * @param string|null $userDN Distinguished name of the authenticated user
     * @param Connection $connection Active LdapRecord connection
     * @param array $SETTINGS Teampass settings
     * @return array{error: bool, message: string, userGroups: array<int, string>} On error
     *         the group list is empty and 'error' is true, so the caller can tell a failed
     *         lookup apart from a user genuinely belonging to no group
     */
    public function getUserADGroups(?string $userDN, Connection $connection, array $SETTINGS): array
    {
        // init
        $groupsArr = [];

        if ($userDN === null || trim($userDN) === '') {
            return [
                'error' => true,
                'message' => 'No user DN available to resolve AD group membership.',
                'userGroups' => [],
            ];
        }

        // get id attribute — lowercased because LdapRecord returns lowercased attribute keys
        if (isset($SETTINGS['ldap_guid_attibute']) === true && empty($SETTINGS['ldap_guid_attibute']) === false) {
            $idAttribute = strtolower($SETTINGS['ldap_guid_attibute']);
        } else {
            $idAttribute = 'objectguid';
        }

        try {
            Container::addConnection($connection);

            $escapedUserDN = ldap_escape($userDN, '', LDAP_ESCAPE_FILTER);

            try {
                // LDAP_MATCHING_RULE_IN_CHAIN (1.2.840.113556.1.4.1941) returns every group
                // the user belongs to, including indirect membership through nested groups.
                $groups = $this->queryGroupsByFilter(
                    $connection,
                    $idAttribute,
                    '(&(objectClass=group)(member:1.2.840.113556.1.4.1941:=' . $escapedUserDN . '))'
                );
            } catch (\Throwable $e) {
                // A directory that does not implement the extended matching rule rejects the
                // filter. Fall back to direct membership only rather than losing every group.
                $groups = $this->queryGroupsByFilter(
                    $connection,
                    $idAttribute,
                    '(&(objectClass=group)(member=' . $escapedUserDN . '))'
                );
            }
        } catch (\Throwable $e) {
            error_log('TEAMPASS LDAP: unable to read AD group membership: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => 'Unable to read AD group membership.',
                'userGroups' => [],
            ];
        }

        foreach ($groups as $group) {
            if (!isset($group[$idAttribute][0])) {
                continue;
            }
            if ($idAttribute === 'objectguid') {
                try {
                    $bin = $group[$idAttribute][0];
                    // Same conversion as getADGroups(): ldaprecord's Guid class handles the
                    // Windows mixed-endian byte order of objectGUID.
                    $adGroupId = strtolower(
                        (new \LdapRecord\Models\Attributes\Guid($bin))->getValue()
                    );
                } catch (\Throwable $e) {
                    if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                        error_log('TEAMPASS LDAP: unreadable objectGUID for group '
                            . ($group['cn'][0] ?? 'Unknown') . ': ' . $e->getMessage());
                    }
                    continue;
                }
            } else {
                $adGroupId = strtolower((string) $group[$idAttribute][0]);
            }
            $groupsArr[] = $adGroupId;
        }

        // The primary group is never returned by the filters above: Active Directory does not
        // list it in the group's `member` attribute, nor in the user's `memberOf`, and
        // LDAP_MATCHING_RULE_IN_CHAIN does not expand it either. Only the user's primaryGroupID
        // RID carries the membership, so it has to be resolved on its own — otherwise the most
        // widely mapped group of a domain, Domain Users, is missing from every login.
        $primaryGroupId = $this->getPrimaryGroupIdentifier($userDN, $idAttribute, $connection);
        if ($primaryGroupId !== null) {
            $groupsArr[] = $primaryGroupId;
        }

        return [
            'error' => false,
            'message' => '',
            // Nested expansion can return the same group through several paths
            'userGroups' => array_values(array_unique($groupsArr)),
        ];
    }

    /**
     * Resolve the identifier of the user's Active Directory primary group.
     *
     * The identifier is built exactly as getADGroups() builds it, since both sides are compared
     * against teampass_ldap_groups_roles.ldap_group_id.
     *
     * @param string $userDN Distinguished name of the authenticated user
     * @param string $idAttribute Group identifier attribute, already lowercased
     * @param Connection $connection Active LdapRecord connection
     * @return string|null Group identifier, or null when there is no resolvable primary group
     */
    private function getPrimaryGroupIdentifier(string $userDN, string $idAttribute, Connection $connection): ?string
    {
        $group = $this->findPrimaryGroup($userDN, $connection);
        if ($group === null) {
            return null;
        }

        try {
            $value = $idAttribute === 'objectguid'
                ? $group->getConvertedGuid()
                : $group->getFirstAttribute($idAttribute);
        } catch (\Throwable $e) {
            return null;
        }

        return $value === null || $value === '' ? null : strtolower((string) $value);
    }

    /**
     * Read the Active Directory primary group entry of a user.
     *
     * Delegates to LdapRecord's HasOnePrimaryGroup relation, which substitutes the user's
     * primaryGroupID RID into the user's own object SID and reads the matching group.
     *
     * @param string $userDN Distinguished name of the authenticated user
     * @param Connection $connection Active LdapRecord connection
     * @return \LdapRecord\Models\Model|null The primary group, null when unresolvable
     */
    private function findPrimaryGroup(string $userDN, Connection $connection): ?\LdapRecord\Models\Model
    {
        if (trim($userDN) === '') {
            return null;
        }

        try {
            Container::addConnection($connection);

            // find() is declared as Model|Collection|null: only a single user entry carries
            // the primaryGroupID relation.
            $user = User::find($userDN);
            if ($user instanceof User === false) {
                return null;
            }

            return $user->primaryGroup()->first();
        } catch (\Throwable $e) {
            // A directory without primaryGroupID, or a group the bind account cannot read.
            // The memberships resolved from the `member` attribute stay valid, so this is not
            // an error: it must not turn a partial answer into a failed lookup.
            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log('TEAMPASS LDAP: primary group resolution unavailable for ' . $userDN
                    . ': ' . $e->getMessage());
            }

            return null;
        }
    }

    /**
     * Run a raw LDAP filter against the group tree and return the matching entries.
     *
     * @param Connection $connection Active LdapRecord connection
     * @param string $idAttribute Group identifier attribute to select
     * @param string $filter Raw LDAP filter, already escaped by the caller
     * @return iterable<array> Matching group entries
     */
    private function queryGroupsByFilter(Connection $connection, string $idAttribute, string $filter): iterable
    {
        $query = $connection->query();
        $query->select(['cn', $idAttribute]);
        $query->rawFilter($filter);

        return $query->paginate();
    }

    /**
     * Check is user is enabled
     *
     * @param string $userDN
     * @param Connection $connection
     * @return bool
     */
    function userIsEnabled(string $userDN, Connection $connection): bool
    {
        $isEnabled = false;
        try {
            Container::addConnection($connection);
            $user = User::find($userDN);
            $isEnabled = $user->isEnabled();
        } catch (\LdapRecord\Auth\BindException $e) {
            // Do nothing
        }
        return $isEnabled;
    }

    /**
     * Check whether a user is a member of a specific LDAP group using the user's memberOf attribute.
     * This is the user-centric alternative to isUserInAllowedGroup() and works best with
     * Active Directory where users carry a memberOf attribute populated by the server.
     *
     * Active Directory populates memberOf with DIRECT membership only. When the attribute does
     * not carry the group and a connection is available, the directory is asked for the
     * transitive answer before access is denied, so both modes resolve nested groups alike.
     *
     * @param string $groupDn Full distinguished name of the required group
     * @param array $userEntry LDAP user entry array that must contain the 'memberof' key
     * @param string $userDn Distinguished name of the authenticating user, required to resolve
     *                       nested membership; membership stays direct-only when empty
     * @param Connection|null $connection Active LdapRecord connection, null to skip the nested
     *                                    resolution entirely and keep the attribute-only check
     * @return bool True if the user belongs to the group, directly or through a nested group,
     *              or if $groupDn is empty
     */
    public function isUserInAllowedGroupByMemberOf(
        string $groupDn,
        array $userEntry,
        string $userDn = '',
        ?Connection $connection = null
    ): bool {
        if (trim($groupDn) === '') {
            return true;
        }

        $memberOf = $userEntry['memberof'] ?? [];
        foreach ($memberOf as $key => $dn) {
            if ($key !== 'count' && strcasecmp((string) $dn, $groupDn) === 0) {
                return true;
            }
        }

        // Direct membership not found: ask for the transitive one. Reached only on the path that
        // would otherwise deny access, so a direct member never pays for the extra query and the
        // "no additional query" property of the user-centric mode is preserved.
        if ($connection !== null
            && $this->isNestedMemberByMemberOf($groupDn, $userDn, $connection) === true
        ) {
            return true;
        }

        // memberOf never carries the primary group, so restricting login to Domain Users would
        // lock out the whole domain. Checked last, for the same reason as the nested lookup.
        if ($connection !== null
            && $this->isPrimaryGroupMember($groupDn, $userDn, $connection) === true
        ) {
            return true;
        }

        if (empty($memberOf)) {
            error_log('TEAMPASS LDAP: isUserInAllowedGroupByMemberOf — user has no memberof attribute; consider using group-centric mode instead.');
        }

        return false;
    }

    /**
     * Check indirect (nested) membership from the user side of the relation.
     *
     * Mirror of isNestedMemberOfGroup(): a scope=base read on the USER entry filtered with
     * LDAP_MATCHING_RULE_IN_CHAIN (1.2.840.113556.1.4.1941) on memberOf, which Active Directory
     * evaluates transitively. Used by the user-centric mode, where the group entry is not read.
     *
     * @param string $groupDn Full distinguished name of the required group
     * @param string $userDn Distinguished name of the authenticating user
     * @param Connection $connection Active LdapRecord connection
     * @return bool True if the user belongs to the group through one or more nested groups
     */
    private function isNestedMemberByMemberOf(string $groupDn, string $userDn, Connection $connection): bool
    {
        if (trim($userDn) === '') {
            return false;
        }

        try {
            $userEntry = $connection->query()
                ->select(['cn'])
                ->setDn($userDn)
                ->read()
                ->rawFilter(
                    '(memberof:1.2.840.113556.1.4.1941:='
                    . ldap_escape($groupDn, '', LDAP_ESCAPE_FILTER) . ')'
                )
                ->first();

            return $userEntry !== null;
        } catch (\Throwable $e) {
            // Directory without support for the extended matching rule: the memberOf attribute
            // has already been scanned by the caller, so this is not an error.
            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log('TEAMPASS LDAP: nested memberOf check unavailable: ' . $e->getMessage());
            }

            return false;
        }
    }

    /**
     * Check whether a user is a member of a specific LDAP group identified by its full DN.
     * Uses a scope=base LDAP read so the group can reside outside the configured users base DN.
     *
     * @param string $groupDn Full distinguished name of the required group
     * @param string $userDn Distinguished name of the authenticating user
     * @param string $userUid Login name of the user (for posixGroup memberUid checks)
     * @param Connection $connection Active LdapRecord connection
     * @return bool True if the user is a member or if $groupDn is empty (no restriction)
     */
    public function isUserInAllowedGroup(
        string $groupDn,
        string $userDn,
        string $userUid,
        Connection $connection
    ): bool {
        if (trim($groupDn) === '') {
            return true;
        }
        try {
            // scope=base search on the group DN — works even if outside the users base DN
            $groupEntry = $connection->query()
                ->select(['member', 'uniquemember', 'memberuid'])
                ->setDn($groupDn)
                ->read()
                ->whereHas('objectclass')
                ->first();

            if ($groupEntry === null) {
                error_log('TEAMPASS LDAP: Allowed login group not found: ' . $groupDn);
                return false;
            }

            // Check member (AD standard) — DN comparison, case-insensitive
            if (isset($groupEntry['member'])) {
                foreach ($groupEntry['member'] as $key => $member) {
                    if ($key !== 'count' && strcasecmp((string) $member, $userDn) === 0) {
                        return true;
                    }
                }
            }

            // Check uniquemember (groupOfUniqueNames) — DN comparison
            if (isset($groupEntry['uniquemember'])) {
                foreach ($groupEntry['uniquemember'] as $key => $member) {
                    if ($key !== 'count' && strcasecmp((string) $member, $userDn) === 0) {
                        return true;
                    }
                }
            }

            // Check memberuid (posixGroup) — UID comparison
            if (isset($groupEntry['memberuid']) && $userUid !== '') {
                foreach ($groupEntry['memberuid'] as $key => $member) {
                    if ($key !== 'count' && strcasecmp((string) $member, $userUid) === 0) {
                        return true;
                    }
                }
            }

            // Not a direct member: last resort, look for an indirect membership through
            // nested groups. Kept last on purpose — the extended matching rule is an
            // unindexed transitive search, so a direct member never pays for it.
            if ($this->isNestedMemberOfGroup($groupDn, $userDn, $connection) === true) {
                return true;
            }

            // The `member` attribute read above never carries the primary group members, so a
            // restriction on Domain Users would deny every user of the domain.
            return $this->isPrimaryGroupMember($groupDn, $userDn, $connection);
        } catch (\Throwable $e) {
            error_log('TEAMPASS LDAP: isUserInAllowedGroup error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check whether a group is the Active Directory primary group of a user.
     *
     * Primary group membership appears in neither the group's `member` attribute nor the user's
     * `memberOf`, and the extended matching rule does not expand it: it is carried by the user's
     * primaryGroupID alone. Without this check, restricting login to the default primary group
     * of a domain (Domain Users) denies access to every user.
     *
     * @param string $groupDn Full distinguished name of the required group
     * @param string $userDn Distinguished name of the authenticating user
     * @param Connection $connection Active LdapRecord connection
     * @return bool True if $groupDn is the user's primary group
     */
    private function isPrimaryGroupMember(string $groupDn, string $userDn, Connection $connection): bool
    {
        $group = $this->findPrimaryGroup($userDn, $connection);
        if ($group === null) {
            return false;
        }

        return strcasecmp((string) $group->getDn(), $groupDn) === 0;
    }

    /**
     * Check whether a user is an indirect member of an Active Directory group, that is a
     * member of a group which is itself a member of $groupDn, at any depth.
     *
     * Relies on the Active Directory extended matching rule LDAP_MATCHING_RULE_IN_CHAIN
     * (1.2.840.113556.1.4.1941), evaluated with a scope=base read on the group entry.
     *
     * @param string $groupDn Full distinguished name of the required group
     * @param string $userDn Distinguished name of the authenticating user
     * @param Connection $connection Active LdapRecord connection
     * @return bool True if the user belongs to the group through one or more nested groups
     */
    private function isNestedMemberOfGroup(string $groupDn, string $userDn, Connection $connection): bool
    {
        if (trim($userDn) === '') {
            return false;
        }

        try {
            $nestedGroupEntry = $connection->query()
                ->select(['cn'])
                ->setDn($groupDn)
                ->read()
                ->rawFilter(
                    '(member:1.2.840.113556.1.4.1941:='
                    . ldap_escape($userDn, '', LDAP_ESCAPE_FILTER) . ')'
                )
                ->first();

            return $nestedGroupEntry !== null;
        } catch (\Throwable $e) {
            // Directory without support for the extended matching rule. Direct membership
            // has already been checked by the caller, so this is not an error.
            if (defined('LOG_TO_SERVER') && LOG_TO_SERVER === true) {
                error_log('TEAMPASS LDAP: nested group check unavailable: ' . $e->getMessage());
            }

            return false;
        }
    }
}