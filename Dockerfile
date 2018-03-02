FROM php:7.1.12-cli
LABEL maintainer="David M. Lee, II <leedm777@yahoo.com>"

#
# composer install (from https://github.com/composer/docker)
#
ENV COMPOSER_ALLOW_SUPERUSER 1
ENV COMPOSER_HOME /tmp
ENV COMPOSER_VERSION 1.6.3
RUN curl -s -f -L -o /tmp/installer.php https://raw.githubusercontent.com/composer/getcomposer.org/da290238de6d63faace0343efbdd5aa9354332c5/web/installer \
 && php -r " \
    \$signature = '669656bab3166a7aff8a7506b8cb2d1c292f042046c5a994c43155c0be6190fa0355160742ab2e1c88d40d5be660b410'; \
    \$hash = hash('SHA384', file_get_contents('/tmp/installer.php')); \
    if (!hash_equals(\$signature, \$hash)) { \
        unlink('/tmp/installer.php'); \
        echo 'Integrity check failed, installer is either corrupt or worse.' . PHP_EOL; \
        exit(1); \
    }" \
 && php /tmp/installer.php --no-ansi --install-dir=/usr/bin --filename=composer --version=${COMPOSER_VERSION} \
 && composer --ansi --version --no-interaction \
 && rm -rf /tmp/* /tmp/.htaccess

#
# Install PHP extensions
#
RUN apt-get update -qq && \
    DEBIAN_FRONTEND=noninteractive \
    apt-get install -y \
            unzip \
            zip \
            && \
    apt-get purge -y --auto-remove && rm -rf /var/lib/apt/lists/*

RUN pecl install -o -f \
        apcu \
        apcu_bc \
        && \
    rm -rf /tmp/pear

# specify --ini-name since modules need a specific load order
RUN docker-php-ext-enable --ini-name 0-apc.ini apcu apc

#
# Install PHP dependencies
#
WORKDIR /usr/src/app
COPY composer.json composer.lock /usr/src/app/
RUN composer install

COPY . /usr/src/app
COPY ./config/php/* /usr/local/etc/php/conf.d/
CMD ["composer", "check"]
