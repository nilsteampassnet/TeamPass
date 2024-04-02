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

class AzureAuthController
{
    protected $provider;
    protected $settings;

    public function __construct(array $settings)
    {
        $this->provider = new Azure([
            'clientId'                => $settings['oauth2_azure_clientId'],
            'clientSecret'            => $settings['oauth2_azure_clientSecret'],
            'redirectUri'             => $settings['cpassman_url'].'/'.OAUTH2_REDIRECTURI,
            'urlAuthorize'            => $settings['oauth2_azure_urlAuthorize'],
            'urlAccessToken'          => $settings['oauth2_azure_urlAccessToken'],
            'urlResourceOwnerDetails' => $settings['oauth2_azure_urlResourceOwnerDetails'],
            'scopes'                  => explode(",", $settings['oauth2_azure_scopes']),
        ]);
    }

    public function redirect()
    {
        // Si nous n'avons pas de code, redirigeons vers le login Azure AD
        $authUrl = $this->provider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $this->provider->getState();
        //error_log('---- INIT REDIRECT ----');
        //error_log('oauth2state: '.$_SESSION['oauth2state']);
        header('Location: ' . $authUrl);
        exit;
    }

    public function callback()
    {
        error_log('---- CALBACK INIT ----');
        //error_log('Verif: '.$_SESSION['oauth2state']." -- ".$_GET['state']);
        // Vérifier l'état pour mitiger les attaques CSRF
        if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            error_log('---- DONE ERROR ----');
            exit('État invalide');
        }

        error_log('---- STEP 2 ----');
        $baseGraphUri = $this->provider->getRootMicrosoftGraphUri(null);
        $this->provider->scope = 'openid profile email offline_access Group.Read.All ' . $baseGraphUri . '/User.Read';
        try {
            // Échanger le code contre un token d'access
            $token = $this->provider->getAccessToken('authorization_code', [
                'scope' => $this->provider->scope,
                'code' => $_GET['code']
            ]);

            // Récupérer les informations de l'utilisateur
            $user = $this->provider->getResourceOwner($token);

            // Get meail and groups
            // Récupérer les informations de profil, y compris l'email
            $baseGraphUri = $this->provider->getRootMicrosoftGraphUri($token) . '/v1.0/me';
            $me = $this->provider->get($baseGraphUri, $token);

            error_log('Value : '.print_r($me, true));
/*            // Convertir la réponse en array
            $userInfo = json_decode($me, true);
            $email = $userInfo['mail'] ?? $userInfo['userPrincipalName']; // L'email peut être dans 'mail' ou 'userPrincipalName'

            // Récupérer les groupes de l'utilisateur
            $response = $this->provider->get($this->provider->getRootMicrosoftGraphUri($token) . '/v1.0/me/memberOf', $token);

            $groupsInfo = json_decode($response, true);
            $groups = $groupsInfo['value']; // Les groupes sont dans la clé 'value'

            error_log('Value2 >> Email: '.$email.' - Groups: '.$groups);*/

            return [
                'error' => false,
                'userAzureInfo' => $user,
            ];

        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => 'Error while catching token: '.$e->getMessage(),
            ];
        }
    }
}
