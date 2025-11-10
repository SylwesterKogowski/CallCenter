#!/bin/bash

# Uruchamia polecenia wewnątrz kontenera frontendowego, np. ./exec.sh npm install
cd ../environment
docker compose run --rm -Pit frontend "$@"