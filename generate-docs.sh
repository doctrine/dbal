#!/bin/bash
rm /var/www/doctrine-docs -Rf
sphinx-build en /var/www/doctrine-docs
