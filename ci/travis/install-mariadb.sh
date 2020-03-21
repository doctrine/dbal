#!/usr/bin/env bash

set -ex

sudo docker run \
    -d \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
    -e MYSQL_DATABASE=doctrine_tests \
    -p 33306:3306 \
    --name mariadb \
    mariadb:${MARIADB_VERSION}

sudo docker exec -i mariadb bash <<< 'until echo \\q | mysql doctrine_tests > /dev/null 2>&1 ; do sleep 1; done'
