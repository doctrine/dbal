#!/bin/bash

set -euo pipefail

docker-php-ext-configure pdo_oci --with-pdo-oci=instantclient,/usr/local/instantclient
sudo -E env PHP_INI_DIR=/usr/local/etc/php docker-php-ext-install pdo_oci
