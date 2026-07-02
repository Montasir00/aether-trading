FROM php:8.2-apache

RUN apt-get update \
  && apt-get install -y --no-install-recommends libgmp-dev libzip-dev git unzip \
  && docker-php-ext-install mysqli gmp zip \
  && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY . /var/www/html

# Install Composer dependencies when composer.json is present.
RUN if [ -f composer.json ]; then \
      php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
      php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
      rm composer-setup.php && \
      composer install --no-interaction --prefer-dist; \
    fi

EXPOSE 80