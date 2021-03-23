FROM alpine:latest

RUN apk --no-cache upgrade
RUN apk add --no-cache apache2 \
    bash \
    curl \
    git \
    jq \
    mariadb \
    openrc \
    php7 \
    php7-apache2 \
    php7-curl \
    php7-iconv \
    php7-json \
    php7-mbstring \
    php7-mysqli \
    php7-openssl \
    php7-pcntl \
    php7-pdo \
    php7-phar \
    php7-posix \
    php7-session \
    php7-simplexml \
    php7-sodium \
    php7-sqlite3 \
    php7-tokenizer \
    php7-xml \
    php7-xmlreader \
    php7-xmlwriter \
    php7-zlib \
    wget \
    zip

ARG APP_DIR=/var/www/app

# Install composer
COPY docker/install-composer.sh /tmp/install-composer.sh
RUN /tmp/install-composer.sh

# Add composer-installed libs to path
ENV PATH=/var/www/app/vendor/bin:$PATH

ADD composer.json composer.lock $APP_DIR/

WORKDIR $APP_DIR
RUN composer install
