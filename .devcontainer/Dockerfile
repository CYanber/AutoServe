FROM mcr.microsoft.com/devcontainers/php:8.3

RUN apt-get update \
 && apt-get install -y --no-install-recommends default-mysql-client \
 && rm -rf /var/lib/apt/lists/* \
 && docker-php-ext-install pdo_mysql
