#!make

.PHONY: stay@% stop @% sf restart-bg b d stop-all li k n r s t \
	dcompose-config push-images pli e restart-fg cu init da start run-phar \
	tag-images test build ni current-tag composer-install pull-images psi \
	list-images list-tags npm-install ci kill restart dcc ct composer-update run \
	composer-dump-autoload clean start-bg babel it rb sb lt composer-update-no-dev \
	cda start-fg sh hooks node bash rf env

-include ${MAIN_ENV}

SHELL    = /bin/bash
REALDIR  =$(dir $(abspath $(firstword $(MAKEFILE_LIST))))
MAKEDIR  ?=${REALDIR}

VAR_FILE ?=${MAKEDIR}.var
MAIN_ENV ?=${MAKEDIR}.env
TRGT_ENV ?=${MAKEDIR}.env.${TARGET}

COMPOSE_FILE =infra/compose/${TARGET}.yml
COMPOSE_TOOLS=infra/compose/tools

-include ${MAIN_ENV}
-include ${TRGT_ENV}
-include ${VAR_FILE}

PROJECT  ?=ids
REPO     ?=seanmorris
BRANCH   :=$$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo nobranch)
HASH     :=$$(echo _$$(git rev-parse --short HEAD 2>/dev/null) || echo init)
DESC     :=$$(git describe --tags 2>/dev/null || echo ${HASH})
SUFFIX   :=-${TARGET}$$([ ${BRANCH} = master ] && echo "" || echo "-${BRANCH}")
TAG      ?=${DESC}${SUFFIX}
FULLNAME ?=${REPO}/${PROJECT}:${TAG}

IMAGE    ?=
DHOST_IP ?=$$(docker network inspect bridge --format='{{ (index .IPAM.Config 0).Gateway}}')
NO_TTY   ?=-T
NO_DEV   ?=--no-dev

DOCKER   ?=$$(which docker)

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

ENTROPY_DIR=/tmp/IDS_ENTROPY
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
			&& echo $$(export ENTROPY_KEY=$$ENTROPY_KEY && ${GET_ENTROPY}) \
			|| echo -E $$ENV_VALUE; \
	};}; done; done < $$FROM > $$TO

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
	MAIN_ENV=${MAIN_ENV} TRGT_ENV=${TRGT_ENV} PROJECT_FULLNAME=${FULLNAME}  \
	PROJECT=${PROJECT} TARGET=${TARGET} MAKEDIR=${MAKEDIR} DOCKER=${DOCKER} \
	${XDEBUG_ENV} NPX="${NPX}" \
	$$(cat ${MAKEDIR}.env 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#) \
	$$(cat ${MAKEDIR}.env.${TARGET} 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#)

DCOMPOSE=export ${ENV} && docker-compose \
	-p ${PROJECT}_${TARGET} \
	-f ${COMPOSE_FILE}

DRUN=docker run --rm \
	-env-file=.env \
	-env-file=.env.${TARGET} \
	-v $$PWD:/app

DCRUN=

build b: ${COMPOSE_FILE}
	@ chmod ug+s . && umask 770
	@ ${GEN_ENV}
	@ echo Building ${FULLNAME}
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

test t: ${COMPOSE_FILE}
	@ ${GEN_ENV} && export TARGET=${TARGET} ${DCOMPOSE} \
		run --rm ${NO_TTY} ${PASS_ENV} \
		idilic -vv SeanMorris/Ids runTests
SEP=
env e: ${COMPOSE_FILE} .env .env.${TARGET}
	@ export ${ENV} && env ${SEP};

start s: ${COMPOSE_FILE} .env .env.${TARGET}
	@ ${DCOMPOSE} up -d

start-fg sf: ${COMPOSE_FILE} .env .env.${TARGET}
	@ ${DCOMPOSE} up

start-bg sb: ${COMPOSE_FILE} .env .env.${TARGET}
	@ ${DCOMPOSE} up &

stop d: ${COMPOSE_FILE} .env .env.${TARGET}
	@ ${DCOMPOSE} down

stop-all da: ${COMPOSE_FILE} .env .env.${TARGET}
	@ ${DCOMPOSE} down --remove-orphans

restart r: ${COMPOSE_FILE} .env .env.${TARGET}
	@ ${DCOMPOSE} down && ${DCOMPOSE} up -d

restart-fg rf: ${COMPOSE_FILE} .env .env.${TARGET}
	@ ${DCOMPOSE} down && ${DCOMPOSE} up

restart-bg rb: ${COMPOSE_FILE} .env .env.${TARGET}
	@ ${DCOMPOSE} down && ${DCOMPOSE} up &

kill k: ${COMPOSE_FILE} .env .env.${TARGET}
	@ ${DCOMPOSE} kill -s 9

current-tag ct: ${COMPOSE_FILE}
	@ echo ${TAG}

current-target ctr: ${COMPOSE_FILE}
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

pull-images pli: ${COMPOSE_FILE}
	@ ${GEN_ENV} && ${DCOMPOSE} pull

hooks: ${COMPOSE_FILE}
	@ git config core.hooksPath githooks

run: ${COMPOSE_FILE}
	@ ${GEN_ENV} && ${DCOMPOSE} run --rm ${NO_TTY} \
		${PASS_ENV} ${CMD}

bash sh: ${COMPOSE_FILE}
	@ ${DCOMPOSE} run --rm ${NO_TTY} \
		${PASS_ENV} --entrypoint=bash idilic

composer-install ci: ${COMPOSE_FILE}
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer update ${NO_DEV}

composer-update cu: ${COMPOSE_FILE}
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer update ${NO_DEV}

composer-dump-autoload cda: ${COMPOSE_FILE}
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer dump-autoload

node n: ${COMPOSE_FILE}
	@ ${GEN_ENV} && ${DCOMPOSE} -f \
	${COMPOSE_TOOLS}/node.yml run --rm ${PASS_ENV} node

PKG=
npm-install ni: ${COMPOSE_FILE}
	@ ${GEN_ENV} && ${DCOMPOSE} -f \
	${COMPOSE_TOOLS}/node.yml run --rm ${PASS_ENV} node npm i ${PKG}

dcompose-config dcc: ${COMPOSE_FILE}
	@ ${GEN_ENV} && ${DCOMPOSE} config

##
stay@%: @%
	@ echo Setting persistent target ${TARGET}...
	@ echo TARGET=${TARGET} > ${VAR_FILE};

@%:
	@ echo Using target ${TARGET}...
	@ $(eval TARGET=$(shell echo ${@} | cut -b 2-))

.env .env.${TARGET}:
	docker run --rm -v ${MAKEDIR}:/app -w=/app debian:buster-20191118-slim bash -c '{\
		mkdir -p ${ENTROPY_DIR} && chmod 700 ${ENTROPY_DIR}; \
		export FROM=config/${@} TO=${@} \
			&& ${STITCH_ENTROPY}; \
		(shopt -s nullglob; rm -rf ${ENTROPY_DIR}); \
	}'
###

babel: ${COMPOSE_FILE}
	${GEN_ENV} && ${DCOMPOSE} -f ${COMPOSE_TOOLS}/node.yml \
		run --rm ${PASS_ENV} node npx babel

run-phar: ${COMPOSE_FILE}
	@ ${GEN_ENV} && ${DCOMPOSE} run --rm \
		--entrypoint='php SeanMorris_Ids.phar' \
		${PASS_ENV} ${CMD}

clean: ${COMPOSE_FILE}
	@ ${GEN_ENV} && ${DCOMPOSE} -f ${COMPOSE_TOOLS}/node.yml \
		run --rm ${PASS_ENV} node bash -c "\
			(shopt -s nullglob; rm -rf .env .env.*); \
			(shopt -s nullglob; rm -rf vendor/);"
