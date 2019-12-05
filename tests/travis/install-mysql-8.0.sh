#!/usr/bin/env bash

set -ex

echo "Starting MySQL 8.0..."

sudo docker pull mysql:8.0
sudo docker run \
    -d \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
    -e MYSQL_DATABASE=doctrine_tests \
    -p 33306:3306 \
    --name mysql80 \
    mysql:8.0 \
    --default-authentication-plugin=mysql_native_password

sudo docker exec -i mysql80 bash <<< 'until echo \\q | mysql doctrine_tests > /dev/null 2>&1 ; do sleep 1; done'
