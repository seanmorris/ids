FROM node:13.5.0-buster-slim as base
MAINTAINER Sean Morris <sean@seanmorr.is>

WORKDIR /build

SHELL ["/bin/bash", "-c"]

RUN set -eux;              \
	mkdir -p /build;       \
	apt-get update;        \
	apt-get install jq:1.5+dfsg-1.3  \
		 -y --no-install-recommends; \
	apt-get purge -y --auto-remove;  \
	apt-get autoremove -y; \
	apt-get clean;         \
	rm -rf /var/lib/apt/lists/*;
