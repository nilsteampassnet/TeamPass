FROM richarvey/nginx-php-fpm:1.2.1

# The location of the web files
ARG VOL=/var/www/html
ENV VOL ${VOL}
VOLUME ${VOL}

# Configure nginx-php-fpm image to use this dir.
ENV WEBROOT ${VOL}/www

RUN echo && \
  # Install and configure missing PHP requirements
  /usr/local/bin/docker-php-ext-configure bcmath && \
  /usr/local/bin/docker-php-ext-install bcmath && \
  apk add --no-cache openldap-dev && \
  /usr/local/bin/docker-php-ext-configure ldap && \
  /usr/local/bin/docker-php-ext-install ldap && \
  apk del openldap-dev && \
  echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/docker-vars.ini && \
echo

COPY teampass-docker-start.sh /teampass-docker-start.sh

# Configure nginx-php-fpm image to pull our code.
ENV REPO_URL https://github.com/nilsteampassnet/TeamPass.git

ENTRYPOINT ["/bin/sh"]
CMD ["/teampass-docker-start.sh"]
