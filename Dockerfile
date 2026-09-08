FROM php:8.3-apache

# Serve Symfony's public/ dir and allow .htaccess (symfony/apache-pack)
RUN a2enmod rewrite \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/apache2.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Trust the nginx-proxy in front of us for the real client IP / scheme
RUN a2enmod remoteip \
    && printf 'RemoteIPHeader X-Forwarded-For\nRemoteIPTrustedProxy 0.0.0.0/1\nRemoteIPTrustedProxy 128.0.0.0/1\nRemoteIPTrustedProxy ::/1\nRemoteIPTrustedProxy 8000::/1\n' \
       > /etc/apache2/conf-available/remoteip.conf \
    && a2enconf remoteip \
    && sed -ri 's/LogFormat "([^"]*)%h([^"]*)" combined/LogFormat "\1%a\2" combined/' /etc/apache2/apache2.conf

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        bash \
        gosu \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
    && rm -rf /var/lib/apt/lists/* \
    && sed -i 's|www-data:x:33:33:www-data:/var/www:/usr/sbin/nologin|www-data:x:33:33:www-data:/var/www/html:/bin/bash|' /etc/passwd

# gd (with jpeg/freetype) is required by phpoffice/phpspreadsheet
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql intl mbstring bcmath zip opcache gd

# Run PHP/Apache as the host user so bind-mounted files stay editable on both
# sides (no root-owned var/cache after the container writes to it).
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "${GID}" www-data && usermod -o -u "${UID}" www-data

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
