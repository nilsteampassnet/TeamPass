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
 * @copyright 2009-2024 Teampass.net
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
                'redirectUri'             => $settings['cpassman_url'].'/index.php?post_type=oauth2',
                'urlAuthorize'            => 'https://login.microsoftonline.com/' . $settings['oauth2_tenant_id'] . '/oauth2/v2.0/authorize', // Using the v2.0 endpoint
                'urlAccessToken'          => 'https://login.microsoftonline.com/' . $settings['oauth2_tenant_id'] . '/oauth2/v2.0/token',     // v2.0 endpoint for the token
                'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',  // Endpoint to get user info
                'scopes'                  => explode(",", $settings['oauth2_client_scopes']),  // Scopes are defined in the settings
                'defaultEndPointVersion'  => '2.0',  // Endpoint version
            ]);

        // Generic Oauth2 provider
        } else {

            $this->provider = new GenericProvider([
                'clientId'                => $settings['oauth2_client_id'],
                'clientSecret'            => $settings['oauth2_client_secret'],
                'redirectUri'             => $settings['cpassman_url'].'/index.php?post_type=oauth2',
                'urlAuthorize'            => $settings['oauth2_client_endpoint'],
                'urlAccessToken'          => $settings['oauth2_client_token'],
                'urlResourceOwnerDetails' => $settings['oauth2_client_urlResourceOwnerDetails'],
                'scopes'                  => explode(",", $settings['oauth2_client_scopes']),
            ]);
        }
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
                // Get user infos
                $resourceOwner = $this->provider->getResourceOwner($token);
                $user = $resourceOwner->toArray();
                $userGroups = $user['realm_access']['roles'];
                $user['userPrincipalName'] = $user['preferred_username'];
                $user['givenname'] = $user['given_name'];
                $user['surname'] = $user['family_name'];
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
                'message' => 'Error retrieving token: ' . $e->getMessage(),
            ];
        }
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
}
