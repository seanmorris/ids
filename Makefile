#!make

.PHONY: @% b babel bash build cda ci clean composer-dump-autoload composer-install \
	composer-update composer-update-no-dev ct ctr cu current-tag current-target d  \
	da dcc dcompose-config e entropy-dir env .env .env% hooks init it k kill li \
	list-images list-tags lt n ni node npm-install pli psi pull-images push-images \
	r rb restart restart-bg restart-fg rf run run-phar s sb sf sh start start-bg \
	start-fg stay@% stop stop-all t tag-images test

MAKEFLAGS += --no-builtin-rules --always-make

SHELL    =/bin/bash
REALDIR  =$(dir $(abspath $(firstword $(MAKEFILE_LIST))))
MAKEDIR  ?=${REALDIR}

VAR_FILE ?=${MAKEDIR}.var
MAIN_ENV ?=${MAKEDIR}.env
TRGT_ENV ?=${MAKEDIR}.env.${TARGET}

-include ${MAIN_ENV}
-include ${TRGT_ENV}
-include ${VAR_FILE}

COMPOSE_FILE =infra/compose/${TARGET}.yml
COMPOSE_TOOLS=infra/compose/tools

PROJECT  ?=ids
REPO     ?=seanmorris
BRANCH   :=$$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo nobranch)
HASH     :=$$(echo _$$(git rev-parse --short HEAD 2>/dev/null) || echo init)
DESC     :=$$(git describe --tags 2>/dev/null || echo ${HASH})
SUFFIX   =-${TARGET}$$([ ${BRANCH} = master ] && echo "" || echo "-${BRANCH}")
TAG      ?=${DESC}${SUFFIX}
FULLNAME ?=${REPO}/${PROJECT}:${TAG}

IMAGE    ?=
DHOST_IP :=$$(docker network inspect bridge --format='{{ (index .IPAM.Config 0).Gateway}}')
NO_TTY   ?=-T
NO_DEV   ?=--no-dev

DOCKER   :=$$(which docker)

NPX=cp  -n /app/package-lock.json /build; \
	cat /app/composer.json           \
		| tr '[:upper:]' '[:lower:]' \
		| tr '/' '-'                 \
		> package.json;              \
	npx

INTERPOLATE_ENV=env -i DHOST_IP=${DHOST_IP} \
	TAG=${TAG} REPO=${REPO} TARGET=${TARGET} \
	PROJECT=${PROJECT} \
	envsubst

XDEBUG_ENV=XDEBUG_CONFIG="`\
	test -f ${MAKEDIR}.env && cat ${MAKEDIR}.env.${TARGET} \
	| ${INTERPOLATE_ENV} \
	| grep ^XDEBUG_CONFIG_ \
	| while read VAR; do echo $$VAR | { \
		IFS='\=' read -r NAME VALUE; \
		echo -En $$NAME | sed -e 's/^XDEBUG_CONFIG_\(.\+\)/\L\1/'; \
		echo -En "=$$VALUE ";\
	} done`"

PASS_ENV=$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /')

WHILE_IMAGES=docker images ${REPO}/${PROJECT}.*:${TAG} -q | while read IMAGE_HASH; do

WHILE_TAGS=${WHILE_IMAGES} \
		docker image inspect --format="{{ index .RepoTags }}" $$IMAGE_HASH \
		| sed -e 's/[][]//g' \
		| sed -e 's/\s/\n/g' \
		| while read TAG_NAME; do

PARSE_ENV=grep -v ^\# \
		| while read -r ENV; do echo $$ENV | { \
			IFS='\=' read -r ENV_NAME ENV_VALUE; \

ENTROPY_DIR?=/tmp/IDS_ENTROPY
ENTROPY_KEY=default
GET_ENTROPY=test -e ${ENTROPY_DIR}/$$ENTROPY_KEY \
		&& cat ${ENTROPY_DIR}/$$ENTROPY_KEY \
		|| cat /dev/urandom \
			| tr -dc 'a-zA-Z0-9' \
			| fold -w 32 \
			| head -n 1 \
			| tee ${ENTROPY_DIR}/$$ENTROPY_KEY

STITCH_ENTROPY=test -f $$TO && test -s $$TO || while read -r ENV_LINE; do \
	test -n "$$ENV_LINE" || continue; \
	echo -n "$$ENV_LINE" | ${PARSE_ENV} \
		grep $$ENV_NAME .entropy | { \
		IFS=":" read -r ENV_KEY ENTROPY_KEY; \
		echo -n $$ENV_NAME=; \
		test -n "$$ENTROPY_KEY" \
			&& echo $$(ENTROPY_KEY=$$ENTROPY_KEY && ${GET_ENTROPY}) \
			|| echo -E $$ENV_VALUE; \
	};}; done; done < $$FROM > $$TO

ifeq (${TARGET},test)
	NO_DEV=
endif

ENV=TAG=$${TAG:-${TAG}} REPO=${REPO} BRANCH=${BRANCH} DHOST_IP=${DHOST_IP} \
	PROJECT=${PROJECT} TARGET=${TARGET} MAKEDIR=${MAKEDIR} DOCKER=${DOCKER} \
	${XDEBUG_ENV} NPX="${NPX}" MAIN_ENV=${MAIN_ENV} TRGT_ENV=${TRGT_ENV} \
	PROJECT_FULLNAME=${FULLNAME} \
	$$(cat ${MAKEDIR}.env 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#) \
	$$(cat ${MAKEDIR}.env.${TARGET} 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#)

DCOMPOSE=export ${ENV} && docker-compose \
	-p ${PROJECT}_${TARGET} \
	-f ${COMPOSE_FILE}

DRUN=docker run --rm \
	-env-file=.env \
	-env-file=.env.${TARGET} \
	-v $$PWD:/app

define UNINCLUDE
	cat ${1} | grep -v ^\# \
		| grep "^[A-Z_]\+=" \
		| sed -e 's/\=.\+$$//' \
		| while read OLD_VAR; do \
			echo -e "$$OLD_VAR=DELETED"; \
		done;
endef

$(eval X=G)

build b: .env .env.${TARGET} ${COMPOSE_FILE}
	@ chmod ug+s . && umask 770
	@ echo Building ${FULLNAME}
	@ [[ "${TARGET}" != "" ]] || (echo "No target set." && false)
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer install ${NO_DEV}
	@ ${DCOMPOSE} -f ${COMPOSE_TOOLS}/node.yml build node
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

test t: .env .env.${TARGET} ${COMPOSE_FILE}
	@ export TARGET=${TARGET} && ${DCOMPOSE} \
		run --rm ${NO_TTY} ${PASS_ENV} \
		idilic -vv SeanMorris/Ids runTests

clean: .env .env.${TARGET}
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app \
		debian:buster-20191118-slim bash -c \
			shopt -s nullglob;
			rm -f .env .env.${TARGET} .var
			rm -rf vendor/

SEP=
env e: .env .env.${TARGET} ${COMPOSE_FILE}
	@ export ${ENV} && env ${SEP};

start s: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} up -d

start-fg sf: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} up

start-bg sb: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} up &

stop d: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} down

stop-all da: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} down --remove-orphans

restart r: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} down && ${DCOMPOSE} up -d

restart-fg rf: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} down && ${DCOMPOSE} up

restart-bg rb: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} down && ${DCOMPOSE} up &

kill k: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} kill -s 9

current-tag ct:
	@ echo ${TAG}

current-target ctr:
	@ echo ${IDS_LOGLEVEL}
	@ [[ "${TARGET}" != "" ]] || (echo "No target set." && false)
	@ echo ${TARGET}

list-images li: ${COMPOSE_FILE}
	@ ${WHILE_IMAGES} \
		echo $$(docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH) \
		$$(docker image inspect --format="{{ .Size }}" $$IMAGE_HASH  \
			| awk '{ S=$$1 /1024/1024 ; print S "MB" }' \
		); \
	done;

list-tags lt: ${COMPOSE_FILE}
	@ ${WHILE_TAGS} \
		echo $$TAG_NAME; \
	done;done;

push-images psi: ${COMPOSE_FILE}
	@ echo Pushing ${PROJECT}:${TAG}
	@ ${WHILE_TAGS} \
		docker push $$TAG_NAME; \
	done;done;

pull-images pli: .env .env.${TARGET} ${COMPOSE_FILE}
	${DCOMPOSE} pull

hooks: ${COMPOSE_FILE}
	@ git config core.hooksPath githooks

run: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} run --rm ${NO_TTY} \
		${PASS_ENV} ${CMD}

bash sh: .env .env.${TARGET} ${COMPOSE_FILE}
	@ ${DCOMPOSE} run --rm ${NO_TTY} \
		${PASS_ENV} --entrypoint=bash idilic

composer-install ci:
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer update ${NO_DEV}

composer-update cu:
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer update ${NO_DEV}

composer-dump-autoload cda:
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer dump-autoload

node n: .env .env.${TARGET} ${COMPOSE_FILE}
	${DCOMPOSE} -f \
	${COMPOSE_TOOLS}/node.yml run --rm ${PASS_ENV} node

PKG=
npm-install ni: ${COMPOSE_FILE} .env .env.${TARGET}
	${DCOMPOSE} -f \
	${COMPOSE_TOOLS}/node.yml run --rm ${PASS_ENV} node npm i ${PKG}

dcompose-config dcc: ${COMPOSE_FILE} .env .env.${TARGET}
	${DCOMPOSE} config

##

stay@%: @%
	@ echo Setting persistent target ${TARGET}...
	@ echo TARGET=${TARGET} > ${VAR_FILE};

@%:
	@ NEWTARGET=`echo ${@} | cut -c 2-`; \
	test -f infra/compose/$$NEWTARGET.yml || (\
		echo "No yml for target '$$NEWTARGET' found in config/ dir." \
		&& false \
	) && echo Using target $$NEWTARGET...

	@ $(eval TARGET=$(shell echo ${@} | cut -b 2-))
	@ $(eval COMPOSE_FILE=infra/compose/${TARGET}.yml)

	$(foreach SETTING, $(shell $(call UNINCLUDE,${MAIN_ENV})), $(eval ${SETTING}))
	$(foreach SETTING, $(shell $(call UNINCLUDE,${TRGT_ENV})), $(eval ${SETTING}))

	@ $(eval MAIN_ENV ?=${MAKEDIR}.env)
	@ $(eval TRGT_ENV ?=${MAKEDIR}.env.${TARGET})
	@ $(eval -include ${MAIN_ENV})
	@ $(eval -include ${TRGT_ENV})

.env%: entropy-dir
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app \
		debian:buster-20191118-slim bash -c '{\
			FILE=`basename ${@}`; \
			[[ $$FILE == .env. ]] && FILE="$${FILE}${TARGET}"; \
			FROM=config/$$FILE TO=$$FILE && ${STITCH_ENTROPY}; \
			(shopt -s nullglob; rm -rf ${ENTROPY_DIR}); \
		}'

.env: entropy-dir
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app \
		debian:buster-20191118-slim bash -c '{\
			FILE=`basename ${@}`; \
			[[ $$FILE == ".env." ]] || FILE=.env \
			FROM=config/$$FILE TO=$$FILE && ${STITCH_ENTROPY}; \
			(shopt -s nullglob; rm -rf ${ENTROPY_DIR}); \
		}'

infra/compose/%yml:
	@ test -f infra/compose/${TARGET}.yml;

entropy-dir: ${ENTROPY_DIR}
	@ mkdir -p ${ENTROPY_DIR} && chmod 700 ${ENTROPY_DIR}
###

babel: ${COMPOSE_FILE} .env .env.${TARGET}
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/node.yml \
		run --rm ${PASS_ENV} node npx babel

run-phar: ${COMPOSE_FILE} .env .env.${TARGET}
	${DCOMPOSE} run --rm \
		--entrypoint='php SeanMorris_Ids.phar' \
		${PASS_ENV} ${CMD}
