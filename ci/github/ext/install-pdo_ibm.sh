#!/usr/bin/env bash

set -ex

echo "Installing extension"
(
    cd /tmp

    wget https://public.dhe.ibm.com/ibmdl/export/pub/software/data/db2/drivers/odbc_cli/linuxx64_odbc_cli.tar.gz

    tar -xf linuxx64_odbc_cli.tar.gz clidriver --directory .
    rm linuxx64_odbc_cli.tar.gz

    wget https://pecl.php.net/get/PDO_IBM-1.5.0.tgz

    tar -xf PDO_IBM-1.5.0.tgz
    rm PDO_IBM-1.5.0.tgz
    cd PDO_IBM-1.5.0
    phpize
    ./configure --with-pdo-ibm=/tmp/clidriver
    make -j "$(nproc)"
    sudo make install
)
