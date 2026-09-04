#!/bin/sh
set -e

# ============================================
# TeamPass Docker Entrypoint Script
# ============================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default environment variables
DB_HOST="${DB_HOST:-db}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-teampass}"
DB_USER="${DB_USER:-teampass}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_PREFIX="${DB_PREFIX:-teampass_}"

ADMIN_EMAIL="${ADMIN_EMAIL:-admin@teampass.local}"
ADMIN_PWD="${ADMIN_PWD:-}"

INSTALL_MODE="${INSTALL_MODE:-manual}"
TEAMPASS_URL="${TEAMPASS_URL:-http://localhost}"

# Extract version from PHP constants (TP_VERSION and TP_VERSION_MINOR)
if [ -f "/var/www/html/app/config/include.php" ]; then
    TP_VERSION=$(grep "define('TP_VERSION'" /var/www/html/app/config/include.php | sed -n "s/.*'\([0-9.]*\)'.*/\1/p")
    TP_VERSION_MINOR=$(grep "define('TP_VERSION_MINOR'" /var/www/html/app/config/include.php | sed -n "s/.*'\([0-9]*\)'.*/\1/p")
    TEAMPASS_VERSION="${TP_VERSION}.${TP_VERSION_MINOR}"
    # Schema floor shipped by this image. TP_VERSION alone cannot express it:
    # two releases share "3.2.2" while only 3.2.2.1 carries the extra migration.
    TP_UPGRADE_MIN_DATE=$(grep 'UPGRADE_MIN_DATE' /var/www/html/app/config/include.php | head -n1 | grep -o '[0-9]\{6,\}' | head -n1)
else
    # Fallback if include.php is not available yet
    TEAMPASS_VERSION="${TEAMPASS_VERSION:-3.1.5.2}"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "  🔐 TeamPass Docker Container"
echo "  Version: ${TEAMPASS_VERSION}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Function to wait for database
wait_for_database() {
    echo -e "${BLUE}⏳ Waiting for database at ${DB_HOST}:${DB_PORT}...${NC}"

    max_attempts=30
    attempt=0

    while [ $attempt -lt $max_attempts ]; do
        if nc -z "$DB_HOST" "$DB_PORT" 2>/dev/null; then
            echo -e "${GREEN}✅ Database is ready!${NC}"
            return 0
        fi

        attempt=$((attempt + 1))
        echo "   Attempt $attempt/$max_attempts - Waiting for database connection..."
        sleep 2
    done

    echo -e "${RED}❌ Database connection timeout after ${max_attempts} attempts${NC}"
    exit 1
}

# Function to check if TeamPass is already installed
is_installed() {
    [ -f "/var/www/html/app/config/settings.php" ]
}

# Function to create required directories
create_directories() {
    echo -e "${BLUE}📁 Creating required directories...${NC}"

    mkdir -p /var/www/html/storage/sk
    mkdir -p /var/www/html/storage/files
    mkdir -p /var/www/html/storage/upload
    mkdir -p /var/www/html/storage/config
    mkdir -p /var/www/html/storage/backups
    mkdir -p /var/www/html/secrets
    mkdir -p /var/www/html/app/includes/libraries/csrfp/log

    echo -e "${GREEN}✅ Directories created${NC}"
}

# Function to set correct permissions
set_permissions() {
    echo -e "${BLUE}🔒 Setting file permissions...${NC}"

    # The storage/ root itself must be writable by nginx so PHP can create
    # runtime sub-directories, and the installer "writable" check passes (issue #5238).
    chown nginx:nginx /var/www/html/storage
    chown -R nginx:nginx /var/www/html/storage/sk
    chown -R nginx:nginx /var/www/html/storage/files
    chown -R nginx:nginx /var/www/html/storage/upload
    chown -R nginx:nginx /var/www/html/storage/config
    chown -R nginx:nginx /var/www/html/storage/backups
    chown -R nginx:nginx /var/www/html/secrets
    chown -R nginx:nginx /var/www/html/app/includes/libraries/csrfp/log

    chmod 750 /var/www/html/storage
    chmod 700 /var/www/html/storage/sk
    chmod 750 /var/www/html/storage/files
    chmod 750 /var/www/html/storage/upload
    chmod 750 /var/www/html/storage/config
    chmod 750 /var/www/html/storage/backups
    chmod 700 /var/www/html/secrets
    chmod 750 /var/www/html/app/includes/libraries/csrfp/log

    echo -e "${GREEN}✅ Permissions set${NC}"
}

# Redirect an application config file to a persistent copy through a symlink.
# $1 = path used by the application, $2 = path on the persistent volume.
# An existing real file is migrated into the volume once, so already-installed
# containers keep their configuration on first upgrade to this image.
link_persistent_file() {
    app_file="$1"
    persist_file="$2"

    # Make sure the persistent target directory exists before any move/link.
    mkdir -p "$(dirname "$persist_file")"

    # Migrate a pre-existing real file into the persistent volume (only once).
    if [ -f "$app_file" ] && [ ! -L "$app_file" ] && [ ! -f "$persist_file" ]; then
        mv "$app_file" "$persist_file"
    fi

    # Point the application path to the persistent copy so that both the
    # installer (write) and the application (read) use the volume-backed file.
    if [ ! -L "$app_file" ]; then
        rm -f "$app_file"
        ln -s "$persist_file" "$app_file"
    fi

    # Force ownership and mode so PHP-FPM (running as the nginx user) can read
    # AND write the persistent file. A file copied in from an on-premise install
    # may arrive owned by www-data or with restrictive bits, which otherwise
    # fails the installer "writable" check (issue #5238).
    chown -h nginx:nginx "$app_file" 2>/dev/null || true
    if [ -f "$persist_file" ]; then
        chown nginx:nginx "$persist_file"
        chmod 0640 "$persist_file"
    fi
}

# Ensure install-time state survives container recreation.
#
# In TeamPass 3.2 the installer writes three artifacts that live outside the
# default data volumes and are therefore lost when the container is recreated
# (docker compose down && up). Their loss makes TeamPass believe it is not
# installed and triggers a reinstall on every restart (issue #5236):
#
#   - app/config/settings.php                             (DB credentials + install marker)
#   - app/includes/libraries/csrfp/libs/csrfp.config.php  (CSRF config, fatal if missing)
#   - secrets/<random>                                    (Defuse master key)
#
# settings.php and csrfp.config.php are redirected through symlinks to the
# persistent config volume (storage/config) so the installer transparently
# writes to the volume. The secrets directory is mounted as a volume directly
# (handled in docker-compose / Dockerfile), here we only ensure it exists.
persist_install_state() {
    echo -e "${BLUE}🔗 Ensuring install state persistence...${NC}"

    PERSIST_DIR=/var/www/html/storage/config
    mkdir -p "$PERSIST_DIR"

    link_persistent_file \
        /var/www/html/app/config/settings.php \
        "$PERSIST_DIR/settings.php"
    link_persistent_file \
        /var/www/html/app/includes/libraries/csrfp/libs/csrfp.config.php \
        "$PERSIST_DIR/csrfp.config.php"

    chown nginx:nginx "$PERSIST_DIR" 2>/dev/null || true
    chmod 750 "$PERSIST_DIR" 2>/dev/null || true

    echo -e "${GREEN}✅ Install state persistence ensured${NC}"
}

# Function to apply dynamic PHP configuration from environment variables.
# Writes a conf.d override file so that values set in .env are actually used
# at runtime instead of being silently discarded.
configure_php() {
    PHP_INI_OVERRIDE=/usr/local/etc/php/conf.d/teampass-env.ini
    {
        echo "memory_limit = ${PHP_MEMORY_LIMIT:-512M}"
        echo "upload_max_filesize = ${PHP_UPLOAD_MAX_FILESIZE:-100M}"
        echo "post_max_size = ${PHP_POST_MAX_SIZE:-${PHP_UPLOAD_MAX_FILESIZE:-100M}}"
        echo "max_execution_time = ${PHP_MAX_EXECUTION_TIME:-120}"
    } > "$PHP_INI_OVERRIDE"
    echo -e "${GREEN}✅ PHP configuration applied (memory=${PHP_MEMORY_LIMIT:-512M}, upload=${PHP_UPLOAD_MAX_FILESIZE:-100M})${NC}"
}

# Function to perform automatic installation
auto_install() {
    echo -e "${BLUE}🚀 Starting automatic installation...${NC}"

    if [ -z "$DB_PASSWORD" ]; then
        echo -e "${RED}❌ Error: DB_PASSWORD is required for auto installation${NC}"
        exit 1
    fi

    if [ -z "$ADMIN_PWD" ]; then
        echo -e "${RED}❌ Error: ADMIN_PWD is required for auto installation${NC}"
        exit 1
    fi

    # Check if install-cli.php exists
    if [ -f "/var/www/html/app/scripts/install-cli.php" ]; then
        php /var/www/html/app/scripts/install-cli.php \
            --db-host="$DB_HOST" \
            --db-port="$DB_PORT" \
            --db-name="$DB_NAME" \
            --db-user="$DB_USER" \
            --db-password="$DB_PASSWORD" \
            --db-prefix="$DB_PREFIX" \
            --admin-email="$ADMIN_EMAIL" \
            --admin-pwd="$ADMIN_PWD" \
            --url="$TEAMPASS_URL"

        if [ $? -eq 0 ]; then
            echo -e "${GREEN}✅ Automatic installation completed successfully!${NC}"
            rm -rf /var/www/html/public/install
        else
            echo -e "${RED}❌ Automatic installation failed${NC}"
            exit 1
        fi
    else
        echo -e "${YELLOW}⚠️  Warning: install-cli.php not found, falling back to manual installation${NC}"
        manual_install_instructions
    fi
}

# Read the TeamPass version recorded in the database.
# Delegates to a PHP helper that connects with the credentials stored in
# app/config/settings.php (the installed source of truth) using the bundled
# mysqli extension. This avoids depending on the "mysql" client binary, which
# is not part of the image and made the auto-upgrade fail silently (issue
# #5266), and removes the reliance on environment variables for the upgrade.
# The helper already tries the current key (teampass_version) then the legacy
# key (cpassman_version) used by TeamPass 3.1.5.x and earlier (issue #5238).
# Echoes the version string, or nothing when it cannot be read.
read_db_version() {
    _helper="/var/www/html/app/scripts/read-db-version.php"
    if [ -f "$_helper" ]; then
        php "$_helper" 2>/dev/null || true
    fi
}

# Read the schema level recorded in the database (teampass_misc.upgrade_timestamp).
#
# This is the value the application itself tests in upgradeRequired()
# (app/sources/main.functions.php) against the UPGRADE_MIN_DATE constant, and it
# is the only signal that separates two releases sharing the same TP_VERSION:
# teampass_version stores TP_VERSION ("3.2.2") and never TP_VERSION_MINOR.
# Echoes the timestamp, or nothing when it cannot be read.
read_db_upgrade_timestamp() {
    _helper="/var/www/html/app/scripts/read-db-upgrade-timestamp.php"
    if [ -f "$_helper" ]; then
        php "$_helper" 2>/dev/null || true
    fi
}

# Return true when the application would still demand an upgrade, i.e. when the
# schema level recorded in the database is below the floor shipped by this image.
#
# An unreadable or missing value is deliberately NOT treated as stale: the
# install directory is already kept whenever the database cannot be read, and
# assuming "stale" here would replay a migration on every single boot.
schema_is_stale() {
    [ -n "$TP_UPGRADE_MIN_DATE" ] || return 1

    _recorded=$(read_db_upgrade_timestamp)
    _recorded=$(printf '%s' "$_recorded" | tr -cd '0-9')
    [ -n "$_recorded" ] || return 1

    [ "$_recorded" -lt "$TP_UPGRADE_MIN_DATE" ]
}

# Convert a dotted version (X, X.Y or X.Y.Z) into a comparable integer so the
# upgrade chain can be ordered without relying on lexicographic sorting (which
# would place 3.10.0 before 3.2.0). Missing or malformed components count as 0.
version_to_number() {
    _rest="$1."
    _maj=${_rest%%.*}; _rest=${_rest#*.}
    _min=${_rest%%.*}; _rest=${_rest#*.}
    _pat=${_rest%%.*}

    _maj=$(printf '%s' "$_maj" | tr -cd '0-9')
    _min=$(printf '%s' "$_min" | tr -cd '0-9')
    _pat=$(printf '%s' "$_pat" | tr -cd '0-9')

    echo $(( ${_maj:-0} * 1000000 + ${_min:-0} * 1000 + ${_pat:-0} ))
}

# Oldest database version this headless chain is allowed to start from.
#
# upgrade_run_3.0.0.php is NOT self-contained: install/upgrade_scripts_manager.php
# drives it together with five sibling scripts (_passwords, _logs, _fields,
# _suggestions, _files) that re-encrypt per-user data, and it does not include
# them itself. Running it alone would leave a half-migrated 2.x database behind.
# Below this floor the container defers to the web wizard, which is the only
# thing able to run that sequence.
UPGRADE_CHAIN_FLOOR="3.1.5"

# List the migration steps shipped by the image, ordered by version.
# Only plain upgrade_run_X.Y.Z.php files at or above the floor are chain steps:
# upgrade_run_3.1.php is a legacy entry point and upgrade_run_3.0.0_users.php
# and friends are helpers invoked by the wizard, not standalone versions.
#
# The interleaved upgrade_operations.php data-sanitation steps of the wizard
# manifest are deliberately not part of this chain: they read their parameters
# from POST and cannot run from the CLI. They stay wizard-only, exactly as
# before this change.
upgrade_chain() {
    _floor_number=$(version_to_number "$UPGRADE_CHAIN_FLOOR")

    for _script in /var/www/html/public/install/upgrade_run_*.php; do
        [ -f "$_script" ] || continue

        _base=${_script##*/}
        _ver=${_base#upgrade_run_}
        _ver=${_ver%.php}

        case "$_ver" in
            *_*) continue ;;
            *.*.*) ;;
            *) continue ;;
        esac

        _ver_number=$(version_to_number "$_ver")
        [ "$_ver_number" -lt "$_floor_number" ] && continue

        echo "$_ver_number $_ver"
    done | sort -n | cut -d' ' -f2
}

# Run a single migration step and report whether it succeeded.
#
# The upgrade scripts signal a failure in their JSON output but always call
# exit() with no argument, which is exit code 0. Relying on the exit status
# alone would therefore treat a failed migration as successful and let the
# recorded version advance past a schema change that never applied.
run_upgrade_step() {
    _step_version="$1"
    _step_script="/var/www/html/public/install/upgrade_run_${_step_version}.php"

    # Capture the output through an "if", never a bare assignment: under "set -e"
    # a plain `out=$(php ...)` aborts the whole entrypoint when the script exits
    # non-zero, which is exactly the container crash of issue #5299.
    if _step_output=$(php "$_step_script" 2>&1); then
        _step_rc=0
    else
        _step_rc=$?
    fi

    _step_error=$(printf '%s' "$_step_output" | sed -n 's/.*"error"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p')

    if [ "$_step_rc" -ne 0 ] || [ -n "$_step_error" ]; then
        echo -e "${YELLOW}⚠️  Database upgrade to ${_step_version} failed: ${_step_error:-exit code ${_step_rc}}${NC}"
        # Echo the raw output only on failure: it is what support needs, and it
        # would be pure noise on the (normal) success path.
        [ -n "$_step_output" ] && echo "      $_step_output"
        return 1
    fi

    # Record the version reached. The standalone upgrade scripts never write
    # teampass_version themselves (only the web wizard does), so without this
    # the migration would be replayed on every boot and the install directory
    # would stay reachable forever.
    if ! php /var/www/html/app/scripts/write-db-version.php "$_step_version"; then
        echo -e "${YELLOW}⚠️  Schema upgraded to ${_step_version} but the version could not be recorded${NC}"
        return 1
    fi

    echo -e "${GREEN}✅ Database upgrade to ${_step_version} completed${NC}"
    return 0
}

# Function to run pending database migrations when the container image is
# newer than the version recorded in teampass_misc.  The upgrade scripts are
# idempotent (CREATE TABLE IF NOT EXISTS / ALTER ... IF NOT EXISTS), so it is
# safe to re-run them on an already up-to-date database.
#
# Every intermediate step is applied, not only the one matching the image
# version: a 3.2.0 database moved to a 3.2.2 image needs upgrade_run_3.2.1.php
# too, which creates tables that upgrade_run_3.2.2.php only alters. Skipping it
# left the schema silently incomplete, because the ALTER statements are guarded
# by a SHOW INDEX / SHOW COLUMNS probe that simply finds nothing.
auto_upgrade() {
    # Read the version stored in the database (current or legacy key). The
    # helper reads the DB credentials from settings.php, so no environment
    # variable is required here for an already-installed instance.
    DB_VERSION=$(read_db_version)

    if [ -z "$DB_VERSION" ]; then
        echo -e "${YELLOW}⚠️  Could not read DB version, skipping auto-upgrade${NC}"
        return 0
    fi

    # TP_VERSION is already extracted at the top of this script into TEAMPASS_VERSION
    IMAGE_VERSION="${TP_VERSION:-${TEAMPASS_VERSION}}"

    _db_number=$(version_to_number "$DB_VERSION")
    _image_number=$(version_to_number "$IMAGE_VERSION")
    _floor_number=$(version_to_number "$UPGRADE_CHAIN_FLOOR")

    # A database older than the floor needs the per-user re-encryption sequence
    # that only the web wizard can drive. Leave it untouched and keep the
    # install directory so /install/upgrade.php stays reachable.
    if [ "$_db_number" -lt "$_floor_number" ]; then
        echo -e "${YELLOW}⚠️  Database version ${DB_VERSION} is older than ${UPGRADE_CHAIN_FLOOR};${NC}"
        echo -e "${YELLOW}   finish the upgrade through /install/upgrade.php (web wizard).${NC}"
        return 0
    fi

    # Compare numerically, never as strings: an image older than the database
    # (a deliberate rollback) must not be treated as a pending upgrade.
    if [ "$_db_number" -ge "$_image_number" ]; then
        # Equal version numbers do NOT mean an equal schema. A patch release
        # (TP_VERSION_MINOR) ships its schema changes inside the existing
        # upgrade_run_<TP_VERSION>.php - 3.2.2.1 added items.revision_changed_at to
        # upgrade_run_3.2.2.php - and teampass_version stays "3.2.2" either way, so
        # the comparison above can never see it. The schema floor can.
        #
        # Replaying the script is safe: every statement is guarded (CREATE TABLE IF
        # NOT EXISTS, addColumnIfNotExist, SHOW INDEX / SHOW COLUMNS probes), and it
        # is what refreshes misc.upgrade_timestamp - the value the application tests
        # before it enables the login button.
        if schema_is_stale; then
            _current_script="/var/www/html/public/install/upgrade_run_${IMAGE_VERSION}.php"

            if [ ! -f "$_current_script" ]; then
                # Typically a container whose writable layer already had the install
                # directory removed by an earlier boot. The files cannot come back on
                # a restart, so recreating the container from the image is the fix.
                echo -e "${YELLOW}⚠️  Database schema is older than this image expects, but the ${IMAGE_VERSION} migration is not available.${NC}"
                echo -e "${YELLOW}   Recreate the container from the image (docker compose up -d --force-recreate),${NC}"
                echo -e "${YELLOW}   or finish the upgrade through /install/upgrade.php (web wizard).${NC}"
                return 0
            fi

            echo -e "${BLUE}🔄 Schema level below ${TP_UPGRADE_MIN_DATE}; replaying the ${IMAGE_VERSION} migration...${NC}"

            if ! run_upgrade_step "$IMAGE_VERSION"; then
                echo -e "${YELLOW}⚠️  Replay failed - finish it through /install/upgrade.php${NC}"
            fi

            return 0
        fi

        echo -e "${GREEN}✅ Database is up to date (${DB_VERSION})${NC}"
        return 0
    fi

    echo -e "${BLUE}🔄 Upgrading database from ${DB_VERSION} to ${IMAGE_VERSION}...${NC}"

    _steps_applied=0
    for _candidate in $(upgrade_chain); do
        _candidate_number=$(version_to_number "$_candidate")

        # Already applied, or newer than the code shipped in this image.
        if [ "$_candidate_number" -le "$_db_number" ] || [ "$_candidate_number" -gt "$_image_number" ]; then
            continue
        fi

        echo -e "${BLUE}   ↳ Applying ${_candidate}...${NC}"

        # Stop at the first failure: the next step may depend on this one, and
        # the recorded version stays where it is so the run can be resumed. The
        # install directory is kept below so the web wizard remains reachable
        # (issue #5299 — a failing upgrade must not crash the container).
        if ! run_upgrade_step "$_candidate"; then
            echo -e "${YELLOW}⚠️  Upgrade stopped at ${_candidate} — finish it through /install/upgrade.php${NC}"
            return 0
        fi

        _steps_applied=$((_steps_applied + 1))
    done

    if [ "$_steps_applied" -eq 0 ]; then
        echo -e "${YELLOW}⚠️  No upgrade script found between ${DB_VERSION} and ${IMAGE_VERSION}, skipping${NC}"
        return 0
    fi

    echo -e "${GREEN}✅ Database upgraded to ${IMAGE_VERSION} (${_steps_applied} step(s) applied)${NC}"
}

# Function to show manual installation instructions
manual_install_instructions() {
    echo ""
    echo -e "${YELLOW}📝 Manual installation required${NC}"
    echo ""
    echo "   Please open your browser and navigate to:"
    echo -e "   ${BLUE}${TEAMPASS_URL}/install/install.php${NC}"
    echo ""
    echo "   Database configuration:"
    echo "   - Host: ${DB_HOST}"
    echo "   - Port: ${DB_PORT}"
    echo "   - Database: ${DB_NAME}"
    echo "   - User: ${DB_USER}"
    echo "   - Password: [Use the password from your .env file]"
    echo ""
    echo "   The secure (saltkey) path is auto-configured by the installer to:"
    echo -e "   ${BLUE}/var/www/html/secrets${NC}"
    echo ""
    echo "   After installation, restart the container to remove the install directory:"
    echo -e "   ${BLUE}docker-compose restart teampass${NC}"
    echo ""
}

# Main execution flow
main() {
    # Apply dynamic PHP configuration from environment variables
    configure_php

    # Wait for database
    wait_for_database

    # Create directories
    create_directories

    # Set permissions
    set_permissions

    # Redirect install state (settings.php + csrfp.config.php) to the persistent
    # volume so the installation survives container recreation (issue #5236).
    persist_install_state

    # Configure PHP-FPM to listen on 127.0.0.1:9000 and run as nginx user
    if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then
        sed -i 's/listen = .*/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf
        sed -i 's/^user = .*/user = nginx/' /usr/local/etc/php-fpm.d/www.conf
        sed -i 's/^group = .*/group = nginx/' /usr/local/etc/php-fpm.d/www.conf
    fi

    # Configure cron job for scheduler
    echo "* * * * * php /var/www/html/app/sources/scheduler.php > /dev/null 2>&1" | crontab -u nginx -

    # Check installation status
    if is_installed; then
        echo -e "${GREEN}✅ TeamPass is already configured${NC}"

        # Auto-upgrade: apply pending database migrations when the image
        # version is newer than the version recorded in teampass_misc.
        auto_upgrade

        # Remove the install directory only when the database is confirmed to be
        # already at the image version. While an upgrade is pending (e.g. a
        # database migrated from an older on-premise install), the install
        # directory is kept so that /install/upgrade.php remains reachable to
        # finish the upgrade through the web wizard (issue #5238). If the version
        # cannot be read, the directory is also kept: removing it would strand a
        # possibly-pending upgrade with no way to recover (issue #5266).
        if [ -d "/var/www/html/public/install" ]; then
            CURRENT_DB_VERSION=$(read_db_version)
            if [ -z "$CURRENT_DB_VERSION" ]; then
                echo -e "${YELLOW}⚠️  Could not read DB version; keeping install directory so /install/upgrade.php stays reachable${NC}"
            elif [ "$CURRENT_DB_VERSION" != "${TP_VERSION:-${TEAMPASS_VERSION}}" ]; then
                echo -e "${YELLOW}⏳ Upgrade pending (DB ${CURRENT_DB_VERSION} → ${TP_VERSION:-${TEAMPASS_VERSION}}); keeping install directory for /install/upgrade.php${NC}"
            elif schema_is_stale; then
                # The versions match but the schema floor does not: this is exactly the
                # case the version test is blind to. Deleting the install directory here
                # would strand the instance - the application disables the login button
                # on the same signal, and the wizard is the only way out.
                echo -e "${YELLOW}⏳ Database schema older than this image expects; keeping install directory for /install/upgrade.php${NC}"
            else
                echo -e "${BLUE}🗑️  Removing install directory...${NC}"
                rm -rf /var/www/html/public/install
            fi
        fi
    else
        echo -e "${YELLOW}⚙️  TeamPass is not configured yet${NC}"

        if [ "$INSTALL_MODE" = "auto" ]; then
            auto_install
        else
            manual_install_instructions
        fi
    fi

    echo ""
    echo -e "${GREEN}✅ TeamPass container is ready!${NC}"
    echo ""

    # Execute the main command (supervisord)
    exec "$@"
}

# Run main function
main "$@"
