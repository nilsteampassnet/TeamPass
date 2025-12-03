# 🔄 Migration Guide - TeamPass Docker

This guide helps you migrate from the old Docker setup to the new optimized version.

## 📋 What's Changed?

### Old Setup (`dormancygrace/teampass`)
- ❌ Container clones GitHub repo at runtime
- ❌ Based on outdated `richarvey/nginx-php-fpm` image
- ❌ Manual setup required every deployment
- ❌ Complex volume management
- ❌ No health checks
- ❌ Single architecture (amd64)

### New Setup (`teampass/teampass`)
- ✅ Application code included in image
- ✅ Modern Alpine-based PHP 8.3-FPM
- ✅ Optional automatic installation
- ✅ Simplified volume structure
- ✅ Built-in health checks
- ✅ Published on Docker Hub and GitHub Container Registry
- ✅ Multi-stage build (smaller image)
- ✅ Better security and performance

---

## 🚀 Migration Process

### Step 1: Backup Your Current Installation

**IMPORTANT:** Always backup before migrating!

```bash
# 1. Backup database
docker-compose exec db mysqldump \
  -u root -p \
  teampass > backup-teampass-$(date +%Y%m%d).sql

# 2. Backup application files
docker cp teampass-web:/var/www/html/sk ./backup-sk
docker cp teampass-web:/var/www/html/files ./backup-files
docker cp teampass-web:/var/www/html/upload ./backup-upload
docker cp teampass-web:/var/www/html/includes/config/settings.php ./backup-settings.php

# 3. Backup your docker-compose.yml
cp docker-compose.yml docker-compose.yml.backup
```

### Step 2: Stop Current Containers

```bash
# Stop but don't remove (keeps volumes)
docker-compose stop

# Or completely remove (we have backups!)
docker-compose down
```

### Step 3: Update Configuration Files

#### Option A: Use New Structure (Recommended)

```bash
# Download new docker-compose setup
cd /path/to/teampass
git pull origin master

# Navigate to new location
cd docker/docker-compose

# Create configuration
cp .env.example .env
```

**Edit `.env` file:**

```bash
# Use your existing database credentials
DB_NAME=teampass
DB_USER=teampass
DB_PASSWORD=YourOldPassword
MARIADB_ROOT_PASSWORD=YourOldRootPassword

# Set port
TEAMPASS_PORT=8080

# Installation already done
INSTALL_MODE=manual
```

#### Option B: Update Existing docker-compose.yml

Replace your current `docker-compose.yml` with the new version:

```yaml
version: "3.8"

services:
  teampass:
    image: teampass/teampass:latest  # Changed from dormancygrace/teampass
    container_name: teampass-app
    restart: unless-stopped

    environment:
      DB_HOST: db
      DB_PORT: 3306
      DB_NAME: ${DB_NAME:-teampass}
      DB_USER: ${DB_USER:-teampass}
      DB_PASSWORD: ${DB_PASSWORD}
      DB_PREFIX: ${DB_PREFIX:-teampass_}
      INSTALL_MODE: manual

    volumes:
      - teampass-sk:/var/www/html/sk
      - teampass-files:/var/www/html/files
      - teampass-upload:/var/www/html/upload

    ports:
      - "${TEAMPASS_PORT:-8080}:80"

    networks:
      - teampass-network

    depends_on:
      db:
        condition: service_healthy

  db:
    image: mariadb:11.2
    container_name: teampass-db
    restart: unless-stopped

    environment:
      MARIADB_ROOT_PASSWORD: ${MARIADB_ROOT_PASSWORD}
      MARIADB_DATABASE: ${DB_NAME:-teampass}
      MARIADB_USER: ${DB_USER:-teampass}
      MARIADB_PASSWORD: ${DB_PASSWORD}

    volumes:
      - teampass-db:/var/lib/mysql

    networks:
      - teampass-network

    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect"]
      interval: 10s
      timeout: 5s
      retries: 5

networks:
  teampass-network:
    driver: bridge

volumes:
  teampass-sk:
  teampass-files:
  teampass-upload:
  teampass-db:
```

### Step 4: Migrate Data

#### If Using Existing Volumes

If your old setup used Docker volumes with the same names, they'll be reused automatically:

```bash
# Check existing volumes
docker volume ls | grep teampass
```

#### If Using Bind Mounts

Convert bind mounts to volumes:

```bash
# Create new volumes
docker volume create teampass-sk
docker volume create teampass-files
docker volume create teampass-upload
docker volume create teampass-db

# Copy data from old bind mounts
docker run --rm \
  -v /path/to/old/teampass-html:/source:ro \
  -v teampass-sk:/dest/sk \
  -v teampass-files:/dest/files \
  -v teampass-upload:/dest/upload \
  alpine sh -c "cp -a /source/sk/* /dest/sk/ && \
                cp -a /source/files/* /dest/files/ && \
                cp -a /source/upload/* /dest/upload/"
```

#### If Restoring from Backup

```bash
# Start database only
docker-compose up -d db

# Wait for database
sleep 30

# Restore database
docker-compose exec -T db mariadb \
  -u root -p${MARIADB_ROOT_PASSWORD} \
  ${DB_NAME} < backup-teampass-20240315.sql

# Restore files
docker run --rm \
  -v teampass-sk:/sk \
  -v teampass-files:/files \
  -v teampass-upload:/upload \
  -v $(pwd):/backup:ro \
  alpine sh -c "cp -a /backup/backup-sk/* /sk/ && \
                cp -a /backup/backup-files/* /files/ && \
                cp -a /backup/backup-upload/* /upload/"
```

### Step 5: Start New Version

```bash
# Pull new image
docker-compose pull

# Start services
docker-compose up -d

# Watch logs
docker-compose logs -f
```

### Step 6: Verify Migration

1. **Check container status:**
   ```bash
   docker-compose ps
   ```

2. **Test application:**
   - Open browser: `http://localhost:8080`
   - Login with your credentials
   - Verify passwords are accessible
   - Check file uploads work

3. **Check health:**
   ```bash
   docker inspect teampass-app | grep -A 10 Health
   ```

### Step 7: Cleanup (Optional)

Once verified everything works:

```bash
# Remove old images
docker rmi dormancygrace/teampass:latest
docker rmi richarvey/nginx-php-fpm:latest

# Remove old backup files (after confirming data is safe!)
rm -rf backup-sk backup-files backup-upload
```

---

## 🔀 Migration Scenarios

### Scenario 1: Fresh Installation on New Server

**Best approach:** Use new docker-compose setup directly

```bash
git clone https://github.com/nilsteampassnet/TeamPass.git
cd TeamPass/docker/docker-compose
cp .env.example .env
# Edit .env
docker-compose up -d
```

### Scenario 2: Update Running Production System

**Best approach:** Migrate with minimal downtime

```bash
# 1. Backup everything (see Step 1)
# 2. During maintenance window:
docker-compose down
# 3. Update docker-compose.yml
# 4. Start new version
docker-compose up -d
# 5. Verify
# 6. Total downtime: ~5-10 minutes
```

### Scenario 3: Parallel Installation (Zero Downtime)

**Best approach:** Run both versions simultaneously

```bash
# Old version on port 8080
# New version on port 8081

# In new directory
cd /opt/teampass-new
cp .env.example .env
# Set TEAMPASS_PORT=8081
docker-compose up -d

# Restore backup to new instance
# Test thoroughly
# Switch nginx/proxy to new port
# Stop old version
```

### Scenario 4: Database on External Server

**Old config:**
```yaml
environment:
  DB_HOST: mysql.example.com
```

**New config (same):**
```yaml
environment:
  DB_HOST: mysql.example.com
  DB_PORT: 3306
  DB_NAME: teampass
  DB_USER: teampass
  DB_PASSWORD: ${DB_PASSWORD}
```

No change needed! Just remove the `db` service from docker-compose.yml.

---

## ⚠️ Common Issues

### Issue 1: "Database connection failed"

**Cause:** Password mismatch or old database still running

**Solution:**
```bash
# Check database is accessible
docker-compose exec db mariadb -u root -p

# Verify environment variables
docker-compose config | grep DB_
```

### Issue 2: "Install directory exists"

**Cause:** Migration didn't recognize existing installation

**Solution:**
```bash
# Manually remove install directory
docker-compose exec teampass rm -rf /var/www/html/install

# Restart
docker-compose restart teampass
```

### Issue 3: "Saltkey not found"

**Cause:** Saltkey volume not restored

**Solution:**
```bash
# Check sk directory
docker-compose exec teampass ls -la /var/www/html/sk

# Restore from backup
docker cp ./backup-sk/sk.txt teampass-app:/var/www/html/sk/

# Fix permissions
docker-compose exec teampass chown -R nginx:nginx /var/www/html/sk
docker-compose exec teampass chmod 700 /var/www/html/sk
```

### Issue 4: "Permission denied"

**Cause:** Wrong file ownership

**Solution:**
```bash
docker-compose exec teampass chown -R nginx:nginx \
  /var/www/html/sk \
  /var/www/html/files \
  /var/www/html/upload
```

### Issue 5: Images still from old registry

**Cause:** Docker cache

**Solution:**
```bash
# Remove old images
docker rmi dormancygrace/teampass:latest

# Force pull new
docker-compose pull

# Recreate containers
docker-compose up -d --force-recreate
```

---

## 📊 Comparison

| Feature | Old (dormancygrace) | New (teampass) |
|---------|-------------------|----------------|
| Image size | ~500MB | ~350MB |
| Startup time | 60-90s | 15-30s |
| PHP version | 7.4 / 8.0 | 8.3 |
| Base image | Ubuntu/Debian | Alpine Linux |
| Auto-install | ❌ | ✅ (optional) |
| Health checks | ❌ | ✅ |
| Registry | Docker Hub | Docker Hub + GHCR |
| Security scans | ❌ | ✅ (Trivy) |
| SBOM | ❌ | ✅ |
| Multi-arch | ❌ | ✅ (amd64) |

---

## ✅ Post-Migration Checklist

- [ ] Backup completed and verified
- [ ] New docker-compose.yml in place
- [ ] .env file configured
- [ ] Database migrated successfully
- [ ] Files (sk, files, upload) migrated
- [ ] Application accessible via browser
- [ ] Can login with existing credentials
- [ ] Passwords are decryptable
- [ ] File uploads work
- [ ] Scheduler running (check logs)
- [ ] Health check passing
- [ ] Old containers stopped
- [ ] Documentation updated
- [ ] Team notified of changes

---

## 🆘 Rollback Procedure

If something goes wrong:

```bash
# 1. Stop new version
docker-compose down

# 2. Restore old docker-compose.yml
cp docker-compose.yml.backup docker-compose.yml

# 3. Start old version
docker-compose up -d

# 4. Verify old version works
# 5. Investigate issue
# 6. Try migration again when ready
```

---

## 📞 Support

- **Documentation:** [DOCKER.md](DOCKER.md)
- **Issues:** https://github.com/nilsteampassnet/TeamPass/issues
- **Community:** https://www.reddit.com/r/TeamPass/

---

**Migration tested on TeamPass 3.1.5.2**
**Last updated: 2024-01-15**
