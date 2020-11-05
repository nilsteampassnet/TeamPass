# Teampass

**This release is a beta version.**

**Only English language is available. Don't change the language!**

[![Codacy Badge](https://api.codacy.com/project/badge/Grade/c1709641128d42d1ac6ec7fad3cb921c)](https://www.codacy.com/app/nilsteampassnet/TeamPass?utm_source=github.com&utm_medium=referral&utm_content=nilsteampassnet/TeamPass&utm_campaign=badger)

Teampass is a Collaborative Passwords Manager

> Copyright © 2009-2019, [Nils Laumaillé](Nils@Teampass.net)

<!-- MDTOC maxdepth:2 firsth1:0 numbering:0 flatten:0 bullets:1 updateOnSave:1 -->

- [Requirements](#requirements)   
- [Usage](#usage)   
   - [With Docker](#with-docker)   
   - [With Docker Compose](#with-docker-compose)   
- [Update](#update)   
- [Languages](#languages)   
- [Licence Agreement](#licence-agreement)   
- [Website](#website)   
- [Bugs](#bugs)   
- [Requests](#requests)   

<!-- /MDTOC -->

## Requirements

* MySQL 5.1 or higher,
* PHP 7.2 or higher,
* PHP extensions:
  * mcrypt
  * openssl
  * ldap (if used)
  * mbstring
  * bcmath
  * iconv
  * xml
  * gd
  * openssl
  * curl

## Usage

* Read [installation related pages](https://teampass.readthedocs.io)
* Once uploaded, launch Teampass in a browser and follow instructions.

### With Docker
The Docker image provided will create a Teampass installation in its `/var/www/html/` directory, which you should mount as a volume to keep persistent. **SSL is not provided** if you use this image without a proxy in front of it. See the included [Docker Compose file](docker-compose.yml) for an example setup.

**Note:** Use `/var/www/html/sk` as your "Absolute path to saltkey" during installation.


### With Docker Compose
The included [docker-compose.yml](docker-compose.yml) file is an example setup, using virtual host-based reverse proxy routing to provide SSL. If you want to use the Compose file as-is, you will need to provide an SSL certificate with a CN matching the `teampass` service's `VIRTUAL_HOST` variable. See the documentation for the [jwilder/nginx-proxy](https://github.com/jwilder/nginx-proxy) image for details.


**Note:** The database's hostname is `db`. You can find the database's credentials in the environment variables of the `db` service.

**Note:** Use `/var/www/html/sk` as your "Absolute path to saltkey" during installation.

## Update

* Read [upgrade related pages](https://teampass.readthedocs.io)
* Once uploaded, launch install/upgrade.php and follow instructions.

## Languages

Teampass is currently available in the following languages:
* CATALAN
* CHINESE
* CZECH
* DUTCH
* ENGLISH
* ESTONIAN
* FRENCH
* GERMAN
* HUNGARIAN
* ITALIAN
* JAPANESE
* NORWEGIAN
* PORTUGUESE
* PORTUGUESE (BR)
* ROMANIAN
* RUSSIAN
* SPANISH
* TURKISH
* UKRAINIAN
* VIETNAMESE

Languages strings are managed at [POEditor.com](https://poeditor.com/projects/view?id=16418).

## Licence Agreement

Licence defined as GNU General Public License v3.0 only.

This library is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

[Read Licence](license.md)

## Website

Visit [Teampass.net]( * @package       /)

## Bugs

If you discover bugs, please report them in [Gitlab Issues](https://gitlab.com/NilsLaumaille/teampass-v3/issues).

## Requests

Please report feature requests in [UserEcho](https://teamPass.userecho.com).
