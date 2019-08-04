#!/usr/bin/env bash

set -ex

echo "Installing language pack..."

sudo apt-key adv --keyserver keyserver.ubuntu.com --recv-keys 6B05F25D762E3157
sudo apt -y -q update
sudo apt install language-pack-de
