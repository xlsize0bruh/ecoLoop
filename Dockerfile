FROM php:8.4-apache
RUN a2enmod rewrite && mkdir -p /var/www/ecoloop-storage && chown www-data:www-data /var/www/ecoloop-storage
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/uploads
ENV ECOLOOP_DATA_DIR=/var/www/ecoloop-storage
