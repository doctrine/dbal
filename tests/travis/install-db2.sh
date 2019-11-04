#!/usr/bin/env bash

set -ex

echo Setting up IBM DB2

echo "su - db2inst1 -c 'db2 CONNECT TO doctrine && db2 CREATE USER TEMPORARY TABLESPACE doctrine_tbsp PAGESIZE 4 K'" > /tmp/doctrine-init.sh
chmod +x /tmp/doctrine-init.sh

sudo docker run \
    -d \
    -p 50000:50000 \
    -e DB2INST1_PASSWORD=Doctrine2018 \
    -e LICENSE=accept \
    -e DBNAME=doctrine \
    -v /tmp/doctrine-init.sh:/var/custom/doctrine-init.sh:ro \
    --name db2 \
    --privileged=true \
    ibmcom/db2:11.5.0.0

sudo docker logs -f db2 | sed '/(*) Setup has completed./ q'

echo DB2 started
