FROM php:8.4-fpm

# System Dependencies & Node.js 22 LTS Installation
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    && curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP Extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get Latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# ১. আগে composer.json ও composer.lock কপি করা (Docker layer caching-এর জন্য)
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

# ২. package.json ও package-lock.json কপি করে Node ডিপেন্ডেন্সি ইনস্টল করা
COPY package*.json ./
RUN npm ci

# ৩. প্রজেক্টের বাকি সব কোড ইমেজের ভেতরে কপি করা
COPY . .

# ৪. Composer autoloader জেনারেট এবং Vite ফ্রন্টএন্ড অ্যাসেট বিল্ড করা
RUN composer dump-autoload --optimize \
    && npm run build

# ৫. Storage ও Cache ডিরেক্টরি পারমিশন ঠিক করা
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache bootstrap/cache \
    && chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]