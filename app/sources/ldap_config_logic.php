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
 * @file      ldap_config_logic.php
 * @author    Nils Laumaillé (nils@teampass.net)
 * @copyright 2009-2026 Teampass.net
 * @license   GPL-3.0
 * @see       https://www.teampass.net
 *
 * Decision logic for the LDAP configuration checks shown on the LDAP setup page.
 *
 * Every function here is free of database, session and network access so the rules can be
 * unit-tested (tests/Unit/LdapConfigChecksTest.php). Findings carry a language KEY, never a
 * translated sentence: the handler resolves them with the administrator's language.
 */

// Severity of a configuration finding.
// error   : the value is invalid, something is broken right now.
// warning : the value is accepted but almost certainly not what the administrator meant.
// info    : the value is valid and explains a behaviour that is often reported as a bug.
if (defined('LDAP_CHECK_ERROR') === false) {
    define('LDAP_CHECK_ERROR', 'error');
    define('LDAP_CHECK_WARNING', 'warning');
    define('LDAP_CHECK_INFO', 'info');
}

/**
 * Build a single finding.
 *
 * @param string $field      Setting the finding is about
 * @param string $severity   One of LDAP_CHECK_ERROR|LDAP_CHECK_WARNING|LDAP_CHECK_INFO
 * @param string $code       Language key describing the problem
 * @param string $suggestion Corrected value the administrator can apply, '' when none
 *
 * @return array{field: string, severity: string, code: string, suggestion: string}
 */
function ldapConfigFinding(string $field, string $severity, string $code, string $suggestion = ''): array
{
    return [
        'field' => $field,
        'severity' => $severity,
        'code' => $code,
        'suggestion' => $suggestion,
    ];
}

/**
 * Split a raw user-object-filter value on its top-level commas.
 *
 * A comma inside parentheses belongs to a value, e.g. "memberOf=CN=x,OU=y", and must be kept.
 *
 * @param string $rawFilter Raw setting value
 *
 * @return array<int, string> Trimmed, non-empty segments
 */
function ldapConfigSplitTopLevelFilters(string $rawFilter): array
{
    $rawFilter = trim($rawFilter);
    if ($rawFilter === '') {
        return [];
    }

    $parts = [];
    $current = '';
    $depth = 0;
    $length = strlen($rawFilter);
    for ($i = 0; $i < $length; $i++) {
        $char = $rawFilter[$i];
        if ($char === '(') {
            $depth++;
        } elseif ($char === ')') {
            $depth--;
        }
        if ($char === ',' && $depth === 0) {
            $parts[] = $current;
            $current = '';
            continue;
        }
        $current .= $char;
    }
    $parts[] = $current;

    return array_values(array_filter(
        array_map('trim', $parts),
        static fn($part) => $part !== ''
    ));
}

/**
 * Build a valid LDAP filter from the user-object-filter setting.
 *
 * The setting accepts either a single filter, e.g. "(objectClass=user)", or several filters
 * separated by a top-level comma, e.g. "(objectCategory=Person),(sAMAccountName=*)". Multiple
 * filters are combined with a logical AND.
 *
 * @param string $rawFilter Raw value stored in settings.
 *
 * @return string A single valid LDAP filter, or '' when none provided.
 */
function tpLdapBuildObjectFilter(string $rawFilter): string
{
    $parts = ldapConfigSplitTopLevelFilters($rawFilter);

    if (count($parts) === 0) {
        return '';
    }
    if (count($parts) === 1) {
        return $parts[0];
    }

    return '(&' . implode('', $parts) . ')';
}

/**
 * Tell whether a filter segment is correctly parenthesised and balanced.
 *
 * @param string $segment One top-level segment of the setting
 *
 * @return bool
 */
function ldapConfigFilterSegmentIsBalanced(string $segment): bool
{
    if (strlen($segment) < 3 || $segment[0] !== '(' || substr($segment, -1) !== ')') {
        return false;
    }

    $depth = 0;
    $length = strlen($segment);
    for ($i = 0; $i < $length; $i++) {
        if ($segment[$i] === '(') {
            $depth++;
        } elseif ($segment[$i] === ')') {
            $depth--;
            // A depth back to zero before the end means two sibling filters glued together,
            // which is only valid inside an operator: "(&(a=1)(b=2))" keeps depth >= 1 here.
            if ($depth === 0 && $i !== $length - 1) {
                return false;
            }
            if ($depth < 0) {
                return false;
            }
        }
    }

    return $depth === 0;
}

/**
 * The user object filter an administrator most probably wanted, per directory type.
 *
 * @param string $ldapType Value of the ldap_type setting
 *
 * @return string
 */
function ldapConfigDefaultUserObjectFilter(string $ldapType): string
{
    return $ldapType === 'ActiveDirectory'
        ? '(&(objectCategory=person)(objectClass=user))'
        : '(objectClass=inetOrgPerson)';
}

/**
 * CHECK 1 - validate the "User Object Filter" setting.
 *
 * The field expects an LDAP filter between parentheses. An attribute name is the mistake the
 * support tickets are made of: it is accepted by the form, then rejected by the directory on the
 * admin Users page as an opaque "An error occurred.".
 *
 * @param string $raw      Raw setting value
 * @param string $ldapType Value of the ldap_type setting, drives the suggestion
 *
 * @return array<int, array{field: string, severity: string, code: string, suggestion: string}>
 */
function ldapConfigValidateUserObjectFilter(string $raw, string $ldapType = ''): array
{
    $field = 'ldap_user_object_filter';
    $value = trim($raw);

    // No filter at all is a valid configuration: every user object is then accepted.
    if ($value === '') {
        return [];
    }

    $segments = ldapConfigSplitTopLevelFilters($value);
    if (count($segments) === 0) {
        return [ldapConfigFinding($field, LDAP_CHECK_ERROR, 'ldap_check_filter_empty_segments', '')];
    }

    foreach ($segments as $segment) {
        // A bare attribute name, the most frequent mistake.
        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $segment) === 1) {
            return [ldapConfigFinding(
                $field,
                LDAP_CHECK_ERROR,
                'ldap_check_filter_is_attribute',
                ldapConfigDefaultUserObjectFilter($ldapType)
            )];
        }

        // An assertion written without its parentheses: the correction is unambiguous.
        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*[:<>~]?=[^()]*$/', $segment) === 1) {
            $suggestion = implode('', array_map(
                static fn(string $part): string => '(' . $part . ')',
                $segments
            ));
            if (count($segments) > 1) {
                $suggestion = '(&' . $suggestion . ')';
            }

            return [ldapConfigFinding($field, LDAP_CHECK_ERROR, 'ldap_check_filter_not_parenthesized', $suggestion)];
        }

        if (ldapConfigFilterSegmentIsBalanced($segment) === false) {
            return [ldapConfigFinding($field, LDAP_CHECK_ERROR, 'ldap_check_filter_unbalanced', '')];
        }
    }

    return [];
}

/**
 * Split a distinguished name on the commas that separate its components.
 *
 * A comma escaped with a backslash belongs to the value, e.g. "CN=Doe\, John".
 *
 * @param string $dn Distinguished name
 *
 * @return array<int, string> Trimmed, non-empty components
 */
function ldapConfigSplitDnComponents(string $dn): array
{
    $components = [];
    $current = '';
    $length = strlen($dn);
    for ($i = 0; $i < $length; $i++) {
        $char = $dn[$i];
        if ($char === '\\' && $i + 1 < $length) {
            $current .= $char . $dn[$i + 1];
            $i++;
            continue;
        }
        if ($char === ',') {
            $components[] = $current;
            $current = '';
            continue;
        }
        $current .= $char;
    }
    $components[] = $current;

    return array_values(array_filter(
        array_map('trim', $components),
        static fn($part) => $part !== ''
    ));
}

/**
 * Comparable form of a distinguished name: lowercase, no space around the separators.
 *
 * @param string $dn Distinguished name
 *
 * @return string
 */
function ldapConfigNormalizeDn(string $dn): string
{
    $components = [];
    foreach (ldapConfigSplitDnComponents($dn) as $component) {
        $components[] = preg_replace('/\s*=\s*/', '=', strtolower($component));
    }

    return implode(',', $components);
}

/**
 * Tell whether every component of a value looks like an "attribute=value" pair.
 *
 * @param array<int, string> $components Output of ldapConfigSplitDnComponents()
 *
 * @return bool
 */
function ldapConfigDnComponentsAreWellFormed(array $components): bool
{
    if (count($components) === 0) {
        return false;
    }

    foreach ($components as $component) {
        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*\s*=\s*\S/', $component) !== 1) {
            return false;
        }
    }

    return true;
}

/**
 * CHECK 2 - validate the "Additional User DN" setting.
 *
 * TeamPass concatenates this value with the base DN itself (users.queries.php, ldapCheckUserPassword()).
 * A value that already carries the base DN therefore targets "OU=x,DC=a,DC=b,DC=a,DC=b", a DN that
 * does not exist, and the search silently returns nothing.
 *
 * @param string $raw     Raw setting value
 * @param string $baseDn  Value of the ldap_bdn setting
 *
 * @return array<int, array{field: string, severity: string, code: string, suggestion: string}>
 */
function ldapConfigValidateAdditionalUserDn(string $raw, string $baseDn = ''): array
{
    $field = 'ldap_dn_additional_user_dn';
    $value = trim($raw);

    // Empty means "search the whole base DN subtree", which is a valid configuration.
    if ($value === '') {
        return [];
    }

    $components = ldapConfigSplitDnComponents($value);
    $cleanValue = implode(',', $components);

    if (ldapConfigDnComponentsAreWellFormed($components) === false) {
        return [ldapConfigFinding($field, LDAP_CHECK_ERROR, 'ldap_check_additional_dn_malformed', '')];
    }

    $normalizedBase = ldapConfigNormalizeDn($baseDn);
    $normalizedValue = ldapConfigNormalizeDn($cleanValue);

    if ($normalizedBase !== '') {
        // The whole base DN repeated: nothing is left to search.
        if ($normalizedValue === $normalizedBase) {
            return [ldapConfigFinding($field, LDAP_CHECK_ERROR, 'ldap_check_additional_dn_is_base', '')];
        }

        if (str_ends_with($normalizedValue, ',' . $normalizedBase) === true) {
            $keep = count($components) - count(ldapConfigSplitDnComponents($baseDn));
            $suggestion = $keep > 0 ? implode(',', array_slice($components, 0, $keep)) : '';

            return [ldapConfigFinding(
                $field,
                LDAP_CHECK_ERROR,
                'ldap_check_additional_dn_contains_base',
                $suggestion
            )];
        }
    }

    // Domain components that do not match the base DN: the value is not relative to it, so the
    // concatenation cannot resolve either. No safe automatic correction here.
    foreach ($components as $component) {
        if (preg_match('/^dc\s*=/i', $component) === 1) {
            return [ldapConfigFinding($field, LDAP_CHECK_WARNING, 'ldap_check_additional_dn_has_domain_components', '')];
        }
    }

    // A stray leading or trailing comma survives the save and doubles the separator.
    if ($cleanValue !== $value) {
        return [ldapConfigFinding($field, LDAP_CHECK_WARNING, 'ldap_check_additional_dn_extra_comma', $cleanValue)];
    }

    return [];
}

/**
 * LDAP types initializeLdapConnection() can actually build a handler for.
 *
 * @return array<int, string>
 */
function ldapConfigSupportedTypes(): array
{
    return ['ActiveDirectory', 'OpenLDAP'];
}

/**
 * Audit the whole LDAP configuration.
 *
 * Covers the two checks above plus the settings whose wrong value is silent: they let the page
 * test succeed while no user can log in, which is what the support tickets describe.
 *
 * @param array $settings Teampass settings
 *
 * @return array<int, array{field: string, severity: string, code: string, suggestion: string}>
 */
function ldapConfigAudit(array $settings): array
{
    $findings = [];
    // The installer seeds several LDAP settings with the string '0', which every `?? ` and
    // `isset()` default leaves in place. It means "not configured", never a real value.
    $get = static function (string $key) use ($settings): string {
        $value = trim((string) ($settings[$key] ?? ''));

        return $value === '0' ? '' : $value;
    };
    $ldapType = $get('ldap_type');

    // --- Connection identity ---
    if ($get('ldap_hosts') === '') {
        $findings[] = ldapConfigFinding('ldap_hosts', LDAP_CHECK_ERROR, 'ldap_check_hosts_empty');
    }

    if ($get('ldap_bdn') === '') {
        $findings[] = ldapConfigFinding('ldap_bdn', LDAP_CHECK_ERROR, 'ldap_check_bdn_empty');
    } elseif (ldapConfigDnComponentsAreWellFormed(ldapConfigSplitDnComponents($get('ldap_bdn'))) === false) {
        $findings[] = ldapConfigFinding('ldap_bdn', LDAP_CHECK_ERROR, 'ldap_check_bdn_malformed');
    }

    if ($ldapType === '') {
        $findings[] = ldapConfigFinding('ldap_type', LDAP_CHECK_ERROR, 'ldap_check_type_empty');
    } elseif (in_array($ldapType, ldapConfigSupportedTypes(), true) === false) {
        // The select offers FreeIPA, initializeLdapConnection() throws on it.
        $findings[] = ldapConfigFinding('ldap_type', LDAP_CHECK_ERROR, 'ldap_check_type_unsupported');
    }

    if ($get('ldap_username') === '' || $get('ldap_password') === '') {
        $findings[] = ldapConfigFinding('ldap_username', LDAP_CHECK_WARNING, 'ldap_check_service_account_empty');
    }

    // --- Transport ---
    $ssl = (int) ($settings['ldap_ssl'] ?? 0) === 1;
    $tls = (int) ($settings['ldap_tls'] ?? 0) === 1;
    $port = (int) ($settings['ldap_port'] ?? 0);

    if ($ssl === true && $tls === true) {
        $findings[] = ldapConfigFinding('ldap_ssl', LDAP_CHECK_ERROR, 'ldap_check_ssl_and_tls');
    }
    if ($ssl === true && $port !== 0 && $port !== 636 && $port !== 3269) {
        $findings[] = ldapConfigFinding('ldap_port', LDAP_CHECK_WARNING, 'ldap_check_ssl_port_mismatch', '636');
    }
    if ($ssl === false && ($port === 636 || $port === 3269)) {
        $findings[] = ldapConfigFinding('ldap_port', LDAP_CHECK_WARNING, 'ldap_check_plain_port_mismatch');
    }

    // --- The attribute the login searches on ---
    // identify.php reads it with `?? 'samaccountname'`, which never replaces an empty string:
    // the search then runs on an empty attribute and no user is ever found. The page test has
    // its own isset+empty fallback, so it succeeds where the login cannot.
    if ($get('ldap_user_attribute') === '') {
        // ldapResolveUserAttribute() falls back to samaccountname, which exists in Active
        // Directory and nowhere else: the same empty value simply breaks every other directory.
        $findings[] = ldapConfigFinding(
            'ldap_user_attribute',
            $ldapType === 'ActiveDirectory' ? LDAP_CHECK_WARNING : LDAP_CHECK_ERROR,
            'ldap_check_user_attribute_empty',
            $ldapType === 'ActiveDirectory' ? 'samaccountname' : 'uid'
        );
    }

    // --- The two settings the tickets are about ---
    $findings = array_merge(
        $findings,
        ldapConfigValidateUserObjectFilter($get('ldap_user_object_filter'), $ldapType),
        ldapConfigValidateAdditionalUserDn($get('ldap_dn_additional_user_dn'), $get('ldap_bdn'))
    );

    // --- Gates that refuse a login the directory just accepted ---
    if ((int) ($settings['ldap_mode'] ?? 0) !== 1) {
        $findings[] = ldapConfigFinding('ldap_mode', LDAP_CHECK_WARNING, 'ldap_check_mode_disabled');
    }

    if ((int) ($settings['enable_ad_user_auto_creation'] ?? 0) !== 1) {
        $findings[] = ldapConfigFinding(
            'enable_ad_user_auto_creation',
            LDAP_CHECK_INFO,
            'ldap_check_auto_creation_disabled'
        );
    }

    $allowedGroup = $get('ldap_allowed_login_group_dn');
    if ($allowedGroup !== '') {
        if (ldapConfigDnComponentsAreWellFormed(ldapConfigSplitDnComponents($allowedGroup)) === false) {
            $findings[] = ldapConfigFinding(
                'ldap_allowed_login_group_dn',
                LDAP_CHECK_ERROR,
                'ldap_check_allowed_group_malformed'
            );
        } else {
            $findings[] = ldapConfigFinding(
                'ldap_allowed_login_group_dn',
                LDAP_CHECK_INFO,
                'ldap_check_allowed_group_restriction'
            );
        }
    }

    return $findings;
}

/**
 * Highest severity present in a finding list.
 *
 * @param array<int, array{severity: string}> $findings Output of ldapConfigAudit()
 *
 * @return string LDAP_CHECK_ERROR|LDAP_CHECK_WARNING|LDAP_CHECK_INFO, '' when the list is empty
 */
function ldapConfigWorstSeverity(array $findings): string
{
    $worst = '';
    foreach ($findings as $finding) {
        if ($finding['severity'] === LDAP_CHECK_ERROR) {
            return LDAP_CHECK_ERROR;
        }
        if ($finding['severity'] === LDAP_CHECK_WARNING) {
            $worst = LDAP_CHECK_WARNING;
        } elseif ($worst === '') {
            $worst = LDAP_CHECK_INFO;
        }
    }

    return $worst;
}
