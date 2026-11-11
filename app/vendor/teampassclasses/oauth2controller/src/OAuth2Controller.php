<?php

namespace TeampassClasses\OAuth2Controller;

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

use TheNetworg\OAuth2\Client\Provider\Azure;
use League\OAuth2\Client\Provider\GenericProvider;


/*** INFO - .htaccess modification ***
 * RewriteEngine On
 * RewriteRule ^login/azure$ /path/to/OAuth2Controller.php?method=redirect [L]
 * RewriteRule ^login/azure/callback$ /path/to/OAuth2Controller.php?method=callback [L]

 */
use Exception;

class OAuth2Controller
{
    /**
     * Issuer key of every Microsoft Entra ID identity. Entra object ids are GUIDs unique across
     * tenants, so the tenant adds nothing; it is not used because oauth2_tenant_id accepts the
     * tenant GUID or a domain, and switching between the two would unbind every account.
     */
    public const ENTRA_IDENTITY_ISSUER = 'entra';

    protected $provider;
    protected $settings;

    public function __construct(array $settings)
    {
        // Initialize the settings property
        $this->settings = $settings;
        
        // MS Azure
        if (self::isEntraEndpoint((string) $settings['oauth2_client_endpoint'])) {

            // Multi-tenant is not allowed
            if (empty($settings['oauth2_tenant_id']) || $settings['oauth2_tenant_id'] === 'common') {
                throw new Exception('Invalid tenant_id provided. Multi-tenant access is not allowed.');
            }

            // Using the v2.0 endpoint
            $this->provider = new Azure([
                'clientId'                => $settings['oauth2_client_id'],
                'clientSecret'            => $settings['oauth2_client_secret'],
                'tenant'                  => $settings['oauth2_tenant_id'],
                'redirectUri'             => rtrim($settings['cpassman_url'], '/').'/index.php?post_type=oauth2',
                'urlAuthorize'            => 'https://login.microsoftonline.com/' . $settings['oauth2_tenant_id'] . '/oauth2/v2.0/authorize', // Using the v2.0 endpoint
                'urlAccessToken'          => 'https://login.microsoftonline.com/' . $settings['oauth2_tenant_id'] . '/oauth2/v2.0/token',     // v2.0 endpoint for the token
                'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',  // Endpoint to get user info
                'scopes'                  => self::parseScopes((string) $settings['oauth2_client_scopes']),  // Scopes are defined in the settings
                'defaultEndPointVersion'  => '2.0',  // Endpoint version
            ]);

        // Generic Oauth2 provider
        } else {

            $this->provider = new GenericProvider([
                'clientId'                => $settings['oauth2_client_id'],
                'clientSecret'            => $settings['oauth2_client_secret'],
                'redirectUri'             => rtrim($settings['cpassman_url'], '/').'/index.php?post_type=oauth2',
                'urlAuthorize'            => $settings['oauth2_client_endpoint'],
                'urlAccessToken'          => $settings['oauth2_client_token'],
                'urlResourceOwnerDetails' => $settings['oauth2_client_urlResourceOwnerDetails'],
                'scopes'                  => self::parseScopes((string) $settings['oauth2_client_scopes']),
                // OAuth2 scopes are space-delimited (RFC 6749 §3.3); League defaults to ','
                'scopeSeparator'          => ' ',
            ]);
        }
    }

    /**
     * Tell whether the authorization endpoint is Microsoft Entra ID, which selects the Azure
     * provider (Graph, directory synchronization, "Test a user") instead of the generic one.
     *
     * @param string $authorizeUrl Setting oauth2_client_endpoint.
     * @return bool
     */
    public static function isEntraEndpoint(string $authorizeUrl): bool
    {
        return str_contains($authorizeUrl, 'login.microsoftonline.com');
    }

    /**
     * Split the comma-separated scope setting into a clean list.
     *
     * Spaces typed around the commas and empty entries are dropped, so that
     * "openid, profile," still requests exactly "openid profile".
     *
     * @param string $scopes Value of the oauth2_client_scopes setting.
     * @return array<int, string>
     */
    private static function parseScopes(string $scopes): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', $scopes)),
            static fn (string $scope): bool => $scope !== ''
        ));
    }

    public function redirect()
    {
        // Force a unique tenant by refusing any other configuration
        if ($this->provider instanceof Azure && $this->settings['oauth2_tenant_id'] === 'common') {
            throw new Exception('Multi-tenant access is not allowed. Tenant must be specified.');
        }

        // Force user to select account
        $options = [
            'prompt' => 'select_account'
        ];

        // Redirect the user to Azure AD for authentication
        $authUrl = $this->provider->getAuthorizationUrl($options);
        $_SESSION['oauth2state'] = $this->provider->getState();
        header('Location: ' . $authUrl);
        exit;
    }

    public function callback()
    {
        // CSRF state verification
        if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            exit('Invalid state');
        }

        try {
            // Exchange the authorization code for an access token
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            if ($this->provider instanceof Azure) {
                // Retrieve user information via Microsoft Graph
                $graphUrl = 'https://graph.microsoft.com/v1.0/me';
                $response = $this->provider->getAuthenticatedRequest('GET', $graphUrl, $token->getToken());
                $user = $this->provider->getParsedResponse($response);
    
                // Retrieve the groups the user belongs to (self-registration check, role matching)
                $userGroups = $this->entraUserGroups('me', $token->getToken());

                $identity = self::entraIdentity($user);
            } else {
                // Get user infos from the UserInfo endpoint of the provider
                $resourceOwner = $this->provider->getResourceOwner($token);
                $user = self::mapGenericUserInfo($resourceOwner->toArray());
                $userGroups = $user['groups'];

                $identity = self::genericIdentity(
                    $token->getValues(),
                    $user,
                    (string) ($this->settings['oauth2_client_endpoint'] ?? '')
                );
            }

            // Return user information. identityIssuer + identitySubject bind the TeamPass
            // account to this identity (users.oauth2_identity), never to the name claim.
            return [
                'error' => false,
                'userOauth2Info' => array_merge($user, array('groups' => $userGroups, 'oauth2TokenUsed' => false, 'oauth2LoginOngoing' => true), $identity),
            ];

        } catch (\Exception $e) {
            error_log('Unexpected error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'OAuth2 sign-in failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve the groups an Entra user belongs to, as {id, displayName} entries.
     *
     * Reads the direct and nested memberships (transitiveMemberOf) and follows the Graph
     * paging, so that the self-registration check sees a user in a group nested in the allowed
     * one, or in more groups than one page holds. Directory roles and administrative units are
     * not groups and are left out; an entry carrying no type is kept, as before.
     * Should the transitive query fail, the direct memberships (memberOf, read before this
     * method existed) are used instead, so a sign-in that worked keeps working.
     *
     * @param string $userPath    'me', or 'users/{object id}' with an application token
     * @param string $accessToken Access token for Microsoft Graph
     * @return array<int, array{id: mixed, displayName: mixed}>
     * @throws Exception When Graph refuses both queries
     */
    private function entraUserGroups(string $userPath, string $accessToken): array
    {
        $baseUrl = 'https://graph.microsoft.com/v1.0/' . $userPath;

        try {
            return $this->entraGroupsFromCollection($baseUrl . '/transitiveMemberOf', $accessToken);
        } catch (Exception $e) {
            error_log('TEAMPASS OAuth2 - nested group memberships unavailable, using direct memberships: ' . $e->getMessage());
        }

        return $this->entraGroupsFromCollection($baseUrl . '/memberOf', $accessToken);
    }

    /**
     * Read every page of a Graph collection of directory objects and keep the groups.
     *
     * The next page is only requested from Graph itself: the access token is never sent to a
     * host named by an answer.
     *
     * @param string $url         First page of the collection
     * @param string $accessToken Access token for Microsoft Graph
     * @return array<int, array{id: mixed, displayName: mixed}>
     * @throws Exception When Graph refuses a page
     */
    private function entraGroupsFromCollection(string $url, string $accessToken): array
    {
        $groups = [];
        $seen = [];
        // 100 objects per page by default: far beyond any real membership count
        for ($page = 0; $page < 100 && $url !== ''; $page++) {
            $response = $this->provider->getAuthenticatedRequest('GET', $url, $accessToken);
            $answer = $this->provider->getParsedResponse($response);

            foreach ((is_array($answer) && is_array($answer['value'] ?? null)) ? $answer['value'] : [] as $object) {
                if (is_array($object) === false
                    || (isset($object['@odata.type']) && $object['@odata.type'] !== '#microsoft.graph.group')
                ) {
                    continue;
                }

                // A group reached through several paths is listed once
                $id = $object['id'] ?? null;
                if (is_string($id) && $id !== '') {
                    if (isset($seen[$id]) === true) {
                        continue;
                    }
                    $seen[$id] = true;
                }

                $groups[] = [
                    'id' => $id,
                    'displayName' => $object['displayName'] ?? null,
                ];
            }

            $next = is_array($answer) && is_string($answer['@odata.nextLink'] ?? null) ? $answer['@odata.nextLink'] : '';
            $url = str_starts_with($next, 'https://graph.microsoft.com/') ? $next : '';
        }

        return $groups;
    }

    /**
     * Map the UserInfo claims of a generic OpenID Connect provider onto the keys
     * the TeamPass login flow reads, which follow the Microsoft Graph naming of Entra.
     *
     * - `preferred_username` becomes `userPrincipalName`: its part before '@' is the
     *   TeamPass login;
     * - `sub` (or `id`) must be present: the login page derives the account password
     *   from it, so it has to be stable for the account to open its keys;
     * - `groups` (standard claim) and `realm_access.roles` (Keycloak) are merged into a
     *   list of {id, displayName} entries, matched against the TeamPass role titles;
     * - `given_name` / `name`, `family_name` and `email` fill the names and the mail.
     *
     * @param array<string, mixed> $claims UserInfo response of the provider.
     * @return array<string, mixed> The claims completed with userPrincipalName,
     *                              givenname, surname, mail and a normalized `groups`.
     * @throws Exception When the provider returned no subject or no username.
     */
    public static function mapGenericUserInfo(array $claims): array
    {
        $subject = self::firstClaim($claims, ['sub', 'id']);
        if (trim($subject) === '') {
            throw new Exception('The identity provider returned no "sub" claim. Check that the "openid" scope is requested.');
        }

        $login = self::firstClaim($claims, ['preferred_username']);
        if (trim($login) === '') {
            throw new Exception('The identity provider returned no "preferred_username" claim. Check that the "profile" scope is requested.');
        }

        $user = $claims;
        $user['userPrincipalName'] = $login;
        $user['givenname'] = self::firstClaim($claims, ['given_name', 'givenName', 'name']);
        $user['surname'] = self::firstClaim($claims, ['family_name', 'surname']);
        $user['mail'] = self::firstClaim($claims, ['mail', 'email']);
        $user['groups'] = self::extractGenericGroups($claims);

        return $user;
    }

    /**
     * Identity Microsoft Graph asserts for the signed-in Entra user.
     *
     * The subject is the object id (`id` of `/me`), immutable for the life of the account.
     * GUIDs are case-insensitive, so it is lowercased.
     *
     * @param array<string, mixed> $user Answer of Graph `/me`.
     * @return array{identityIssuer: string, identitySubject: string} An empty subject when Graph
     *                                                                returned no object id.
     */
    public static function entraIdentity(array $user): array
    {
        return [
            'identityIssuer' => self::ENTRA_IDENTITY_ISSUER,
            'identitySubject' => strtolower(trim(self::firstClaim($user, ['id']))),
        ];
    }

    /**
     * Identity a generic OpenID Connect provider asserts for the signed-in user.
     *
     * - subject: the value mapGenericUserInfo() required (`sub`, else `id`);
     * - issuer: the `iss` claim of the ID token. The token comes straight from the token
     *   endpoint over TLS, which OpenID Connect Core §3.1.3.7 accepts in place of a signature
     *   check. A provider that returns no ID token (plain OAuth2) falls back to the origin of
     *   the authorization endpoint.
     *
     * @param array<string, mixed> $tokenValues  Extra values of the token response (id_token...).
     * @param array<string, mixed> $user         Result of mapGenericUserInfo().
     * @param string               $authorizeUrl Setting oauth2_client_endpoint.
     * @return array{identityIssuer: string, identitySubject: string}
     * @throws Exception When the ID token names another subject than the UserInfo answer
     *                   (OpenID Connect Core §5.3.2).
     */
    public static function genericIdentity(array $tokenValues, array $user, string $authorizeUrl): array
    {
        $subject = self::firstClaim($user, ['sub', 'id']);
        $idTokenClaims = self::idTokenClaims($tokenValues['id_token'] ?? null);

        $idTokenSubject = self::firstClaim($idTokenClaims, ['sub']);
        if ($idTokenSubject !== '' && $idTokenSubject !== $subject) {
            throw new Exception('The "sub" claim of the UserInfo answer does not match the ID token.');
        }

        $issuer = trim(self::firstClaim($idTokenClaims, ['iss']));

        return [
            'identityIssuer' => $issuer !== '' ? $issuer : self::urlOrigin($authorizeUrl),
            'identitySubject' => $subject,
        ];
    }

    /**
     * Claims of an ID token, read without verifying its signature (see genericIdentity()).
     *
     * @param mixed $idToken The id_token value of the token response.
     * @return array<string, mixed> The claims, or [] when the value is not a JWT.
     */
    private static function idTokenClaims($idToken): array
    {
        if (is_string($idToken) === false) {
            return [];
        }

        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            return [];
        }

        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $json = base64_decode($payload, true);
        if ($json === false) {
            return [];
        }

        $claims = json_decode($json, true);

        return is_array($claims) ? $claims : [];
    }

    /**
     * Origin (scheme://host[:port]) of a URL, lowercased.
     *
     * @param string $url Any absolute URL.
     * @return string The origin, or '' when the URL has no scheme or host.
     */
    private static function urlOrigin(string $url): string
    {
        $parts = parse_url(trim($url));
        if (is_array($parts) === false || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        return strtolower($parts['scheme']) . '://' . strtolower($parts['host'])
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    /**
     * Return the first non-empty string claim among the given names.
     *
     * @param array<string, mixed> $claims UserInfo claims.
     * @param array<int, string>   $names  Claim names, by order of preference.
     * @return string The claim value, or '' when none is a non-empty string.
     */
    private static function firstClaim(array $claims, array $names): string
    {
        foreach ($names as $name) {
            $value = $claims[$name] ?? null;
            if ((is_string($value) || is_int($value)) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    /**
     * Collect the groups of a generic provider user as {id, displayName} entries.
     *
     * Reads the standard `groups` claim and Keycloak's `realm_access.roles`. Each entry
     * may be a plain name or an object carrying `displayName` / `name` (and `id`).
     * Duplicates and unusable entries are dropped.
     *
     * @param array<string, mixed> $claims UserInfo claims.
     * @return array<int, array{id: string, displayName: string}>
     */
    private static function extractGenericGroups(array $claims): array
    {
        $sources = [];
        if (isset($claims['groups']) && is_array($claims['groups'])) {
            $sources[] = $claims['groups'];
        }
        if (isset($claims['realm_access']) && is_array($claims['realm_access'])
            && isset($claims['realm_access']['roles']) && is_array($claims['realm_access']['roles'])
        ) {
            $sources[] = $claims['realm_access']['roles'];
        }

        $groups = [];
        foreach ($sources as $source) {
            foreach ($source as $entry) {
                if (is_string($entry) || is_int($entry)) {
                    $name = trim((string) $entry);
                    $id = $name;
                } elseif (is_array($entry)) {
                    $name = trim(self::firstClaim($entry, ['displayName', 'name']));
                    $id = trim(self::firstClaim($entry, ['id']));
                    $id = $id === '' ? $name : $id;
                } else {
                    continue;
                }

                if ($name === '' || isset($groups[$name]) === true) {
                    continue;
                }
                $groups[$name] = ['id' => $id, 'displayName' => $name];
            }
        }

        return array_values($groups);
    }

    public function getAllGroups($token = null)
    {
        try {
            if (is_null($token)) {
                // Obtain a token with the appropriate scopes to access groups
                $token = $this->provider->getAccessToken('authorization_code', [
                    'code' => $_GET['code'],
                ]);
            }

            // Make a request to retrieve all groups
            $graphUrl = 'https://graph.microsoft.com/v1.0/groups';
            $response = $this->provider->getAuthenticatedRequest('GET', $graphUrl, $token->getToken());
            $groupsResponse  = $this->provider->getParsedResponse($response);

            // Initialize an array to store groups with id and displayName
            $groupsList = [];

            // Check if the response contains groups
            if (isset($groupsResponse['value']) && is_array($groupsResponse['value'])) {
                foreach ($groupsResponse['value'] as $group) {
                    // Extract the ID and displayName of each group
                    $groupsList[] = [
                        'id' => $group['id'] ?? null, // Use null coalescing operator to avoid errors
                        'displayName' => $group['displayName'] ?? null,
                    ];
                }
            }

            // Return the list of groups with id and displayName
            return $groupsList;

        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => 'Error while getting groups: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve every directory user, with the groups they belong to.
     *
     * Always returns a well-formed envelope so that a failure can never be
     * mistaken for a user list by the caller.
     *
     * @param string|null $token Unused, kept for backward compatibility.
     * @return array{error: bool, message: string, users: array<int, array<string, mixed>>}
     */
    public function getAllUsers($token = null): array
    {
        // Directory synchronization is only implemented for Microsoft Entra ID.
        if (($this->provider instanceof Azure) === false) {
            return [
                'error' => true,
                'message' => 'User synchronization is only supported with Microsoft Entra ID (Azure).',
                'users' => [],
            ];
        }

        try {
            // Exchange the authorization code for an access token
            $token = $this->provider->getAccessToken('client_credentials', [
                'scope' => 'https://graph.microsoft.com/.default',
            ]);

            // Call Graph API to retrieve users
            $graphUrl = 'https://graph.microsoft.com/v1.0/users?$select=id,displayName,userPrincipalName,givenName,surname,mail,accountEnabled,lastPasswordChangeDateTime&$top=999';
            $usersList = [];

            // Graph paginates its collections: follow @odata.nextLink until exhausted,
            // otherwise only the first page of the directory would ever be synchronized.
            while (empty($graphUrl) === false) {
                $response = $this->provider->getAuthenticatedRequest('GET', $graphUrl, $token->getToken());
                $usersResponse = $this->provider->getParsedResponse($response);

                if (isset($usersResponse['value']) && is_array($usersResponse['value'])) {
                    foreach ($usersResponse['value'] as $user) {
                        // Ignore anything that is not a usable directory object
                        if (is_array($user) === false || empty($user['id']) === true) {
                            continue;
                        }

                        // Add user with their groups to the list
                        $usersList[] = [
                            'id' => $user['id'],
                            'displayName' => $user['displayName'] ?? null,
                            'userPrincipalName' => $user['userPrincipalName'] ?? null,
                            'givenName' => $user['givenName'] ?? null,
                            'surname' => $user['surname'] ?? null,
                            'mail' => $user['mail'] ?? null,
                            'accountEnabled' => $user['accountEnabled'] ?? null,
                            'lastPasswordChangeDateTime' => $user['lastPasswordChangeDateTime'] ?? null,
                            'groups' => $this->getUserGroups((string) $user['id'], $token->getToken()),
                        ];
                    }
                }

                $graphUrl = (string) ($usersResponse['@odata.nextLink'] ?? '');
            }

            return [
                'error' => false,
                'message' => '',
                'users' => $usersList,
            ];

        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => 'Error while getting users: ' . $e->getMessage(),
                'users' => [],
            ];
        }
    }

    /**
     * Retrieve the groups a single directory user belongs to.
     *
     * A failure on one user must not abort the whole synchronization, so the
     * error is swallowed and an empty group list is returned for that user.
     *
     * @param string $userId      Directory object id of the user.
     * @param string $accessToken Access token to use for the Graph call.
     * @return array<int, array{id: string|null, displayName: string|null}>
     */
    private function getUserGroups(string $userId, string $accessToken): array
    {
        $userGroups = [];

        try {
            $groupsUrl = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($userId) . '/memberOf';
            $groupsRequest = $this->provider->getAuthenticatedRequest('GET', $groupsUrl, $accessToken);
            $groupsResponse = $this->provider->getParsedResponse($groupsRequest);

            if (isset($groupsResponse['value']) && is_array($groupsResponse['value'])) {
                foreach ($groupsResponse['value'] as $group) {
                    if (is_array($group) === true && ($group['@odata.type'] ?? '') === '#microsoft.graph.group') {
                        $userGroups[] = [
                            'id' => $group['id'] ?? null,
                            'displayName' => $group['displayName'] ?? null,
                        ];
                    }
                }
            }
        } catch (Exception $e) {
            error_log('TEAMPASS OAuth2 - unable to get groups of user ' . $userId . ': ' . $e->getMessage());
        }

        return $userGroups;
    }

    /**
     * Directory users who sign in to TeamPass with a given login, with their groups.
     *
     * The TeamPass login of an Entra user is the part of the UPN before '@', so every user whose
     * UPN starts with "<login>@" reaches the same TeamPass account: all of them are returned
     * (at most 10), the one whose full UPN was given first. Their groups are read by
     * entraUserGroups(), the method of the sign-in, so that the "Test a user" check of the OAuth
     * page sees what the sign-in sees.
     *
     * The directory is read as the application (client credentials grant), like getAllUsers():
     * this needs the Application permissions User.Read.All and Group.Read.All.
     *
     * @param string $loginOrUpn TeamPass login, or full UPN of the user
     * @return array{error: bool, message: string, code: int, users: array<int, array<string, mixed>>}
     *         `code` is the HTTP status Graph answered a failure with (403: permission missing)
     */
    public function describeDirectoryUsers(string $loginOrUpn): array
    {
        if (($this->provider instanceof Azure) === false) {
            return ['error' => true, 'message' => 'Only supported with Microsoft Entra ID (Azure).', 'code' => 0, 'users' => []];
        }

        $upn = trim($loginOrUpn);
        $localPart = strstr($upn, '@', true);
        $login = $localPart === false ? $upn : $localPart;
        if ($login === '') {
            return ['error' => true, 'message' => 'No login given.', 'code' => 0, 'users' => []];
        }

        try {
            $token = $this->applicationToken();
            $url = 'https://graph.microsoft.com/v1.0/users?$filter='
                . rawurlencode("startswith(userPrincipalName,'" . self::odataString($login . '@') . "')")
                . '&$select=id,displayName,userPrincipalName,accountEnabled&$top=10';
            $answer = $this->provider->getParsedResponse(
                $this->provider->getAuthenticatedRequest('GET', $url, $token)
            );

            $users = [];
            foreach ((is_array($answer) && is_array($answer['value'] ?? null)) ? $answer['value'] : [] as $user) {
                $principalName = is_array($user) ? ($user['userPrincipalName'] ?? null) : null;
                if (is_string($principalName) === false || empty($user['id']) === true) {
                    continue;
                }
                // Defensive: only the UPNs whose part before '@' is the login, as the sign-in reads it
                $userLocalPart = strstr($principalName, '@', true);
                if ($userLocalPart === false || mb_strtolower($userLocalPart) !== mb_strtolower($login)) {
                    continue;
                }

                $users[] = [
                    'id' => (string) $user['id'],
                    'displayName' => $user['displayName'] ?? null,
                    'userPrincipalName' => $principalName,
                    'accountEnabled' => $user['accountEnabled'] ?? null,
                    'groups' => $this->entraUserGroups('users/' . rawurlencode((string) $user['id']), $token),
                ];
            }

            // The user whose UPN was given comes first
            usort(
                $users,
                static fn (array $a, array $b): int => (int) (strcasecmp($b['userPrincipalName'], $upn) === 0)
                    <=> (int) (strcasecmp($a['userPrincipalName'], $upn) === 0)
            );

            return ['error' => false, 'message' => '', 'code' => 0, 'users' => $users];
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => 'Error while reading the directory: ' . $e->getMessage(),
                'code' => (int) $e->getCode(),
                'users' => [],
            ];
        }
    }

    /**
     * Look up the directory groups each entry of the self-registration list names.
     *
     * An entry shaped like an object id is read as an id; any other entry is a display name,
     * which Entra does not keep unique: several groups may answer, and the members of each of
     * them may then self-register. An entry nothing answers is a typo, or a group removed.
     *
     * @param array<int, string> $entries Entries of oauth_self_register_groups, '*' left out
     * @return array{error: bool, message: string, code: int, groups: array<int, array{entry: string, matches: array<int, array{id: mixed, displayName: mixed}>}>}
     */
    public function checkGroupNames(array $entries): array
    {
        if (($this->provider instanceof Azure) === false) {
            return ['error' => true, 'message' => 'Only supported with Microsoft Entra ID (Azure).', 'code' => 0, 'groups' => []];
        }

        try {
            $token = $this->applicationToken();
            $groups = [];
            foreach ($entries as $entry) {
                $groups[] = ['entry' => $entry, 'matches' => $this->directoryGroupsNamed($entry, $token)];
            }

            return ['error' => false, 'message' => '', 'code' => 0, 'groups' => $groups];
        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => 'Error while reading the directory: ' . $e->getMessage(),
                'code' => (int) $e->getCode(),
                'groups' => [],
            ];
        }
    }

    /**
     * Groups of the directory an entry of the self-registration list names.
     *
     * @param string $entry       Object id or display name
     * @param string $accessToken Application token for Microsoft Graph
     * @return array<int, array{id: mixed, displayName: mixed}>
     * @throws Exception When Graph refuses the query (a group id it does not know excepted)
     */
    private function directoryGroupsNamed(string $entry, string $accessToken): array
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $entry) === 1) {
            try {
                $group = $this->provider->getParsedResponse($this->provider->getAuthenticatedRequest(
                    'GET',
                    'https://graph.microsoft.com/v1.0/groups/' . rawurlencode($entry) . '?$select=id,displayName',
                    $accessToken
                ));
            } catch (Exception $e) {
                if ((int) $e->getCode() === 404) {
                    return [];
                }
                throw $e;
            }

            return is_array($group) && isset($group['id'])
                ? [['id' => $group['id'], 'displayName' => $group['displayName'] ?? null]]
                : [];
        }

        $answer = $this->provider->getParsedResponse($this->provider->getAuthenticatedRequest(
            'GET',
            'https://graph.microsoft.com/v1.0/groups?$filter='
                . rawurlencode("displayName eq '" . self::odataString($entry) . "'")
                . '&$select=id,displayName&$top=10',
            $accessToken
        ));

        $matches = [];
        foreach ((is_array($answer) && is_array($answer['value'] ?? null)) ? $answer['value'] : [] as $group) {
            if (is_array($group) && isset($group['id'])) {
                $matches[] = ['id' => $group['id'], 'displayName' => $group['displayName'] ?? null];
            }
        }

        return $matches;
    }

    /**
     * Access token of the application itself for Microsoft Graph (client credentials grant).
     *
     * @return string
     */
    private function applicationToken(): string
    {
        return $this->provider->getAccessToken('client_credentials', [
            'scope' => 'https://graph.microsoft.com/.default',
        ])->getToken();
    }

    /**
     * Escape a value for a string literal of a Graph $filter (OData doubles single quotes).
     *
     * @param string $value Raw value
     * @return string
     */
    private static function odataString(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
