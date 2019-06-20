#!/bin/bash

set -euo pipefail

phpbrew ext install pdo_oci -- --with-pdo-oci=instantclient,/usr/local/instantclient
