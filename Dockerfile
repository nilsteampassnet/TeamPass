# ============================================
# TeamPass Docker Image - Optimized Multi-stage Build
# ============================================

# Stage 1: Composer dependencies builder
FROM composer:2.7 AS composer-builder

WORKDIR /app

# Copy composer files
COPY composer.json composer.lock ./

# Copy local packages required by composer
COPY app/includes/libraries/teampassclasses ./app/includes/libraries/teampassclasses
COPY app/includes/libraries/ezimuel ./app/includes/libraries/ezimuel

# Install production dependencies only
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --optimize-autoloader \
    --prefer-dist\
    --ignore-platform-reqs

# ============================================
# Stage 2: Final production image
# ============================================
FROM php:8.3-fpm-alpine3.19

# Metadata labels
LABEL maintainer="TeamPass <nils@teampass.net>" \
      org.opencontainers.image.title="TeamPass" \
      org.opencontainers.image.description="Collaborative Passwords Manager" \
      org.opencontainers.image.url="https://teampass.net" \
      org.opencontainers.image.source="https://github.com/nilsteampassnet/TeamPass" \
      org.opencontainers.image.documentation="https://documentation.teampass.net" \
      org.opencontainers.image.licenses="GPL-3.0" \
      org.opencontainers.image.vendor="TeamPass"

# Build arguments
ARG TEAMPASS_VERSION=3.1.5.2
ENV TEAMPASS_VERSION=${TEAMPASS_VERSION}

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    # System packages
    nginx \
    supervisor \
    busybox-suid \
    netcat-openbsd \
    # Libraries for PHP extensions
    gnu-libiconv \
    libldap \
    gmp \
    icu-libs \
    libzip \
    freetype \
    libjpeg-turbo \
    libpng \
    libxml2 \
    oniguruma \
    && apk add --no-cache --virtual .build-deps \
    # Build dependencies
    $PHPIZE_DEPS \
    openldap-dev \
    gmp-dev \
    icu-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libxml2-dev \
    oniguruma-dev \
    # Configure and install PHP extensions
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-configure ldap \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        pdo_mysql \
        bcmath \
        ldap \
        gmp \
        gd \
        zip \
        intl \
        opcache \
        mbstring \
        xml \
    # Cleanup build dependencies
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

# Add GNU libiconv for better performance
ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/teampass.ini

# Copy Nginx configuration
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/teampass.conf /etc/nginx/http.d/default.conf

# Copy Supervisor configuration
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# Create application directory
WORKDIR /var/www/html

# Copy application files
COPY --chown=nginx:nginx . .

# Copy vendor from composer builder
COPY --from=composer-builder --chown=nginx:nginx /app/app/vendor ./app/vendor

# Create required directories with proper permissions
RUN mkdir -p \
    storage/sk \
    storage/files \
    storage/upload \
    storage/config \
    storage/backups \
    secrets \
    app/includes/libraries/csrfp/log \
    /var/lib/nginx/tmp \
    /var/log/supervisor \
    /run/nginx \
    && chown -R nginx:nginx \
        storage \
        storage/sk \
        storage/files \
        storage/upload \
        storage/config \
        storage/backups \
        secrets \
        app/includes/libraries/csrfp/log \
        /var/lib/nginx \
        /var/log \
        /run/nginx \
    && chmod 700 storage/sk secrets \
    && chmod 750 storage storage/files storage/upload storage/config storage/backups app/includes/libraries/csrfp/log

# Remove unnecessary files for production
RUN rm -rf \
    .git \
    .github \
    tests \
    .gitignore \
    .dockerignore \
    .scrutinizer.yml \
    .codacy.yml \
    .eslintrc \
    teampass-docker-start.sh \
    Dockerfile

# Setup cron for TeamPass scheduler
RUN echo "* * * * * php /var/www/html/app/sources/scheduler.php > /dev/null 2>&1" > /var/spool/cron/crontabs/nginx \
    && chmod 600 /var/spool/cron/crontabs/nginx

# Copy and set entrypoint script
COPY docker/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost/health || exit 1

# Expose HTTP port
EXPOSE 80

# Define volumes for persistent data.
# storage/config holds the install state (settings.php, csrfp.config.php) and
# secrets holds the Defuse master key: both must persist across container
# recreation, otherwise TeamPass would try to reinstall itself (issue #5236).
VOLUME ["/var/www/html/storage/sk", "/var/www/html/storage/files", "/var/www/html/storage/upload", "/var/www/html/storage/config", "/var/www/html/secrets"]

# Set entrypoint and default command
ENTRYPOINT ["/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
