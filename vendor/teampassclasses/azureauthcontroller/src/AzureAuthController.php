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
            'redirectUri'             => $settings['oauth2_azure_redirectUri'],
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
        header('Location: ' . $authUrl);
        exit;
    }

    public function callback()
    {
        // Vérifier l'état pour mitiger les attaques CSRF
        if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            exit('État invalide');
        }

        try {
            // Échanger le code contre un token d'access
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);

            // Récupérer les informations de l'utilisateur
            $user = $this->provider->getResourceOwner($token);

            // Ici, gérer la connexion de l'utilisateur dans votre système
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
