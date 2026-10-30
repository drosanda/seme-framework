# FROM arm64v8/php:8.2.35-fpm-bookworm
FROM php:8.2.35-fpm-bookworm

RUN apt-get update 
RUN apt-get upgrade -y 
RUN apt-get install -y nano software-properties-common git openssh-client build-essential 
RUN ssh-keyscan github.com > known_hosts

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions memcached opcache
RUN install-php-extensions bz2 zip zstd
RUN install-php-extensions tidy
RUN install-php-extensions xsl
RUN install-php-extensions yaml
RUN install-php-extensions mysqli pgsql
RUN install-php-extensions pdo_mysql pdo_pgsql
RUN install-php-extensions mcrypt bcmath
RUN install-php-extensions gd 
RUN install-php-extensions gmagick 
RUN install-php-extensions exif 
RUN install-php-extensions amqp 
RUN install-php-extensions intl
RUN install-php-extensions xlswriter

COPY php.ini /usr/local/etc/php/php.ini

EXPOSE 9000

CMD ["php-fpm", "-F"]