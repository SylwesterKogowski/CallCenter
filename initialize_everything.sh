#!/bin/bash

cd environment
docker compose --profile=all down
docker compose --profile=all build

echo "Installing dependencies for the frontend"
cd ../frontend
./exec.sh npm install
./exec.sh npm run build

echo "Installing dependencies for the backend"
cd ../backend
./exec.sh composer install


cd ../environment
docker compose --profile=all up -d --wait

echo "Waiting for the database to be ready"
sleep 10

echo "Migrating the database"
cd ../backend
./exec.sh composer run migrate

echo "Everything is initialized and running"
echo "Frontend is running at http://localhost:8000"