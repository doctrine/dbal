#!/usr/bin/env bash

set -ex

echo Setting up IBM DB2

sudo docker pull ibmcom/db2express-c:10.5.0.5-3.10.0
sudo docker run \
    -d \
    -p 50000:50000 \
    -e DB2INST1_PASSWORD=Doctrine2018 \
    -e LICENSE=accept \
    --name db2 \
    ibmcom/db2express-c:10.5.0.5-3.10.0 \
    db2start

sleep 15

sudo docker exec db2 su - db2inst1 -c \
    'db2 CREATE DB doctrine && db2 CONNECT TO doctrine && db2 CREATE USER TEMPORARY TABLESPACE doctrine_tbsp PAGESIZE 4 K'

echo DB2 started
