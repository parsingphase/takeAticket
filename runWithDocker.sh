#!/usr/bin/env bash

which docker>/dev/null || echo 'Please install docker from https://www.docker.com'

docker run -i -t -p 8000:8000 takeaticket:latest

echo "Listening on http://localhost:8000"

#php -S 0:8000 -t www/

# WORKS with ENTRYPOINT php -S 0:8000 -t www
# Any bash entrypoints seem to fail :/