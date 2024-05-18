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

/*** INFO - .htaccess modification ***
 * RewriteEngine On
 * RewriteRule ^login/azure$ /path/to/AzureAuthController.php?method=redirect [L]
 * RewriteRule ^login/azure/callback$ /path/to/AzureAuthController.php?method=callback [L]

 */

class AzureAuthController
{
    protected $provider;
    protected $settings;

    public function __construct(array $settings, bool $setup = false)
    {
        $this->provider = new Azure([
            'clientId'                => $settings['oauth2_client_id'],
            'clientSecret'            => $settings['oauth2_client_secret'],
            'redirectUri'             => $settings['cpassman_url'].'/'.($setup === false ? OAUTH2_REDIRECTURI : 'index.php?page=oauth'),
            'urlAuthorize'            => $settings['oauth2_client_endpoint'],
            'urlAccessToken'          => $settings['oauth2_client_token'],
            'urlResourceOwnerDetails' => '',
            'scopes'                  => explode(",", $settings['oauth2_client_scopes']),
        ]);
    }

    public function redirect()
    {
        // Si nous n'avons pas de code, redirigeons vers le login Azure AD
        //$baseGraphUri = $this->provider->getRootMicrosoftGraphUri(null);
        //$this->provider->scope = 'openid profile email Group.Read.All User.Read offline_access';
        //$authUrl = $this->provider->getAuthorizationUrl(['scope' => $this->provider->scope]);
        $authUrl = $this->provider->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $this->provider->getState();
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

        $baseGraphUri = $this->provider->getRootMicrosoftGraphUri(null);
        $this->provider->scope = 'openid profile email User.Read offline_access';//Group.Read.All
        try {
            // Échanger le code contre un token d'access
            $token = $this->provider->getAccessToken('authorization_code', [
                //'scope' => 'Group.Read.All',//$this->provider->scope,
                'scope' => $this->provider->scope,
                'code' => $_GET['code']
            ]);

            // Récupérer les informations de l'utilisateur
            $user = $this->provider->getResourceOwner($token);
            error_log('Value : '.print_r($user, true)." ---- RESR ----".$baseGraphUri);

            var_dump(array($user));;
            
            echo "getMemberGroups:<br>";
            $groupMember = $this->provider->get($this->provider->getRootMicrosoftGraphUri($token) . '/v1.0/me', $token);
            var_dump($groupMember);

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

    public function getAllGroups()
{
    error_log('---- getAllGroups INIT ----');
    try {
        $accessToken = $this->provider->getAccessToken('authorization_code', [
            'code' => $_GET['code'],
        ]);
        //error_log(print_r($accessToken, true));

        $response = $this->provider->get($this->provider->getRootMicrosoftGraphUri($accessToken) . '/v1.0/groups', $accessToken);
        error_log(print_r($response, true));

    } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
        exit('Erreur lors de la récupération des groupes : ' . $e->getMessage());
    }
}

    public function callbackSetup()
    {
        error_log('---- CALBACKSETUP INIT ----');
        // Vérifier l'état pour mitiger les attaques CSRF
        if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            error_log('---- DONE ERROR ----');
            exit('État invalide');
        }
// aud: api://46ea19d2-1db1-4005-b131-4b4c12d39786
        $this->provider->scope = 'api://46ea19d2-1db1-4005-b131-4b4c12d39786/Group.Read.All';
        $accessToken = $this->provider->getAccessToken('authorization_code', [
            'scope' => $this->provider->scope,
            'code' => $_GET['code'],
        ]);
        error_log(print_r($accessToken, true));
        //error_log($this->provider->getRootMicrosoftGraphUri($accessToken));
        //$userData = $this->provider->get($this->provider->getRootMicrosoftGraphUri($accessToken) . '/v1.0/me', $accessToken);

        /*
        // Utilisation du token pour faire une requête à Microsoft Graph
        $graphUrl = "https://graph.microsoft.com/v1.0/me";
        $response = $this->provider->getAuthenticatedRequest(
            'GET',
            $graphUrl,
            $accessToken->getToken()  // Assurez-vous de passer le token correctement
        );
        $userData = $this->provider->getParsedResponse($response);
        */
        print_r($userData);

        
    }
}

/*
        $baseGraphUri = $this->provider->getRootMicrosoftGraphUri(null);
        $this->provider->scope = 'openid profile email User.Read offline_access';//Group.Read.All
        try {
            // Échanger le code contre un token d'access
            $accessToken = $this->provider->getAccessToken('authorization_code', [
                'scope' => $this->provider->scope,
                'code' => $_GET['code'],
            ]);

            // Utilisation du token pour accéder à Microsoft Graph
            $resourceUrl = $this->provider->getRootMicrosoftGraphUri(null) . '/v1.0/me';
            $response = $this->provider->getAuthenticatedRequest('GET', $resourceUrl, $accessToken);
            error_log("---ICI---");
            // Exécution de la requête
            $userData = $this->provider->getParsedResponse($response);
            error_log(print_r($userData, true));

            return [
                'error' => false,
            ];

        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => 'Error while catching token: '.$e->getMessage(),
            ];
        }*/

//--------------------------

/*
            $accessToken = $token->getToken(); // Obtenez le token d'accès du token retourné par getAccessToken
            if (isset($token)) {
                $graphUrl = "https://graph.microsoft.com/v1.0/me";//api://46ea19d2-1db1-4005-b131-4b4c12d39786/Group.Read.All
                $ch = curl_init($graphUrl);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Authorization: Bearer " . $accessToken,
                    "Content-Type: application/json"
                ]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $userData = curl_exec($ch);
                curl_close($ch);
            
                print_r($userData);
            }
/*

            // Get meail and groups
            // Récupérer les informations de profil, y compris l'email
            $baseGraphUri = $this->provider->getRootMicrosoftGraphUri($token) . '/v1.0/me';
            error_log('Value : '.($baseGraphUri));
            $me = $this->provider->get($baseGraphUri, $token);

            error_log('Value : '.print_r($me, true));
            // Convertir la réponse en array
            $userInfo = json_decode($me, true);
            $email = $userInfo['mail'] ?? $userInfo['userPrincipalName']; // L'email peut être dans 'mail' ou 'userPrincipalName'

            // Récupérer les groupes de l'utilisateur
            $response = $this->provider->get($this->provider->getRootMicrosoftGraphUri($token) . '/v1.0/me/memberOf', $token);

            $groupsInfo = json_decode($response, true);
            $groups = $groupsInfo['value']; // Les groupes sont dans la clé 'value'

            error_log('Value2 >> Email: '.$email.' - Groups: '.$groups);
*/