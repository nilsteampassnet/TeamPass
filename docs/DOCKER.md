# TeamPass Docker Documentation

This guide explains how to run TeamPass using Docker and Docker Compose.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Installation Wizard](#installation-wizard)
- [Configuration](#configuration)
- [SSL/HTTPS Setup](#sslhttps-setup)
- [Upgrading](#upgrading)
- [Backup and Restore](#backup-and-restore)
- [Troubleshooting](#troubleshooting)
- [Advanced Usage](#advanced-usage)

---

## Prerequisites

- Docker Engine 20.10+ or Docker Desktop
- Docker Compose 2.0+
- At least 2 GB of free disk space

### Linux / WSL2

Add your user to the `docker` group before any operation:

```bash
sudo usermod -aG docker $USER
newgrp docker   # or close/re-open the terminal
```

> Without this, all Docker commands will fail with "permission denied".

---

## Quick Start

### 1. Get the docker-compose files

```bash
git clone https://github.com/nilsteampassnet/TeamPass.git
cd TeamPass/docker/docker-compose
```

### 2. Create your environment file

```bash
cp .env.example .env
```

Edit `.env` and set your passwords:

```bash
nano .env
```

Minimum required changes:

```dotenv
DB_PASSWORD=YourSecureDBPassword
MARIADB_ROOT_PASSWORD=YourSecureRootPassword
```

### 3. Start the containers

```bash
docker compose up -d
```

> The first run downloads images (~300 MB) and initializes the database. Wait ~30 seconds.

### 4. Create the secure key directory

TeamPass needs a directory to store its master encryption key. This directory must exist
and be writable by the PHP-FPM process (`nginx` user inside the container).

```bash
docker exec teampass-app sh -c "mkdir -p /var/TeampassSecurity && chown nginx:nginx /var/TeampassSecurity && chmod 750 /var/TeampassSecurity"
```

> **Persistence warning:** This directory is inside the container and will be lost if the
> container is recreated (`docker compose down -v`). For production, mount it as a volume
> (see [Advanced Usage](#advanced-usage)).

### 5. Complete the installation wizard

Open your browser: **http://localhost:8080/install/install.php**

See [Installation Wizard](#installation-wizard) for field values.

### 6. Restart after installation

```bash
docker compose restart teampass
```

---

## Installation Wizard

Fill in the installation form with these values:

| Field | Value |
|---|---|
| Absolute path of the application | `/var/www/html` |
| URL of the application | `http://localhost:8080` |
| Absolute path to secure key | `/var/TeampassSecurity` |
| Saltkey absolute path | `/var/www/html/sk` |
| Database host | `db` |
| Database port | `3306` |
| Database name | `teampass` (or `DB_NAME` from `.env`) |
| Database login | `teampass` (or `DB_USER` from `.env`) |
| Database password | value of `DB_PASSWORD` from `.env` |
| Table prefix | `teampass_` |

> The database host **must** be `db` (Docker service name), not `localhost`.

---

## Configuration

### Environment Variables

All configuration is managed via the `.env` file in `docker/docker-compose/`.

#### Database

```dotenv
DB_NAME=teampass              # Database name
DB_USER=teampass              # Database user
DB_PASSWORD=YourSecurePassword!   # Database password
DB_PREFIX=teampass_               # Table prefix
MARIADB_ROOT_PASSWORD=YourRootPassword!  # MariaDB root password
MARIADB_VERSION=11.2              # MariaDB image version
```

#### Network

```dotenv
TEAMPASS_PORT=8080            # External port
TEAMPASS_URL=http://localhost:8080  # Public URL (used in installer)
```

#### PHP

```dotenv
PHP_MEMORY_LIMIT=512M
PHP_UPLOAD_MAX_FILESIZE=100M
PHP_MAX_EXECUTION_TIME=120
```

#### Installation mode

```dotenv
INSTALL_MODE=manual   # manual (default) or auto
```

### Docker image tags

Docker Hub (`teampass/teampass`) provides:

| Tag | Description |
|---|---|
| `latest` | Latest build from master branch |
| `master` | Same as latest |
| `develop` | Development branch |
| `sha-xxxxxxx` | Specific commit build |
| `3.1.5.2`, `3.1.6.x` | Versioned releases (published on GitHub Release only) |

For testing, use `latest`. Versioned tags only appear after a GitHub Release is published.

### Volumes

| Volume | Container path | Purpose |
|---|---|---|
| `teampass-sk` | `/var/www/html/sk` | Saltkey file |
| `teampass-files` | `/var/www/html/files` | Uploaded files |
| `teampass-upload` | `/var/www/html/upload` | Temporary uploads |
| `teampass-db` | `/var/lib/mysql` | Database data |

---

## SSL/HTTPS Setup

### With nginx-proxy and Let's Encrypt

1. Edit `.env`:

```dotenv
VIRTUAL_HOST=teampass.example.com
LETSENCRYPT_HOST=teampass.example.com
LETSENCRYPT_EMAIL=admin@example.com
TEAMPASS_URL=https://teampass.example.com
```

2. Start with the proxy configuration:

```bash
docker compose -f docker-compose.with-proxy.yml up -d
```

**Requirements:** domain pointing to your server, ports 80 and 443 open.

### With an existing reverse proxy (nginx, Traefik, Caddy)

```nginx
location / {
    proxy_pass http://localhost:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

---

## Upgrading

1. **Backup your data first** (see [Backup and Restore](#backup-and-restore))

2. Pull the new image and restart:

```bash
docker compose pull
docker compose down
docker compose up -d
```

> `down` without `-v` preserves your data volumes.

---

## Backup and Restore

### Backup

**Database:**

```bash
docker exec teampass-db mariadb-dump \
  -u root -pYourRootPassword \
  teampass > teampass-backup-$(date +%Y%m%d).sql
```

**Files:**

```bash
docker run --rm \
  -v docker-compose_teampass-sk:/sk:ro \
  -v docker-compose_teampass-files:/files:ro \
  -v $(pwd):/backup \
  alpine tar czf /backup/teampass-files-$(date +%Y%m%d).tar.gz /sk /files
```

### Restore

**Database:**

```bash
docker exec -i teampass-db mariadb \
  -u root -pYourRootPassword \
  teampass < teampass-backup-20240315.sql
```

**Files:**

```bash
docker run --rm \
  -v docker-compose_teampass-sk:/sk \
  -v docker-compose_teampass-files:/files \
  -v $(pwd):/backup \
  alpine tar xzf /backup/teampass-files-20240315.tar.gz
```

---

## Troubleshooting

### Permission denied on Docker socket

```
permission denied while trying to connect to the Docker daemon socket
```

**Fix:**
```bash
sudo usermod -aG docker $USER
newgrp docker
```

### "MySQL server has gone away" during installation

This error occurs when the installer sets an empty SSL array, which causes mysqli to fail
over TCP connections (required in Docker). It does not occur on traditional (non-Docker)
installations because PHP uses Unix sockets there.

**Workaround (already applied in latest image):** The installer files `run.step3.php`
through `run.step6.php` must have `DB::$ssl = null` instead of an empty SSL array.

If you encounter this on an older image, patch the files manually:

```bash
for f in run.step3.php run.step4.php run.step5.php run.step6.php; do
  docker exec teampass-app sed -i \
    '/DB::\$ssl = array(/,/);/c\    DB::$ssl = null;' \
    /var/www/html/install/install-steps/$f
done
```

### Database password rejected / "Access denied"

After changing `DB_PASSWORD` in `.env`, you **must** destroy the database volume to force
MariaDB to reinitialize with the new password:

```bash
docker compose down -v   # destroys all volumes including database
docker compose up -d
```

> If you don't use `-v`, MariaDB ignores the new password (existing data files take precedence).

### MariaDB crashes on WSL2 (io_uring error)

WSL2 does not support `O_DIRECT` for InnoDB. The `docker/mariadb/custom.cnf` is already
configured with `innodb_flush_method = fsync` to work around this. If you see:

```
InnoDB: liburing disabled: falling back to innodb_use_native_aio=OFF
```

This is normal on WSL2 and does not affect functionality.

### Container logs

```bash
# All services
docker compose logs -f

# TeamPass only
docker compose logs -f teampass

# MariaDB only
docker compose logs -f db

# PHP errors
docker exec teampass-app cat /var/log/php_errors.log

# Nginx errors
docker exec teampass-app cat /var/log/nginx/teampass-error.log
```

### Health check

```bash
docker compose ps
curl http://localhost:8080/health
```

### Full reset (destroys all data)

```bash
docker compose down -v
docker compose up -d
```

---

## Advanced Usage

### Persist the secure key directory

By default `/var/TeampassSecurity` is inside the container and lost on recreation.
For production, add a named volume in `docker-compose.yml`:

```yaml
services:
  teampass:
    volumes:
      - teampass-sk:/var/www/html/sk
      - teampass-files:/var/www/html/files
      - teampass-upload:/var/www/html/upload
      - teampass-security:/var/TeampassSecurity   # add this line

volumes:
  teampass-security:
    driver: local
```

Then recreate and set permissions:

```bash
docker compose down && docker compose up -d
docker exec teampass-app sh -c "chown nginx:nginx /var/TeampassSecurity && chmod 750 /var/TeampassSecurity"
```

### Custom PHP configuration

Create `docker/php/custom.ini` and mount it:

```yaml
volumes:
  - ../../docker/php/custom.ini:/usr/local/etc/php/conf.d/custom.ini:ro
```

### External database

Remove the `db` service from `docker-compose.yml` and update:

```dotenv
DB_HOST=mysql.example.com
DB_PORT=3306
```

### Shell access

```bash
# TeamPass container
docker exec -it teampass-app sh

# Database container
docker exec -it teampass-db mariadb -u root -pYourRootPassword
```

---

## Useful Commands

```bash
# Start
docker compose -f docker/docker-compose/docker-compose.yml --env-file docker/docker-compose/.env up -d

# Stop (keep data)
docker compose -f docker/docker-compose/docker-compose.yml down

# Stop and destroy all data
docker compose -f docker/docker-compose/docker-compose.yml down -v

# Restart TeamPass only
docker compose -f docker/docker-compose/docker-compose.yml restart teampass

# Pull latest image
docker compose -f docker/docker-compose/docker-compose.yml pull

# Container stats
docker stats teampass-app teampass-db
```

---

## Additional Resources

- **Documentation:** https://teampass.readthedocs.io
- **Docker Hub:** https://hub.docker.com/r/teampass/teampass
- **GitHub:** https://github.com/nilsteampassnet/TeamPass
- **Issues:** https://github.com/nilsteampassnet/TeamPass/issues

---

**Last updated:** 2026-03-11
**Tested with:** TeamPass 3.1.6+ / Docker Compose 2.x / MariaDB 11.2
