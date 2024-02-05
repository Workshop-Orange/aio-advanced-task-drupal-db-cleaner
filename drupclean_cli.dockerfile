FROM uselagoon/lagoon-cli:latest as LAGOONCLI
FROM uselagoon/php-8.2-cli:latest

COPY cli/* /app/

COPY --from=LAGOONCLI /lagoon /usr/local/bin/lagoon

RUN COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev
