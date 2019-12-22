#!make
.PHONY: init

SHELL    = /bin/bash
PROJECT  ?=project
REPO     ?=repository
MAKEDIR  =$(dir $(abspath $(firstword $(MAKEFILE_LIST))))

-include .env
-include .env.${TARGET}

-include vendor/seanmorris/ids/Makefile

init: ${TARGET_COMPOSE}
	@ docker run --rm \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer init
	@ docker run --rm \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer require seanmorris/ids
	@ docker run --rm \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer install
