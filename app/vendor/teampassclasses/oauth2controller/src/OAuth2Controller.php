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
    protected $provider;
    protected $settings;

    public function __construct(array $settings)
    {
        // Initialize the settings property
        $this->settings = $settings;
        
        // MS Azure
        if (str_contains($settings['oauth2_client_endpoint'], 'login.microsoftonline.com')) {

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
    
                // Retrieve the groups the user belongs to
                $groupsUrl = 'https://graph.microsoft.com/v1.0/me/memberOf';
                $groupsResponse = $this->provider->getAuthenticatedRequest('GET', $groupsUrl, $token->getToken());
                $groups = $this->provider->getParsedResponse($groupsResponse);
                
                // Extract only the IDs and names of the groups if available
                $userGroups = [];
                if (isset($groups['value']) && is_array($groups['value'])) {
                    foreach ($groups['value'] as $group) {
                        $userGroups[] = [
                            'id' => $group['id'] ?? null,
                            'displayName' => $group['displayName'] ?? null,
                        ];
                    }
                }
            } else {
                // Get user infos from the UserInfo endpoint of the provider
                $resourceOwner = $this->provider->getResourceOwner($token);
                $user = self::mapGenericUserInfo($resourceOwner->toArray());
                $userGroups = $user['groups'];
            }

            // Return user information
            return [
                'error' => false,
                'userOauth2Info' => array_merge($user, array('groups' => $userGroups, 'oauth2TokenUsed' => false, 'oauth2LoginOngoing' => true)),
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
}
