#!/usr/bin/env bash

set -ex

echo "Installing MySQL 8.0..."

echo mysql-apt-config mysql-apt-config/select-server select mysql-8.0 | sudo debconf-set-selections
wget https://dev.mysql.com/get/mysql-apt-config_0.8.10-1_all.deb
sudo dpkg --install mysql-apt-config_0.8.10-1_all.deb
sudo apt-get update -q
sudo apt-get install -q -y --force-yes -o Dpkg::Options::=--force-confnew mysql-server
echo -e "[mysqld]\ndefault_authentication_plugin=mysql_native_password" | sudo tee --append /etc/mysql/my.cnf
sudo /etc/init.d/mysql start
sudo mysql_upgrade

mysql --version
