#!/usr/bin/env bash

set -ex

echo "Installing extension"
(
    # https://travis-ci.community/t/then-sudo-apt-get-update-failed-public-key-is-not-available-no-pubkey-6b05f25d762e3157-in-ubuntu-xenial/1728/12
    sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 6B05F25D762E3157

    # updating APT packages as per support recommendation
    sudo apt -y -q update
    sudo apt install ksh

    cd /tmp

    wget http://cdn1.netmake.com.br/download/Conexao/DB2/Linux/x64_v10.5fp8_linuxx64_dsdriver.tar.gz

    tar xf x64_v10.5fp8_linuxx64_dsdriver.tar.gz
    ksh dsdriver/installDSDriver

    pecl download ibm_db2
    tar xf ibm_db2-*
    rm ibm_db2-*.tgz
    cd ibm_db2-*
    phpize
    ./configure --with-IBM_DB2=/tmp/dsdriver
    make -j `nproc`
    make install
    echo -e 'extension=ibm_db2.so\nibm_db2.instance_name=db2inst1' > ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/ibm_db2.ini
)
