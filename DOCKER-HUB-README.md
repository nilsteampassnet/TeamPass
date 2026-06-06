# TeamPass - Collaborative Password Manager

![TeamPass Logo](https://teampass.net/wp-content/uploads/2021/01/logo-teampass.png)

[![Docker Pulls](https://img.shields.io/docker/pulls/teampass/teampass)](https://hub.docker.com/r/teampass/teampass)
[![Docker Image Version](https://img.shields.io/docker/v/teampass/teampass?sort=semver)](https://hub.docker.com/r/teampass/teampass/tags)
[![Docker Image Size](https://img.shields.io/docker/image-size/teampass/teampass/latest)](https://hub.docker.com/r/teampass/teampass)
[![GitHub](https://img.shields.io/github/license/nilsteampassnet/TeamPass)](https://github.com/nilsteampassnet/TeamPass)

TeamPass is a collaborative, on-premise password manager designed for teams. Store and share passwords securely with fine-grained access control, LDAP integration, and comprehensive audit trails.

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
- `3.1.5.2`, `3.1.5`, `3.1`, `3` - Specific versions
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

- **Full Docker Guide:** [DOCKER.md](https://github.com/nilsteampassnet/TeamPass/blob/master/DOCKER.md)
- **Migration Guide:** [DOCKER-MIGRATION.md](https://github.com/nilsteampassnet/TeamPass/blob/master/DOCKER-MIGRATION.md)
- **Official Docs:** https://documentation.teampass.net
- **Website:** https://teampass.net

## 🏗️ Architecture

- **Base:** Alpine Linux 3.19
- **Web Server:** Nginx
- **PHP:** 8.3-FPM with OPcache
- **Process Manager:** Supervisord
- **Database:** MariaDB 11.2+ (separate container)

## ✨ Features

- 🔐 Secure password storage with encryption
- 👥 Role-based access control (RBAC)
- 📁 Hierarchical folder organization
- 🔍 Advanced search and filtering
- 📊 Comprehensive audit logs
- 🔗 LDAP/Active Directory integration
- 📱 Two-factor authentication (2FA)
- 🌍 Multi-language support (19 languages)
- 📤 Import/Export capabilities
- 🔔 Email notifications
- 📅 Password expiration policies
- 🔄 API for integrations

## 🆘 Support

- **GitHub Issues:** https://github.com/nilsteampassnet/TeamPass/issues
- **Community:** https://www.reddit.com/r/TeamPass/
- **Email:** nils@teampass.net

## 📜 License

TeamPass is licensed under GNU GPL v3.0

## 🙏 Credits

Developed and maintained by [Nils Laumaillé](https://github.com/nilsteampassnet) and contributors.

---

**⚠️ Important:** Always use strong passwords for `DB_PASSWORD` and `MARIADB_ROOT_PASSWORD`. Never use default values in production!
