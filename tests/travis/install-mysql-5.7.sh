#!/usr/bin/env bash

set -ex

echo "Starting MySQL 5.7..."

sudo docker run \
    --health-cmd='mysqladmin ping --silent' \
    -d \
    -e MYSQL_ALLOW_EMPTY_PASSWORD=yes \
    -e MYSQL_DATABASE=doctrine_tests \
    -p 33306:3306 \
    --name mysql57 \
    mysql:5.7

until [ "$(sudo docker inspect --format "{{json .State.Health.Status }}" mysql57)" == "\"healthy\"" ]
do
  echo "Waiting for MySQL to become ready…"
  sleep 1
done
