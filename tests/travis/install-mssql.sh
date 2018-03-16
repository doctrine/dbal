#!/usr/bin/env bash

set -ex

echo Installing drivers
curl https://packages.microsoft.com/keys/microsoft.asc | sudo apt-key add -
curl https://packages.microsoft.com/config/ubuntu/14.04/prod.list | sudo tee /etc/apt/sources.list.d/mssql.list
sudo apt-get update
ACCEPT_EULA=Y sudo apt-get install -qy msodbcsql17 mssql-tools unixodbc libssl1.0.0

echo Setting up Microsoft SQL Server

sudo docker pull microsoft/mssql-server-linux:2017-latest
sudo docker run \
    -e 'ACCEPT_EULA=Y' \
    -e 'SA_PASSWORD=Doctrine2018' \
    -p 127.0.0.1:1433:1433 \
    --name db \
    -d \
    microsoft/mssql-server-linux:2017-latest


retries=10
until (echo quit | /opt/mssql-tools/bin/sqlcmd -S 127.0.0.1 -l 1 -U sa -P Doctrine2018 &> /dev/null)
do
    if [[ "$retries" -le 0 ]]; then
        echo SQL Server did not start
        exit 1
    fi

    retries=$((retries - 1))

    echo Waiting for SQL Server to start...

    sleep 2s
done

echo SQL Server started
