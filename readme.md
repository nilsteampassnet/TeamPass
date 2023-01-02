[![SWUbanner](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner-direct.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

# Teampass 3

[![StandWithUkraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

![](https://img.shields.io/github/stars/nilsteampassnet/TeamPass?style=social)
![](https://img.shields.io/github/license/nilsteampassnet/teampass)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg?style=flat-square)](https://makeapullrequest.com)

![](https://img.shields.io/github/v/release/nilsteampassnet/Teampass)
![](https://img.shields.io/github/commits-since/nilsteampassnet/teampass/latest)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/badges/build.png?b=master)](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/build-status/master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)

Teampass is a Collaborative Passwords Manager

> Copyright © 2009-2023, [Nils Laumaillé](Nils@Teampass.net)

<!-- MDTOC maxdepth:2 firsth1:0 numbering:0 flatten:0 bullets:1 updateOnSave:1 -->

- [Teampass 3](#teampass-3)
  - [Requirements](#requirements)
  - [Documentation](#documentation)
    - [With Docker](#with-docker)
    - [With Docker Compose](#with-docker-compose)
  - [Languages](#languages)
  - [Licence Agreement](#licence-agreement)
  - [Website](#website)
  - [Bugs](#bugs)

<!-- /MDTOC -->

## Requirements

* MySQL 5.7 or higher,
* Mariadb 10.7 or higher
* PHP 7.4 or higher (recommended is 8.x),
* PHP extensions:
  * mcrypt
  * openssl
  * ldap (if used)
  * mbstring
  * bcmath
  * iconv
  * xml
  * gd
  * mysql
  * curl

## Documentation

* Read [V3 documentation](https://documentation.teampass.net)

### With Docker
The Docker image provided will create a Teampass installation in its `/var/www/html/` directory, which you should mount as a volume to keep persistent. **SSL is not provided** if you use this image without a proxy in front of it. See the included [Docker Compose file](docker-compose.yml) for an example setup.

**Note:** Use `/var/www/html/sk` as your "Absolute path to saltkey" during installation.


### With Docker Compose
The included [docker-compose.yml](docker-compose.yml) file is an example setup, using virtual host-based reverse proxy routing to provide SSL. If you want to use the Compose file as-is, you will need to provide an SSL certificate with a CN matching the `teampass` service's `VIRTUAL_HOST` variable. See the documentation for the [jwilder/nginx-proxy](https://github.com/jwilder/nginx-proxy) image for details. In short, you'll need to put your certificate file (with extension .crt, e.g. teampass.domain.local.crt) and the according private key file (with extension .key, e.g. teampass.domain.local.key) into the directory ssl, named exactly after the FQDN you put into the `VIRTUAL_HOST` variable. Make sure to restart the nginx service after changes to the certificate or at least signal it with the reload command: `docker-compose exec nginx nginx -s reload`.

**Note:** As you might have noticed, the docker hub image of teampass is currently sort of unmaintained as of Feb 2022. Therefore the docker-compose example provided here will build the teampass image out of the latest sources of this repository. When the docker hub image is getting built again, you may comment out `build: .` and remove the comment for `image: teampass/teampass`. We'll inform you here once that's the case.

**Note:** The database's hostname is `db`. You can find the database's credentials in the environment variables of the `db` service.

**Note:** Use `/var/www/html/sk` as your "Absolute path to saltkey" during installation.

## Languages

Teampass is currently available in the following languages:
* ENGLISH
* CATALAN
* CHINESE
* CZECH
* DUTCH
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

Languages strings are managed at [POEditor.com](https://poeditor.com/join/project?hash=0vptzClQrM).

## Licence Agreement

Licence defined as GNU General Public License v3.0 only.

This library is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.

[Read Licence](license.md)

## Website

Visit [Teampass.net](https://teampass.net/)

## Bugs

If you discover bugs, please report them in [Github Issues](https://github.com/nilsteampassnet/TeamPass/issues).

