# Use the official PHP Apache image
FROM php:8.2-apache

# Copy project files to the Apache document root
COPY . /var/www/html/

# Install PHP extensions if needed (example: mysqli, pdo, etc.)
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set permissions (optional, for uploads)
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
