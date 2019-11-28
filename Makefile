#!make
.PHONY: it clean build images start stop stop-all tag run

-include .env

PROJECT  = Ids
REPO     = seanmorris
TAG      = latest

TARGET   ?= base
BRANCH   ?= `git rev-parse --abbrev-ref HEAD`
DESC     ?= `git describe --tags 2>/dev/null || git rev-parse --short HEAD`
TARGET   ?= test
TAG      ?= ${BRANCH}-${DESC}-${TARGET}
IMAGE    ?=
DCOMPOSE ?=export TAG=${TAG} REPO=${REPO} && docker-compose \
	-p ${PROJECT} \
	-f infra/compose/${TARGET}.yml

it:
	docker run --rm -it \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer install
	make build TAG=latest IMAGE=idilic
	make build
	${DCOMPOSE} up --no-start
	make images

# 	make run CMD='idilic php idilic -d=\; link'

clean:
	rm -rf vendor/

build:
	${DCOMPOSE} build ${IMAGE}

images:
	@ ${DCOMPOSE} images -q | while read IMAGE_HASH; do \
		docker image inspect --format="{{index .RepoTags 0}}" $$IMAGE_HASH \
		| sed s/\:.*\$/// \
		| grep "^${REPO}" \
		| grep "${TAG}" \
		| while read IMAGE_NAME; do \
			docker tag $$IMAGE_HASH $$IMAGE_NAME:latest-${TARGET}; \
			echo $$IMAGE_NAME:latest-${TARGET}; \
		done; \
	done;
	@ ${DCOMPOSE} images

restart:
	make stop
	make start

start:
	${DCOMPOSE} up

stop:
	${DCOMPOSE} down

stop-all:
	${DCOMPOSE} down --remove-orphans

tag:
	@ echo ${TAG}

run:
	${DCOMPOSE} run --rm ${CMD}
