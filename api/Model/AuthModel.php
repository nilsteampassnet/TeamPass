<?php
/**
 * Teampass - a collaborative passwords manager.
 * ---
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * ---
 *
 * @project   Teampass API
 *
 * @file      AuthModel.php
 * ---
 *
 * @author    Nils Laumaillé (nils@teampass.net)
 *
 * @copyright 2009-2022 Teampass.net
 *
 * @license   https://spdx.org/licenses/GPL-3.0-only.html#licenseText GPL-3.0
 * ---
 *
 * @see       https://www.teampass.net
 */
require_once PROJECT_ROOT_PATH . "/Model/Database.php";

 
class AuthModel extends Database
{
    public function getUserAuth($login, $password, $apikey)
    {
        // Check if user exists
        $userInfo = $this->select("SELECT id, pw FROM " . prefixTable('users') . " WHERE login='".$login."'");
        
        // Check password
        include_once PROJECT_ROOT_PATH . '/../sources/SplClassLoader.php';
        $pwdlib = new SplClassLoader('PasswordLib', PROJECT_ROOT_PATH . '/../includes/libraries');
        $pwdlib->register();
        $pwdlib = new PasswordLib\PasswordLib();
        if ($pwdlib->verifyPasswordHash($password, $userInfo[0]['pw']) === true) {
            // Correct credentials
            // Now check apikey
            $apiInfo = $this->select("SELECT count(*) FROM " . prefixTable('api') . " WHERE value='".$apikey."'");
            if ((int) $apiInfo[0]['count(*)'] === 1) {
                return $this->getUserJWT($userInfo[0]['id'], $login);
            } else {
                return array("error" => "Login failed.", "apikey" => "Not valid");
            }
        } else {
            return array("error" => "Login failed.", "password" => $password);
        }
    }

    private function getUserJWT($id, $login): array
    {
        require PROJECT_ROOT_PATH . '/../includes/config/tp.config.php';
        $headers = array('alg'=>'HS256','typ'=>'JWT');
		$payload = array('username'=>$login, 'id'=>$id, 'exp'=>(time() + $SETTINGS['api_token_duration']));

        include_once PROJECT_ROOT_PATH . '/inc/jwt_utils.php';
		return array('token' => generate_jwt($headers, $payload));
    }
}