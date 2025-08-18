FROM php:8.2

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

ARG WITH_XDEBUG=false

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions zip uopz xdebug

RUN if [ $WITH_XDEBUG = "true" ] ; then \
        docker-php-ext-enable xdebug \
        && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
        && echo "xdebug.client_host = host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini ; \
    fi;

COPY . /home

WORKDIR /home

RUN composer install
