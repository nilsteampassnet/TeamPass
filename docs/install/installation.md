<!-- docs/install/install.md -->

## On GNU/Linux server

The easiest way to install Teampass is to install a LAMP stack dedicated to the GNU/Linux distribution you use.

This document highlights a basic setup. You can refer to many other existing tutorials to install Apache, MariaDB (or MySQL) and PHP.

> :bulb: **Note:** Teampass requires **PHP 8.1** or later. The `master` branch targets the most recent stable PHP version.

---

### Directory layout (3.2.x)

Since version 3.2.0, Teampass uses a split directory structure. Your web server's `DocumentRoot` must point to `public/`, not to the project root.

```
/path/to/teampass/
├── app/          ← application code & config  (not web-accessible)
│   ├── config/
│   │   └── settings.php    ← DB credentials
│   ├── includes/
│   └── vendor/             ← Composer dependencies
├── public/       ← webroot (DocumentRoot points here)
│   ├── index.php
│   ├── install/
│   └── assets/
│       └── avatars/
├── storage/      ← runtime data  (not web-accessible)
│   ├── files/    ← background task trigger files
│   ├── upload/   ← encrypted file attachments
│   └── backups/  ← SQL backup files
└── secrets/      ← encryption key  (not web-accessible)
    └── teampass-seckey.txt
```

---

### Install the Apache web server and required PHP extensions

In addition to the Apache web server, the following PHP extensions are required:

* `mbstring`
* `openssl`
* `bcmath`
* `mysql`
* `xml`
* `curl`
* `pcntl` *(CLI only — required for the background task daemon)*
* `posix` *(CLI only — required for the background task daemon)*

Install them with:

```bash
sudo apt-get update
sudo apt-get install php8.2-mbstring php8.2-fpm php8.2-common php8.2-xml openssl php8.2-mysql php8.2-bcmath php8.2-curl
```

> :bulb: **Note:** Adapt the version number to your target PHP release.

> :bulb: **Note:** `pcntl` and `posix` are CLI-only extensions. They are not loaded by Apache/FPM but must be available for the PHP CLI. On most distributions they are included in `php8.2-common`. Verify with `php -m | grep -E "(pcntl|posix)"`.

### Optional — Performance extensions

The following extensions are not required but are strongly recommended for production deployments:

| Extension          | Purpose                                                              |
|--------------------|----------------------------------------------------------------------|
| `apcu`             | In-memory settings cache — reduces DB reads on every request        |
| `redis` + ext-redis | Redis-based session storage — improves performance under high load  |
| `Zend OPcache`     | Bytecode cache — significantly speeds up PHP execution               |

See the [Performance](install/performance.md) page for installation and configuration details.

### Increase max execution time

On low-resource servers, increase `max_execution_time` to allow all installation queries to complete:

```bash
nano /etc/php/8.2/apache2/php.ini
```

Set `max_execution_time = 60`.

---

### Prepare the database

#### Using phpMyAdmin

* Open the phpMyAdmin web interface
* Select the **Databases** tab
* In the **Create new database** section, enter your database name (e.g. `teampass`) and select `utf8mb4_general_ci` as collation
* Click **Create**

#### Using the command line (Debian / Ubuntu)

```bash
# Install MariaDB if not already present
sudo apt-get install mariadb-server
mysql_secure_installation

# Connect as root
mysql -uroot -p

# Inside MySQL:
CREATE DATABASE teampass CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
GRANT ALL PRIVILEGES ON teampass.* TO 'teampass_admin'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';
FLUSH PRIVILEGES;
EXIT;
```

---

### Set up SSL

* **LAN only:** see https://wiki.debian.org/Self-Signed_Certificate
* **Public internet:** use a certificate from https://letsencrypt.org/ or a commercial provider

---

### Get TeamPass

> :bulb: **Note:** Always prefer `git clone` — it makes future upgrades much easier.

#### Using Git (recommended)

```bash
cd /var/www/html
git clone https://github.com/nilsteampassnet/TeamPass.git teampass
cd teampass
composer install --no-dev --optimize-autoloader
```

#### Manual download

* Download from [Teampass releases](https://github.com/nilsteampassnet/TeamPass/releases/latest)
* Unzip into the web root (e.g. `/var/www/html/teampass`)
* Run `composer install --no-dev --optimize-autoloader` inside the folder

---

### Configure the web server

#### Apache virtual host

Set `DocumentRoot` to the `public/` subdirectory:

```apache
<VirtualHost *:443>
    ServerName teampass.yourdomain.com
    DocumentRoot /var/www/html/teampass/public

    <Directory /var/www/html/teampass/public>
        AllowOverride All
        Require all granted
    </Directory>

    # ... SSL directives ...
</VirtualHost>
```

Enable `mod_rewrite` and reload Apache:

```bash
sudo a2enmod rewrite
sudo systemctl reload apache2
```

> :warning: **`AllowOverride All` is required.** TeamPass ships `.htaccess` files that handle URL rewriting, PHP execution control, and access restrictions. Without `AllowOverride All` these rules are silently ignored.

**Subdirectory install (Apache Alias)**

If TeamPass is served from a path such as `https://example.com/teampass/`, adjust `RewriteBase` in `public/.htaccess`:

```apache
RewriteBase /teampass/
```

#### Nginx

The Nginx configuration must route requests for the web UI through `index.php` and API requests through `api/index.php`. It must also block direct access to `core.php` (a bootstrap include, not an endpoint).

```nginx
server {
    listen 443 ssl;
    server_name teampass.yourdomain.com;
    root /var/www/html/teampass/public;
    index index.php;

    # Block direct access to the bootstrap include
    location ~* /core\.php$ {
        deny all;
        return 403;
    }

    # API — route through the API front controller
    location /api/ {
        try_files $uri $uri/ /api/index.php?$query_string;
    }

    # Web UI — route through the main front controller
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

> :bulb: **Note:** `RewriteBase` is an Apache-only directive. For Nginx, the base path is implicit in the `location` blocks; no equivalent setting is needed.

---

### Set folder permissions

Run these commands from inside the TeamPass root (e.g. `/var/www/html/teampass`):

```bash
# Web server user — adjust if yours is different (e.g. nginx, apache, http)
WEB_USER=www-data

# Application source: not writable by the web server
chmod 0755 app/ public/
chown -R root:root app/ public/

# Configuration file: writable by the web server (written during install/upgrade)
chown ${WEB_USER}:${WEB_USER} app/config/settings.php
chmod 0640 app/config/settings.php
chown ${WEB_USER}:${WEB_USER} app/config/
chmod 0750 app/config/

# CSRF protection files
chown ${WEB_USER}:${WEB_USER} app/includes/libraries/csrfp/libs/
chmod 0750 app/includes/libraries/csrfp/libs/
chown ${WEB_USER}:${WEB_USER} app/includes/libraries/csrfp/log/
chmod 0750 app/includes/libraries/csrfp/log/

# Runtime storage: writable by the web server
chown -R ${WEB_USER}:${WEB_USER} storage/
chmod 0750 storage/ storage/files/ storage/upload/ storage/backups/

# Avatars (optional — only if avatar upload is enabled)
chmod 0750 public/assets/avatars/
chown ${WEB_USER}:${WEB_USER} public/assets/avatars/

# Encryption key: readable by the web server, outside webroot
chown ${WEB_USER}:${WEB_USER} secrets/
chmod 0750 secrets/
```

> :warning: **Security note:** `app/` and `public/` must **not** be writable by the web server. Only the specific sub-paths listed above need write access.

---

### Finish the installation

Browse to `https://<your_teampass_domain>/install/install.php` and follow the on-screen wizard.

#### Lock down the install directory after setup

Once the wizard completes, **block HTTP access to `public/install/`**. The installer files must not remain publicly accessible in production.

**Apache** — uncomment the deny directive in `public/install/.htaccess`:

```apache
Require all denied
```

Or disable the directory in your virtual host:

```apache
<Directory /var/www/html/teampass/public/install>
    Require all denied
</Directory>
```

**Nginx** — add a location block before the catch-all:

```nginx
location /install/ {
    deny all;
    return 403;
}
```

> :warning: Skipping this step leaves the upgrade scripts accessible, which is a security risk on a running instance.

---

## Docker

### Install Docker

```bash
apt update
apt install docker.io docker-compose
```

### Download the Docker Compose file

```bash
wget https://raw.githubusercontent.com/nilsteampassnet/TeamPass/master/docker-compose.yml
```

### Edit `docker-compose.yml` for your environment

```yaml
VIRTUAL_HOST: teampass.yourdomain.local
CERT_NAME: teampass.yourdomain.local
MYSQL_PASSWORD: YOUR_SECRET_PASSWORD
```

See the main page for certificate instructions: https://github.com/nilsteampassnet/TeamPass#with-docker-compose

### Start the containers

```bash
docker network create backend
docker-compose up -d
docker container ls
```

Browse to `https://teampass.yourdomain.local` and follow the instructions in Note1 and Note2 of the [Docker guide](https://github.com/nilsteampassnet/TeamPass#with-docker-compose).
