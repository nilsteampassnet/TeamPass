FROM richarvey/nginx-php-fpm:latest

# The location of the web files
ARG VOL=/var/www/html
ENV VOL ${VOL}
VOLUME ${VOL}

# Configure nginx-php-fpm image to use this dir.
ENV WEBROOT ${VOL}
RUN apk add --no-cache gnu-libiconv libldap gmp
ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so php

RUN echo && \
  # Install and configure missing PHP requirements
  /usr/local/bin/docker-php-ext-configure bcmath && \
  /usr/local/bin/docker-php-ext-install bcmath && \
  apk add --no-cache --virtual .docker-php-dependencies \
            openldap-dev gmp-dev && \ 
  /usr/local/bin/docker-php-ext-configure ldap gmp && \
  /usr/local/bin/docker-php-ext-install ldap gmp && \
  apk del .docker-php-dependencies && \
  echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/docker-vars.ini && \
echo

# Fix API URL, BUG: API not working in container. #2100
# Search last } and insert configuration rows before
RUN sed -i "/^}/i \
  location /api/ {\
          try_files $uri $uri/ /api/index.php?$args;\
  }" /etc/nginx/sites-enabled/default.conf

COPY teampass-docker-start.sh /teampass-docker-start.sh

# Configure nginx-php-fpm image to pull our code.
ENV REPO_URL https://github.com/nilsteampassnet/TeamPass.git
#ENV GIT_TAG 3.0.0.14

ENTRYPOINT ["/bin/sh"]
CMD ["/teampass-docker-start.sh"]
