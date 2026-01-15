FROM php:8.2-apache

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    libreoffice-writer \
    libreoffice-java-common \
    default-jre \
    pandoc \
    poppler-utils \
    libldap2-dev \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install pdo pdo_mysql ldap \
    && a2enmod rewrite \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html/

RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html/uploads
