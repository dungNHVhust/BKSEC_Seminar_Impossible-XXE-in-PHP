FROM php:8.1-apache

# Bật mod_rewrite
RUN a2enmod rewrite

# Tạo flag ở root
RUN echo 'BKSEC{REDACTED}' > /flag && chmod 444 /flag

# Copy file PHP vào webroot
WORKDIR /var/www/html
COPY index.php /var/www/html/index.php
COPY vul.php /var/www/html/vul.php

EXPOSE 80
