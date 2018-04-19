#!/bin/bash

# This script is a small convenience wrapper for running the doctrine testsuite against a large bunch of databases.
# Create *.phpunit.xml files and specify database connection parameters in the <php /> section.

for i in *.phpunit.xml; do
    echo "RUNNING TESTS WITH CONFIG $i"
    vendor/bin/phpunit -c "$i" "$@"
done
