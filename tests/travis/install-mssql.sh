#!/usr/bin/env bash

set -ex

echo Setting up Microsoft SQL Server

sudo docker pull microsoft/mssql-server-linux:2017-latest
sudo docker run \
    -e 'ACCEPT_EULA=Y' \
    -e 'SA_PASSWORD=Doctrine2018' \
    -p 127.0.0.1:1433:1433 \
    --name mssql \
    -d \
    microsoft/mssql-server-linux:2017-latest

sudo docker exec -i mssql bash <<< 'until echo quit | /opt/mssql-tools/bin/sqlcmd -S 127.0.0.1 -l 1 -U sa -P Doctrine2018 > /dev/null 2>&1 ; do sleep 1; done'

echo SQL Server started
