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
- `3.2.1.7`, `3.2.1`, `3.2`, `3` - Specific versions
- `develop` - Development branch (not for production)

## 🔧 Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_HOST` | `db` | Database hostname |
| `DB_NAME` | `teampass` | Database name |
| `DB_USER` | `teampass` | Database user |
| `DB_PASSWORD` | *required* | Database password |
| `INSTALL_MODE` | `manual` | Installation mode: `manual` or `auto` |
| `TEAMPASS_URL` | `http://localhost` | Public URL of TeamPass |
| `PHP_MEMORY_LIMIT` | `512M` | PHP memory limit |

### Volumes

| Volume | Purpose |
|--------|---------|
| `/var/www/html/sk` | Encryption saltkey (critical!) |
| `/var/www/html/files` | Uploaded files |
| `/var/www/html/upload` | Temporary uploads |

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

### Database Backup

```bash
docker-compose exec db mariadb-dump -u root -p teampass > backup.sql
```

### Files Backup

```bash
docker run --rm \
  -v teampass-sk:/sk:ro \
  -v teampass-files:/files:ro \
  -v $(pwd):/backup \
  alpine tar czf /backup/teampass-files.tar.gz /sk /files
```

## 🔄 Upgrading

```bash
docker-compose pull
docker-compose down
docker-compose up -d
```

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
