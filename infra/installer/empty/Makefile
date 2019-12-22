PROJECT?=presskit
REPO   ?=seanmorris

-include .env
-include .env.${TARGET}

-include vendor/seanmorris/ids/Makefile

init:
	@ docker run --rm \
		-v $$PWD:/app \
		-v $${COMPOSER_HOME:-$$HOME/.composer}:/tmp \
		composer install
