#!/bin/bash

cd environment
docker compose --profile=all build
docker compose down backend
docker compose up -d frontend

cd ../frontend
./exec.sh npm install
./exec.sh npm run build

cd ../backend
./exec.sh composer install


cd ../environment
docker compose --profile=all up -d --wait

sleep 5
cd ../backend
./exec.sh composer run migrate

echo "Everything is initialized and running"
echo "Frontend is running at http://localhost:8000"