#!/usr/bin/env bash

which docker>/dev/null || echo 'Please install docker from https://www.docker.com'

docker build  -t takeaticket:latest .
