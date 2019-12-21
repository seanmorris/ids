#!make
.PHONY: it composer-install composer-update composer-update-no-dev tag-images \
	push-images pull-images tag-images start start-fg restart restart-fg stop \
	stop-all run run-phar test env hooks

SHELL    = /bin/bash
MAKEDIR  =$(dir $(abspath $(lastword $(MAKEFILE_LIST))))
TARGET   ?=base

MAIN_ENV ?=${MAKEDIR}.env
TRGT_ENV ?=${MAKEDIR}.env.${TARGET}

-include ${MAIN_ENV}
-include ${TRGT_ENV}

PROJECT  ?=ids
REPO     ?=seanmorris
BRANCH   ?=$$(git rev-parse --abbrev-ref HEAD  2>/dev/null)
HASH     ?=$$(echo _$$(git rev-parse --short HEAD) || echo init)
DESC     ?=$$(git describe --tags 2>/dev/null || echo ${HASH})

IMAGE    ?=
DHOST_IP ?=$$(docker network inspect bridge --format='{{ (index .IPAM.Config 0).Gateway}}')
NO_TTY   ?=-T
NO_DEV   ?=--no-dev

INTERPOLATE_ENV=env -i DHOST_IP=${DHOST_IP} \
	TAG=${TAG} REPO=${REPO} TARGET=${TARGET} \
	PROJECT=${PROJECT} \
	envsubst

PASS_ENV=$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /')

SUFFIX   =-${TARGET}$$([ ${BRANCH} = master ] && echo "" || echo "-${BRANCH}")
TAG      ?=${DESC}${SUFFIX}
FULLNAME ?=${REPO}/${PROJECT}:${TAG}

WHILE_IMAGES=docker images ${REPO}/${PROJECT}.*:${TAG} -q | while read IMAGE_HASH; do

WHILE_TAGS=${WHILE_IMAGES} \
		docker image inspect --format="{{ index .RepoTags }}" $$IMAGE_HASH \
		| sed -e 's/[][]//g' \
		| sed -e 's/\s/\n/g' \
		| while read TAG_NAME; do

PARSE_ENV=grep -v ^\# \
		| while read -r ENV; do echo $$ENV | { \
			IFS='\=' read -r NAME VALUE; \

ENTROPY_DIR=/tmp/IDS_ENTROPY
ENTROPY_KEY=default
GET_ENTROPY=test -e ${ENTROPY_DIR}/$$ENTROPY_KEY \
		&& cat ${ENTROPY_DIR}/$$ENTROPY_KEY \
		|| cat /dev/urandom \
			| tr -dc 'a-zA-Z0-9' \
			| fold -w 32 \
			| head -n 1 \
			| tee ${ENTROPY_DIR}/$$ENTROPY_KEY

STITCH_ENTROPY=test -e $$TO || while read -r LINE; do \
	test -n "$$LINE" || continue; \
	echo -n "$$LINE" | ${PARSE_ENV} \
		grep $$NAME .entropy | { \
		IFS=":" read -r ENV_KEY ENTROPY_KEY; \
		echo -n $$NAME=; \
		test -n "$$ENTROPY_KEY" \
			&& echo $$(export ENTROPY_KEY=$$ENTROPY_KEY && ${GET_ENTROPY}) \
			|| echo -E $$VALUE; \
	};}; done; done < $$FROM > $$TO

ifeq (${TARGET},test)
	NO_DEV=
endif

ifeq (${TARGET},dev)
	NO_DEV=
	XDEBUG_ENV=XDEBUG_CONFIG="`\
		test -f .env.dev && cat .env.dev \
		| ${INTERPOLATE_ENV} \
		| grep -v ^\# \
		| grep ^XDEBUG_CONFIG_ \
		| while read VAR; do echo $$VAR | \
		{ \
			IFS='\=' read -r NAME VALUE; \
			echo -En $$NAME | sed -e 's/^XDEBUG_CONFIG_\(.\+\)/\L\1/'; \
			echo -En =$$VALUE;\
		} \
		; done | cut -c 2- \
	 ` "
else
	XDEBUG_ENV=
endif

ENV=TAG=$${TAG:-${TAG}} REPO=${REPO} BRANCH=${BRANCH} DHOST_IP=${DHOST_IP} \
	MAIN_ENV=${MAIN_ENV} TRGT_ENV=${TRGT_ENV} PROJECT_FULLNAME=${FULLNAME} \
	PROJECT=${PROJECT} TARGET=${TARGET} ${XDEBUG_ENV} \
	$$(cat .env 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#) \
	$$(cat .env.${TARGET} 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#)

DCOMPOSE ?=export ${ENV} \
	&& docker-compose \
	-p ${PROJECT}_${TARGET} \
	-f infra/compose/${TARGET}.yml

it: infra/compose/${TARGET}.yml
	@ echo Building ${FULLNAME}
	@ mkdir -p ${ENTROPY_DIR} && chmod 700 ${ENTROPY_DIR};
	@ export FROM=.env.sample TO=.env && ${STITCH_ENTROPY};
	@ export FROM=.env.${TARGET}.sample TO=.env.${TARGET} && ${STITCH_ENTROPY};
	@ (shopt -s nullglob; rm -rf ${ENTROPY_DIR})
	@ touch -a .env.${TARGET}
	@ touch -a .env
	@ docker run --rm \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		-v $$PWD:/app \
		composer install ${NO_DEV}
	@ export TAG=latest-${TARGET} && ${DCOMPOSE} build idilic
	@ ${DCOMPOSE} build
	@ ${DCOMPOSE} up --no-start
	@ ${WHILE_IMAGES} \
		docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH \
		| while read IMAGE_NAME; do \
			IMAGE_PREFIX=`echo "$$IMAGE_NAME" | sed -e "s/\:.*\$$//"`; \
			echo "$$IMAGE_HASH $$IMAGE_PREFIX":${TAG}; \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":${HASH}${SUFFIX}; \
			echo "$$IMAGE_HASH $$IMAGE_PREFIX":${HASH}${SUFFIX}; \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":`date '+%Y%m%d'`${SUFFIX}; \
			echo "$$IMAGE_HASH $$IMAGE_PREFIX":`date '+%Y%m%d'`${SUFFIX}; \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":latest${SUFFIX}; \
			echo "$$IMAGE_HASH $$IMAGE_PREFIX":latest${SUFFIX}; \
		done; \
	done;

composer-update: infra/compose/${TARGET}.yml
	@ docker run --rm \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer update

list-images:
	@ ${WHILE_IMAGES} \
		echo $$(docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH) \
		$$(docker image inspect --format="{{ .Size }}" $$IMAGE_HASH  \
			| awk '{ S=$$1 /1024/1024 ; print S "MB" }' \
		); \
	done;

list-tags:
	@ ${WHILE_TAGS} \
		echo $$TAG_NAME; \
	done;done;

push-images: infra/compose/${TARGET}.yml
	@ echo Pushing ${PROJECT}:${TAG}
	@ ${WHILE_TAGS} \
		docker push $$TAG_NAME; \
	done;done;

pull-images: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} pull

restart: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} down
	@ ${DCOMPOSE} up -d

restart-fg: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} down
	@ ${DCOMPOSE} up

start: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} up -d

start-fg: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} up

stop: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} down

stop-all: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} down --remove-orphans

current-tag: infra/compose/${TARGET}.yml
	@ echo ${TAG}

bash: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} run --rm ${NO_TTY} \
		${PASS_ENV} --entrypoint=bash idilic

run: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} run --rm ${NO_TTY} \
		${PASS_ENV} ${CMD}

run-phar: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} run --rm --entrypoint='php SeanMorris_Ids.phar' \
		${PASS_ENV} ${CMD}

test: infra/compose/${TARGET}.yml
	@ make --no-print-directory run \
		TARGET=${TARGET} CMD="idilic -vv SeanMorris/Ids runTests SeanMorris/Ids"

env: infra/compose/${TARGET}.yml
	@ env -i ${ENV} bash -c "env"

hooks: infra/compose/${TARGET}.yml
	@ git config core.hooksPath githooks

dcompose-config:
	@ ${DCOMPOSE} config
