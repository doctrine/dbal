#!/usr/bin/env bash

set -ex

echo "Installing extension"
(
    cd /tmp

    wget https://public.dhe.ibm.com/ibmdl/export/pub/software/data/db2/drivers/odbc_cli/linuxx64_odbc_cli.tar.gz

    tar xf linuxx64_odbc_cli.tar.gz

    pecl download ibm_db2
    tar xf ibm_db2-*
    rm ibm_db2-*.tgz
    cd ibm_db2-*
    phpize
    ./configure --with-IBM_DB2=/tmp/clidriver
    make -j "$(nproc)"
    sudo make install
)
