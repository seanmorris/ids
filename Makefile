#!make
.PHONY: it composer-install composer-update composer-update-no-dev tag-images \
	push-images pull-images tag-images start start-fg restart restart-fg stop \
	stop-all run run-phar test env hooks

TARGET   ?=dev

-include .env
-include .env.${TARGET}

PROJECT  ?=ids
REPO     ?=seanmorris
BRANCH   ?=$$(git rev-parse --abbrev-ref HEAD  2>/dev/null)
DESC     ?=$$(git describe --tags 2>/dev/null || echo _$$(git rev-parse --short HEAD) || echo init)

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

ifeq (${TARGET},test)
	NO_DEV=
endif

ifeq (${TARGET},dev)
	NO_DEV=
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

ENV=TAG=$${TAG:-${TAG}} REPO=${REPO} DHOST_IP=${DHOST_IP} ${XDEBUG_ENV} \
	PROJECT=${PROJECT} TARGET=${TARGET} \
	$$(cat .env 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#) \
	$$(cat .env.${TARGET} 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#)


DCOMPOSE ?=export ${ENV} \
	&& docker-compose \
	-p ${PROJECT} \
	-f infra/compose/${TARGET}.yml

it: infra/compose/${TARGET}.yml
	@ echo Building ${FULLNAME}
	@ docker run --rm \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		-v $$PWD:/app \
		composer install ${NO_DEV}
	@ cp -n .env.sample .env 2>/dev/null|| true
	@ cp -n .env.${TARGET}.sample .env.${TARGET} 2>/dev/null|| true
	@ make -s composer-install TARGET=${TARGET} PROJECT=${PROJECT}
	@ export TAG=latest-${TARGET} ${DCOMPOSE} build idilic
	@ ${DCOMPOSE} build
	@ ${DCOMPOSE} up --no-start
	@ docker images seanmorris/ids.*:${TAG} -q | while read IMAGE_HASH; do \
		docker image inspect --format="{{ index .RepoTags 0 }}" $$IMAGE_HASH \
		| while read IMAGE_NAME; do \
			IMAGE_PREFIX=`echo "$$IMAGE_NAME" | sed -e "s/\:.*\$$//"`; \
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

push-images: infra/compose/${TARGET}.yml
	@ echo Pushing ${PROJECT}:${TAG}
	@ export TAG="latest-${TARGET}" \
		&& ${DCOMPOSE} push
	@ export TAG=$$(date '+%Y%m%d')-${TARGET} \
		&& ${DCOMPOSE} push
	@ ${DCOMPOSE} push

pull-images: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} pull

images: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} images

imagesq: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} images -q

restart: infra/compose/${TARGET}.yml
	@ make -s stop
	@ make -s start

restart-fg: infra/compose/${TARGET}.yml
	@ make -s stop
	@ make -s start-fg

start: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} up -d

start-fg: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} up

stop: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} down

stop-all: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} down --remove-orphans

tag: infra/compose/${TARGET}.yml
	@ echo ${TAG}

run: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} run --rm ${NO_TTY} \
		$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /') \
		${CMD}

run-phar: infra/compose/${TARGET}.yml
	@ ${DCOMPOSE} run --rm --entrypoint='php SeanMorris_Ids.phar' \
	$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /') \
	${CMD}

test: infra/compose/${TARGET}.yml
	@ make --no-print-directory run \
		TARGET=${TARGET} CMD="idilic -vv SeanMorris/Ids runTests SeanMorris/Ids"

env: infra/compose/${TARGET}.yml
	@ env -i ${ENV} bash -c "env"

hooks: infra/compose/${TARGET}.yml
	@ git config core.hooksPath githooks
