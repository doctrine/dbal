#!/usr/bin/env bash

set -ex

echo "Installing language pack de..."

sudo apt -y -q update
sudo apt install language-pack-de
