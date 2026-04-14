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
if [ -f "/var/www/html/includes/config/include.php" ]; then
    TP_VERSION=$(grep "define('TP_VERSION'" /var/www/html/includes/config/include.php | sed -n "s/.*'\([0-9.]*\)'.*/\1/p")
    TP_VERSION_MINOR=$(grep "define('TP_VERSION_MINOR'" /var/www/html/includes/config/include.php | sed -n "s/.*'\([0-9]*\)'.*/\1/p")
    TEAMPASS_VERSION="${TP_VERSION}.${TP_VERSION_MINOR}"
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
    [ -f "/var/www/html/includes/config/settings.php" ]
}

# Function to create required directories
create_directories() {
    echo -e "${BLUE}📁 Creating required directories...${NC}"

    mkdir -p /var/www/html/sk
    mkdir -p /var/www/html/files
    mkdir -p /var/www/html/upload
    mkdir -p /var/www/html/includes/libraries/csrfp/log

    echo -e "${GREEN}✅ Directories created${NC}"
}

# Function to set correct permissions
set_permissions() {
    echo -e "${BLUE}🔒 Setting file permissions...${NC}"

    chown -R nginx:nginx /var/www/html/sk
    chown -R nginx:nginx /var/www/html/files
    chown -R nginx:nginx /var/www/html/upload
    chown -R nginx:nginx /var/www/html/includes/libraries/csrfp/log

    chmod 700 /var/www/html/sk
    chmod 750 /var/www/html/files
    chmod 750 /var/www/html/upload
    chmod 750 /var/www/html/includes/libraries/csrfp/log

    echo -e "${GREEN}✅ Permissions set${NC}"
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
    if [ -f "/var/www/html/scripts/install-cli.php" ]; then
        php /var/www/html/scripts/install-cli.php \
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
            rm -rf /var/www/html/install
        else
            echo -e "${RED}❌ Automatic installation failed${NC}"
            exit 1
        fi
    else
        echo -e "${YELLOW}⚠️  Warning: install-cli.php not found, falling back to manual installation${NC}"
        manual_install_instructions
    fi
}

# Function to run pending database migrations when the container image is
# newer than the version recorded in teampass_misc.  The upgrade scripts are
# idempotent (CREATE TABLE IF NOT EXISTS / ALTER ... IF NOT EXISTS), so it is
# safe to re-run them on an already up-to-date database.
auto_upgrade() {
    if [ -z "$DB_PASSWORD" ]; then
        echo -e "${YELLOW}⚠️  DB_PASSWORD not set, skipping auto-upgrade check${NC}"
        return 0
    fi

    # Read the version stored in the database
    DB_VERSION=$(mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASSWORD" \
        "$DB_NAME" --skip-column-names --silent \
        -e "SELECT valeur FROM ${DB_PREFIX}misc WHERE type='admin' AND intitule='teampass_version' LIMIT 1" 2>/dev/null || true)

    if [ -z "$DB_VERSION" ]; then
        echo -e "${YELLOW}⚠️  Could not read DB version, skipping auto-upgrade${NC}"
        return 0
    fi

    # TP_VERSION is already extracted at the top of this script into TEAMPASS_VERSION
    IMAGE_VERSION="${TP_VERSION:-${TEAMPASS_VERSION}}"

    if [ "$DB_VERSION" = "$IMAGE_VERSION" ]; then
        echo -e "${GREEN}✅ Database is up to date (${DB_VERSION})${NC}"
        return 0
    fi

    echo -e "${BLUE}🔄 Upgrading database from ${DB_VERSION} to ${IMAGE_VERSION}...${NC}"

    UPGRADE_SCRIPT="/var/www/html/install/upgrade_run_${IMAGE_VERSION}.php"
    if [ ! -f "$UPGRADE_SCRIPT" ]; then
        echo -e "${YELLOW}⚠️  No upgrade script found for ${IMAGE_VERSION}, skipping${NC}"
        return 0
    fi

    php "$UPGRADE_SCRIPT" \
        --db-host="$DB_HOST" \
        --db-port="$DB_PORT" \
        --db-name="$DB_NAME" \
        --db-user="$DB_USER" \
        --db-password="$DB_PASSWORD" \
        --db-prefix="$DB_PREFIX"

    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Database upgrade to ${IMAGE_VERSION} completed${NC}"
    else
        echo -e "${YELLOW}⚠️  Database upgrade script returned an error — check logs${NC}"
    fi
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
    echo "   Saltkey absolute path:"
    echo -e "   ${BLUE}/var/www/html/sk${NC}"
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

    # Configure PHP-FPM to listen on 127.0.0.1:9000 and run as nginx user
    if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then
        sed -i 's/listen = .*/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf
        sed -i 's/^user = .*/user = nginx/' /usr/local/etc/php-fpm.d/www.conf
        sed -i 's/^group = .*/group = nginx/' /usr/local/etc/php-fpm.d/www.conf
    fi

    # Configure cron job for scheduler
    echo "* * * * * php /var/www/html/sources/scheduler.php > /dev/null 2>&1" | crontab -u nginx -

    # Check installation status
    if is_installed; then
        echo -e "${GREEN}✅ TeamPass is already configured${NC}"

        # Remove install directory if it exists (keep it during upgrades so
        # upgrade.php is accessible, then remove once complete)
        if [ -d "/var/www/html/install" ]; then
            echo -e "${BLUE}🗑️  Removing install directory...${NC}"
            rm -rf /var/www/html/install
        fi

        # Auto-upgrade: apply pending database migrations when the image
        # version is newer than the version recorded in teampass_misc.
        auto_upgrade
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
