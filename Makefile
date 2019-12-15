#!make
TARGET ?=dev

.PHONY: it clean build images start start-fg restart restart-fg stop stop-all tag run test env

-include .env
-include .env.${TARGET}

PROJECT =Ids
REPO    =seanmorris

TARGET ?=base
BRANCH ?=$$(git rev-parse --abbrev-ref HEAD)
DESC   ?=$$(git describe --tags 2>/dev/null || git rev-parse --short HEAD)

TAG       ?=${BRANCH}-${DESC}-${TARGET}
IMAGE     ?=
DHOST_IP  ?=$$(docker network inspect bridge --format='{{ (index .IPAM.Config 0).Gateway}}')

INTERPOLATE_ENV=env -i DHOST_IP=${DHOST_IP} \
	TAG=${TAG} REPO=${REPO} TARGET=${TARGET} \
	envsubst

ifeq ($(TARGET),dev)
	XDEBUG_ENV=XDEBUG_CONFIG="`\
		cat .env.dev | ${INTERPOLATE_ENV} \
		| grep -v ^\# \
		| grep ^XDEBUG_CONFIG_ \
		| while read VAR; do echo $$VAR | \
		{ \
			IFS='\=' read -r NAME VALUE; \
			echo -n ' '; \
			echo -n $$NAME | sed -e 's/^XDEBUG_CONFIG_\(.\+\)/\L\1/'; \
			echo -n =$$VALUE;\
		} \
		; done | cut -c 2- \
	 ` "
else
	XDEBUG_ENV=
endif

ENV=TAG=${TAG} REPO=${REPO} DHOST_IP=${DHOST_IP} ${XDEBUG_ENV} \
	$$(cat .env | ${INTERPOLATE_ENV} | grep -v ^\#) \
	$$(cat .env.${TARGET} | ${INTERPOLATE_ENV} | grep -v ^\#)


DCOMPOSE ?=export ${ENV} \
	&& docker-compose \
	-p ${PROJECT} \
	-f infra/compose/${TARGET}.yml

it:
	@ echo Building ${PROJECT} ${TAG}
	@ sleep 2
	@ docker run --rm \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer install
	make build TAG=latest IMAGE=idilic
	make build
	${DCOMPOSE} up --no-start
	make images

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

restart-fg:
	make stop
	make start-fg

start:
	${DCOMPOSE} up -d

start-fg:
	${DCOMPOSE} up

stop:
	${DCOMPOSE} down

stop-all:
	${DCOMPOSE} down --remove-orphans

tag:
	@ echo ${TAG}

run:
	${DCOMPOSE} run --rm \
	$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /') \
	${CMD}

test:
	echo ${ENV};
	@ make --no-print-directory run \
		TARGET=${TARGET} CMD="idilic -vv SeanMorris/Ids runTests SeanMorris/Ids"

env:
	echo ${XDEBUG_ENV}
	#env -i ${ENV} bash -c "env"

hooks:
	git config core.hooksPath githooks