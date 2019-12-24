#make!make

.PHONY: @% b babel bash build cda ci clean composer-dump-autoload composer-install \
	composer-update composer-update-no-dev ct ctr cu current-tag current-target d  \
	da dcc dcompose-config e entropy-dir_env _env..env% hooks init it k kill li \
	list-images list-tags lt n ni node npm-install pli psi pull-images push-images \
	r rb restart restart-bg restart-fg rf run run-phar s sb sf sh start start-bg \
	start-fg stay@% stop stop-all t tag-images test

MAKEFLAGS += --no-builtin-rules --always-make

SHELL    =/bin/bash
REALDIR  :=$(dir $(abspath $(lastword $(MAKEFILE_LIST))))
MAKEDIR  ?=${REALDIR}
VAR_FILE ?=${MAKEDIR}.var

MAIN_DLT ?=${MAKEDIR}.env.default
TRGT_DLT ?=${MAKEDIR}.env.default$(if ${TARGET},${TARGET},base)

MAIN_ENV ?=${MAKEDIR}.env
TRGT_ENV ?=${MAKEDIR}.env.$(if ${TARGET},${TARGET},base)

ENV_LOCK ?=${MAKEDIR}.lock_env

-include ${MAIN_ENV}
-include ${TRGT_ENV}
-include ${VAR_FILE}
-include ${ENV_LOCK}

COMPOSE_TARGET =infra/compose/$(if ${TARGET},${TARGET},base).yml
COMPOSE_TOOLS=infra/compose/tools

PROJECT  ?=ids
REPO     ?=seanmorris
BRANCH   :=$(shell git rev-parse --abbrev-ref HEAD 2>/dev/null || echo nobranch)
HASH     :=$(shell echo _$$(git rev-parse --short HEAD 2>/dev/null) || echo init)
DESC     :=$(shell git describe --tags 2>/dev/null || echo ${HASH})
SUFFIX   =-$(if ${TARGET},${TARGET},base)$$([ ${BRANCH} = master ] && echo "" || echo "-${BRANCH}")
TAG      ?=${DESC}${SUFFIX}
FULLNAME ?=${REPO}/${PROJECT}:${TAG}

IMAGE    ?=
DHOST_IP :=$(shell docker network inspect bridge --format='{{ (index .IPAM.Config 0).Gateway}}')
NO_TTY   ?=-T

ifneq ($(filter ${TARGET},"target dev"),)
	NO_DEV=
else
	NO_DEV=--no-dev
endif

DOCKER   :=$$(which docker)

DEVTARGETS=test dev
ISDEV     =echo "${DEVTARGETS}" | grep -wq "${TARGET}"

define NPX
	cp  -n /app/package-lock.json /build; \
		cat /app/composer.json            \
			| tr '[:upper:]' '[:lower:]'  \
			| tr '/' '-'                  \
			> package.json;               \
		npx
endef

define INTERPOLATE_ENV
	env -i DHOST_IP=${DHOST_IP} \
		TAG=${TAG} REPO=${REPO} TARGET=${TARGET} \
		PROJECT=${PROJECT} \
		envsubst
endef

define XDEBUG_ENV
	XDEBUG_CONFIG="`\
		test -f ${MAKEDIR}.env \
		&& cat ${MAKEDIR}.env.$(if ${TARGET},${TARGET},'base') \
		| ${INTERPOLATE_ENV} \
		| grep ^XDEBUG_CONFIG_ \
		| while read VAR; do echo $$VAR | { \
			IFS='\=' read -r NAME VALUE; \
			echo -En $$NAME \
				| sed -e 's/^XDEBUG_CONFIG_\(.\+\)/\L\1/'; \
			echo -En "=$$VALUE ";\
		} done`"
endef

PASS_ENV=$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /')

define WHILE_IMAGES
	docker images ${REPO}/${PROJECT}.*:${TAG} -q \
	| while read IMAGE_HASH; do
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

ENTROPY_DIR?=/tmp/IDS_ENTROPY
ENTROPY_KEY=default

define GET_ENTROPY
test -e ${ENTROPY_DIR}/$$ENTROPY_KEY \
	&& cat ${ENTROPY_DIR}/$$ENTROPY_KEY \
	|| cat /dev/urandom \
		| tr -dc 'a-zA-Z0-9' \
		| fold -w 32 \
		| head -n 1 \
		| tee ${ENTROPY_DIR}/$$ENTROPY_KEY
endef

define STITCH_ENTROPY
	test -f $$TO && test -s $$TO || while read -r ENV_LINE; do \
		test -n "$$ENV_LINE" || continue; \
		echo -n "$$ENV_LINE" | ${PARSE_ENV} \
			grep $$ENV_NAME .entropy | { \
			IFS=":" read -r ENV_KEY ENTROPY_KEY; \
			echo -n $$ENV_NAME=; \
			test -n "$$ENTROPY_KEY" \
				&& echo $$(ENTROPY_KEY=$$ENTROPY_KEY && ${GET_ENTROPY}) \
				|| echo -E $$ENV_VALUE; \
	};}; done; done < $$FROM > $$TO
endef

define UNINCLUDE
	cat ${1} | grep -v ^\# \
		| grep "^[A-Z_]\+=" \
		| sed -e 's/\=.\+$$//' \
		| while read OLD_VAR; do \
			echo -e "$$OLD_VAR=DELETED"; \
		done;
endef

define NEWTARGET:
	NEWTARGET=`echo ${@} | cut -c 3-`; \
	test -f infra/compose/$$NEWTARGET.yml || (\
		echo "No yml for target '$$NEWTARGET' found in config/ dir." \
		&& false \
	) && echo Using target $$NEWTARGET...

	$(eval COMPOSE_TARGET=infra/compose/${TARGET}.yml)

	$(foreach SETTING, $(shell $(call UNINCLUDE,${MAIN_DLT})), $(eval ${SETTING}))
	$(foreach SETTING, $(shell $(call UNINCLUDE,${TRGT_DLT})), $(eval ${SETTING}))
	$(foreach SETTING, $(shell $(call UNINCLUDE,${MAIN_ENV})), $(eval ${SETTING}))
	$(foreach SETTING, $(shell $(call UNINCLUDE,${TRGT_ENV})), $(eval ${SETTING}))

	$(eval MAIN_ENV =${MAKEDIR}.env)
	$(eval TRGT_ENV =${MAKEDIR}.env.${TARGET})
	$(eval MAIN_DLT =${MAKEDIR}.env.default)
	$(eval TRGT_DLT =${MAKEDIR}.env.default.${TARGET})

	$(eval -include ${MAIN_ENV})
	$(eval -include ${TRGT_ENV})
	$(eval -include ${MAIN_DLT}).default
	$(eval -include ${TRGT_DLT}).default
endef

ENV=TAG=$${TAG:-${TAG}} REPO=${REPO} BRANCH=${BRANCH} DHOST_IP=${DHOST_IP}  \
	PROJECT=${PROJECT} TARGET=${TARGET} MAKEDIR=${MAKEDIR} DOCKER=${DOCKER} \
	${XDEBUG_ENV} NPX="${NPX}" MAIN_ENV=${MAIN_ENV} TRGT_ENV=${TRGT_ENV}    \
	MAIN_DLT=${MAIN_DLT} TRGT_DLT=${TRGT_DLT} REALDIR=${REALDIR}            \
	PROJECT_FULLNAME=${FULLNAME}                                            \
	$$(cat ${MAKEDIR}.env 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#)   \
	$$(cat ${MAKEDIR}.env.default 2>/dev/null                               \
		| ${INTERPOLATE_ENV} | grep -v ^\#)                                 \
	$$(cat ${MAKEDIR}.env.default.$(if ${TARGET},${TARGET},base)            \
		| ${INTERPOLATE_ENV} | grep -v ^\#)                                 \
	$$(cat ${MAKEDIR}.env.$(if ${TARGET},${TARGET},base)                    \
		| ${INTERPOLATE_ENV} | grep -v ^\#)                                 \
	$$(cat ${MAKEDIR}.env.$(if ${TARGET},${TARGET},base)                    \
		| ${INTERPOLATE_ENV} | grep -v ^\#)

DCOMPOSE=export ${ENV} && docker-compose -p ${PROJECT}_${TARGET}

DRUN=docker run --rm \
	-env-file=.env \
	-env-file=.env.${TARGET} \
	-v $$PWD:/app

PREBUILD= .env .lock_env

build b: ${PREBUILD}
	@ echo Building ${FULLNAME}
	@ chmod ug+s . && umask 770
	@ export TAG=latest-${TARGET} && ${DCOMPOSE} -f ${COMPOSE_TARGET} build idilic
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} build --parallel
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} up --no-start
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

test t: ${PREBUILD}
	@ export TARGET=${TARGET} && ${DCOMPOSE} -f ${COMPOSE_TARGET} \
		run --rm ${NO_TTY} ${PASS_ENV} \
		idilic -vv SeanMorris/Ids runTests

clean:
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app          \
		debian:buster-20191118-slim bash -c "           \
			rm -f .env .env.${TARGET} .var;             \
			rm -f .env.default .env.default.${TARGET};  \
			rm -rf  .lock_env vendor/;                  \
		"
	docker volume prune -a;

SEP=
env e:
	@ export ${ENV} && env ${SEP};

start s: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} up -d

start-fg sf: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} up

start-bg sb: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} up &

stop d: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} down

stop-all da: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} down --remove-orphans

restart r: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} down && ${DCOMPOSE} -f ${COMPOSE_TARGET} up -d

restart-fg rf: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} down && ${DCOMPOSE} -f ${COMPOSE_TARGET} up

restart-bg rb: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} down && ${DCOMPOSE} -f ${COMPOSE_TARGET} up &

kill k: ${PREBUILD}
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} kill -s 9

kill-all:
	@ ${WHILE_IMAGES} echo docker kill -9 $$IMAGE_HASH; done;

current-tag ct:
	@ echo ${TAG}

current-target ctr:
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
	${DCOMPOSE} -f ${COMPOSE_TARGET} pull

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
		`${ISDEV} || echo "--no-dev"`

composer-update cu:
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer update \
		`${ISDEV} || echo "--no-dev"`

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
	${DCOMPOSE} -f ${COMPOSE_TARGET} config

dcompose dc: env
	${DCOMPOSE} -f ${COMPOSE_TARGET}

.lock_env:
	$(shell)
	[[ "${ENV_LOCK_STATE}" == "${TAG}" ]] || ( \
		${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer install \
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
	@ echo TARGET=${TARGET} > ${VAR_FILE};
	@ echo Setting persistent target ${TARGET}...
	${NEWTARGET}

@%:
	$(eval TARGET=$(shell echo ${@} | cut -c 2-))
	@ echo Setting current target ${TARGET}...
	${NEWTARGET}

.env:
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app \
		debian:buster-20191118-slim bash -c '{\
			mkdir -p ${ENTROPY_DIR} && chmod 770 ${ENTROPY_DIR}; \
			FILE=.`basename ${@} | cut -c 2-`;                   \
			                                                     \
			FROM=config/$$FILE.default TO=$$FILE.default         \
				&& ${STITCH_ENTROPY};                            \
			                                                     \
			FROM=config/$$FILE TO=$$FILE && ${STITCH_ENTROPY};   \
			                                                     \
			TARGET=$(if ${TARGET},${TARGET},base);               \
			FROM=config/$$FILE.$$TARGET TO=$$FILE.$$TARGET       \
				&& ${STITCH_ENTROPY};                            \
			                                                     \
			FROM=config/$$FILE.default.$$TARGET                  \
			TO=$$FILE.default.$$TARGET                           \
				&& ${STITCH_ENTROPY};                            \
			(shopt -s nullglob; rm -rf ${ENTROPY_DIR});          \
		}'

infra/compose/%yml:
	@ test -z "${TARGET}" || test -f infra/compose/${TARGET}.yml;

###

babel: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TARGET} -f ${COMPOSE_TOOLS}/node.yml \
		run --rm ${PASS_ENV} node npx babel

run-phar: ${PREBUILD}
	${DCOMPOSE} -f ${COMPOSE_TARGET} run --rm \
		--entrypoint='php SeanMorris_Ids.phar' \
		${PASS_ENV} ${CMD}

dirs:
	@ echo ${MAKEDIR}
	@ echo ${REALDIR}
