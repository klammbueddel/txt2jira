# -------------------------------
# Builder Stage
# -------------------------------
FROM php:8.3-cli-alpine AS builder

# Set the working directory
WORKDIR /builder

# Copy composer files
COPY composer.json composer.lock ./

# Copy composer executable from the official composer image
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install PHP dependencies without dev packages and optimize autoloader
RUN composer install --no-dev --no-scripts --no-plugins --ignore-platform-reqs && \
    composer dump-autoload --optimize && \
    composer clear-cache

# -------------------------------
# Production Stage
# -------------------------------
FROM php:8.3-cli-alpine AS prod

# Add the install-php-extensions script
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/install-php-extensions

# Make the script executable and install PHP extensions
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions zip uopz && \
    rm /usr/local/bin/install-php-extensions

# Optional build arguments for configurations
ARG _uid_gid=1000
ARG _shell=ash
ARG _editor=vi
ARG _tz=UTC

# Install optional packages
RUN apk update --no-cache && \
    packages="tzdata" && \
    if [ "${_editor}" != "vi" ]; then \
        packages="$packages ${_editor}"; \
    fi; \
    if [ "${_shell}" != "ash" ]; then \
        packages="$packages ${_shell}"; \
    fi; \
    apk add --no-cache $packages && \
    rm -rf /var/cache/apk/*

# Set timezone
RUN ln -snf "/usr/share/zoneinfo/${_tz}" /etc/localtime && echo "${_tz}" > /etc/timezone

# Determine the shell path and add the user
RUN if [ -z "/usr/bin/${_shell}" ]; then \
        echo "Shell '${_shell}' not found. Please specify a valid shell." >&2; \
        exit 1; \
    fi && \
    adduser -D -h /home/jira -s "/usr/bin/${_shell}" -u "${_uid_gid}" jira

# Copy application code
COPY --chown=jira:jira ./ /home/jira

# Copy composer dependencies from the builder stage
COPY --chown=jira:jira --from=builder /builder/vendor /home/jira/vendor

# Create a symbolic link for 'txt2jira' in a directory that's in the PATH
# Set timezone in php.ini
RUN ln -s /home/jira/txt2jira /usr/local/bin/ && \
    echo "date.timezone = ${_tz}" >> "$PHP_INI_DIR/php.ini"

# Set environment variables
ENV ENV="/home/jira/.${_shell}rc"
ENV TZ="${_tz}"

# Set the working directory
WORKDIR /home/jira

# Switch to the 'jira' user
USER jira

# Set the entrypoint to the shell
ENTRYPOINT ["/bin/ash","-c"]
#  CMD [ "ash" ]

# -------------------------------
# Development Stage
# -------------------------------
FROM prod AS dev

# Switch back to root to install development tools
USER root

# Add the install-php-extensions script
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/install-php-extensions

# Make the script executable and install PHP extensions
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions xdebug && \
    rm /usr/local/bin/install-php-extensions

# Configure xdebug
RUN echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "display_startup_errors = On" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "display_errors = On" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini && \
    echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Install development tools and utilities
RUN apk update --no-cache && apk add --no-cache \
    bash \
    zsh \
    bind-tools \
    net-tools \
    netcat-openbsd \
    curl \
    htop \
    && rm -rf /var/cache/apk/*

# Switch back to the 'jira' user
USER jira
