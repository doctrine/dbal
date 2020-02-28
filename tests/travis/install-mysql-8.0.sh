#!/usr/bin/env bash

set -ex

echo "Starting MySQL 8.0..."

sudo docker pull mysql:8.0
sudo docker run \
    --health-cmd='mysqladmin ping --silent' \
    -d \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
    -e MYSQL_DATABASE=doctrine_tests \
    -p 33306:3306 \
    --name mysql80 \
    mysql:8.0 \
    --default-authentication-plugin=mysql_native_password

until [ "$(sudo docker inspect --format "{{json .State.Health.Status }}" mysql80)" == "\"healthy\"" ]
do
  echo "Waiting for MySQL to become ready…"
  sleep 1
done
