#!/usr/bin/env bash

which docker>/dev/null || echo 'Please install docker from https://www.docker.com'

docker run -i -t -P takeaticket
#php -S 0:8080 -t www/

# WORKS with ENTRYPOINT php -S 0:8080 -t www
# Any bash entrypoints seem to fail :/