#!make
.PHONY: it composer-install composer-update composer-update-no-dev tag-images \
	push-images pull-images tag-images start start-fg restart restart-fg stop \
	stop-all run run-phar test env hooks


TARGET ?=dev

-include .env
-include .env.${TARGET}

PROJECT ?=ids
REPO    ?=seanmorris
BRANCH  ?=$$(git rev-parse --abbrev-ref HEAD)
DESC    ?=$$(git describe --tags 2>/dev/null || git rev-parse --short HEAD)

TAG       ?=${DESC}-${TARGET}-${BRANCH}
IMAGE     ?=
DHOST_IP  ?=$$(docker network inspect bridge --format='{{ (index .IPAM.Config 0).Gateway}}')
NO_TTY    ?=-T

INTERPOLATE_ENV=env -i DHOST_IP=${DHOST_IP} \
	TAG=${TAG} REPO=${REPO} TARGET=${TARGET} \
	PROJECT=${PROJECT} \
	envsubst

ifeq (${TARGET},dev)
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
	PROJECT=${PROJECT} TARGET=${TARGET} \
	$$(cat .env 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#) \
	$$(cat .env.${TARGET} 2>/dev/null | ${INTERPOLATE_ENV} | grep -v ^\#)


DCOMPOSE ?=export ${ENV} \
	&& docker-compose \
	-p ${PROJECT} \
	-f infra/compose/${TARGET}.yml

it: infra/compose/${TARGET}.yml
	@ echo Building ${PROJECT}:${TAG}
	@ make -s composer-install TARGET=${TARGET} PROJECT=${PROJECT}
	@ ${DCOMPOSE} build ${IMAGE}
	@ ${DCOMPOSE} build
	@ ${DCOMPOSE} up --no-start
	@ ${DCOMPOSE} images -q | while read IMAGE_HASH; do \
		docker image inspect --format="{{index .RepoTags 0}}" $$IMAGE_HASH \
		| grep "^${REPO}" \
		| grep "${TAG}$$" \
		| while read IMAGE_NAME; do \
			IMAGE_PREFIX=`echo "$$IMAGE_NAME" | sed -e "s/\:.*\$$//"`; \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":`date '+%Y%m%d'`-${TARGET}; \
			echo "$$IMAGE_PREFIX":`date '+%Y%m%d'`-${TARGET}; \
			docker tag "$$IMAGE_HASH" "$$IMAGE_PREFIX":latest-${TARGET}; \
			echo "$$IMAGE_PREFIX":latest-${TARGET}; \
		done; \
	done;
	@ ${DCOMPOSE} images

composer-install:
	@ docker run --rm \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer install

composer-update:
	@ docker run --rm \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer update

composer-update-no-dev:
	@ docker run --rm \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer update --no-dev

push-images:
	${DCOMPOSE} push

pull-images:
	${DCOMPOSE} pull

images:
	@ ${DCOMPOSE} images

imagesq:
	@ ${DCOMPOSE} images -q

restart:
	@ make -s stop
	@ make -s start

restart-fg:
	@ make -s stop
	@ make -s start-fg

start:
	@ ${DCOMPOSE} up -d

start-fg:
	@ ${DCOMPOSE} up

stop:
	@ ${DCOMPOSE} down

stop-all:
	@ ${DCOMPOSE} down --remove-orphans

tag:
	@ echo ${TAG}

run:
	${DCOMPOSE} run --rm ${NO_TTY} \
	$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /') \
	${CMD}

run-phar:
	@ ${DCOMPOSE} run --rm --entrypoint='php SeanMorris_Ids.phar' \
	$$(env -i ${ENV} bash -c "compgen -e" | sed 's/^/-e /') \
	${CMD}

test:
	@ make --no-print-directory run \
		TARGET=${TARGET} CMD="idilic -vv SeanMorris/Ids runTests SeanMorris/Ids"

env:
	@ env -i ${ENV} bash -c "env"

hooks:
	@ git config core.hooksPath githooks
