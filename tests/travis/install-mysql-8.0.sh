#!/usr/bin/env bash

set -ex

echo "Starting MySQL 8.0..."

echo -e "[mysqld]\ndefault_authentication_plugin=mysql_native_password" > /tmp/mysql-auth.cnf

sudo docker pull mysql:8.0
sudo docker run \
    -d \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
    -e MYSQL_DATABASE=doctrine_tests \
    -v /tmp/mysql-auth.cnf:/etc/mysql/conf.d/auth.cnf:ro \
    -p 33306:3306 \
    --name mysql80 \
    mysql:8.0

sudo docker exec -i mysql80 bash <<< 'until echo \\q | mysql doctrine_tests > /dev/null 2>&1 ; do sleep 1; done'
