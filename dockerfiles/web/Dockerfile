FROM php:5.4-apache
RUN apt-get update --fix-missing
RUN echo "date.timezone=Europe/Paris" >> "/usr/local/etc/php/php.ini"
RUN echo "Include sites-enabled/" >> /etc/apache2/apache2.conf
RUN a2enmod rewrite
COPY apache.conf /etc/apache2/sites-available/000-default.conf

