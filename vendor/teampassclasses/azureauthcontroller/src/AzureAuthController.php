<?php

namespace TeampassClasses\AzureAuthController;

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
//use League\OAuth2\Client\Token\AccessToken;

/*** INFO - .htaccess modification ***
 * RewriteEngine On
 * RewriteRule ^login/azure$ /path/to/AzureAuthController.php?method=redirect [L]
 * RewriteRule ^login/azure/callback$ /path/to/AzureAuthController.php?method=callback [L]

 */
use Exception;

class AzureAuthController
{
    protected $provider;
    protected $settings;

    public function __construct(array $settings)
    {
        // Utilisation du point de terminaison v2.0

        $this->provider = new Azure([
            'clientId'                => $settings['oauth2_client_id'],
            'clientSecret'            => $settings['oauth2_client_secret'],
            'redirectUri'             => $settings['cpassman_url'].'/index.php?post_type=oauth2',
            'urlAuthorize'            => 'https://login.microsoftonline.com/' . $settings['oauth2_tenant_id'] . '/oauth2/v2.0/authorize', // Utilisation du endpoint v2.0
            'urlAccessToken'          => 'https://login.microsoftonline.com/' . $settings['oauth2_tenant_id'] . '/oauth2/v2.0/token',     // Endpoint v2.0 pour le token
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',  // Endpoint pour obtenir les infos de l'utilisateur
            'scopes'                  => explode(",", $settings['oauth2_client_scopes']),  // Les scopes sont définis dans les paramètres
            'defaultEndPointVersion'  => '2.0',  // Version du point de terminaison
        ]);
    }

    public function redirect()
    {
        // Force user to select account
        $options = [
            'prompt' => 'select_account'
        ];

        // Rediriger l'utilisateur vers Azure AD pour l'authentification
        $authUrl = $this->provider->getAuthorizationUrl($options);
        $_SESSION['oauth2state'] = $this->provider->getState();
        header('Location: ' . $authUrl);
        exit;
    }

    public function callback()
    {
        // Vérification de l'état CSRF
        if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            exit('État invalide');
        }

        try {
            // Échanger le code d'autorisation contre un token d'accès
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);
            //error_log('Token récupéré : ' . print_r($token, true));

            // Récupérer les informations de l'utilisateur via Microsoft Graph
            $graphUrl = 'https://graph.microsoft.com/v1.0/me';
            $response = $this->provider->getAuthenticatedRequest('GET', $graphUrl, $token->getToken());
            $user = $this->provider->getParsedResponse($response);

            //error_log('Utilisateur récupéré : ' . print_r($user, true));

            // Récupérer les groupes auxquels l'utilisateur appartient
            $groupsUrl = 'https://graph.microsoft.com/v1.0/me/memberOf';
            $groupsResponse = $this->provider->getAuthenticatedRequest('GET', $groupsUrl, $token->getToken());
            $groups = $this->provider->getParsedResponse($groupsResponse);
            
            // Extraire uniquement les IDs et noms des groupes si disponibles
            $userGroups = [];
            if (isset($groups['value']) && is_array($groups['value'])) {
                foreach ($groups['value'] as $group) {
                    $userGroups[] = [
                        'id' => $group['id'] ?? null,
                        'displayName' => $group['displayName'] ?? null,
                    ];
                }
            }
            //error_log('Utilisateur  : ' . print_r(array_merge($user, array('groups' => $userGroups)), true));

            // Get groups
            //$groups = $this->getAllGroups($token);
            //error_log('Groupes récupérés : ' . print_r($groups, true));

            // Retourner les informations de l'utilisateur
            return [
                'error' => false,
                'userOauth2Info' => array_merge($user, array('groups' => $userGroups, 'oauth2TokenUsed' => false, 'oauth2LoginOngoing' => true)),
            ];

        } catch (\Exception $e) {
            error_log('Erreur inattendue : ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Erreur lors de la récupération du token : ' . $e->getMessage(),
            ];
        }
    }

    public function getAllGroups($token = null)
    {
        try {
            if (is_null($token)) {
                // Obtenir un token avec les scopes appropriés pour accéder aux groupes
                $token = $this->provider->getAccessToken('authorization_code', [
                    'code' => $_GET['code'],
                ]);
            }

            // Faire une requête pour récupérer tous les groupes
            $graphUrl = 'https://graph.microsoft.com/v1.0/groups';
            $response = $this->provider->getAuthenticatedRequest('GET', $graphUrl, $token->getToken());
            $groupsResponse  = $this->provider->getParsedResponse($response);

            // Initialiser un tableau pour stocker les groupes avec id et displayName
            $groupsList = [];

            // Vérifier si la réponse contient des groupes
            if (isset($groupsResponse['value']) && is_array($groupsResponse['value'])) {
                foreach ($groupsResponse['value'] as $group) {
                    // Extraire l'ID et le displayName de chaque groupe
                    $groupsList[] = [
                        'id' => $group['id'] ?? null, // Utiliser l'opérateur null coalescent pour éviter les erreurs
                        'displayName' => $group['displayName'] ?? null,
                    ];
                }
            }

            // Retourner la liste des groupes avec id et displayName
            return $groupsList;

        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => 'Error while getting groups: ' . $e->getMessage(),
            ];
        }
    }
}


class AzureAuthController_OLD
{
    protected $provider;
    protected $settings;

    public function __construct(array $settings)
    {
        $this->provider = new Azure([
            'clientId'                => $settings['oauth2_client_id'],
            'clientSecret'            => $settings['oauth2_client_secret'],
            'redirectUri'             => $settings['cpassman_url'].'/index.php?post_type=oauth2',
            'urlAuthorize'            => strpos($settings['oauth2_client_endpoint'], '{tenant-id}') !== false ? str_replace('{tenant-id}', $settings['oauth2_tenant_id'], $settings['oauth2_client_endpoint']) : $settings['oauth2_client_endpoint'],
            'urlAccessToken'          => strpos($settings['oauth2_client_token'], '{tenant-id}') !== false ? str_replace('{tenant-id}', $settings['oauth2_tenant_id'], $settings['oauth2_client_token']) : $settings['oauth2_client_token'],
            'urlResourceOwnerDetails' => 'https://graph.microsoft.com/v1.0/me',
            'scopes'                  => explode(",", $settings['oauth2_client_scopes']),
            'defaultEndPointVersion' => '1.0',
        ]);
    }

    public function redirect()
    {
        // Si nous n'avons pas de code, redirigeons vers le login Azure AD
        $authUrl = $this->provider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $this->provider->getState();
        header('Location: ' . $authUrl);
        exit;
    }

    public function callback()
    {
        error_log('Callback initiated');
        // Vérifier l'état pour mitiger les attaques CSRF
        if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            error_log('Invalid state detected');
            exit('État invalide');
        }

        try {
            // Échanger le code contre un token d'access
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);
            error_log('Access token obtained successfully');

            // Récupérer les informations de l'utilisateur
            $user = $this->provider->getResourceOwner($token);
            error_log('User information retrieved successfully');

            // Ici, gérer la connexion de l'utilisateur dans votre système
            return [
                'error' => false,
                'userOauth2Info' => $user,
            ];

        } catch (\Exception $e) {
            error_log('Error while catching token: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Error while catching token: '.$e->getMessage(),
            ];
        }
    }
}
