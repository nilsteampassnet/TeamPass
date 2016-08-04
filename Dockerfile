FROM debian:jessie

ENV MEMORY_LIMIT 256M

ENV MAX_EXECUTION_TIME 180

# Install base packages
RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get -yq install \
        curl \
        apache2 \
        libapache2-mod-php5 \
        php5-mysql \
        php5-mcrypt \
        php5-gd \
        php5-curl \
        php-pear \
        php-apc \
        php5-xdebug \
	gettext \
	mc \
	locales \
        git-core && \
    rm -rf /var/lib/apt/lists/* && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN /usr/sbin/php5enmod mcrypt
RUN /usr/sbin/a2enmod rewrite

RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    sed -i "s/variables_order.*/variables_order = \"EGPCS\"/g" /etc/php5/apache2/php.ini

RUN sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf


# Set memory limit 
RUN sed -i "s@^memory_limit =.*@memory_limit = $MEMORY_LIMIT@" /etc/php5/apache2/php.ini
# Set Max execution time 
RUN sed -i "s@^max_execution_time = .*@max_execution_time = $MAX_EXECUTION_TIME@" /etc/php5/apache2/php.ini

ENV ALLOW_OVERRIDE **False**


RUN touch /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.remote_autostart=true >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.remote_mode=req >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.remote_handler=dbgp >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.remote_connect_back=1 >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.remote_port=9000 >> /etc/php5/mods-available/xdebug.ini
# RUN echo xdebug.remote_host=127.0.0.1 >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.idekey=PHPSTORM >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.remote_enable=1 >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.profiler_append=0 >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.profiler_enable=0 >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.profiler_enable_trigger=1 >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.profiler_output_dir=/var/debug >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.profiler_output_name=cachegrind.out.%s.%u >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.var_display_max_data=-1 >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.var_display_max_children=-1 >> /etc/php5/mods-available/xdebug.ini
RUN echo xdebug.var_display_max_depth=-1 >> /etc/php5/mods-available/xdebug.ini


# Add image configuration and scripts
ADD run.sh /run.sh
RUN chmod 755 /*.sh

# Configure /app folder with sample app
#RUN mkdir -p /app && rm -fr /var/www/html && ln -s /app /var/www/html
#ADD sample/ /app

EXPOSE 80
#WORKDIR /app
CMD ["/run.sh"]
