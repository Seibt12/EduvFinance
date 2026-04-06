# ============================================================
# EduFinance — PHP 8.2 + Apache + PDO PostgreSQL
# ============================================================
FROM php:8.2-apache

# Instala a extensão PDO para PostgreSQL
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilita mod_rewrite (necessário para .htaccess)
RUN a2enmod rewrite

# Copia a configuração customizada do Apache
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Copia o entrypoint (aguarda DB + cria admin + sobe Apache)
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
