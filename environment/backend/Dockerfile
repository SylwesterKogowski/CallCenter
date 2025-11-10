FROM shinsenter/symfony:dev-php8.4-apache

RUN apt update && apt install -y apt-transport-https gnupg

# symfony cli
RUN curl -1sLf 'https://dl.cloudsmith.io/public/symfony/stable/setup.deb.sh' |  bash && apt install -y symfony-cli