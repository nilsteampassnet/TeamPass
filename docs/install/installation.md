<!-- docs/install/install.md -->




##  On GNU/Linux server

The easiest way to install Teampass is to install LAMP dedicated to the GNU/Linux distribution you have. 

This document highlights a basic setup, but you can refer to many other existing tutorials to install Apache, MariaDB (or mySQL) and PHP.

> :bulb: **Note:**  Teampass should be installed using the most recent PHP version.
>The branch `master` is the living one that is improved and comes with new features.
>It requires __at least__ `PHP 8.1` installed on the server.
>Nevertheless, Teampass can be used with PHP 7.4 version.
>The Github Teampass project has a dedicated branch called `PHP_7.4` for this version.
>Notice that only bug fixing will be performed on this branch.

### Install the Apache web server and the required PHP extensions

In addition to the Apache web server, the following PHP extensions are required by Teampass:

* `mcrypt`
* `mbstring`
* `openssl`
* `bcmath`
* `iconv`
* `gd`
* `mysql`
* `xml`
* `gmp`
* `curl`

Install them by running next commands:

```
sudo apt-get update
sudo apt-get install php8.2-mcrypt php8.2-mbstring php8.2-fpm php8.2-common php8.2-xml php8.2-gd openssl php8.2-mysql php8.2-bcmath php8.2-gmp php8.2-curl
```
> :bulb: **Note:**  Adapt version to your expectation

### Max execution time increase

On some low ressource server, it may be required to increase `max_execution_time` to permit all installation queries to be performed.

```
nano /etc/php8.2/apache2/php.ini
```

Find and adapt `max_execution_time` to 60


### Prepare the database

#### Using phpMyAdmin web interface:

* Install phpMyAdmin, open its web interface
* Select tab called `Databases`
* In the `Create new database` section, enter your database name (for example `teampass`) and select `utf8mb3_general_ci` as collation.
* Click on `Create` button

#### Using command line, on a Debian GNU/Linux system:

* Install the `mariadb-server` package, specify a password when prompted to (consider using pwqgen from the passwdqc package to generate the password)
* Run `mysql_secure_installation` to finish the initial installation
* Access your newly configured server (you'll be prompted for the database root password): 
  ```# mysql -uroot -p```
* Create the TeamPass database: 
  ```create database teampass character set utf8mb3 collate utf8mb3_general_ci;```

### Set the database Administrator

We will now create a specific Administrator for this database.

#### Using phpMyAdmin web interface

* Click on `localhost` in order to get back to home page
* Select `Privileges` tab
* Click on `Add a new user` link
* Enter the login information (I suggest to create a user `teampass_admin` for better understanding of what is this user)
* Do not give any rights/privileges at this level of the user creation
* Click on `Go` button

Now it's time to set some privileges to this user.

* From Home page, click on `Privileges` tab
* Click on `Edit privileges` button corresponding to the `teampass_admin` user
* Click on `Check All` link
* Validate by clicking on button `Go`

#### Using command line, on a Debian GNU/Linux system:

* Access your newly configured server:
  ```# mysql -uroot -p```
* Create the teampass_admin user, assigning it full rights to the TeamPass table: 
  ```grant all privileges on teampass.* to teampass_admin@localhost identified by 'PASSWORD';```

### Setup SSL

* If your use of TeamPass will be limited to your LAN, on Debian systems, see https://wiki.debian.org/Self-Signed_Certificate
* If your TeamPass install will be on a public Internet system, consider using an SSL certificate from https://letsencrypt.org/ or from a commercial provider

### Get TeamPass

> :bulb: **Note:**  Always prefer to use git clone feature to get Teampass on the server. This will highly simplify version upgrade.

#### Using git

```
cd path/to/teampass/folder
git clone https://github.com/nilsteampassnet/TeamPass.git
```

#### Manual operation

* Once your Apache server is running, download TeamPass from https://github.com/nilsteampassnet/TeamPass/releases (under Downloads, .zip file).
* Unzip the file into your localhost folder (by default it is `/opt/lampp/htdocs`) using command `unzip teampass.zip -d /opt/lampp/htdocs`.

Note:

* On CentOS systems, the default folder is `/var/html/www`
* On Debian systems, the default folder is `/var/www/html`

### Set folders permissions

* Open your terminal
* Point to htdocs folder `cd /opt/lampp/htdocs` - see the note above about distribution-specific folders
* Enter the following commands
```
chmod 0777 teampass/includes
chmod -R 0777 teampass/includes/config
chmod -R 0777 teampass/includes/avatars
chmod -R 0777 teampass/includes/libraries/csrfp/libs
chmod -R 0777 teampass/includes/libraries/csrfp/log
chmod -R 0777 teampass/includes/libraries/csrfp/js
chmod -R 0777 teampass/backups
chmod -R 0777 teampass/files
chmod -R 0777 teampass/install
chmod -R 0777 teampass/upload
```
You may also use directly
```
sudo chmod 0777 install/ includes/ includes/config/ includes/avatars/ includes/libraries/csrfp/libs/ includes/libraries/csrfp/js/ includes/libraries/csrfp/log/ files/ upload/
```

### Finish the TeamPass installation

Once installation is done, enter the next commands to put back the limited rights on the folders

```
chmod -R 0750 teampass
chown -R apache:apache teampass
```

## Docker

### Install docker
```
apt update
apt install docker.io docker-compose
```

### Download docker compose 
```
wget https://raw.githubusercontent.com/nilsteampassnet/TeamPass/master/docker-compose.yml
```

### Modify docker-compose.yml file to suit your env (look at this values) 
```
VIRTUAL_HOST: teampass.yourdomain.local
CERT_NAME: teampass.yourdomain.local
MYSQL_PASSWORD: YOUR_SECRET_PASSWORD
```
About the certificate read the instructions on the main page: https://github.com/nilsteampassnet/TeamPass#with-docker-compose

### Configure docker and start containers 
```
docker network create backend
docker-compose up -d
docker container ls 
```

Check your browser at https://teampass.yourdomain.local and follow Note1 and Note2 in the [instructions](https://github.com/nilsteampassnet/TeamPass#with-docker-compose).

