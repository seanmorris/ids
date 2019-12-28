#!make

.PHONY: @% b babel bash build cda ci clean composer-dump-autoload composer-install \
	composer-update composer-update-no-dev ct ctr cu current-tag current-target d  \
	da dcc dcompose-config e entropy-dir_env hooks init it k kill li \
	list-images list-tags lt n ni node npm-install pli psi pull-images push-images \
	r rb restart restart-bg restart-fg rf run run-phar s sb sf sh start start-bg \
	start-fg stay@% stop stop-all t tag-images test .lock_env

.SECONDEXPANSION:

MAKEFLAGS += --no-builtin-rules

DEBIAN   ?= debian:buster-20191118-slim
PHP      ?= 7.3
SHELL    =/bin/bash
REALDIR  :=$(dir $(abspath $(lastword $(MAKEFILE_LIST))))
MAKEDIR  ?=${REALDIR}
VAR_FILE ?=${MAKEDIR}.var

MAIN_ENV ?=${MAKEDIR}.env
MAIN_DLT ?=${MAKEDIR}.env.default

-include ${MAIN_ENV}
-include ${VAR_FILE}

ifeq ($(filter @%,$(firstword ${MAKECMDGOALS})),)
ifeq ($(filter stay@%,$(firstword ${MAKECMDGOALS})),)
ifeq (${TARGET},)
$(error Please set a target with @target or stay@target.)
endif
else
$(eval TARGET=$(shell echo ${firstword ${MAKECMDGOALS}} | cut -c 6-))
endif
else
$(eval TARGET=$(shell echo ${firstword ${MAKECMDGOALS}} | cut -c 2-))
endif

TRGT_ENV =${MAKEDIR}.env_${TARGET}
TRGT_DLT =${MAKEDIR}.env_${TARGET}.default

-include ${TRGT_ENV}
-include ${TRGT_DLT}

$(shell >&2 echo Starting with target: ${TARGET})

DOCKDIR ?=${REALDIR}infra/docker/

DOCKER_TEMPLATES=$(shell ls ${DOCKDIR}*.template)

DEBIAN_ESC=$(shell echo ${DEBIAN} | sed -e 's/\:/__/gi')

PREBUILD =.env                       \
	.env.default                     \
	.env_$${TARGET}                  \
	.env_$${TARGET}.default          \
	.lock_env                        \
	$(shell ls ${DOCKDIR}*.template  \
		| sed -e 's/\.[a-z]\+$$//gi' \
		| sed -e 's/\(\..\+\)/._gen\1/gi' \
	)

ENV_LOCK ?=${MAKEDIR}.lock_env

-include ${ENV_LOCK}

COMPOSE_BASE   =infra/compose/base.yml
COMPOSE_TARGET =infra/compose/${TARGET}.yml
COMPOSE_TOOLS  =infra/compose/tools

PROJECT  ?=ids
REPO     ?=seanmorris
BRANCH   :=$(shell git rev-parse --abbrev-ref HEAD 2>/dev/null || echo nobranch)
HASH     :=$(shell echo _$$(git rev-parse --short HEAD 2>/dev/null) || echo init)
DESC     :=$(shell git describe --tags 2>/dev/null || echo ${HASH})
SUFFIX   =-${TARGET}$(shell [[ ${PHP} = 7.3  ]] || echo -${PHP})

TAG      ?=${DESC}${SUFFIX}$(shell [[ ${BRANCH} = "master" ]] && echo -${BRANCH})
FULLNAME ?=${REPO}/${PROJECT}:${TAG}

IMAGE    ?=
DHOST_IP :=$(shell docker network inspect bridge --format='{{ (index .IPAM.Config 0).Gateway}}')
NO_TTY   ?=-T

ifneq ($(filter ${TARGET},"target dev"),)
	NO_DEV=
else
	NO_DEV=--no-dev
endif

DOCKER   :=$(shell which docker)

DEVTARGETS=test dev
ISDEV     =echo "${DEVTARGETS}" | grep -wq "${TARGET}"

define NPX
cp  -n /app/package-lock.json /build;   \
	cat /app/composer.json              \
		| tr '[:upper:]' '[:lower:]'    \
		| tr '/' '-'                    \
		> package.json;                 \
	npx
endef

define INTERPOLATE_ENV
env -i DHOST_IP=${DHOST_IP}             \
	TAG=${TAG} REPO=${REPO}             \
	TARGET=${TARGET} PROJECT=${PROJECT} \
	envsubst
endef

define XDEBUG_ENV
XDEBUG_CONFIG="`\
	test -f ${MAKEDIR}.env_${TARGET}    \
	&& cat ${MAKEDIR}.env_${TARGET}     \
	| ${INTERPOLATE_ENV}                \
	| grep ^XDEBUG_CONFIG_              \
	| while read VAR; do echo $$VAR | { \
		IFS='\=' read -r NAME VALUE;    \
		echo -En $$NAME                 \
			| sed -e 's/^XDEBUG_CONFIG_\(.\+\)/\L\1/'; \
		echo -En "=$$VALUE ";           \
	} done`"
endef

PASS_ENV=$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /')

define WHILE_IMAGES
docker images ${REPO}/${PROJECT}.*:${TAG} -q | while read IMAGE_HASH; do
endef

define WHILE_TAGS
${WHILE_IMAGES} \
	docker image inspect --format="{{ index .RepoTags }}" $$IMAGE_HASH \
	| sed -e 's/[][]//g' \
	| sed -e 's/\s/\n/g' \
	| while read TAG_NAME; do
endef

define PARSE_ENV
grep -v ^\# \
	| while read -r ENV; do echo $$ENV | { \
		IFS='\=' read -r ENV_NAME ENV_VALUE;
endef

define QUOTE_ENV
	${PARSE_ENV} echo   -n " $$ENV_NAME="; printf %q "$$ENV_VALUE"; }; done
endef

ENTROPY_DIR=/tmp/IDS_ENTROPY-${TARGET}
ENTROPY_KEY=default

define RANDOM_STRING
cat /dev/urandom         \
	| tr -dc 'a-zA-Z0-9' \
	| fold -w 32         \
	| head -n 1
endef

define GET_ENTROPY
test -e ${ENTROPY_DIR}/$$ENTROPY_KEY    \
	&& cat ${ENTROPY_DIR}/$$ENTROPY_KEY \
	|| ${RANDOM_STRING} | tee ${ENTROPY_DIR}/$$ENTROPY_KEY
endef

define STITCH_ENTROPY
test -d ${ENTROPY_DIR}                       \
	|| mkdir -m 700 -p ${ENTROPY_DIR};       \
while read -r ENV_LINE; do                   \
	test -n "$$ENV_LINE" || continue;        \
	echo -n "$$ENV_LINE" | ${PARSE_ENV}      \
		grep $$ENV_NAME .entropy | {         \
		IFS=":" read -r ENV_KEY ENTROPY_KEY; \
		echo -n $$ENV_NAME=;                 \
		test -n "$$ENTROPY_KEY"              \
			&& echo $$(ENTROPY_KEY=$$ENTROPY_KEY && ${GET_ENTROPY}) \
			|| echo -E $$ENV_VALUE;          \
};}; done; done < $$FROM > $$TO
endef

define UNINCLUDE
cat ${1} | grep -v ^\#       \
	| grep "^[A-Z_]\+="      \
	| sed -e 's/\=.\+$$//'   \
	| while read OLD_VAR; do \
		echo -e "$$OLD_VAR=DELETED"; \
	done;
endef

define NEWTARGET
test -f infra/compose/${TARGET}.yml;
$(eval COMPOSE_TARGET:=$(shell echo infra/compose/${TARGET}.yml))
$(eval PREBUILD:=$(shell echo env .env.default .env_${TARGET} .env_${TARGET}.default .lock_env))
$(eval MAIN_ENV:=$(shell echo ${MAKEDIR}.env))
$(eval MAIN_DLT:=$(shell echo ${MAKEDIR}.env.default))
$(eval TRGT_ENV:=$(shell echo ${MAKEDIR}.env_${TARGET}))
$(eval TRGT_DLT:=$(shell echo ${MAKEDIR}.env_${TARGET}.default))
endef

define ENV=
TAG=$${TAG:=${TAG}} BRANCH=${BRANCH} PROJECT_FULLNAME=${FULLNAME}  \
MAKEDIR=${MAKEDIR} PROJECT=${PROJECT} TARGET=$${TARGET:=${TARGET}} \
DOCKER=${DOCKER} DHOST_IP=${DHOST_IP} REALDIR=${REALDIR}           \
REPO=${REPO} MAIN_ENV=${MAIN_ENV} MAIN_DLT=${MAIN_DLT}             \
TRGT_ENV=${TRGT_ENV} TRGT_DLT=${TRGT_DLT} DEBIAN=${DEBIAN}         \
DEBIAN_ESC=${DEBIAN_ESC} PHP=${PHP}
endef

define ENVSUBST

endef

ifneq (${MAIN_DLT},)
ENV+=$(shell cat ${MAIN_DLT} | ${INTERPOLATE_ENV} | ${QUOTE_ENV} | grep -v ^\#)
endif

ifneq (${MAIN_ENV},)
ENV+=$(shell cat ${MAIN_ENV} | ${INTERPOLATE_ENV} | ${QUOTE_ENV} | grep -v ^\#)
endif

ifneq (${TRGT_DLT},)
ENV+=$(shell cat ${TRGT_DLT} | ${INTERPOLATE_ENV} | ${QUOTE_ENV} | grep -v ^\#)
endif

ifneq (${TRGT_ENV},)
ENV+=$(shell cat ${TRGT_ENV} | ${INTERPOLATE_ENV} | ${QUOTE_ENV} | grep -v ^\#)
endif

DCOMPOSE= export ${ENV} && docker-compose -p ${PROJECT}_${TARGET}

DRUN=docker run --rm         \
	-env-file=.env.default   \
	-env-file=.env           \
	-env-file=.env_${TARGET} \
	-env-file=.env_${TARGET}.default \
	-v $$PWD:/app

build b: ${PREBUILD}
	@ echo Building ${FULLNAME}
	export TARGET=base TAG=_latest_local && \
		${DCOMPOSE} -f ${COMPOSE_BASE} build idilic
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} build
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} up --no-start
	@ ${WHILE_IMAGES} \
		docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH \
		| while read IMAGE_NAME; do                                          \
			IMAGE_PREFIX=`echo "$$IMAGE_NAME" | sed -e "s/\:.*\$$//"`;       \
			                                                                 \
			echo "original:$$IMAGE_HASH $$IMAGE_PREFIX":${TAG};              \
			                                                                 \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":latest${SUFFIX};      \
			echo "  latest:$$IMAGE_HASH $$IMAGE_PREFIX":latest${SUFFIX};     \
			                                                                 \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":${HASH}${SUFFIX};     \
			echo "    hash:$$IMAGE_HASH $$IMAGE_PREFIX":${HASH}${SUFFIX};    \
			                                                                 \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":`date '+%Y%m%d'`${SUFFIX};  \
			echo "    date:$$IMAGE_HASH $$IMAGE_PREFIX":`date '+%Y%m%d'`${SUFFIX}; \
		done; \
	done;

test t: ${PREBUILD}
	@ export TARGET=${TARGET} && ${DCOMPOSE} -f ${COMPOSE_TARGET} \
		run --rm ${NO_TTY} ${PASS_ENV}                            \
		idilic SeanMorris/Ids runTests

clean: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} down --remove-orphans
	@ docker volume prune -f;

	@ docker run --rm -v ${MAKEDIR}:/app -w=/app      \
		${DEBIAN} bash -c "                           \
			rm -f infra/docker/*._gen.*;              \
			set -o noglob;                            \
			rm -f .env.default;                       \
			rm -f .env .env_${TARGET} .var;           \
			rm -f .lock_env .env_${TARGET}.default;"

	@ docker run --rm -v ${REALDIR}:/app -w=/app      \
		${DEBIAN} bash -c "                           \
			rm -f infra/docker/*._gen.*;              \
			cat data/global/_schema.json > data/global/schema.json ;"

SEP=
env e:
	@ export ${ENV} && env ${SEP};

start s: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} up -d

start-fg sf: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} up

start-bg sb: ${PREBUILD}
	(${DCOMPOSE} -f ${COMPOSE_TARGET} up &)

stop d: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} down

stop-all da: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} down --remove-orphans

restart r: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} down && ${DCOMPOSE} -f ${COMPOSE_TARGET} up -d

restart-fg rf: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} down && ${DCOMPOSE} -f ${COMPOSE_TARGET} up

restart-bg rb: ${PREBUILD}
	@ (${DCOMPOSE} -f ${COMPOSE_TARGET} down && ${DCOMPOSE} -f ${COMPOSE_TARGET} up &)

kill k: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} kill -s 9

kill-all:
	@ ${WHILE_IMAGES} echo docker kill -9 $$IMAGE_HASH; done;

current-tag ct: ${COMPOSE_TARGET}
	@ echo ${TAG}

current-target ctr: ${COMPOSE_TARGET}
	@ [[ "${TARGET}" != "" ]] || (echo "No target set." && false)
	@ echo ${TARGET}

list-images li:${PREBUILD}
	@ ${WHILE_IMAGES} \
		echo $$(docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH) \
		$$(docker image inspect --format="{{ .Size }}" $$IMAGE_HASH  \
			| awk '{ S=$$1 /1024/1024 ; print S "MB" }' \
		); \
	done;

list-tags lt:
	@ ${WHILE_TAGS} echo $$TAG_NAME; done;done;

push-images psi: ${COMPOSE_TARGET}
	@ echo Pushing ${PROJECT}:${TAG}
	@ ${WHILE_TAGS} \
		docker push $$TAG_NAME; \
	done;done;

pull-images pli: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} pull

hooks: ${COMPOSE_TARGET}
	@ git config core.hooksPath githooks

run: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} run --rm ${NO_TTY} \
		${PASS_ENV} ${CMD}

bash sh: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} run --rm ${NO_TTY} \
		${PASS_ENV} --entrypoint=bash idilic

composer-install ci:
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer install \
		--ignore-platform-reqs `${ISDEV} || echo "--no-dev"`

composer-update cu:
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer update \
		--ignore-platform-reqs `${ISDEV} || echo "--no-dev"`

composer-dump-autoload cda:
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer dump-autoload

node n: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} -f \
		${COMPOSE_TOOLS}/node.yml run --rm ${PASS_ENV} node

PKG=
npm-install ni: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} -f \
		${COMPOSE_TOOLS}/node.yml run --rm ${PASS_ENV} node npm i ${PKG}

dcompose-config dcc: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} config

dcompose dc:
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET}

.lock_env:
	@ [[ "${ENV_LOCK_STATE}" == "${TAG}" ]] || (    \
		${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer install \
			--ignore-platform-reqs                  \
			`${ISDEV} || echo "--no-dev"`;          \
			                                        \
		${DCOMPOSE}                                 \
			-f ${REALDIR}${COMPOSE_TOOLS}/node.yml  \
			run node npm install                    \
	);
	@ test ! -z "${TAG}" && echo ENV_LOCK_STATE=${TAG} > ${ENV_LOCK} || true;

##

stay@%:
	$(eval TARGET=$(shell echo ${@} | cut -c 6-))
	@ >&2 echo Setting current target ${TARGET}...
	echo TARGET=${TARGET} > ${VAR_FILE};
	@ ${NEWTARGET}

@%:
	$(eval TARGET=$(shell echo ${@} | cut -c 2-))
	@ >&2 echo Setting current target ${TARGET}...
	@ ${NEWTARGET}

.env: config/.env
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app \
		${DEBIAN} bash -c '{   \
			FROM=config/.env                     \
			TO=.env                              \
				&& ${STITCH_ENTROPY};            \
		}'

.env.default: config/.env.default
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app \
		${DEBIAN} bash -c '{   \
			FROM=config/.env.default             \
			TO=.env.default                      \
				&& ${STITCH_ENTROPY};            \
		}'

.env_${TARGET}: config/.env_${TARGET}
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app \
		${DEBIAN} bash -c '{   \
			FROM=config/.env_${TARGET}           \
			TO=.env_${TARGET}                    \
				&& ${STITCH_ENTROPY};            \
			FROM=config/.env_${TARGET}.default   \
			TO=.env_${TARGET}.default            \
				&& ${STITCH_ENTROPY};            \
		}'

.env_${TARGET}.default: config/.env_${TARGET}.default
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app \
		${DEBIAN} bash -c '{   \
			FROM=config/.env_${TARGET}           \
			TO=.env_${TARGET}                    \
				&& ${STITCH_ENTROPY};            \
			FROM=config/.env_${TARGET}.default   \
			TO=.env_${TARGET}.default            \
				&& ${STITCH_ENTROPY};            \
		}'

config/.env   config/.env_${TARGET}:
	@ test -f ${@} || touch ${@};

config/.env.default config/.env_${TARGET}.default:
	@ test -f ${@};

infra/compose/%yml:
	@ test -f infra/compose/${TARGET}.yml;

###

babel: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TARGET} -f ${COMPOSE_TOOLS}/node.yml \
		run --rm ${PASS_ENV} node npx babel

run-phar: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TARGET} run --rm  \
		--entrypoint='php SeanMorris_Ids.phar' \
		${PASS_ENV} ${CMD}

dirs:
	@ echo ${MAKEDIR}
	@ echo ${REALDIR}

graylog-start gls: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up -d

graylog-start-fg glsf: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up

graylog-start-bg glsb: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up &

graylog-restart glr: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up -d

graylog-restart-fg glrf: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up

graylog-restart-bg glrb: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up &

graylog-stop gld: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down

graylog-backup glbak:
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml run --rm mongo bash -c \
		'mongodump -h mongo --db graylog --out /settings; ls /; ls /settings'

graylog-restore glres:
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml run --rm mongo bash -c \
		'mongorestore -h mongo --db graylog /settings/graylog'

${MAKEDIR}%._gen.dockerfile: ${MAKEDIR}%.dockerfile.template
${DOCKDIR}%._gen.dockerfile: ${DOCKDIR}%.dockerfile.template
	@ $(eval IN:=$(shell                         \
		echo `dirname ${@}`/`basename ${@}`          \
		| sed -e 's/\._gen\.\(.\+\?\)/.\1.template/' \
	))
# 	$(eval SOURCE:=$(shell printf "%q\n" "$$(ls -al)"))
	$(eval OUT:=$(shell printf %q "`cat ${IN}`" | tr \', \\))
	$(eval OUT:=$(shell printf %q "`cat ${IN}`" | tr \', \\))
	@ echo -e '${OUT}'
# 	$(eval SOURCE:= ' $(shell printf "%q" "$$(cat ${TEMPLATE})") ')
# 	$(info ${SOURCE})
# 	printf "%b" ${SOURCE};

# 	printf "%b" "${SOURCE}"

	false
