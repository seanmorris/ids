#!make

.PHONY: it composer-install composer-update composer-update-no-dev tag-images \
	push-images pull-images tag-images start start-fg restart restart-fg stop \
	stop-all run run-phar test env hooks

SHELL    = /bin/bash
MAKEDIR  ?=$(dir $(abspath $(firstword $(MAKEFILE_LIST))))

MAIN_ENV ?=${MAKEDIR}.env
TRGT_ENV ?=${MAKEDIR}.env.${TARGET}

SURE_ENV =touch ${MAIN_ENV} ${TRGT_ENV}

TARGET_COMPOSE=infra/compose/${TARGET}.yml

-include ${MAIN_ENV}
-include ${TRGT_ENV}

PROJECT  ?=ids
REPO     ?=seanmorris
BRANCH   =$$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo nobranch)
HASH     =$$(echo _$$(git rev-parse --short HEAD 2>/dev/null) || echo init)
DESC     =$$(git describe --tags 2>/dev/null || echo ${HASH})
SUFFIX   =-${TARGET}$$([ ${BRANCH} = master ] && echo "" || echo "-${BRANCH}")
TAG      ?=${DESC}${SUFFIX}
FULLNAME ?=${REPO}/${PROJECT}:${TAG}

IMAGE    ?=
DHOST_IP ?=$$(docker network inspect bridge --format='{{ (index .IPAM.Config 0).Gateway}}')
NO_TTY   ?=-T
NO_DEV   ?=--no-dev

INTERPOLATE_ENV=env -i DHOST_IP=${DHOST_IP} \
	TAG=${TAG} REPO=${REPO} TARGET=${TARGET} \
	PROJECT=${PROJECT} \
	envsubst

PASS_ENV=$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /')

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

STITCH_ENTROPY=test -f $$TO && test -s $$TO || while read -r LINE; do \
	test -n "$$LINE" || continue; \
	echo -n "$$LINE" | ${PARSE_ENV} \
		grep $$NAME .entropy | { \
		IFS=":" read -r ENV_KEY ENTROPY_KEY; \
		echo -n $$NAME=; \
		test -n "$$ENTROPY_KEY" \
			&& echo $$(export ENTROPY_KEY=$$ENTROPY_KEY && ${GET_ENTROPY}) \
			|| echo -E $$VALUE; \
	};}; done; done < $$FROM > $$TO

GEN_ENV=mkdir -p ${ENTROPY_DIR} && chmod 700 ${ENTROPY_DIR}; \
	export \
		FROM=${MAKEDIR}/config/.env \
		TO=${MAKEDIR}/.env \
		&& ${STITCH_ENTROPY}; \
	export \
		FROM=${MAKEDIR}/config/.env.${TARGET} \
		TO=${MAKEDIR}/.env.${TARGET} \
		&& ${STITCH_ENTROPY}; \
	(shopt -s nullglob; rm -rf ${ENTROPY_DIR}); \
	touch -a ${MAKEDIR}.env.${TARGET}; \
	touch -a ${MAKEDIR}.env;

ifeq (${TARGET},test)
	NO_DEV=
endif

ifeq (${TARGET},dev)
	NO_DEV=
	XDEBUG_ENV=XDEBUG_CONFIG="`\
		test -f ${MAKEDIR}.env.dev && cat ${MAKEDIR}.env.dev \
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
	PROJECT=${PROJECT} TARGET=${TARGET:-${TARGET}} MAKEDIR=${MAKEDIR} ${XDEBUG_ENV}\
	$$(cat ${MAKEDIR}.env 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#) \
	$$(cat ${MAKEDIR}.env.${TARGET} 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#)

DCOMPOSE ?=export ${ENV} \
	&& docker-compose \
	-p ${PROJECT}_${TARGET} \
	-f ${TARGET_COMPOSE}

it: ${TARGET_COMPOSE}
	@ echo Building ${FULLNAME}
	@ ${GEN_ENV}
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

composer-update: ${TARGET_COMPOSE}
	@ docker run --rm \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer update

list-images: ${TARGET_COMPOSE}
	@ ${WHILE_IMAGES} \
		echo $$(docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH) \
		$$(docker image inspect --format="{{ .Size }}" $$IMAGE_HASH  \
			| awk '{ S=$$1 /1024/1024 ; print S "MB" }' \
		); \
	done;

list-tags: ${TARGET_COMPOSE}
	@ ${WHILE_TAGS} \
		echo $$TAG_NAME; \
	done;done;

push-images: ${TARGET_COMPOSE}
	@ echo Pushing ${PROJECT}:${TAG}
	@ ${WHILE_TAGS} \
		docker push $$TAG_NAME; \
	done;done;

pull-images: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} pull

start: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} up -d

start-fg: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} up

start-bg: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} up &

stop: ${TARGET_COMPOSE}
	@ ${SURE_ENV}
	@ ${DCOMPOSE} down

restart: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} down
	@ ${DCOMPOSE} up -d

restart-fg: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} down
	@ ${DCOMPOSE} up

restart-bg: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} down
	@ ${DCOMPOSE} up &

stop-all: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} down --remove-orphans

current-tag: ${TARGET_COMPOSE}
	@ echo ${TAG}

bash: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} run --rm ${NO_TTY} \
		${PASS_ENV} --entrypoint=bash idilic

run: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} run --rm ${NO_TTY} \
		${PASS_ENV} ${CMD}

run-phar: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} run --rm --entrypoint='php SeanMorris_Ids.phar' \
		${PASS_ENV} ${CMD}

test: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ export TARGET=${TARGET} ${DCOMPOSE} run --rm ${NO_TTY} \
		${PASS_ENV} \
		idilic -vv SeanMorris/Ids runTests
clean: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ ${DCOMPOSE} run --rm --entrypoint=bash \
		${PASS_ENV} idilic -c " \
			(shopt -s nullglob; rm -rf .env .env.${TARGET}); \
			(shopt -s nullglob; rm -rf vendor/); \
		"

env: ${TARGET_COMPOSE}
	@ ${GEN_ENV}
	@ env -i ${ENV} bash -c "env"

hooks: ${TARGET_COMPOSE}
	@ git config core.hooksPath githooks

dcompose-config: ${TARGET_COMPOSE}
	@ ${DCOMPOSE} config

md:
	echo ${MAKEDIR}
