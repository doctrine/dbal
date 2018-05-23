#!/usr/bin/env bash

set -ex

echo "Preparing Postgres 11"

sudo service postgresql stop || true

sudo docker build -t postgres11 - < tests/travis/Dockerfile-postgres11
sudo docker run -d --name postgres11 -p 5432:5432 postgres11
sudo docker exec postgres11 service postgresql start
sudo docker exec -i postgres11 su -c psql postgres <<<"create database doctrine_tests"

echo "Postgres 11 ready"
