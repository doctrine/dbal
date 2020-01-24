#!/usr/bin/env bash

set -ex

echo Installing driver dependencies

curl https://packages.microsoft.com/keys/microsoft.asc | sudo apt-key add -
curl https://packages.microsoft.com/config/ubuntu/14.04/prod.list | sudo tee /etc/apt/sources.list.d/mssql.list
sudo apt-get update
ACCEPT_EULA=Y sudo apt-get install -qy msodbcsql17 unixodbc unixodbc-dev libssl1.0.0
