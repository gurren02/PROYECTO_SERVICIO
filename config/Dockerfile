# Usar la imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instalar las extensiones PDO y MySQL para que tu código funcione
RUN docker-php-ext-install pdo pdo_mysql

# Dar permisos a la carpeta (para que puedas subir tus archivos CSV sin error)
RUN chown -R www-data:www-data /var/www/html