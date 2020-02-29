#!/usr/bin/env bash

set -ex

echo "Starting RDBMS…">&2

if [[ "$IMAGE" == "mysql:8.0" ]]
then
  CMD_OPTIONS="--default-authentication-plugin=mysql_native_password"
else
  CMD_OPTIONS=""
fi

docker run \
    --health-cmd='mysqladmin ping --silent' \
    --detach \
    --env MYSQL_ALLOW_EMPTY_PASSWORD=yes \
    --env MYSQL_DATABASE=doctrine_tests \
    --publish 33306:3306 \
    --name rdbms \
    "$IMAGE" $CMD_OPTIONS

while true; do
  healthStatus=$(docker inspect --format "{{json .State.Health.Status }}" rdbms)
  case $healthStatus in
    '"starting"')
      echo "Waiting for RDBMS to become ready…">&2
      sleep 1
      ;;
    '"healthy"')
      echo "Container is healthy">&2
      break
      ;;
    '"unhealthy"')
      echo "Container is unhealthy">&2
      exit 1
      ;;
    *)
      echo "Unexpected health status $healthStatus">&2
      ;;
  esac
done
