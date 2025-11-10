#!/bin/bash

# Uruchamia polecenia wewnątrz kontenera backendowego, np. ./exec.sh composer install
cd ../environment
docker compose exec -it backend "$@"