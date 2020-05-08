#!make

.PHONY: @% b babel bash build cda ci clean composer-dump-autoload composer-install \
	composer-update composer-update-no-dev ct ctr cu current-tag current-target d  \
	da dcc dcompose-config e entropy-dir_env hooks init it k kill li \
	list-images list-tags lt pli psi pull-images push-images \
	r rb restart restart-bg restart-fg rf run run-phar s sb sf sh start start-bg \
	start-fg stay@% stop stop-all t tag-images test help help-% retarget

.SECONDEXPANSION:

SHELL     =/bin/bash
MAKEFLAGS += --no-builtin-rules --warn-undefined-variables

BASELINUX ?= debian:buster-20191118-slim## %var The BASE base image.
PHP       ?= 7.3

CORERELDIR:=$(dir $(lastword $(MAKEFILE_LIST)))
ROOTRELDIR:=$(dir $(firstword $(MAKEFILE_LIST)))

COREDIR   :=$(dir $(abspath $(lastword $(MAKEFILE_LIST))))
ROOTDIR   :=$(dir $(abspath $(firstword $(MAKEFILE_LIST))))

OUTCOREDIR?=${COREDIR}
OUTROOTDIR?=${ROOTDIR}

MAIN_ENV  :=${ROOTDIR}.env
MAIN_DLT  :=${ROOTDIR}.env.default

ENV_LOCK ?=${ROOTDIR}.lock_env
VAR_FILE ?=${ROOTDIR}.var

-include ${ENV_LOCK}

D_UID ?= $(shell id -u)
D_GID ?= $(shell id -g)
D_USR ?= $(shell whoami)

-include ${MAIN_ENV}
-include ${MAIN_DLT}

-include ${VAR_FILE}

CUT_TARGET=cut -d @ -f 2 | cut -d + -f 1 |  cut -d - -f 1

ifeq ($(filter @%,$(firstword ${MAKECMDGOALS})),)
$(eval TGT_STR= )
ifeq ($(filter stay@%,$(firstword ${MAKECMDGOALS})),)
ifeq (${TARGET},)
$(error Please set a target with @target or stay@target.)
endif
else
$(eval TARGET=$(shell echo ${firstword ${MAKECMDGOALS}} | ${CUT_TARGET} ))
$(eval TGT_STR=$(firstword ${MAKECMDGOALS}))
endif
else
$(eval TARGET=$(shell echo ${firstword ${MAKECMDGOALS}} | ${CUT_TARGET} ))
$(eval TGT_STR=$(firstword ${MAKECMDGOALS}))
endif

# @ ( [[ "${ENV_LOCK_TAG}" != "" ]] || [[ "${ENV_LOCK_TGT_SVC}" != "${TGT_SVC}" ]] ) \

TRGT_ENV :=${ROOTDIR}.env_${TARGET}
TRGT_DLT :=${ROOTDIR}.env_${TARGET}.default

-include ${TRGT_ENV}
-include ${TRGT_DLT}

$(shell >&2 echo -e "\e[1m"Starting with target: \"${TARGET}\" on `hostname` "\e[0m")

DOCKDIR  ?=${COREDIR}infra/docker/
DEBIAN_ESC=$(shell echo ${BASELINUX} | sed 's/\:/__/gi')

# $(shell >&2 echo -e "$(call TEMPLATE_PATTERNS,${1}))")

ENVBUILD =${ROOTDIR}.env         \
	${ROOTDIR}.env.default       \
	${ROOTDIR}.env_$${TARGET}    \
	${ROOTDIR}.env_$${TARGET}.default \

PREBUILD = retarget ${VAR_FILE} ${ENVBUILD}

COMPOSE_BASE   =${COREDIR}infra/compose/base.yml
COMPOSE_TARGET =${COREDIR}infra/compose/${TARGET}.yml
COMPOSE_TOOLS  =${COREDIR}infra/compose/tools

PROJECT  ?=ids
REPO     ?=seanmorris
BRANCH   :=$(shell git rev-parse --abbrev-ref HEAD 2>/dev/null || echo nobranch)
HASH     :=$(shell echo _$$(git rev-parse --short HEAD 2>/dev/null) || echo init)
DESC     :=$(shell git describe --tags 2>/dev/null || echo ${HASH})
SUFFIX   =-${TARGET}$(shell [[ ${PHP} = 7.3  ]] || echo -${PHP})
DBRANCH  :=$(shell [[ ${BRANCH} == "master" ]] || echo -${BRANCH})
TAG      ?=${DESC}${SUFFIX}
FULLNAME ?=${REPO}/${PROJECT}:${TAG}

ENV_LOCK_TAG?=
ENV_LOCK_TGT_SVC?=
TGT_SVC?=

IMAGE    ?=
DHOST_IP :=$(shell docker network inspect bridge --format='{{ (index .IPAM.Config 0).Gateway}}')
NO_TTY   ?=-T

ifneq ($(filter ${TARGET},target dev),)
	NO_DEV=
else
	NO_DEV=--no-dev
endif

DOCKER   :=$(shell which docker)

DEVTARGETS=test dev

ifeq (${ISDEV},)
	ISDEV?=echo "${DEVTARGETS}" | grep -wq "${TARGET}"
endif

define RANDOM_STRING ## %func Generate a random 32 character alphanumeric string.
cat /dev/urandom         \
	| tr -dc 'a-zA-Z0-9' \
	| fold -w 32         \
	| head -n 1
endef

IDS_DATABASES_MAIN_ROOT_PASSWORD:=`${RANDOM_STRING}`

define NPX
cp  -n /app/package-lock.json /build;   \
	cat /app/composer.json              \
		| tr '[:upper:]' '[:lower:]'    \
		| tr '/' '-'                    \
		> package.json;                 \
	npx
endef ## %var Perform some magic for NPM

define INTERPOLATE_ENV
env -i DHOST_IP=${DHOST_IP}             \
	TAG=${TAG} REPO=${REPO}             \
	TARGET=${TARGET} PROJECT=${PROJECT} \
	envsubst
endef

define XDEBUG_ENV
XDEBUG_CONFIG="`\
	test -f ${ROOTDIR}.env_${TARGET}    \
	&& cat ${ROOTDIR}.env_${TARGET}     \
	| ${INTERPOLATE_ENV}                \
	| grep ^XDEBUG_CONFIG_              \
	| while read VAR; do echo $$VAR | { \
		IFS="=" read -r NAME VALUE;    \
		echo -En $$NAME                 \
			| sed 's/^XDEBUG_CONFIG_\(.\+\)/\L\1/'; \
		echo -En "=$$VALUE ";           \
	} done`"
endef

YQ=${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} run --rm \
	--entrypoint yq idilic

PASS_ENV=$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /')

define WHILE_IMAGES ## %frag Loop over images. Provides $$IMAGE_HASH. Finish with "done;"
docker images ${REPO}/${PROJECT}.*:${TAG} -q | while read IMAGE_HASH; do
endef

define WHILE_TAGS ## %frag Loop over tags. Provides $$TAG_NAME and $$IMAGE_HASH. Finish with "done;done;"
${WHILE_IMAGES} \
	docker image inspect --format="{{ index .RepoTags }}" $$IMAGE_HASH \
	| sed 's/[][]//g' \
	| sed 's/\s/\n/g' \
	| while read TAG_NAME; do
endef

define PARSE_ENV ## %frag Loop over environment files. Provides $$ENV_NAME and $$ENV_VALUE. Finish with "done;"
grep -v ^\# \
	| while read -r ENV; do echo $$ENV | { \
		IFS="\=" read -r ENV_NAME ENV_VALUE;
endef


define QUOTE_ENV ## %frag quotes environment vars:
	${PARSE_ENV} echo -n " $$ENV_NAME="; printf %q "$$ENV_VALUE"; }; done
endef

ENTROPY_DIR=/tmp/IDS_ENTROPY

define GET_ENTROPY ## %func Return entropy value for a given key.
test -d "${ENTROPY_DIR}" || mkdir -m 700 -p ${ENTROPY_DIR}; \
test -w "${ENTROPY_DIR}/${1}"                               \
	&& cat ${ENTROPY_DIR}/${1}                              \
	|| ${RANDOM_STRING} | tee ${ENTROPY_DIR}/${1}
endef

define STITCH_ENTROPY ## %func Return entropy value for a given key.
while read -r ENV_LINE; do                     \
	test -z "$$ENV_LINE" && continue;          \
	echo -n "$$ENV_LINE" | ${PARSE_ENV}        \
		echo -n $$ENV_NAME=;                   \
		grep $$ENV_NAME .entropy | {           \
		IFS=":" read -r ENV_KEY ENTROPY_KEY;   \
		test -n "$$ENTROPY_KEY"                \
			&& echo $$(ENTROPY_KEY=$$ENTROPY_KEY  \
				$(call GET_ENTROPY,$$ENTROPY_KEY) \
			)                                  \
			|| echo -E "$$ENV_VALUE";          \
};}; done; done
endef

define UNINCLUDE ## %func Overwrite all values from given environment file.
cat ${1} | grep -v ^\#       \
	| grep "^[A-Z_]\+="      \
	| sed 's/\=.\+$$//'      \
	| while read OLD_VAR; do \
		echo -e "$$OLD_VAR=DELETED"; \
	done;
endef

DCOMPOSE_TARGET_STACK:= -f ${COMPOSE_TARGET}

define NEWTARGET ## %frag Set up environment for new target.
$(eval COMPOSE_TARGET:=$(shell echo ${ROOTRELDIR}infra/compose/${TARGET}.yml))
$(eval MAIN_ENV:=$(shell echo ${ROOTDIR}.env))
$(eval MAIN_DLT:=$(shell echo ${ROOTDIR}.env.default))
$(eval TRGT_ENV:=$(shell echo ${ROOTDIR}.env_${TARGET}))
$(eval TRGT_DLT:=$(shell echo ${ROOTDIR}.env_${TARGET}.default))

$(eval DCOMPOSE_FILES:=)

$(eval DCOMPOSE_FILES+= $(and        \
	$(filter +aptcache,${TGT_SVC}),  \
	-f ${COMPOSE_TOOLS}/aptcache.yml \
))

$(eval DCOMPOSE_FILES+=$(and         \
	$(filter +graylog,${TGT_SVC}),   \
	-f ${COMPOSE_TOOLS}/graylog.yml  \
))

$(eval DCOMPOSE_FILES+=$(and         \
	$(filter +inotify,${TGT_SVC}),   \
	-f ${COMPOSE_TOOLS}/inotify.yml  \
))

$(eval DCOMPOSE_FILES+=$(and         \
	$(filter +make,${TGT_SVC}),      \
	-f ${COMPOSE_TOOLS}/make.yml     \
))

$(eval DCOMPOSE_FILES+=$(and         \
	$(filter +cloc,${TGT_SVC}),      \
	-f ${COMPOSE_TOOLS}/cloc.yml     \
))

$(eval DCOMPOSE_TARGET_STACK:= -f ${COMPOSE_TARGET} ${DCOMPOSE_FILES})
$(eval DCOMPOSE_BASE_STACK  := -f ${COMPOSE_TARGET} -f ${COMPOSE_BASE})
endef

define ENV ## %var List of environment vars to pass to sub commands.
REPO=${REPO} MAIN_ENV=${MAIN_ENV} MAIN_DLT=${MAIN_DLT} D_GID=${D_GID} \
ROOTDIR=${ROOTDIR} PROJECT=${PROJECT} TARGET=$${TARGET:=${TARGET}}    \
TAG=$${TAG:=${TAG}} BRANCH=${BRANCH} PROJECT_FULLNAME=${FULLNAME}     \
OUTROOTDIR=${OUTROOTDIR} OUTCOREDIR=${OUTCOREDIR} D_UID=${D_UID}      \
TRGT_ENV=${TRGT_ENV} TRGT_DLT=${TRGT_DLT} DEBIAN=${BASELINUX}         \
IDS_DB_ROOT_PASSWORD=${IDS_DB_ROOT_PASSWORD}            \
DOCKER=${DOCKER} DHOST_IP=${DHOST_IP} COREDIR=${COREDIR}              \
CORERELDIR=${CORERELDIR} ROOTRELDIR=${ROOTRELDIR}                     \
DEBIAN_ESC=${DEBIAN_ESC} PHP=${PHP}
endef

define EXTRACT_TARGET_SERVICES ## %frag extract the target & optional service configs from args.
$(eval TGT_STR:=$(subst -, -,$(subst +, +,${1})))
$(eval NEW_SVC:=$(wordlist 2, $(words ${TGT_STR}), ${TGT_STR}))
$(eval NEW_INV:=$(subst *,-,$(subst -,+,$(subst +,*,${NEW_SVC}))))
$(eval NEW_ENABLE :=$(filter +%,${NEW_SVC}))
$(eval NEW_DISABLE:=$(filter -%,${NEW_SVC}))
$(eval TGT_SVC:=$(sort ${NEW_ENABLE} ${NEW_DISABLE}))
$(eval REMAINING_SVC:=$(filter-out ${NEW_INV}, ${ENV_LOCK_TGT_SVC:=}))
$(eval TGT_SVC:=$(sort ${NEW_SVC} ${REMAINING_SVC}))
test "${TGT_SVC}" == "${ENV_LOCK_TGT_SVC}" || touch -d 0 "${ENV_LOCK}"
endef

SPACE  :=${""} ${""}
COMMA  :=,
NEWLINE:=\n

define IMPORT_TEMPLATE ## %func Get a multiline return value from the shell.
$(eval ___IMPORT_TEMPLATE:=$(subst #,\#,$(shell printf "%q" "$$(${1})" \
	| sed "s/^$$'//g"     \
	| sed "s/'$$//g"      \
	| sed "s/'/'\\\\''/g" \
)))$$(printf "%b" '${___IMPORT_TEMPLATE}' | sed "s/\\\'/'/g")
endef

define SHELLOUT ## %func Get a multiline return value from the shell.
$(shell echo -E "$$(printf "%q" "$$(${1})")")
endef

define TEMPLATE_SHELL ## %func Get a multiline return value from the shell for a template.
$(eval ___TEMPLATE_SHELL:=$(subst #,\#,$(shell printf "%q" "$$(${1})" \
	| sed "s/^$$'//g"     \
	| sed "s/'$$//g"      \
	| sed "s/'/'\\\\''/g" \
)))${___TEMPLATE_SHELL}
endef

define JOIN ## DELIM,LIST,[ORIGDELIM] Join a list by a delimiter.
$(subst ${3:=${SPACE}},${1},${2})
endef

ifneq (${MAIN_DLT},)
ENV+=$(shell grep -hs ^ ${MAIN_DLT} | ${INTERPOLATE_ENV} | ${QUOTE_ENV} | grep -v ^\#)
endif

ifneq (${MAIN_ENV},)
ENV+=$(shell grep -hs ^ ${MAIN_ENV} | ${INTERPOLATE_ENV} | ${QUOTE_ENV} | grep -v ^\#)
endif

ifneq (${TRGT_DLT},)
ENV+=$(shell grep -hs ^ ${TRGT_DLT} | ${INTERPOLATE_ENV} | ${QUOTE_ENV} | grep -v ^\#)
endif

ifneq (${TRGT_ENV},)
ENV+=$(shell grep -hs ^ ${TRGT_ENV} | ${INTERPOLATE_ENV} | ${QUOTE_ENV} | grep -v ^\#)
endif

DCOMPOSE= export ${ENV} && docker-compose -p ${PROJECT}_${TARGET}

DRUN=docker run --rm         \
	-env-file=${ROOTDIR}.env.default   \
	-env-file=${ROOTDIR}.env           \
	-env-file=${ROOTDIR}.env_${TARGET} \
	-env-file=${ROOTDIR}.env_${TARGET}.default \
	-v ${OUTROOTDIR}:/app

1:=
define TEMPLATE_PATTERNS
test -f ${COREDIR}.templating \
&& (${DCOMPOSE} -f ${COREDIR}infra/compose/tools/yjq.yml run ${PASS_ENV} --rm \
	yjq bash -c 'yq r - -j | jq -r "to_entries[]  | select(.value) \
		| [.key, (.value | to_entries[] | .key, .value)] | @tsv"' \
	) < <(grep -hs ^ ${COREDIR}.templating | sed 's/\t/  /g') \
		| while IFS=$$'\t' read -r PREFIX FIND REPLACE; do \
			test -f "$$PREFIX" && { \
				export GENERATED=`echo $${PREFIX/$$FIND/$$REPLACE}`; \
				(test -z ${1}) \
					&& echo -ne $$PREFIX'\t'; \
				(test -z ${1}) \
					&& echo $$GENERATED; \
				(test "$$GENERATED" == "${1}") \
					&& echo $$PREFIX; \
				(test "-t" == "${1}") \
					&& echo -n "$$PREFIX "; \
				(test "-g" == "${1}") \
					&& echo -n "$$GENERATED "; \
			}; \
			test -d "$$PREFIX" && { \
				find $$PREFIX -name "*$$FIND*" | { \
					while read TEMPLATE; do\
						export GENERATED=`echo $${TEMPLATE/$$FIND/$$REPLACE}`; \
						(test -z "${1}") \
							&& echo -ne $$TEMPLATE'\t'; \
						(test -z "${1}") \
							&& echo $$GENERATED; \
						(test "$$GENERATED" == "${1}") \
							&& echo $$TEMPLATE; \
						(test "-t" == "${1}") \
							&& echo -n "$$TEMPLATE "; \
						(test "-g" == "${1}") \
							&& echo -n "$$GENERATED "; \
					done; \
				}; \
			}; \
	done || true;
endef

define GEN_TO_TEMP ## %func convert a generatable name to a template name.
${TEMPLATES.$(strip ${1})}
endef

TEMPLATES:=$(shell $(call TEMPLATE_PATTERNS,-t))
GENERABLE:=$(shell $(call TEMPLATE_PATTERNS,-g))
TEMPLEN  :=$(words ${GENERABLE})
TEMPINDEX:=$(shell echo {1..${TEMPLEN}})

ifneq (${TEMPLEN},0)
$(foreach I,${TEMPINDEX},$(eval \
	TEMPLATES.$(word ${I},${GENERABLE})=$(word ${I},${TEMPLATES}) \
))
endif

IMAGE?=
build b: ${VAR_FILE} ${ENV_LOCK} ${PREBUILD} ${GENERABLE} ## Build the project.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} build ${IMAGE}
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up --no-start ${IMAGE}
	@ ${WHILE_IMAGES}                           \
		docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH \
		| while read IMAGE_NAME; do                                          \
			IMAGE_PREFIX=`echo "$$IMAGE_NAME" | sed "s/\:.*\$$//"`;          \
			                                                                 \
			echo "original:$$IMAGE_HASH $$IMAGE_PREFIX":${TAG};              \
			                                                                 \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":latest${SUFFIX}${DBRANCH};   \
			echo "  latest:$$IMAGE_HASH $$IMAGE_PREFIX":latest${SUFFIX}${DBRANCH};  \
			                                                                 \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":${HASH}${SUFFIX}${DBRANCH};  \
			echo "    hash:$$IMAGE_HASH $$IMAGE_PREFIX":${HASH}${SUFFIX}${DBRANCH}; \
			                                                                 \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":`date '+%Y%m%d'`${SUFFIX}${DBRANCH};  \
			echo "    date:$$IMAGE_HASH $$IMAGE_PREFIX":`date '+%Y%m%d'`${SUFFIX}${DBRANCH}; \
		done; \
	done;

template-patterns:
	@ $(call TEMPLATE_PATTERNS)

test t: ${ENV_LOCK} ${PREBUILD} ${GENERABLE} ## Run the tests
	@ export TARGET=${TARGET} && ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} \
		run --rm ${NO_TTY} ${PASS_ENV}                            \
		idilic SeanMorris/Ids runTests

clean: ${PREBUILD}## Clean the project. Only applies to files from the current target.
	@- ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down --remove-orphans
	@- docker volume rm -f `sed 's/[^a-z0-9]//g' <<< ${PROJECT}`_${TARGET}_schema;
	@- docker run --rm -v ${OUTROOTDIR}:/app -w=/app   \
		${BASELINUX} bash -c "            \
			set -o noglob;                \
			rm -f ${GENERABLE};           \
			rm -f .var;                   \
			rm -f .lock_env;              \
			rm -f .env_${TARGET}.default; \
			rm -f .env_${TARGET};         \
			rm -f .env.default            \
			rm -f .env"

	@- docker run --rm -v ${OUTCOREDIR}:/app -w=/app \
		${BASELINUX} bash -c "                    \
			rm -f ${GENERABLE};                   \
			cat data/global/_schema.json > data/global/schema.json ;"

SEP=
env e: ## Export the environment as seen from MAKE.
	@ export ${ENV} && env ${SEP};

start s: ${ENV_LOCK} ${PREBUILD} ${GENERABLE} ## Start the project services.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up -d

start-fg sf: ${ENV_LOCK} ${PREBUILD} ${GENERABLE} ## Start the project services in the foreground.
	${DCOMPOSE} -f ${COMPOSE_TARGET} up

start-bg sb: ${ENV_LOCK} ${PREBUILD} ${GENERABLE} ## Start the project services in the background, streaming output to terminal.
	(${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up &)

stop d: ${ENV_LOCK} ${PREBUILD} ${GENERABLE} ## Stop the current project services on the current target.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down

stop-all da: ${ENV_LOCK} ${PREBUILD} ${GENERABLE} ## Stop the all project services on the current target. including orphans.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down --remove-orphans

restart r: ${ENV_LOCK} ${PREBUILD} ${GENERABLE} ## Restart the project services in the foreground.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} restart ${IMAGE}

restart-fg rf: ${ENV_LOCK} ${PREBUILD} ${GENERABLE} ## Start the project services in the foreground.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down && ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up

restart-bg rb: ${ENV_LOCK} ${PREBUILD} ${GENERABLE}## Start the project services in the background, streaming output to terminal.
	@ (${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down && ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up &)

kill k: ${ENV_LOCK} ${PREBUILD} ${GENERABLE}## Kill current project services.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} kill -s 9

kill-all: ${ENV_LOCK} ${GENERABLE}## Kill all project services.
	@ ${WHILE_IMAGES} echo docker kill -9 $$IMAGE_HASH; done;

current-tag ct: ## Get the current project tag.
	@ echo ${TAG}

current-target ctr: ${COMPOSE_TARGET} ${ENV_LOCK} ## Get the current target.
	@ [[ "${TARGET}" != "" ]] || (echo "No target set." && false)
	@ echo ${TARGET}

list-images li:${ENV_LOCK} ${PREBUILD}## List available images from current target.
	${WHILE_IMAGES} \
		echo $$(docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH) \
		$$(docker image inspect --format="{{ .Size }}" $$IMAGE_HASH  \
			| awk '{ S=$$1 /1024/1024 ; print S "MB" }' \
		); \
	done;

list-tags lt: ## List the images tagged from the current target.
	@ ${WHILE_TAGS} echo $$TAG_NAME; done;done;

push-images psi: ${ENV_LOCK} ## Push locally built images.
	@ echo Pushing ${PROJECT}:${TAG}
	@ ${WHILE_TAGS} \
		docker push $$TAG_NAME; \
	done;done;

pull-images pli: ${ENV_LOCK} ${PREBUILD} ## Pull remotely hosted images.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} pull

hooks: ${COMPOSE_TARGET} ## Register git hootks for development.
	@ git config core.hooksPath githooks

run: ${PREBUILD} ${GENERABLE}## CMD 'SERVICE COMMAND' Run a command in a given service's container.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} run --rm ${NO_TTY} \
		 ${PASS_ENV} ${CMD}

bash sh: ${PREBUILD} ${GENERABLE}## Get a bash propmpt to an idilic container.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} run --rm ${NO_TTY} \
		${PASS_ENV} --entrypoint=bash idilic

composer-install ci: ## Run composer install. Will download composer docker image if not available..
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer install \
		--ignore-platform-reqs `${ISDEV} || echo "--no-dev"`

composer-update cu: ## Run composer update. Will download docker image if not available.
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer update \
		--ignore-platform-reqs `${ISDEV} || echo "--no-dev"`

composer-dump-autoload cda:## Run composer dump-autoload. Will download composer docker image if not available..
	@ ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer dump-autoload

dcompose-config dcc: ${PREBUILD} ${GENERABLE}## Print the current docker-compose configuration.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} config

dcompose-version dcv: ${PREBUILD} ${GENERABLE}## Print the current docker-compose version.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} version

${ENV_LOCK}: ${VAR_FILE} ### Lock the environment target
	@ (test "${ENV_LOCK_TAG:=}" != "${TAG}"           \
	|| test "${ENV_LOCK_TGT_SVC:=}" != "${TGT_SVC}" \
	) && {                                          \
		echo -e >&2 "\e[2m"Env changed, need to check dependencies..."\e[0m"; \
		 ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
			composer install --ignore-platform-reqs \
			`${ISDEV} || echo "--no-dev"`;          \
	} || true;

	@ ( test "${ENV_LOCK_TAG:=}" != "${TAG}"        \
		|| test "${ENV_LOCK_TGT_SVC:=}" != "${TGT_SVC}" ) \
	&& {                                            \
		>&2 echo "Locking env...";                  \
		echo ENV_LOCK_TGT_SVC=${TGT_SVC:=} > ${ENV_LOCK}; \
		echo ENV_LOCK_TAG=${TAG:=} >> ${ENV_LOCK};  \
		echo ENV_LOCK_TIME=`date` >> ${ENV_LOCK};   \
		touch -d 0 ${ENV_LOCK};                     \
		$(eval ENV_LOCK_TGT_SVC:=${TGT_SVC:=})      \
		$(eval ENV_LOCK_TAG:=${TAG:=})              \
	} || true;

stay@%: ${VAR_FILE} ### Set the current target and persist for later invocations.
	@ >&2 echo Setting persistent target ${TARGET}...
	@ echo TARGET=${TARGET} > ${VAR_FILE}

@%: ${VAR_FILE}
	@ >&2 echo -n

${VAR_FILE}: retarget
	@ >&2 echo -n
# 	@ touch ${VAR_FILE}

retarget: ### Set the current target for one invocation.
	@- $(eval ORIG?=${TARGET})
	@- $(eval TGT_STR?=${TGT_STR})
	@ $(call EXTRACT_TARGET_SERVICES, ${TGT_STR})
	@ [[ "${ORIG}" == "${TARGET}" ]] \
		|| >&2 echo Setting target ${ORIG}...
	@ test -z "${TGT_STR}" \
		|| >&2 echo Setting services for ${TGT_STR}...
	@ ${NEWTARGET}

${ROOTDIR}config/.env:
	@ test -f ${@} || touch -d 0 ${@}

${ROOTDIR}config/.env%:
	@ test -f ${@} || touch -d 0 ${@}

infra/compose/%yml: ${ROOTDIR}.env ${ROOTDIR}.env.default ${ROOTDIR}.env_$${TARGET} ${COREDIR}.env_$${TARGET}.default ${ENV_LOCK}
	@ test -f infra/compose/${TARGET}.yml;

help: help-all ## print this message
	@ echo "Help for SeanMorris/Ids v0.0.0"

help-%:
	@ $(eval HELPTYPE:= echo ${@} | sed 's/.+-//' )
	@ $(foreach MKFL,${MAKEFILE_LIST},  \
		cat "${MKFL}" | grep '\:.*\#\#' | grep -v '###' \
		| while read -r LINE; do        \
			NAME=`echo $$LINE | sed -r 's/[:= ].*//'`;  \
			DESC=`echo $$LINE | sed -r 's/.*\#\#//'`;   \
			TYPE=`echo $$DESC \
				| grep '%'    \
				| sed -r 's/.*(%[a-z]*).*/\1/;'`;       \
			DESC=`echo $$DESC | sed -r 's/^%[a-z]*//'`; \
			[[ -z "$$TYPE" ]] || continue;              \
			echo -e "$$NAME: $$DESC";                   \
		done | column -ts:;                             \
	)
	@ echo SeanMorris/Ids/Makefile generated its own helpfile @`date`

.SECONDEXPANSION:

templates: ${GENERABLE}

${GENERABLE}: $$(call GEN_TO_TEMP,$${@}) ${ENV_LOCK} ${ENVBUILD}
	@ echo -e >&2 "\e[2m"Rebuilding template `basename ${@}`"\e[0m";
	@ test -w ${@} || test -w `dirname ${@}`;
	@ export TEMPLATE_SOURCE="$(call IMPORT_TEMPLATE,cat ${<})" \
	&& echo -en "$$TEMPLATE_SOURCE" >${@}

# 	export TEMPLATE_SOURCE="$(call SHELLOUT,cat ${<})" \
# 	@ eval printf "%q" "$(call SHELLOUT,cat ${<}) >${@}";\
# 	@ printf "%q" '$(call SHELLOUT,cat ${<})' >${@};

${ROOTDIR}.env: ${ROOTDIR}config/.env
	@ export ${ENV} \
	&& export ENV_SOURCE="$(call SHELLOUT,cat ${ROOTDIR}config/.env)" \
	&& {                                                \
		test "$${ENV_SOURCE:0:1}" == '$$'               \
		&& eval echo -en $$ENV_SOURCE                   \
		|| eval eval echo -en $$"$$ENV_SOURCE";         \
		echo -en "\n";                                  \
	} | docker run --rm -iv ${OUTROOTDIR}:/app -w=/app  \
		${BASELINUX} bash -c '${STITCH_ENTROPY}' > ${@} \


${ROOTDIR}.env%: ${ROOTDIR}config/.env$${*}
	@ export ${ENV} \
	&& export ENV_SOURCE="$(call SHELLOUT,cat ${ROOTDIR}config/.env${*})" \
	&& {                                                \
		test "$${ENV_SOURCE:0:1}" == '$$'               \
		&& eval echo -en $$ENV_SOURCE                   \
		|| eval eval echo -en $$"$$ENV_SOURCE";         \
		echo -en "\n";                                  \
	} | docker run --rm -iv ${OUTROOTDIR}:/app -w=/app  \
		${BASELINUX} bash -c '${STITCH_ENTROPY}' > ${@} \

###

graylog-start gls: ${PREBUILD} ${GENERABLE}### Start graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up -d

graylog-start-fg glsf: ${PREBUILD} ${GENERABLE}### start-fg for graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up

graylog-start-bg glsb: ${PREBUILD} ${GENERABLE}### start-bg for graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up &

graylog-restart glr: ${PREBUILD} ${GENERABLE}### Restart graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up -d

graylog-restart-fg glrf: ${PREBUILD} ${GENERABLE}### restart-fg for graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up

graylog-restart-bg glrb: ${PREBUILD} ${GENERABLE}### restart-bg for graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up &

graylog-stop gld: ${PREBUILD} ${GENERABLE}### Stop graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down

graylog-backup glbak: ${GENERABLE}### Backup graylog config to files.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml run --rm mongo bash -c \
		'mongodump -h mongo --db graylog --out /settings; ls /; ls /settings'

graylog-restore glres: ${GENERABLE}### Restore graylog config from files.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml run --rm mongo bash -c \
		'mongorestore -h mongo --db graylog /settings/graylog'

inotify-start: ${PREBUILD} ${GENERABLE}### Start inotify.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/inotify.yml up

inotify-stop: ${PREBUILD} ${GENERABLE}### Stop inotify.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/inotify.yml down

inotify-build: ${PREBUILD} ${GENERABLE}### Stop inotify.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/inotify.yml build

aptcache-start: ${PREBUILD} ${GENERABLE}### Start apt-cache.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/aptcache.yml up

aptcache-stop: ${PREBUILD} ${GENERABLE}### Stop apt-cache.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/aptcache.yml down

aptcache-build: ${PREBUILD} ${GENERABLE}### Stop apt-cache.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/aptcache.yml build

###

post-coverage:
	echo -e "$(call IMPORT_TEMPLATE,bash <(curl -s https://codecov.io/bash) -t ${CODECOV_TOKEN} -f /tmp/coverage-report.json)"

dir:
	@ echo ${ROOTRELDIR}
	@ ls -al ${CORERELDIR}infra/xdebug/30-xdebug-cli.ini
