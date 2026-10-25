FROM serversideup/php:8.5-fpm-nginx

USER root

RUN install-php-extensions intl bcmath gd exif

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Match the container user to the host user so files created inside (vendor, storage) stay writable.
ARG USER_ID=1000
ARG GROUP_ID=1000
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID} \
    && docker-php-serversideup-set-file-permissions --owner ${USER_ID}:${GROUP_ID} --service nginx

USER www-data
