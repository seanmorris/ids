FROM node:13.5.0-buster-slim as base
MAINTAINER Sean Morris <sean@seanmorr.is>

RUN set -eux; \
	mkdir /build;
