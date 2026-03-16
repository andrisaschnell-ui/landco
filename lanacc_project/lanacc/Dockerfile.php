FROM php:8.2-apache

# ── System packages ─────────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    libzip-dev zip unzip curl git \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mysqli zip gd \
    && rm -rf /var/lib/apt/lists/*

# ── Apache config ────────────────────────────────────────────────
RUN a2enmod rewrite
COPY .docker/apache.conf /etc/apache2/sites-available/000-default.conf

# ── PHP config ───────────────────────────────────────────────────
RUN echo "upload_max_filesize = 64M"  >> /usr/local/etc/php/php.ini && \
    echo "post_max_size = 64M"        >> /usr/local/etc/php/php.ini && \
    echo "memory_limit = 256M"        >> /usr/local/etc/php/php.ini && \
    echo "max_execution_time = 300"   >> /usr/local/etc/php/php.ini && \
    echo "display_errors = Off"       >> /usr/local/etc/php/php.ini && \
    echo "log_errors = On"            >> /usr/local/etc/php/php.ini

# ── Uploads directory ────────────────────────────────────────────
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/uploads

WORKDIR /var/www/html
EXPOSE 80
