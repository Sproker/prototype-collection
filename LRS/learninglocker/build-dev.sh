#!/bin/sh
set -e

export DOCKER_TAG=afb2858ab47bb76a1153ae2dd8a3bdf79d10b863
docker build --no-cache -t michzimny/learninglocker2-app:$DOCKER_TAG app
docker build --no-cache -t michzimny/learninglocker2-nginx:$DOCKER_TAG nginx

