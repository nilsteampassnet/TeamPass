<?php
namespace Authentication\TwoFactors;
/**
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */


class Radius
{
	private $radius;

	public function __construct() {
		$this->radius = radius_auth_open();
	}

	/**
	* @param $serverlist
	* @param $secret
	* @return bool
	*/
	public function add_servers($serverlist,$secret) {
		$servers = array();
		$servers = explode(",",$serverlist);
		$connected = false;
		if(count($servers) > 0) {
			foreach($servers as $server) {
				// Perhaps port, retries and timeout should also be configurable 
				// through the web interface. For now, they are hard coded
				radius_add_server($this->radius,$server,1812,$secret,2,2);
			}
		}
		return $connected;
	}

	/**
	* @param $login
	* @param $code
	* @return bool
	*/
	public function checkCode($login,$code) {
		$retval = false;
		if(radius_create_request($this->radius,RADIUS_ACCESS_REQUEST)) {
			radius_put_attr($this->radius, RADIUS_NAS_IDENTIFIER,$_SERVER['SERVER_NAME']);
			radius_put_attr($this->radius, RADIUS_CALLING_STATION_ID,$_SERVER['REMOTE_ADDR']);
			radius_put_attr($this->radius,RADIUS_USER_NAME,$login);
			radius_put_attr($this->radius,RADIUS_USER_PASSWORD,$code);
			switch(radius_send_request($this->radius)) {
				case RADIUS_ACCESS_ACCEPT:
					$retval = true;
					break;
				default: 
					break;
			}
		} else {
			echo radius_strerror($this->radius);
		}

		return $retval;
	}

	public function __destruct() {
		radius_close($this->radius);
	}
}
?>
