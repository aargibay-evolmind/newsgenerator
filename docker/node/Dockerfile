FROM node:20-alpine

ARG UID=1000
ARG GID=1000

# Alpine uses shadow package for usermod/groupmod
RUN apk add --no-cache shadow \
    && usermod -u ${UID} node \
    && groupmod -g ${GID} node

RUN mkdir -p /var/www/frontend

RUN chown -R ${UID}:${GID} /var/www

WORKDIR /var/www/frontend

# Use the customized user
USER node