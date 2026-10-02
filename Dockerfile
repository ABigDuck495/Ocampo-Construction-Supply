FROM php:8.2-cli

WORKDIR /var/www

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    default-mysql-client \
    git \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    nodejs \
    npm \
    unzip \
    zip \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && npm install -g npm@latest \
    && rm -rf /var/lib/apt/lists/*

COPY .env.docker /var/www/.env
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader --no-scripts --no-plugins

COPY package.json package-lock.json ./
RUN npm install --no-audit --no-fund

COPY . .

RUN php artisan key:generate --force \
    && npm run build

EXPOSE 8000

CMD ["sh", "-c", "cp -n .env.docker .env && php artisan migrate --force --seed && php artisan serve --host=0.0.0.0 --port=8000"]
