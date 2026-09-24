FROM php:8.3-cli

RUN apt-get update \
    && apt-get install -y libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .

CMD php -S 0.0.0.0:$PORT router.php
