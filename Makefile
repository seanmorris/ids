#!make

.PHONY: @% b babel bash build cda ci clean composer-dump-autoload composer-install \
	composer-update composer-update-no-dev ct ctr cu current-tag current-target d  \
	da dcc dcompose-config e entropy-dir_env hooks init it k kill li \
	list-images list-tags lt pli psi pull-images push-images \
	r rb restart restart-bg restart-fg rf run run-phar s sb sf sh start start-bg \
	start-fg stay@% stop stop-all t tag-images test .lock_env help help-% \
	localbase

.SECONDEXPANSION:

SHELL     =/bin/bash
MAKEFLAGS += --no-builtin-rules --warn-undefined-variables

BASELINUX ?= debian:buster-20191118-slim## %var The BASE base image.
PHP       ?= 7.3
REALDIR   :=$(dir $(abspath $(lastword $(MAKEFILE_LIST))))
MAKEDIR   ?=${REALDIR}
VAR_FILE  ?=${MAKEDIR}.var

MAIN_ENV  ?=${MAKEDIR}.env
MAIN_DLT  ?=${MAKEDIR}.env.default

-include ${MAIN_ENV}
-include ${VAR_FILE}

CUT_TARGET=cut -d @ -f 2 | cut -d + -f 1 |  cut -d - -f 1

ifeq ($(filter @%,$(firstword ${MAKECMDGOALS})),)
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

TRGT_ENV =${MAKEDIR}.env_${TARGET}
TRGT_DLT =${MAKEDIR}.env_${TARGET}.default

-include ${TRGT_ENV}
-include ${TRGT_DLT}

$(shell >&2 echo Starting with target: \"${TARGET}\")

DOCKDIR  ?=${REALDIR}infra/docker/
DEBIAN_ESC=$(shell echo ${BASELINUX} | sed 's/\:/__/gi')

GEN_EXT='___gen'
TMP_EXT='idstmp'

define TEMP_TO_GEN ## %func convert a template name to a generatable name.
$(shell                                             \
	echo `dirname ${1}`/`basename ${1}`             \
	| sed -r 's/\.${TMP_EXT}\.(.*)/.${GEN_EXT}.\1/gi' \
)
endef

define GEN_TO_TEMP ## %func convert a generatable name to a template name.
$(shell                                             \
	echo `dirname ${1}`/`basename ${1}`             \
	| sed -r 's/\.${GEN_EXT}\.(.*)/.${TMP_EXT}.\1/' \
)
endef

TEMPLATES:=$(shell find | grep .${TMP_EXT}\..*$$)## %var List of available templates.
GENERABLE:=$(foreach TEMPLATE,${TEMPLATES},$(call TEMP_TO_GEN,${TEMPLATE}))## %var List of prospective generatables..

ENVBUILD =.env         \
	.env.default       \
	.env_$${TARGET}    \
	.env_$${TARGET}.default \
	.lock_env          \

PREBUILD = retarget ${ENVBUILD} ${GENERABLE}

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

TAG      ?=${DESC}${SUFFIX}$(shell [[ ${BRANCH} == "master" ]] || echo -${BRANCH})
FULLNAME ?=${REPO}/${PROJECT}:${TAG}

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
ISDEV     =echo "${DEVTARGETS}" | grep -wq "${TARGET}"

define RANDOM_STRING ## %func Generate a random 32 character alphanumeric string.
cat /dev/urandom         \
	| tr -dc 'a-zA-Z0-9' \
	| fold -w 32         \
	| head -n 1
endef

define NPX
cp  -n /app/package-lock.json /build;  \
	cat /app/composer.json               \
		| tr '[:upper:]' '[:lower:]'       \
		| tr '/' '-'                       \
		> package.json;                    \
	npx
endef ## %var Perform some magic for NPM

define INTERPOLATE_ENV
env -i DHOST_IP=${DHOST_IP}            \
	TAG=${TAG} REPO=${REPO}              \
	TARGET=${TARGET} PROJECT=${PROJECT}  \
	envsubst
endef

define XDEBUG_ENV
XDEBUG_CONFIG="`\
	test -f ${MAKEDIR}.env_${TARGET}    \
	&& cat ${MAKEDIR}.env_${TARGET}     \
	| ${INTERPOLATE_ENV}                \
	| grep ^XDEBUG_CONFIG_              \
	| while read VAR; do echo $$VAR | { \
		IFS="="" read -r NAME VALUE;      \
		echo -En $$NAME                   \
			| sed 's/^XDEBUG_CONFIG_\(.\+\)/\L\1/'; \
		echo -En "=$$VALUE ";             \
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
		IFS='\=' read -r ENV_NAME ENV_VALUE;
endef

define QUOTE_ENV ## %frag quotes environment vara:
	${PARSE_ENV} echo -n " $$ENV_NAME="; printf %q "$$ENV_VALUE"; }; done
endef

define GET_ENTROPY ## %func Return entropy value for a given key.
test -w "${ENTROPY_DIR}/${1}"  \
	&& cat ${ENTROPY_DIR}/${1} \
	|| ${RANDOM_STRING} | tee ${ENTROPY_DIR}/${1}
endef

define STITCH_ENTROPY ## %func Return entropy value for a given key.
test -d "${ENTROPY_DIR}"                   \
	|| mkdir -m 700 -p ${ENTROPY_DIR};       \
while read -r ENV_LINE; do                 \
	echo -n "$$ENV_LINE" | ${PARSE_ENV}      \
		echo -n $$ENV_NAME=;                   \
		grep $$ENV_NAME .entropy | {           \
		IFS=":" read -r ENV_KEY ENTROPY_KEY;   \
		test -n "$$ENTROPY_KEY"                \
			&& echo $$(ENTROPY_KEY=$$ENTROPY_KEY \
				$(call GET_ENTROPY,$$ENTROPY_KEY)  \
			)                                    \
			|| echo -E $$ENV_VALUE;              \
};}; done; done < $$FROM > $$TO
endef

define UNINCLUDE ## %func Overwrite all values from given environment file.
cat ${1} | grep -v ^\#     \
	| grep "^[A-Z_]\+="      \
	| sed 's/\=.\+$$//'      \
	| while read OLD_VAR; do \
		echo -e "$$OLD_VAR=DELETED"; \
	done;
endef

define NEWTARGET ## %frag Set up environment for new target.
test -f infra/compose/${TARGET}.yml;
$(eval COMPOSE_TARGET:=$(shell echo infra/compose/${TARGET}.yml))
$(eval PREBUILD:=$(shell echo env .env.default .env_${TARGET} .env_${TARGET}.default .lock_env))
$(eval MAIN_ENV:=$(shell echo ${MAKEDIR}.env))
$(eval MAIN_DLT:=$(shell echo ${MAKEDIR}.env.default))
$(eval TRGT_ENV:=$(shell echo ${MAKEDIR}.env_${TARGET}))
$(eval TRGT_DLT:=$(shell echo ${MAKEDIR}.env_${TARGET}.default))

$(eval DCOMPOSE_FILES:=)

$(eval DCOMPOSE_FILES+= $(and      \
	$(filter +aptcache,${TGT_SVC}),  \
	-f ${COMPOSE_TOOLS}/aptcache.yml \
))

$(eval DCOMPOSE_FILES+=$(and       \
	$(filter +graylog,${TGT_SVC}),   \
	-f ${COMPOSE_TOOLS}/graylog.yml  \
))

$(eval DCOMPOSE_FILES+=$(and       \
	$(filter +inotify,${TGT_SVC}),   \
	-f ${COMPOSE_TOOLS}/inotify.yml  \
))

$(eval DCOMPOSE_TARGET_STACK:= -f ${COMPOSE_TARGET} ${DCOMPOSE_FILES})
$(eval DCOMPOSE_BASE_STACK  := -f ${COMPOSE_TARGET} -f ${COMPOSE_BASE})
endef

define ENV ## %var List of environment vars to pass to sub commands.
TAG=$${TAG:=${TAG}} BRANCH=${BRANCH} PROJECT_FULLNAME=${FULLNAME}  \
MAKEDIR=${MAKEDIR} PROJECT=${PROJECT} TARGET=$${TARGET:=${TARGET}} \
DOCKER=${DOCKER} DHOST_IP=${DHOST_IP} REALDIR=${REALDIR}           \
DEBIAN_ESC=${DEBIAN_ESC} PHP=${PHP} LOCALBASE=${LOCALBASE}         \
TRGT_ENV=${TRGT_ENV} TRGT_DLT=${TRGT_DLT} DEBIAN=${BASELINUX}      \
REPO=${REPO} MAIN_ENV=${MAIN_ENV} MAIN_DLT=${MAIN_DLT}
endef

define EXTRACT_TARGET_SERVICES ## %frag extract the target & optional service configs from args.
$(eval TGT_STR:=$(subst -, -,$(subst +, +,${1})))
$(eval TARGET=$(lastword $(subst @, ,$(firstword ${TGT_STR}))))
$(eval NEW_SVC:=$(wordlist 2, $(words ${TGT_STR}), ${TGT_STR}))
$(eval NEW_INV:=$(subst *,-,$(subst -,+,$(subst +,*,${NEW_SVC}))))
$(eval NEW_ENABLE :=$(filter +%,${NEW_SVC}))
$(eval NEW_DISABLE:=$(filter -%,${NEW_SVC}))
$(eval TGT_SVC:=$(sort ${NEW_ENABLE} ${NEW_DISABLE}))
$(eval REMAINING_SVC:=$(filter-out ${NEW_INV}, ${ENV_LOCK_TGT_SVC}))
$(eval TGT_SVC:=$(sort ${NEW_SVC} ${REMAINING_SVC}))
endef

define SHELLOUT ## %func Get a multiline return value from the shell.
$(eval ___SHELLOUT_RETURN:=$(subst #,\#,$(shell printf "%q" "$$(${1})" \
	| sed "s/^$$'//g"     \
	| sed "s/'$$//g"      \
	| sed "s/'/'\\\\''/g" \
)))$$(printf "%b" '${___SHELLOUT_RETURN}' | sed "s/\\\'/'/g")
endef

SPACE  :=${} ${}
COMMA  :=,
NEWLINE:=\n

define JOIN ## DELIM,LIST,[ORIGDELIM] Join a list by a delimiter.
$(subst ${3:=${SPACE}},${1},${2})
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

DRUN=docker run --rm       \
	-env-file=.env.default   \
	-env-file=.env           \
	-env-file=.env_${TARGET} \
	-env-file=.env_${TARGET}.default \
	-v $$PWD:/app

build b: retarget .lock_env templates localbase ${PREBUILD} ## Build the project.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} build
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up --no-start
	@ ${WHILE_IMAGES}                           \
		docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH \
		| while read IMAGE_NAME; do                                        \
			IMAGE_PREFIX=`echo "$$IMAGE_NAME" | sed "s/\:.*\$$//"`;          \
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

test t: .lock_env ${PREBUILD} ${TEMPLATES} ## Run the tests
	@ export TARGET=${TARGET} && ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} \
		run --rm ${NO_TTY} ${PASS_ENV}                            \
		idilic SeanMorris/Ids runTests

clean: ${PREBUILD} ## Clean the project. Only applies to files from the current target.
	@- ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down --remove-orphans
	@- docker volume prune -f;

	@- docker run --rm -v ${MAKEDIR}:/app -w=/app \
		${BASELINUX} bash -c "                  \
			rm -f infra/docker/*.${GEN_EXT}.*;    \
			set -o noglob;                        \
			rm -f .env.default;                   \
			rm -f .env .env_${TARGET} .var;       \
			rm -f .lock_env .env_${TARGET}.default;"

	@- docker run --rm -v ${REALDIR}:/app -w=/app \
		${BASELINUX} bash -c "                      \
			rm -f ${GENERABLE};                       \
			cat data/global/_schema.json > data/global/schema.json ;"

localbase: ${ENVBUILD} .lock_env
	@ echo Building ${FULLNAME} \(Local ${LOCALBASE}\);
	export TARGET=base TAG=${LOCALBASE} \
		&& ${DCOMPOSE} ${DCOMPOSE_BASE_STACK} build idilic

SEP=
env e: ## Export the environment as seen from MAKE.
	@ export ${ENV} && env ${SEP};

start s: ${PREBUILD} ## Start the project services.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up -d

start-fg sf: ${PREBUILD} ## Start the project services in the foreground.
	@ ${DCOMPOSE} -f ${COMPOSE_TARGET} up

start-bg sb: ${PREBUILD} ## Start the project services in the background, streaming output to terminal.
	(${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up &)

stop d: ${PREBUILD} ## Stop the current project services on the current target.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down

stop-all da: ${PREBUILD} ## Stop the all project services on the current target. including orphans.
	${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down --remove-orphans

restart r: ${PREBUILD} ## Restart the project services in the foreground.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down && ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up -d

restart-fg rf: ${PREBUILD} ## Start the project services in the foreground.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down && ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up

restart-bg rb: ${PREBUILD}## Start the project services in the background, streaming output to terminal.
	@ (${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} down && ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} up &)

kill k: ${PREBUILD} ## Kill current project services.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} kill -s 9

kill-all: ## Kill all project services.
	@ ${WHILE_IMAGES} echo docker kill -9 $$IMAGE_HASH; done;

current-tag ct: ${COMPOSE_TARGET} ## Get the current project tag.
	@ echo ${TAG}

current-target ctr: ${COMPOSE_TARGET} ## Get the current target.
	@ [[ "${TARGET}" != "" ]] || (echo "No target set." && false)
	@ echo ${TARGET}

list-images li:${PREBUILD} ## List available images from current target.
	@ ${WHILE_IMAGES} \
		echo $$(docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH) \
		$$(docker image inspect --format="{{ .Size }}" $$IMAGE_HASH  \
			| awk '{ S=$$1 /1024/1024 ; print S "MB" }' \
		); \
	done;

list-tags lt: ## List the images tagged from the current target.
	@ ${WHILE_TAGS} echo $$TAG_NAME; done;done;

push-images psi: ${COMPOSE_TARGET} ## Push locally built images.
	@ echo Pushing ${PROJECT}:${TAG}
	@ ${WHILE_TAGS} \
		docker push $$TAG_NAME; \
	done;done;

pull-images pli: ${PREBUILD} ## Pull remotely hosted images.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} pull

hooks: ${COMPOSE_TARGET} ## Register git hootks for development.
	@ git config core.hooksPath githooks

run: ${PREBUILD} ## CMD= 'SERVICE COMMAND' Run a command in a given service's container.
	@ ${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} run --rm ${NO_TTY} \
		${PASS_ENV} ${CMD}

bash sh: ${PREBUILD} ## Get a bash propmpt to an idilic container.
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

dcompose-config dcc: ${PREBUILD} ## Print the current docker-compose configuration.
	${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} config 2>&1

dcompose-version dcv: ${PREBUILD} ## Print the current docker-compose configuration.
	${DCOMPOSE} ${DCOMPOSE_TARGET_STACK} version

.lock_env: retarget ### Lock the environment target
	touch .lock_env
	$(eval LOCALBASE:=$(or    \
		, ${LOCALBASE}        \
		, $(shell ${RANDOM_STRING} ) \
	))

	@ [[ "${ENV_LOCK_TAG}" != "${TAG}" ]] \
	||[[ "${ENV_LOCK_TGT_SVC}" != "${TGT_SVC}" ]] \
	|| ( \
		 ${DRUN} -v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp composer install \
			--ignore-platform-reqs                  \
			`${ISDEV} || echo "--no-dev"`;          \
			                                        \
	);

	@ test ! -z "${TAG}"      \
		&& echo ENV_LOCK_TGT_SVC=${TGT_SVC} > ${ENV_LOCK} \
		&& echo LOCALBASE=${LOCALBASE} >> ${ENV_LOCK} \
		&& echo ENV_LOCK_TAG=${TAG}    >> ${ENV_LOCK} \
		|| true;

# 	@[[ "${ENV_LOCK_LOCALBASE}" == "${LOCALBASE}" ]] \
# 	|| ( \
# 	);

stay@%: retarget ### Set the current target and persist for later invocations.
	@ >&2 echo Setting persistent target ${TARGET}...
	@ echo TARGET=${TARGET} > ${VAR_FILE}

@%: retarget
	@ >&2 echo Setting current target ${TARGET}...

retarget: ### Set the current target for one invocation.
	@ $(call EXTRACT_TARGET_SERVICES, $(or ${TGT_STR},${TARGET}))
	@ >&2 echo Checking current target ${TARGET}...
	@ ${NEWTARGET}

.env: config/.env
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app  \
		${BASELINUX} bash -c '{                     \
			FROM=config/.env                          \
			TO=.env                                   \
				&& ${STITCH_ENTROPY};                   \
		}'

	docker run --rm -v ${MAKEDIR}:/app -w=/app    \
		${BASELINUX} bash -c '{                     \
			FROM=config/.env.default                  \
			TO=.env.default                           \
				&& ${STITCH_ENTROPY};                   \
		}'

.env_${TARGET}: config/.env_${TARGET}
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app \
		${BASELINUX} bash -c '{                    \
			FROM=config/.env_${TARGET}               \
			TO=.env_${TARGET}                        \
				&& ${STITCH_ENTROPY};                  \
			FROM=config/.env_${TARGET}.default       \
			TO=.env_${TARGET}.default                \
				&& ${STITCH_ENTROPY};                  \
		}'

.env_${TARGET}.default: config/.env_${TARGET}.default
	@ docker run --rm -v ${MAKEDIR}:/app -w=/app \
		${BASELINUX} bash -c '{                    \
			FROM=config/.env_${TARGET}               \
			TO=.env_${TARGET}                        \
				&& ${STITCH_ENTROPY};                  \
			FROM=config/.env_${TARGET}.default       \
			TO=.env_${TARGET}.default                \
				&& ${STITCH_ENTROPY};                  \
		}'

config/.env   config/.env_${TARGET}:
	@ test -f ${@} || touch ${@};

config/.env.default config/.env_${TARGET}.default:
	@ test -f ${@};

infra/compose/%yml: .env .env.default .env_$${TARGET} .env_$${TARGET}.default .lock_env
	@ test -f infra/compose/${TARGET}.yml;


help: help-all ## print this message
	echo "SeanMorris/Ids v0.0.0"
help-%:
	echo "SeanMorris/Ids v0.0.0"
	@ $(eval HELPTYPE:= echo ${@} | sed 's/.+-//' )
	@ $(foreach MKFL,${MAKEFILE_LIST},  \
		cat "${MKFL}" | grep '\:.*\#\#' | grep -v '###'\
		| while read -r LINE; do        \
			NAME=`echo $$LINE | sed -r 's/[:= ].*//'`;  \
			DESC=`echo $$LINE | sed -r 's/.*\#\#//'`;   \
			TYPE=`echo $$DESC \
				| grep '%'    \
				| sed -r 's/.*(%[a-z]*).*/\1/;'`;         \
			DESC=`echo $$DESC | sed -r 's/^%[a-z]*//'`; \
			[[ -z "$$TYPE" ]] || continue;              \
			echo -e "$$NAME: $$DESC";                   \
		done | column -ts:;                           \
	)
	@ echo SeanMorris/Ids/Makefile generated its own helpfile @`date`

.SECONDEXPANSION:

templates: ${GENERABLE}

# ${GENERABLE}: $$(call GEN_TO_TEMP,$${@}) .lock_env ${ENVBUILD}
${GENERABLE}: $$(call GEN_TO_TEMP,$${@}) ${ENVBUILD}
	@ echo Rebuilding template `basename ${@}`;
	@ test -w ${@} || test -w `dirname ${@}`;
	@ echo -e "$(call SHELLOUT,cat ${<})" > ${@}
	@ test -f ${@};

###

graylog-start gls: ${PREBUILD} ### Start graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up -d

graylog-start-fg glsf: ${PREBUILD} ### start-fg for graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up

graylog-start-bg glsb: ${PREBUILD} ### start-bg for graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up &

graylog-restart glr: ${PREBUILD} ### Restart graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up -d

graylog-restart-fg glrf: ${PREBUILD} ### restart-fg for graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up

graylog-restart-bg glrb: ${PREBUILD} ### restart-bg for graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml up &

graylog-stop gld: ${PREBUILD} ### Stop graylog.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml down

graylog-backup glbak: ### Backup graylog config to files.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml run --rm mongo bash -c \
		'mongodump -h mongo --db graylog --out /settings; ls /; ls /settings'

graylog-restore glres: ### Restore graylog config from files.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/graylog.yml run --rm mongo bash -c \
		'mongorestore -h mongo --db graylog /settings/graylog'

inotify-start: ${PREBUILD} ### Start inotify.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/inotify.yml up

inotify-stop: ${PREBUILD} ### Stop inotify.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/inotify.yml down

inotify-build: ${PREBUILD} ### Stop inotify.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/inotify.yml build

aptcache-start: ${PREBUILD} ### Start apt-cache.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/aptcache.yml up

aptcache-stop: ${PREBUILD} ### Stop apt-cache.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/aptcache.yml down

aptcache-build: ${PREBUILD} ### Stop apt-cache.
	${DCOMPOSE} -f ${COMPOSE_TOOLS}/aptcache.yml build

###
yq:
	${YQ}
