#!/usr/bin/env bash

set -e
set -x

echo "Installing MySQL 5.7..."
sudo service mysql stop
echo mysql-apt-config mysql-apt-config/select-server select mysql-5.7 | sudo debconf-set-selections
wget http://dev.mysql.com/get/mysql-apt-config_0.8.6-1_all.deb
sudo DEBIAN_FRONTEND=noninteractive dpkg -i mysql-apt-config_0.8.6-1_all.deb
sudo rm -rf /var/lib/apt/lists/*
sudo apt-get clean
sudo apt-get update -qq
sudo apt-get install -qq mysql-server libmysqlclient-dev

echo "Restart mysql..."
sudo mysql -e "use mysql; update user set authentication_string=PASSWORD('') where User='root'; update user set plugin='mysql_native_password';FLUSH PRIVILEGES;"
sudo mysql -uroot --version
