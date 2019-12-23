FROM node:13.5.0-buster-slim as base
MAINTAINER Sean Morris <sean@seanmorr.is>

WORKDIR /build

SHELL ["/bin/bash", "-c"]

RUN set -eux;              \
	mkdir -p /build;       \
	apt-get update;        \
	apt-get install jq -y --no-install-recommends;  \
	apt-get purge -y --auto-remove; \
	apt-get autoremove -y; \
	apt-get clean;

CMD cp -n /app/package-lock.json /build; \
	NAME=jq '.name' /app/composer.json \
	jq '.name |= (sub("/"; "_") | ascii_downcase)' /app/composer.json \
	> package.json;        \
	cat package.json;      \
	npm install;           \
	cp package-lock.json /app; \
	jq '.name |= "$NAME")' package.json \
	> /app/composer.json;  \
	echo Done!;            \
