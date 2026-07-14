FROM wordpress:cli
USER root
RUN apk add --no-cache bash curl mysql-client \
    && mkdir -p /tests/tmp \
    && chown -R www-data:www-data /tests
USER www-data
