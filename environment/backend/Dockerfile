FROM shinsenter/symfony:dev-php8.4-apache

RUN apt update && apt install -y apt-transport-https gnupg autoconf build-essential

# Install Xdebug extension for PHP
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# symfony cli
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' |  bash && apt install -y symfony-cli