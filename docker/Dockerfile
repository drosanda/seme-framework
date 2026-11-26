# WARNING: PHP 7.4 is end-of-life and has no official fix for CVE-2026-109618 / CVE-2026-109619 (see issue #24).
# Legacy/local use only. Never expose this image to the internet. Use docker/8.2 or newer instead.
FROM arm64v8/php:7.4-apache

ADD https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions /usr/local/bin/

RUN chmod uga+x /usr/local/bin/install-php-extensions && sync && \
    install-php-extensions mysqli bcmath bz2 exif gd gmagick intl mcrypt zip