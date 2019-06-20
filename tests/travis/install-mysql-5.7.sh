#!/usr/bin/env bash

set -ex

echo "Starting MySQL 5.7..."

sudo docker run \
    -d \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
    -e MYSQL_DATABASE=doctrine_tests \
    -p 33306:3306 \
    --name mysql57 \
    mysql:5.7

sudo docker exec -i mysql57 bash <<< 'until echo \\q | mysql doctrine_tests > /dev/null 2>&1 ; do sleep 1; done'
