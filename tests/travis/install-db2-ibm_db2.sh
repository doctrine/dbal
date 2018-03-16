#!/usr/bin/env bash

set -ex

echo "Installing extension"
(
    cd /tmp
    pecl download ibm_db2
    tar xf ibm_db2-*
    cd ibm_db2-*
    phpize
    ./configure --with-IBM_DB2=/tmp/db2
    make -j `nproc`
    make install
    echo -e 'extension=ibm_db2.so\nibm_db2.instance_name=db2inst1' > ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/ibm_db2.ini
)
