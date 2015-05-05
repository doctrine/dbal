#!/bin/bash

set -e

wget -qO- "https://drive.google.com/uc?export=download&id=$SQLANYWHERE_GDRIVE_ID" | tar xz # SQL Anywhere server
wget -qO- "https://drive.google.com/uc?export=download&id=0BxDG3LHQ2MkLS1oxaUQzTy1Jczg" | tar xz # PHP extensions

echo "extension = `pwd`/php_sqlanywhere/sqlanywhere_$(phpenv version-name).so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

cd sqlanywhere

./bin64/dbspawn ./bin64/dbsrv -x tcpip{DOBROADCAST=NO} demo.db
