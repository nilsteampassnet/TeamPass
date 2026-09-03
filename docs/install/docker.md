<!-- docs/install/docker.md -->

## Overview

Teampass can be deployed using Docker and Docker Compose. This is the recommended approach for quick evaluation, isolated environments, and containerised production deployments.

> The complete Docker documentation is maintained in the repository root:
>
> - **[DOCKER.md](https://github.com/nilsteampassnet/TeamPass/blob/master/docs/DOCKER.md)** — Full setup guide: quick start, SSL, environment variables, backup, troubleshooting, advanced usage.
> - **[DOCKER-MIGRATION.md](https://github.com/nilsteampassnet/TeamPass/blob/master/docs/DOCKER-MIGRATION.md)** — Upgrade procedures between Docker image versions.

---

## Requirements

- Docker Engine 20.10+ or Docker Desktop
- Docker Compose 2.0+
- At least 2 GB of free disk space

---

## Quick start

```bash
# 1. Clone the repository
git clone https://github.com/nilsteampassnet/TeamPass.git
cd TeamPass/docker/docker-compose

# 2. Create and configure the environment file
cp .env.example .env
# Edit .env and set DB_PASSWORD and MARIADB_ROOT_PASSWORD at minimum

# 3. Start the stack
docker compose up -d

# 4. Create the secure key directory inside the container
docker exec teampass-app sh -c \
  "mkdir -p /var/TeampassSecurity && \
   chown nginx:nginx /var/TeampassSecurity && \
   chmod 750 /var/TeampassSecurity"

# 5. Open http://localhost in your browser and run the installation wizard
```

---

## Key environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `DB_PASSWORD` | — | **Required.** MariaDB password for the Teampass user |
| `MARIADB_ROOT_PASSWORD` | — | **Required.** MariaDB root password |
| `DB_HOST` | `db` | Database service hostname |
| `DB_NAME` | `teampass` | Database name |
| `DB_USER` | `teampass` | Database user |
| `TP_DOMAIN` | `localhost` | Public hostname (used for link generation) |

See `.env.example` in the repository for the full list.

---

## Upgrading

Refer to **[DOCKER-MIGRATION.md](https://github.com/nilsteampassnet/TeamPass/blob/master/docs/DOCKER-MIGRATION.md)** for step-by-step upgrade instructions, including database migration steps and breaking changes between versions.

The general process is:

```bash
# Pull the new image
docker compose pull

# Recreate the container
docker compose up -d

# Run the web-based upgrade wizard at http://<your-host>/install/upgrade.php
```

> 🔔 Always back up the database and the secure key directory before upgrading.

---

## Backup

Critical data to back up:

| What | Where |
|------|-------|
| Database | `docker exec teampass-db mysqldump -u root -p teampass > backup.sql` |
| Secure key | The directory mounted at `/var/TeampassSecurity` inside the container |
| Uploaded files | The volume mounted at `/var/www/html/teampass/upload` |

> 🔔 The secure key file is required to decrypt all data. Losing it makes the database unrecoverable.
