<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/sources/ldap_config_logic.php';

/**
 * The LDAP setup page accepted values that look right and are not, then answered
 * "User is successfully authenticated" while no user could log in:
 *
 *  - "User Object Filter" holding an attribute name (samaccountname) instead of an LDAP filter;
 *  - "Additional User DN" holding a full DN, which TeamPass concatenates with the base DN again,
 *    so the search targets "OU=x,DC=a,DC=b,DC=a,DC=b";
 *  - "User name attribute" left at the string '0' the installer seeds, which the login read raw.
 *
 * These tests pin the decision rules and the sharing of the authentication code between the login
 * and the page test.
 */
class LdapConfigChecksTest extends TestCase
{
    private static function source(string $relativePath): string
    {
        $content = file_get_contents(__DIR__ . '/../../' . $relativePath);
        self::assertNotFalse($content, $relativePath . ' must be readable');

        return (string) $content;
    }

    /**
     * @param array<int, array{field: string, severity: string, code: string, suggestion: string}> $findings
     * @return array<int, string>
     */
    private static function codes(array $findings): array
    {
        return array_map(static fn(array $finding): string => $finding['code'], $findings);
    }

    // -----------------------------------------------------------------------
    // CHECK 1 - User Object Filter
    // -----------------------------------------------------------------------

    public function testAnEmptyUserObjectFilterIsValid(): void
    {
        self::assertSame([], ldapConfigValidateUserObjectFilter('', 'ActiveDirectory'));
        self::assertSame([], ldapConfigValidateUserObjectFilter('   ', 'OpenLDAP'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validFilterProvider(): array
    {
        return [
            'single assertion' => ['(objectClass=user)'],
            'AD conjunction' => ['(&(objectCategory=person)(objectClass=user))'],
            'disjunction' => ['(|(objectClass=user)(objectClass=person))'],
            'negation' => ['(!(userAccountControl:1.2.840.113556.1.4.803:=2))'],
            'two top level filters' => ['(objectCategory=Person),(sAMAccountName=*)'],
            'dn value holding commas' => ['(memberOf=CN=Team,OU=Groups,DC=example,DC=com)'],
        ];
    }

    /**
     * @dataProvider validFilterProvider
     */
    public function testValidUserObjectFiltersRaiseNothing(string $filter): void
    {
        self::assertSame([], ldapConfigValidateUserObjectFilter($filter, 'ActiveDirectory'), $filter);
    }

    public function testAnAttributeNameIsReportedWithADirectoryAwareSuggestion(): void
    {
        $findings = ldapConfigValidateUserObjectFilter('samaccountname', 'ActiveDirectory');

        self::assertCount(1, $findings);
        self::assertSame('ldap_user_object_filter', $findings[0]['field']);
        self::assertSame(LDAP_CHECK_ERROR, $findings[0]['severity']);
        self::assertSame('ldap_check_filter_is_attribute', $findings[0]['code']);
        self::assertSame('(&(objectCategory=person)(objectClass=user))', $findings[0]['suggestion']);

        $openLdap = ldapConfigValidateUserObjectFilter('uid', 'OpenLDAP');
        self::assertSame('(objectClass=inetOrgPerson)', $openLdap[0]['suggestion']);
    }

    public function testAnAssertionWithoutParenthesesIsWrappedInTheSuggestion(): void
    {
        $findings = ldapConfigValidateUserObjectFilter('objectClass=user', 'OpenLDAP');

        self::assertSame(['ldap_check_filter_not_parenthesized'], self::codes($findings));
        self::assertSame('(objectClass=user)', $findings[0]['suggestion']);
    }

    public function testSeveralAssertionsWithoutParenthesesAreCombinedWithAnAnd(): void
    {
        $findings = ldapConfigValidateUserObjectFilter('objectCategory=person,objectClass=user', 'ActiveDirectory');

        self::assertSame(['ldap_check_filter_not_parenthesized'], self::codes($findings));
        self::assertSame('(&(objectCategory=person)(objectClass=user))', $findings[0]['suggestion']);
    }

    public function testUnbalancedParenthesesAreReportedWithoutASuggestion(): void
    {
        foreach (['(objectClass=user', 'objectClass=user)', '(&(objectClass=user)'] as $filter) {
            $findings = ldapConfigValidateUserObjectFilter($filter, 'ActiveDirectory');
            self::assertNotSame([], $findings, $filter . ' must be rejected');
            self::assertSame('', $findings[0]['suggestion'], $filter . ' has no unambiguous correction');
        }
    }

    public function testTwoSiblingFiltersGluedTogetherAreRejected(): void
    {
        // "(a=1)(b=2)" is not a filter: it needs an operator, "(&(a=1)(b=2))".
        self::assertSame(
            ['ldap_check_filter_unbalanced'],
            self::codes(ldapConfigValidateUserObjectFilter('(objectClass=user)(objectCategory=person)', 'ActiveDirectory'))
        );
    }

    // -----------------------------------------------------------------------
    // CHECK 2 - Additional User DN
    // -----------------------------------------------------------------------

    public function testAnEmptyAdditionalUserDnIsValid(): void
    {
        self::assertSame([], ldapConfigValidateAdditionalUserDn('', 'DC=example,DC=com'));
    }

    public function testARelativeAdditionalUserDnIsValid(): void
    {
        self::assertSame([], ldapConfigValidateAdditionalUserDn('OU=Users', 'DC=example,DC=com'));
        self::assertSame([], ldapConfigValidateAdditionalUserDn('OU=Staff,OU=Users', 'DC=example,DC=com'));
    }

    public function testTheBaseDnSuffixIsReportedAndStrippedInTheSuggestion(): void
    {
        $findings = ldapConfigValidateAdditionalUserDn(
            'OU=uzytkownicy,DC=szym-it,DC=lan',
            'DC=szym-it,DC=lan'
        );

        self::assertCount(1, $findings);
        self::assertSame('ldap_dn_additional_user_dn', $findings[0]['field']);
        self::assertSame(LDAP_CHECK_ERROR, $findings[0]['severity']);
        self::assertSame('ldap_check_additional_dn_contains_base', $findings[0]['code']);
        self::assertSame('OU=uzytkownicy', $findings[0]['suggestion']);
    }

    public function testTheBaseDnComparisonIgnoresCaseAndSpacing(): void
    {
        $findings = ldapConfigValidateAdditionalUserDn(
            'ou=Users, dc=Example, dc=COM',
            'DC=example,DC=com'
        );

        self::assertSame(['ldap_check_additional_dn_contains_base'], self::codes($findings));
        self::assertSame('ou=Users', $findings[0]['suggestion']);
    }

    public function testTheBaseDnAloneLeavesNothingToSearch(): void
    {
        self::assertSame(
            ['ldap_check_additional_dn_is_base'],
            self::codes(ldapConfigValidateAdditionalUserDn('DC=example,DC=com', 'dc=example,dc=com'))
        );
    }

    public function testForeignDomainComponentsAreReportedWithoutACorrection(): void
    {
        $findings = ldapConfigValidateAdditionalUserDn('OU=Users,DC=other,DC=tld', 'DC=example,DC=com');

        self::assertSame(['ldap_check_additional_dn_has_domain_components'], self::codes($findings));
        self::assertSame('', $findings[0]['suggestion']);
    }

    public function testAMalformedAdditionalUserDnIsReported(): void
    {
        self::assertSame(
            ['ldap_check_additional_dn_malformed'],
            self::codes(ldapConfigValidateAdditionalUserDn('Users', 'DC=example,DC=com'))
        );
    }

    public function testAStrayCommaIsCorrected(): void
    {
        $findings = ldapConfigValidateAdditionalUserDn('OU=Users,', 'DC=example,DC=com');

        self::assertSame(['ldap_check_additional_dn_extra_comma'], self::codes($findings));
        self::assertSame('OU=Users', $findings[0]['suggestion']);
    }

    public function testAnEscapedCommaBelongsToItsValue(): void
    {
        self::assertSame(
            ['OU=Doe\\, John', 'OU=Users'],
            ldapConfigSplitDnComponents('OU=Doe\\, John,OU=Users')
        );
    }

    // -----------------------------------------------------------------------
    // The filter builder the admin Users page consumes
    // -----------------------------------------------------------------------

    public function testTheObjectFilterBuilderKeptItsBehaviourAfterTheMove(): void
    {
        self::assertSame('', tpLdapBuildObjectFilter(''));
        self::assertSame('(objectClass=user)', tpLdapBuildObjectFilter('(objectClass=user)'));
        self::assertSame(
            '(&(objectCategory=Person)(sAMAccountName=*))',
            tpLdapBuildObjectFilter('(objectCategory=Person),(sAMAccountName=*)')
        );
        self::assertSame(
            '(memberOf=CN=Team,OU=Groups)',
            tpLdapBuildObjectFilter('(memberOf=CN=Team,OU=Groups)')
        );
        self::assertSame('(objectClass=user)', tpLdapBuildObjectFilter('(objectClass=user),'));
    }

    public function testTheBuilderLivesWithTheValidatorSoBothSplitAlike(): void
    {
        self::assertStringNotContainsString(
            'function tpLdapBuildObjectFilter',
            self::source('app/sources/users.queries.php'),
            'The builder must not be duplicated next to its consumer'
        );
        self::assertStringContainsString(
            'function tpLdapBuildObjectFilter',
            self::source('app/sources/ldap_config_logic.php')
        );
    }

    // -----------------------------------------------------------------------
    // The full audit
    // -----------------------------------------------------------------------

    /**
     * @return array<string, string>
     */
    private static function workingSettings(): array
    {
        return [
            'ldap_mode' => '1',
            'ldap_type' => 'ActiveDirectory',
            'ldap_hosts' => 'dc01.example.com',
            'ldap_port' => '389',
            'ldap_bdn' => 'DC=example,DC=com',
            'ldap_username' => 'CN=svc,DC=example,DC=com',
            'ldap_password' => 'secret',
            'ldap_user_attribute' => 'samaccountname',
            'ldap_user_object_filter' => '(&(objectCategory=person)(objectClass=user))',
            'ldap_dn_additional_user_dn' => 'OU=Users',
            'ldap_ssl' => '0',
            'ldap_tls' => '0',
            'enable_ad_user_auto_creation' => '1',
            'ldap_allowed_login_group_dn' => '',
        ];
    }

    public function testAWorkingConfigurationRaisesNothing(): void
    {
        self::assertSame([], ldapConfigAudit(self::workingSettings()));
        self::assertSame('', ldapConfigWorstSeverity([]));
    }

    public function testTheSeededZeroValueIsTreatedAsNotConfigured(): void
    {
        // run.step5.php seeds ldap_user_attribute and ldap_type with the string '0'. No `?? ` and
        // no isset() default replaces it, so the login searched the attribute named "0".
        $settings = self::workingSettings();
        $settings['ldap_user_attribute'] = '0';

        self::assertContains('ldap_check_user_attribute_empty', self::codes(ldapConfigAudit($settings)));

        $settings['ldap_type'] = '0';
        $codes = self::codes(ldapConfigAudit($settings));
        self::assertContains('ldap_check_type_empty', $codes);
        self::assertNotContains('ldap_check_type_unsupported', $codes);
    }

    public function testAnUnconfiguredUserAttributeOnlySurvivesOnActiveDirectory(): void
    {
        // ldapResolveUserAttribute() falls back to samaccountname, which exists nowhere else.
        $ad = self::workingSettings();
        $ad['ldap_user_attribute'] = '';
        $findings = array_values(array_filter(
            ldapConfigAudit($ad),
            static fn(array $f): bool => $f['code'] === 'ldap_check_user_attribute_empty'
        ));
        self::assertSame(LDAP_CHECK_WARNING, $findings[0]['severity']);
        self::assertSame('samaccountname', $findings[0]['suggestion']);

        $openLdap = $ad;
        $openLdap['ldap_type'] = 'OpenLDAP';
        $findings = array_values(array_filter(
            ldapConfigAudit($openLdap),
            static fn(array $f): bool => $f['code'] === 'ldap_check_user_attribute_empty'
        ));
        self::assertSame(LDAP_CHECK_ERROR, $findings[0]['severity']);
        self::assertSame('uid', $findings[0]['suggestion']);
    }

    public function testFreeIpaIsReportedBecauseTheConnectionBuilderThrowsOnIt(): void
    {
        $settings = self::workingSettings();
        $settings['ldap_type'] = 'FreeIPA';

        self::assertContains('ldap_check_type_unsupported', self::codes(ldapConfigAudit($settings)));
        self::assertSame(['ActiveDirectory', 'OpenLDAP'], ldapConfigSupportedTypes());
    }

    public function testTheTicketConfigurationIsFullyReported(): void
    {
        $settings = self::workingSettings();
        $settings['ldap_user_object_filter'] = 'samaccountname';
        $settings['ldap_dn_additional_user_dn'] = 'OU=uzytkownicy,DC=szym-it,DC=lan';
        $settings['ldap_bdn'] = 'DC=szym-it,DC=lan';

        $codes = self::codes(ldapConfigAudit($settings));
        self::assertContains('ldap_check_filter_is_attribute', $codes);
        self::assertContains('ldap_check_additional_dn_contains_base', $codes);
        self::assertSame(LDAP_CHECK_ERROR, ldapConfigWorstSeverity(ldapConfigAudit($settings)));
    }

    public function testTheGatesThatRefuseAnAcceptedPasswordAreReported(): void
    {
        $settings = self::workingSettings();
        $settings['ldap_mode'] = '0';
        $settings['enable_ad_user_auto_creation'] = '0';
        $settings['ldap_allowed_login_group_dn'] = 'CN=TeamPass,OU=Groups,DC=example,DC=com';

        $codes = self::codes(ldapConfigAudit($settings));
        self::assertContains('ldap_check_mode_disabled', $codes);
        self::assertContains('ldap_check_auto_creation_disabled', $codes);
        self::assertContains('ldap_check_allowed_group_restriction', $codes);
        // None of them is an error: the configuration is legitimate, it just explains a refusal.
        self::assertSame(LDAP_CHECK_WARNING, ldapConfigWorstSeverity(ldapConfigAudit($settings)));
    }

    public function testTransportInconsistenciesAreReported(): void
    {
        $settings = self::workingSettings();
        $settings['ldap_ssl'] = '1';
        $settings['ldap_tls'] = '1';

        $codes = self::codes(ldapConfigAudit($settings));
        self::assertContains('ldap_check_ssl_and_tls', $codes);
        self::assertContains('ldap_check_ssl_port_mismatch', $codes);

        $settings = self::workingSettings();
        $settings['ldap_port'] = '636';
        self::assertContains('ldap_check_plain_port_mismatch', self::codes(ldapConfigAudit($settings)));
    }

    // -----------------------------------------------------------------------
    // The page test must be the login, not a copy of it
    // -----------------------------------------------------------------------

    public function testTheHandlerNoLongerCarriesItsOwnSearchAndBind(): void
    {
        $handler = self::source('app/sources/ldap.queries.php');

        self::assertStringContainsString('ldapRunConfigurationTest(', $handler);
        foreach (['->firstOrFail()', 'auth()->attempt(', 'userIsEnabled(', 'shadowexpire'] as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $handler,
                'The page test must not reimplement the login: ' . $needle
            );
        }
    }

    public function testTheLoginAndTheTestShareTheSameDirectoryFunctions(): void
    {
        $shared = self::source('app/sources/ldap.functions.php');
        foreach ([
            'function initializeLdapConnection(',
            'function authenticateUser(',
            'function isAccountExpired(',
            'function getUserADGroups(',
            'function ldapUserIsInAllowedLoginGroup(',
            'function ldapResolveUserAttribute(',
        ] as $needle) {
            self::assertStringContainsString($needle, $shared);
        }

        $identify = self::source('app/sources/identify.php');
        foreach ([
            'function initializeLdapConnection(',
            'function authenticateUser(',
            'function isAccountExpired(',
            'function getUserADGroups(',
        ] as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $identify,
                'A second copy in identify.php would let the two paths drift apart again'
            );
        }
        self::assertStringContainsString('ldapUserIsInAllowedLoginGroup(', $identify);

        $bootstrap = self::source('app/sources/main.functions.php');
        self::assertStringContainsString("require_once __DIR__ . '/ldap.functions.php';", $bootstrap);
        self::assertStringContainsString("require_once __DIR__ . '/ldap_config_logic.php';", $bootstrap);
    }

    public function testNoConsumerReadsTheUserAttributeSettingRaw(): void
    {
        // The raw read is what made the page test pass while the login could not find anybody.
        foreach ([
            'app/sources/users.queries.php',
            'app/sources/main.functions.php',
            'app/sources/identify.php',
        ] as $path) {
            self::assertStringNotContainsString(
                "\$SETTINGS['ldap_user_attribute']",
                self::source($path),
                $path . ' must resolve the attribute through ldapResolveUserAttribute()'
            );
        }

        // The setting itself is only ever read by the two resolvers.
        $shared = self::source('app/sources/ldap.functions.php');
        self::assertSame(
            2,
            substr_count($shared, "\$SETTINGS['ldap_user_attribute']"),
            'Only ldapResolveUserAttribute() and ldapUserAttributeIsConfigured() read the raw setting'
        );
    }

    public function testTheTestRunnerPerformsNoWrite(): void
    {
        $shared = self::source('app/sources/ldap.functions.php');
        $start = strpos($shared, 'function ldapRunConfigurationTest(');
        self::assertIsInt($start);
        $runner = substr($shared, (int) $start);

        foreach (['DB::insert', 'DB::update', 'DB::delete', 'setUserRoles(', 'externalAdCreateUser(', 'handleUserKeys('] as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $runner,
                'The configuration test must never write: ' . $needle
            );
        }
    }

    public function testEveryFindingAndStepCodeIsTranslated(): void
    {
        $english = include __DIR__ . '/../../app/includes/language/english.php';
        $french = include __DIR__ . '/../../app/includes/language/french.php';

        $codes = [];
        foreach ([self::source('app/sources/ldap_config_logic.php'), self::source('app/sources/ldap.functions.php')] as $source) {
            preg_match_all("/'(ldap_check_[a-z0-9_]+|ldap_step_[a-z0-9_]+)'/", $source, $matches);
            $codes = array_merge($codes, $matches[1]);
        }
        $codes = array_values(array_unique($codes));
        self::assertNotSame([], $codes);

        foreach ($codes as $code) {
            self::assertArrayHasKey($code, $english, $code . ' is missing from english.php');
            self::assertArrayHasKey($code, $french, $code . ' is missing from french.php');
        }
    }
}
