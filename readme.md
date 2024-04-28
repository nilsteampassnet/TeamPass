[![SWUbanner](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/banner-direct.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

# Teampass 3

Teampass is a Collaborative Passwords Manager solution installed On-Premise.

[![StandWithUkraine](https://raw.githubusercontent.com/vshymanskyy/StandWithUkraine/main/badges/StandWithUkraine.svg)](https://github.com/vshymanskyy/StandWithUkraine/blob/main/docs/README.md)

![](https://img.shields.io/github/stars/nilsteampassnet/TeamPass?style=social)
![](https://img.shields.io/github/license/nilsteampassnet/teampass)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg?style=flat-square)](https://makeapullrequest.com)

![](https://img.shields.io/github/v/release/nilsteampassnet/Teampass)
![](https://img.shields.io/github/commits-since/nilsteampassnet/teampass/latest)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/badges/build.png?b=master)](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/build-status/master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/nilsteampassnet/TeamPass/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)

> Copyright © 2009-2024, [Nils Laumaillé](Nils@Teampass.net)

<!-- MDTOC maxdepth:2 firsth1:0 numbering:0 flatten:0 bullets:1 updateOnSave:1 -->

- [Requirements](#requirements)
  - [About PHP versions](#about-php-versions)
- [Documentation](#documentation)
  - [Run from Docker image](#run-from-docker-image)
  - [Install with Docker](#install-with-docker)
  - [Install with Docker Compose](#install-with-docker-compose)
- [Languages](#languages)
- [Licence Agreement](#licence-agreement)
- [Website](#website)
- [Bugs](#bugs)

<!-- /MDTOC -->

## Requirements

* MySQL 5.7 or higher,
* Mariadb 10.7 or higher
* PHP 8.1 or newer,
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
  * gmp

Website article with instructions: https://www.valters.eu/teampass-a-self-hosted-password-manager-to-increase-organizations-cybersecurity/  



YouTube video: https://youtu.be/eXieWAIsGzc?feature=shared


### About PHP versions

Teampass should be installed using the most recent PHP version.
The branch `master` is the living one that is improved and comes with new features.
It requires __at least__ `PHP 8.1` installed on the server.

Nevertheless, Teampass can be used with PHP 7.4 version.
The Github Teampass project has a dedicated branch called `PHP_7.4` for this version.
Notice that only bug fixing will be performed on this branch.

## Documentation

> ✍️ [Documentation](https://documentation.teampass.net) is available.

### Run from Docker image

[@valterseu](https://github.com/valterseu) created a Docker image from scratch with no dependencies and fully automated that you can also use.

Open the [Docker Hub image](https://hub.docker.com/r/valterseu/teampass) and get all information.

Complete usage documentation can be found at [GitHub Docker image URL](https://github.com/valterseu/TeamPass).

### Install with Docker
The Docker image provided will create a Teampass installation in its `/var/www/html/` directory, which you should mount as a volume to keep persistent. **SSL is not provided** if you use this image without a proxy in front of it. See the included [Docker Compose file](docker-compose.yml) for an example setup.

**Note:** Use `/var/www/html/sk` as your "Absolute path to saltkey" during installation.


### Install with Docker Compose
The included [docker-compose.yml](docker-compose.yml) file is an example setup, using virtual host-based reverse proxy routing to provide SSL. If you want to use the Compose file as-is, you will need to provide an SSL certificate with a CN matching the `teampass` service's `VIRTUAL_HOST` variable. See the documentation for the [jwilder/nginx-proxy](https://github.com/jwilder/nginx-proxy) image for details. In short, you'll need to put your certificate file (with extension .crt, e.g. teampass.domain.local.crt) and the according private key file (with extension .key, e.g. teampass.domain.local.key) into the directory ssl, named exactly after the FQDN you put into the `VIRTUAL_HOST` variable. Make sure to restart the nginx service after changes to the certificate or at least signal it with the reload command: `docker-compose exec nginx nginx -s reload`.

**Note1:** The database's hostname is `db`. You can find the database's credentials in the environment variables of the `db` service.

**Note2:** Use `/var/www/html/sk` as your "Absolute path to saltkey" during installation.

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

Languages strings are managed at [POEditor.com](https://poeditor.com/projects/view?id=433631). 
Please participate to improving its translation by joining Teampass POEditor project.

## Licence Agreement

For detailed information on the licenses of our dependencies and our licence policy, please see [Detailed Licence Information](/licences/dependencies.licences.md).

## Website

Visit [Teampass.net](https://teampass.net/)

## Bugs

If you discover bugs, please report them in [Github Issues](https://github.com/nilsteampassnet/TeamPass/issues).

