#!/bin/sh
 
docker compose run \
    --rm \
    -i \
    backend \
    php "$@"
 
return $?