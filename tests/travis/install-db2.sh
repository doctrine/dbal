#!/usr/bin/env bash

set -ex

echo Setting up IBM DB2

sudo docker pull ibmcom/db2
docker run \
    -d \
    --name db2 \
    --privileged=true \
    -p 50000:50000 \
    -e LICENSE=accept \
    -e DB2INST1_PASSWORD=Doctrine2018 \
    -e DBNAME=doctrine \
    ibmcom/db2 \
    "sh -c 'tail -f /dev/null'"

while ! sudo docker exec db2 su - db2inst1 -c 'which db2 2> /dev/null'; do sleep 7; done

while ! docker logs db2 | grep "All databases are now active."; do sleep 7; done

sudo docker exec db2 su - db2inst1 -c \
    'db2 CONNECT TO doctrine && db2 CREATE USER TEMPORARY TABLESPACE doctrine_tbsp PAGESIZE 4 K'

echo DB2 started
