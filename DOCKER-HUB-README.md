# Teampass — Self-hosted collaborative password manager

![Teampass Logo](https://raw.githubusercontent.com/nilsteampassnet/TeamPass/master/public/assets/images/teampass-logo2-login.png)

[![Docker Pulls](https://img.shields.io/docker/pulls/teampass/teampass)](https://hub.docker.com/r/teampass/teampass)
[![Docker Image Version](https://img.shields.io/docker/v/teampass/teampass?sort=semver)](https://hub.docker.com/r/teampass/teampass/tags)
[![Docker Image Size](https://img.shields.io/docker/image-size/teampass/teampass/latest)](https://hub.docker.com/r/teampass/teampass)
[![GitHub](https://img.shields.io/github/license/nilsteampassnet/TeamPass)](https://github.com/nilsteampassnet/TeamPass)

**Teampass is an open-source credential vault you run yourself.** Your secrets never leave your infrastructure: folder-level access control, authenticated AES-256-GCM encryption, per-user encryption keys and a full audit trail.

Free and GPL-3.0, maintained since 2009 — [teampass.net](https://teampass.net)

## 🚀 Quick Start

```bash
# Create a directory for TeamPass
mkdir teampass && cd teampass

# Download docker-compose.yml and .env.example
curl -O https://raw.githubusercontent.com/nilsteampassnet/TeamPass/master/docker/docker-compose/docker-compose.yml
curl -O https://raw.githubusercontent.com/nilsteampassnet/TeamPass/master/docker/docker-compose/.env.example

# Configure
cp .env.example .env
nano .env  # Set DB_PASSWORD and MARIADB_ROOT_PASSWORD

# Start TeamPass
docker-compose up -d

# Access at http://localhost:8080
```

## 📦 Supported Tags

- `latest` - Latest stable release
- `3.2.2.0`, `3.2.2.1`, … - One tag per release, from 3.2.2.0 onward (no older versions, no rolling `3.2` / `3` tags)
- `develop` - Development branch (not for production)

## 🔧 Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `db` | Database hostname |
| `DB_NAME` | `teampass` | Database name |
| `DB_USER` | `teampass` | Database user |
| `DB_PASSWORD` | *required* | Database password |
| `TEAMPASS_URL` | `http://localhost` | Public URL of TeamPass |
| `PHP_MEMORY_LIMIT` | `512M` | PHP memory limit |

### Volumes

| Container path | Holds |
|----------------|-------|
| `/var/www/html/secrets` | The master encryption key (critical!) |
| `/var/www/html/storage/config` | `settings.php` (database connection) and `csrfp.config.php` (critical!) |
| `/var/www/html/storage/files` | Generated files, imports and restore/backup working files |
| `/var/www/html/storage/upload` | Encrypted item attachments (persistent data, never a temporary cache) |
| `/var/www/html/storage/sk` | Legacy saltkey |

Mount a named volume on each of these exact paths, as the examples below do. A volume on a parent directory such as `/var/www/html/storage` is not enough: Docker still mounts an **anonymous** volume on top of each path the image declares, and anonymous volumes are left behind when the container is removed. Losing `storage/config` makes TeamPass believe it was never installed; losing `secrets` makes its data unrecoverable.

PHP runs as `nginx` inside the official image, not the host's `www-data`. At startup the entrypoint sets `secrets/` and `storage/sk/` to `0700`, and storage/configuration/data directory nodes to `0750`; the installer creates the key in `0600`. Volume ownership is reset at each start, but file modes are not recursively reset. The image uses PHP ownership, not the split-owner hardened model; see [File permissions](https://documentation.teampass.net/#/install/file-permissions) for audit warnings and custom-image/NFS restrictions.

## 📋 Example Usage

### Basic Setup

```yaml
version: "3.8"

services:
  teampass:
    image: teampass/teampass:latest
    ports:
      - "8080:80"
    environment:
      DB_HOST: db
      DB_PASSWORD: YourSecurePassword
    volumes:
      - teampass-sk:/var/www/html/storage/sk
      - teampass-files:/var/www/html/storage/files
      - teampass-upload:/var/www/html/storage/upload
      # Install state and master key — required to avoid a reinstall on restart
      - teampass-config:/var/www/html/storage/config
      - teampass-secrets:/var/www/html/secrets
    depends_on:
      - db

  db:
    image: mariadb:11.2
    environment:
      MARIADB_ROOT_PASSWORD: RootPassword
      MARIADB_DATABASE: teampass
      MARIADB_USER: teampass
      MARIADB_PASSWORD: YourSecurePassword
    volumes:
      - teampass-db:/var/lib/mysql

volumes:
  teampass-sk:
  teampass-files:
  teampass-upload:
  teampass-config:
  teampass-secrets:
  teampass-db:
```

### With SSL (Let's Encrypt)

```yaml
version: "3.8"

services:
  nginx-proxy:
    image: nginxproxy/nginx-proxy:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/tmp/docker.sock:ro
      - certs:/etc/nginx/certs

  letsencrypt:
    image: nginxproxy/acme-companion
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - certs:/etc/nginx/certs
    environment:
      DEFAULT_EMAIL: admin@example.com

  teampass:
    image: teampass/teampass:latest
    environment:
      VIRTUAL_HOST: teampass.example.com
      LETSENCRYPT_HOST: teampass.example.com
      LETSENCRYPT_EMAIL: admin@example.com
      DB_HOST: db
      DB_PASSWORD: YourSecurePassword
    volumes:
      - teampass-sk:/var/www/html/storage/sk
      - teampass-files:/var/www/html/storage/files
      - teampass-upload:/var/www/html/storage/upload
      # Install state and master key — required to avoid a reinstall on restart
      - teampass-config:/var/www/html/storage/config
      - teampass-secrets:/var/www/html/secrets

  db:
    image: mariadb:11.2
    environment:
      MARIADB_ROOT_PASSWORD: RootPassword
      MARIADB_DATABASE: teampass
      MARIADB_USER: teampass
      MARIADB_PASSWORD: YourSecurePassword
    volumes:
      - teampass-db:/var/lib/mysql

volumes:
  teampass-sk:
  teampass-files:
  teampass-upload:
  teampass-config:
  teampass-secrets:
  teampass-db:
  certs:
```

## 🔒 Security

- **Encryption:** All passwords encrypted with Defuse PHP Encryption
- **Saltkey:** Unique per installation, stored in secure volume
- **2FA:** Supports TOTP, Duo, and Yubico
- **LDAP/AD:** Native integration for enterprise authentication
- **Audit Logs:** Complete tracking of all password access
- **HTTPS:** SSL/TLS support via reverse proxy

## 📊 Health Check

The container includes a health check endpoint:

```bash
docker inspect teampass-app | grep -A 10 Health
curl http://localhost:8080/health
```

## 💾 Backup

Run these from the directory holding your compose file.

Quiesce application/background writes while taking the database and file backups. Restrict new backup-file permissions on the host:

```bash
umask 077
```

### Database Backup

```bash
docker compose exec -T db sh -c 'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" teampass' > teampass-$(date +%Y%m%d).sql
```

### Master Key, Configuration and Attachments Backup

```bash
docker compose exec -T teampass tar -C /var/www/html -czf - secrets storage/config storage/files storage/upload storage/sk > teampass-state-$(date +%Y%m%d).tar.gz
```

The archive covers all five declared state volumes, including attachments in `storage/upload/` and the legacy saltkey. Include any additional configured data paths/volumes and test a restore. The master key in `secrets/` is required to decrypt the database dump: protect/encrypt this key/configuration archive and keep it separate from the database backup. Both are needed for recovery; access to both opens every secret.

## 🔄 Upgrading

```bash
# Back up first (see Backup above), then:
docker compose pull
docker compose up -d

# Check what the container did
docker compose logs teampass | head -60
```

The container applies the database migrations itself when it starts, then removes the install directory: `/install/upgrade.php` answers 404 after a successful upgrade.

Never add `-v` to `docker compose down`, and do not prune volumes: the master key and `settings.php` live on volumes. If the log says `TeamPass is not configured yet` on an instance that was already installed, do **not** run the installer: follow [Recovering a lost configuration](https://documentation.teampass.net/#/install/docker?id=recovering-a-lost-configuration).

## 📚 Documentation

- **Full Docker Guide:** [DOCKER.md](https://github.com/nilsteampassnet/TeamPass/blob/master/docs/DOCKER.md)
- **Migration Guide:** [DOCKER-MIGRATION.md](https://github.com/nilsteampassnet/TeamPass/blob/master/docs/DOCKER-MIGRATION.md)
- **Official Docs:** https://documentation.teampass.net
- **Website:** https://teampass.net

## 🏗️ Architecture

- **Base:** Alpine Linux 3.19
- **Web Server:** Nginx
- **PHP:** 8.3-FPM with OPcache
- **Process Manager:** Supervisord
- **Database:** MariaDB 11.2+ (separate container)

## ✨ Features

**Encryption you can describe to an auditor**

- 🔐 AES-256-GCM with random nonces and per-secret salts, under 256-bit object keys
- 🔑 Per-user key distribution — removing an account actually revokes access
- 🛡️ Vulnerabilities triaged, fixed and published as [security advisories](https://github.com/nilsteampassnet/TeamPass/security/advisories)

**Access control and audit**

- 📁 Hierarchical folders with per-folder complexity rules
- 👥 Role-based access control, resolved least-permissive-wins
- 📊 A record of who accessed what, and when
- 🏛️ Access recertification campaigns, compliance reports and evidence export

**Identity and automation**

- 🔗 LDAP / Active Directory with nested groups, OAuth2 SSO
- 📱 Multi-factor: TOTP, Duo Security, YubiKey, AGSES
- 🔄 JWT-authenticated REST API with an OpenAPI 3.1 spec
- 🧩 Browser extension with one-click auto-configuration

**Day to day**

- 🔍 Search across labels, descriptions, tags and custom fields
- 📤 Import from Bitwarden, LastPass, 1Password, KeePassXC — and export back out
- 📅 Password expiration policies and breach detection
- 🔔 Email notifications and a notification center
- 🌍 Multi-language support (25 languages)

## 🆘 Support

- **Questions & discussions:** https://github.com/nilsteampassnet/TeamPass/discussions
- **Documentation:** https://documentation.teampass.net
- **Bug reports:** https://github.com/nilsteampassnet/TeamPass/issues
- **Security vulnerabilities:** https://github.com/nilsteampassnet/TeamPass/security/advisories/new
- **Commercial support:** https://teampass.net/pricing.html — or nils@teampass.net

## ❤️ Support the project

Teampass is free, GPL-3.0, and maintained by one person. Sponsorship funds security work,
releases, documentation and 25 translations.

**[Sponsor on GitHub](https://github.com/sponsors/nilsteampassnet)**

## 📜 License

TeamPass is licensed under GNU GPL v3.0

## 🙏 Credits

Developed and maintained by [Nils Laumaillé](https://github.com/nilsteampassnet) and contributors.

---

**⚠️ Important:** Always use strong passwords for `DB_PASSWORD` and `MARIADB_ROOT_PASSWORD`. Never use default values in production!
