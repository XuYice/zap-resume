# Mudamos para a versão 8.4!
FROM php:8.4-cli

# Instalamos a extensão do MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Dizemos pro Docker que a nossa pasta principal de trabalho vai ser a /app
WORKDIR /app
