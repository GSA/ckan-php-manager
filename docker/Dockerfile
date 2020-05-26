FROM php:7.3

ARG APP_DIR=/var/www/app

RUN apt-get update && apt-get install -y git wget zip

# Install composer
COPY docker/install-composer.sh /tmp/install-composer.sh
RUN /tmp/install-composer.sh

# Add composer-installed libs to path
ENV PATH=/var/www/app/vendor/bin:$PATH

ADD composer.json composer.lock $APP_DIR/

WORKDIR $APP_DIR
RUN composer install
