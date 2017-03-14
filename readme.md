# Teampass

Teampass is a Collaborative Passwords Manager

> Copyright (c) 2009-2017, [Nils Laumaillé] (Nils@TeamPass.net)

## Licence Agreement

Before installing and using TeamPass, you must accept its licence defined as GNU AFFERO GPL.

This program is free software: you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation, either version 3 of the License, or any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more details.

[Read Licence] (license.md)

## Website

Visit [Teampass.net](http://www.teampass.net/)

## Bugs

For bugs discovery, please report in [Github Issues] (https://github.com/nilsteampassnet/TeamPass/issues)

## Requests

For requests, please report in [UserEcho] (https://teamPass.userecho.com)

## Requirements

* Apache 2.0,
* MySQL 5.1,
* PHP 5.5.0 (or higher),
* PHP extensions:
** mcrypt
** openssl
** ldap (if used)
** mbstring
** bcmath
** iconv
** xml
** gd
** openssl
** curl
* Function 'mysqli_fetch_all'

## Installation

* Read [installation related pages] (https://teampass.readthedocs.io)
* Once uploaded, launch install/install.php and follow instructions.

### Docker Installation/Use
*Currently SSL is not provided in this setup, it is advised to use something like HAproxy to add SSL support*

Two ways to provide Docker install
In both cases, the Teampass will be persistent IF you keep the data volume intact between runs and the database content (of course)

#### Docker Compose
* using the provided docker compose file, that you will edit to match your setup (ports/volumes/mysql passwords etc), then build the Teampass image :
```docker-compose build```
* and run the compose app
```docker-compose up -d```
* the first time Teampass is launched, you will be prompted to configured it :
 * for the ''Absolute path to saltkey'', please use ```/teampass/sk```
 * for the database setup :
  * the host is ''db''
  * the other credentials are the ones you provided in your docker-compose file

#### Simple Docker container
* In this scenario, it is assumed you have a mysql database ready to be used.
* First build the Teampass container :
```docker build -t teampass .```
* Then simply run the Teampass container with a volume to store the data :
```docker run -d -p 80:80 -v /srv/teampass:/teampass --name teampass teampass```
* The first launch, you will be prompted to configure Teampass :
 * for the ''Absolute path to saltkey'', please use ```/teampass/sk```
 * for the database, please provide your own database parameters


## Update

* Read [upgrade related pages] (https://teampass.readthedocs.io)
* Once uploaded, launch install/upgrade.php and follow instructions.

## Languages

Teampass is translated in next languages:
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

Languages strings are managed at [POEditor.com] (https://poeditor.com/projects/view?id=16418).
