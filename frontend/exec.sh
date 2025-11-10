#!/bin/bash

# Uruchamia polecenia wewnątrz kontenera frontendowego, np. ./exec.sh npm install
cd ../environment
if [ -t 1 ]; then
  docker compose run --rm -Pit frontend "$@"
else
  docker compose run --rm -Pi frontend "$@"
fi