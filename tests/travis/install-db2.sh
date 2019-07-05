#!/usr/bin/env bash

set -ex

echo Setting up IBM DB2

sudo docker pull ibmcom/db2
docker run \
    -itd \
    --name db2 \
    --privileged=true \
    -p 50000:50000 \
    -e LICENSE=accept \
    -e DB2INST1_PASSWORD=Doctrine2018 \
    -e DBNAME=doctrine \
    ibmcom/db2

while ! sudo docker exec db2 su - db2inst1 -c 'which db2 2> /dev/null'; do sleep 1; done

while ! sudo docker exec db2 su - db2inst1 -c 'db2start'; do sleep 1; done

sudo docker exec db2 su - db2inst1 -c \
    'db2 CREATE DB doctrine && db2 CONNECT TO doctrine && db2 CREATE USER TEMPORARY TABLESPACE doctrine_tbsp PAGESIZE 4 K'

echo DB2 started
