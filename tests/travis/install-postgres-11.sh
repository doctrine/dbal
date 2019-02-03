#!/usr/bin/env bash

set -ex

echo "Preparing Postgres 11"

sudo service postgresql stop || true

sudo docker run -d --name postgres11 -p 5432:5432 postgres:11.1
sudo docker exec -i postgres11 bash <<< 'until pg_isready -U postgres > /dev/null 2>&1 ; do sleep 1; done'

echo "Postgres 11 ready"
