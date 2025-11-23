# 🐳 TeamPass Docker Documentation

This guide explains how to run TeamPass using Docker and Docker Compose.

## 📋 Table of Contents

- [Quick Start](#quick-start)
- [Installation Methods](#installation-methods)
- [Configuration](#configuration)
- [SSL/HTTPS Setup](#sslhttps-setup)
- [Upgrading](#upgrading)
- [Backup and Restore](#backup-and-restore)
- [Troubleshooting](#troubleshooting)
- [Advanced Usage](#advanced-usage)

---

## 🚀 Quick Start

### Prerequisites

- Docker Engine 20.10+ or Docker Desktop
- Docker Compose 2.0+
- At least 2GB of free disk space

### Basic Deployment

1. **Clone the repository or download docker-compose files:**

```bash
git clone https://github.com/nilsteampassnet/TeamPass.git
cd TeamPass/docker/docker-compose
```

2. **Create your environment file:**

```bash
cp .env.example .env
```

3. **Edit the `.env` file and set secure passwords:**

```bash
nano .env
```

**⚠️ IMPORTANT:** Change at least these values:
- `DB_PASSWORD` - Database password
- `MARIADB_ROOT_PASSWORD` - Database root password

4. **Start TeamPass:**

```bash
docker-compose up -d
```

5. **Open your browser:**

Navigate to `http://localhost:8080` and complete the installation wizard.

**Installation wizard values:**
- Database host: `db`
- Database name: `teampass` (or value from `.env`)
- Database user: `teampass` (or value from `.env`)
- Database password: (use the value from your `.env` file)
- Saltkey path: `/var/www/html/sk`

6. **Restart after installation:**

```bash
docker-compose restart teampass
```

---

## 📦 Installation Methods

TeamPass Docker supports two installation modes:

### 1. Manual Installation (Default)

This is the **recommended method** for most users:

```bash
INSTALL_MODE=manual
```

- Complete installation via web browser
- More control over settings
- Follows official TeamPass installation process
- Better for understanding the setup

### 2. Automatic Installation

For automated deployments and DevOps workflows:

```bash
INSTALL_MODE=auto
ADMIN_EMAIL=admin@example.com
ADMIN_PWD=YourSecurePassword123!
```

**Note:** Auto-installation is currently simplified and may require completing some steps via the web interface.

---

## ⚙️ Configuration

### Environment Variables

All configuration is done via the `.env` file:

#### Database Configuration

```bash
DB_NAME=teampass              # Database name
DB_USER=teampass              # Database user
DB_PASSWORD=SecurePass123!    # Database password (REQUIRED)
DB_PREFIX=teampass_           # Table prefix
MARIADB_ROOT_PASSWORD=Root456! # MariaDB root password (REQUIRED)
```

#### Network Configuration

```bash
TEAMPASS_PORT=8080            # External port for TeamPass
TEAMPASS_URL=http://localhost:8080  # Public URL
```

#### PHP Configuration

```bash
PHP_MEMORY_LIMIT=512M         # PHP memory limit
PHP_UPLOAD_MAX_FILESIZE=100M  # Max upload file size
PHP_MAX_EXECUTION_TIME=120    # Max execution time
```

### Volumes and Data Persistence

TeamPass uses Docker volumes for persistent data:

| Volume | Purpose | Path in Container |
|--------|---------|-------------------|
| `teampass-sk` | Encryption saltkey | `/var/www/html/sk` |
| `teampass-files` | Uploaded files | `/var/www/html/files` |
| `teampass-upload` | Temporary uploads | `/var/www/html/upload` |
| `teampass-db` | Database data | `/var/lib/mysql` |

**To inspect volumes:**

```bash
docker volume ls | grep teampass
docker volume inspect teampass-db
```

---

## 🔒 SSL/HTTPS Setup

### Using Nginx Proxy with Let's Encrypt

TeamPass includes a ready-to-use configuration with automatic SSL:

1. **Use the SSL-enabled compose file:**

```bash
cd docker/docker-compose
cp .env.example .env
nano .env
```

2. **Set your domain in `.env`:**

```bash
VIRTUAL_HOST=teampass.example.com
LETSENCRYPT_HOST=teampass.example.com
LETSENCRYPT_EMAIL=admin@example.com
TEAMPASS_URL=https://teampass.example.com
```

3. **Start with the proxy configuration:**

```bash
docker-compose -f docker-compose.with-proxy.yml up -d
```

**Requirements:**
- Domain pointing to your server's IP
- Ports 80 and 443 accessible from the internet
- Valid email for Let's Encrypt notifications

### Using Your Own Reverse Proxy

If you already have a reverse proxy (Traefik, Caddy, etc.):

```yaml
# Example nginx configuration
location / {
    proxy_pass http://localhost:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

---

## 🔄 Upgrading

### Upgrade to a New Version

1. **Backup your data** (see [Backup section](#backup-and-restore))

2. **Pull the new image:**

```bash
docker-compose pull teampass
```

3. **Stop and remove old container:**

```bash
docker-compose down
```

4. **Start with new version:**

```bash
docker-compose up -d
```

5. **Check logs:**

```bash
docker-compose logs -f teampass
```

### Upgrade from Specific Version

```bash
# In your .env file
TEAMPASS_VERSION=3.1.5.2

# Or directly
docker pull teampass/teampass:3.1.5.2
```

---

## 💾 Backup and Restore

### Backup

**1. Backup Database:**

```bash
docker-compose exec db mariadb-dump \
  -u root -p${MARIADB_ROOT_PASSWORD} \
  ${DB_NAME} > teampass-backup-$(date +%Y%m%d).sql
```

**2. Backup Files:**

```bash
docker run --rm \
  -v teampass-sk:/sk:ro \
  -v teampass-files:/files:ro \
  -v teampass-upload:/upload:ro \
  -v $(pwd):/backup \
  alpine tar czf /backup/teampass-files-$(date +%Y%m%d).tar.gz /sk /files /upload
```

### Restore

**1. Restore Database:**

```bash
docker-compose exec -T db mariadb \
  -u root -p${MARIADB_ROOT_PASSWORD} \
  ${DB_NAME} < teampass-backup-20240315.sql
```

**2. Restore Files:**

```bash
docker run --rm \
  -v teampass-sk:/sk \
  -v teampass-files:/files \
  -v teampass-upload:/upload \
  -v $(pwd):/backup \
  alpine tar xzf /backup/teampass-files-20240315.tar.gz
```

---

## 🔍 Troubleshooting

### Container Won't Start

**Check logs:**

```bash
docker-compose logs teampass
docker-compose logs db
```

**Common issues:**

1. **Database connection error:**
   - Verify DB_PASSWORD matches in `.env`
   - Wait for database to be ready (30-60 seconds)

2. **Port already in use:**
   - Change TEAMPASS_PORT in `.env`
   - Check: `sudo netstat -tulpn | grep 8080`

3. **Permission issues:**
   ```bash
   docker-compose exec teampass chown -R nginx:nginx /var/www/html/sk
   ```

### Installation Won't Complete

**Clear and restart:**

```bash
docker-compose down
docker volume rm teampass-db
docker-compose up -d
```

### Health Check Failing

```bash
# Check container health
docker-compose ps

# Manual health check
docker-compose exec teampass wget -O- http://localhost/health
```

### Database Issues

**Reset database:**

```bash
docker-compose down
docker volume rm teampass-db
docker-compose up -d
```

**Access database shell:**

```bash
docker-compose exec db mariadb -u root -p
```

---

## 🛠️ Advanced Usage

### Custom PHP Configuration

Create `docker/php/custom.ini`:

```ini
max_execution_time = 300
memory_limit = 1024M
```

Mount in `docker-compose.yml`:

```yaml
volumes:
  - ../../docker/php/custom.ini:/usr/local/etc/php/conf.d/custom.ini:ro
```

### Running Behind a Corporate Proxy

```yaml
environment:
  HTTP_PROXY: http://proxy.company.com:8080
  HTTPS_PROXY: http://proxy.company.com:8080
  NO_PROXY: localhost,127.0.0.1,db
```

### Using External Database

```yaml
# Remove the 'db' service from docker-compose.yml
# Update environment variables:
environment:
  DB_HOST: mysql.example.com
  DB_PORT: 3306
```

### Performance Tuning

**Increase PHP workers:**

Edit `docker/supervisor/supervisord.conf`:

```ini
[program:php-fpm]
process_name=%(program_name)s_%(process_num)02d
numprocs=4
```

**Optimize MariaDB:**

Edit `docker/mariadb/custom.cnf`:

```ini
innodb_buffer_pool_size = 1G
max_connections = 300
```

### Running Multiple Instances

```bash
# Instance 1
COMPOSE_PROJECT_NAME=teampass1 docker-compose up -d

# Instance 2
COMPOSE_PROJECT_NAME=teampass2 docker-compose up -d
```

---

## 📊 Monitoring

### Container Stats

```bash
docker stats teampass-app teampass-db
```

### Application Logs

```bash
# Follow all logs
docker-compose logs -f

# Follow TeamPass only
docker-compose logs -f teampass

# Last 100 lines
docker-compose logs --tail=100 teampass
```

### Health Check

```bash
# Via Docker
docker inspect teampass-app | grep -A 10 Health

# Via endpoint
curl http://localhost:8080/health
```

---

## 🔗 Useful Commands

```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# Restart TeamPass
docker-compose restart teampass

# View logs
docker-compose logs -f

# Execute command in container
docker-compose exec teampass sh

# Update images
docker-compose pull

# Remove all data (DANGER!)
docker-compose down -v

# Shell into database
docker-compose exec db mariadb -u root -p

# Check configuration
docker-compose config

# Scale (not recommended for TeamPass)
docker-compose up -d --scale teampass=2
```

---

## 📚 Additional Resources

- **Official Documentation:** https://teampass.readthedocs.io
- **Docker Hub:** https://hub.docker.com/r/teampass/teampass
- **GitHub Repository:** https://github.com/nilsteampassnet/TeamPass
- **Issue Tracker:** https://github.com/nilsteampassnet/TeamPass/issues
- **Community:** https://www.reddit.com/r/TeamPass/

---

## 🆘 Getting Help

If you encounter issues:

1. Check this documentation
2. Review container logs: `docker-compose logs`
3. Search existing [GitHub Issues](https://github.com/nilsteampassnet/TeamPass/issues)
4. Create a new issue with:
   - Docker version: `docker --version`
   - Compose version: `docker-compose --version`
   - Error logs
   - Steps to reproduce

---

**Last updated:** 2024-01-15
**TeamPass Version:** 3.1.5.2
