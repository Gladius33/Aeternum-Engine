FROM node:22-bookworm-slim AS client
WORKDIR /src
COPY client/package.json client/package-lock.json ./client/
RUN npm ci --prefix client --no-audit --no-fund
COPY client/src ./client/src
COPY client/tsconfig.json ./client/tsconfig.json
RUN npm run build --prefix client

FROM php:8.4-apache-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends openssl \
    && docker-php-ext-install pdo_mysql \
    && a2enmod ssl rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /opt/aeternum
COPY composer.json ./
COPY packages/core/src ./packages/core/src
COPY database/migrations ./database/migrations
COPY games ./games
COPY schemas ./schemas
COPY bin ./bin
COPY public/index.php public/play.html public/play.js public/play.css public/locale.js public/world-scene.js public/world-scene.css public/game-ui.js public/game-ui.css public/game-ui-profiles.js public/game-art.js ./public/
COPY --from=client /src/public/client-dist ./public/client-dist
RUN composer dump-autoload --no-interaction --no-dev --classmap-authoritative \
    && mkdir -p /etc/aeternum-lab \
    && openssl req -x509 -newkey rsa:2048 -nodes -days 30 -subj /CN=localhost -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1' -keyout /etc/aeternum-lab/tls.key -out /etc/aeternum-lab/tls.crt \
    && chmod 600 /etc/aeternum-lab/tls.key
COPY lab/apache.conf /etc/apache2/sites-available/aeternum-lab.conf
RUN a2dissite 000-default && a2ensite aeternum-lab
COPY lab/registry.php lab/init.sh lab/worker.sh ./lab/
RUN chmod 755 lab/init.sh lab/worker.sh \
    && php lab/registry.php /etc/aeternum-lab/worlds.json \
    && chmod 644 /etc/aeternum-lab/worlds.json \
    && php bin/registry-status.php /etc/aeternum-lab/worlds.json
