FROM php:8.1-fpm

# 設定時區為台北時間
ENV TZ=Asia/Taipei
RUN ln -snf /usr/share/zoneinfo/Asia/Taipei /etc/localtime && echo Asia/Taipei > /etc/timezone

# 安裝系統依賴與 PHP 擴展
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install \
        intl \
        mbstring \
        pdo_mysql \
        mysqli \
        zip \
    && rm -rf /var/lib/apt/lists/*

# 設定 PHP date.timezone
RUN echo 'date.timezone=Asia/Taipei' > /usr/local/etc/php/conf.d/timezone.ini

# 安裝 Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 設定 PHP-FPM 以 www-data 執行
RUN chown -R www-data:www-data /var/www/html

USER www-data
