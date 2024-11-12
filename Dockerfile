FROM php:8.2-cli
COPY ./src /usr/src/app
COPY ./composer.json /usr/src/app/composer.json
WORKDIR /usr/src/app
RUN apt-get update && apt-get install -y git
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install
CMD [ "php", "./wphandbook.php" ]