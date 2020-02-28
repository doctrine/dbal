#!/usr/bin/env bash

set -ex

sudo docker run \
    --health-cmd='mysqladmin ping --silent' \
    -d \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
    -e MYSQL_DATABASE=doctrine_tests \
    -p 33306:3306 \
    --name mariadb \
    mariadb:${MARIADB_VERSION}

until [ "$(sudo docker inspect --format "{{json .State.Health.Status }}" mariadb)" == "\"healthy\"" ]
do
  echo "Waiting for MariaDB to become ready…"
  sleep 1
done
