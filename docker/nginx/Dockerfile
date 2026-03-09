FROM nginx:alpine

ARG UID=1000
ARG GID=1000

# Alpine uses shadow package for usermod/groupmod
RUN apk add --no-cache shadow \
    && usermod -u ${UID} nginx \
    && groupmod -g ${GID} nginx

RUN mkdir -p /var/www/backend
RUN mkdir -p /var/www/frontend

RUN chown -R ${UID}:${GID} /var/www

WORKDIR /var/www/backend
