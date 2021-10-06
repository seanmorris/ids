FROM ubuntu:xenial-20210804
MAINTAINER Sean Morris
SHELL ["/bin/bash", "-c"]

ARG IDS_APT_PROXY_HOST
ARG IDS_APT_PROXY_PORT

COPY ./infra/apt/proxy-detect.sh /usr/bin/proxy-detect

RUN set -eux;               \
	chmod ugo+rx /usr/bin/proxy-detect;         \
	echo 'Acquire::HTTP::Proxy-Auto-Detect /usr/bin/proxy-detect;' \
		> /etc/apt/apt.conf.d/02proxy;          \
	echo "HTTP Proxy:" `/usr/bin/proxy-detect`; \
	apt-get update;         \
	apt-get install -y --no-install-recommends software-properties-common \
		apt-transport-https \
		ca-certificates     \
		gnupg               \
		jq                  \
		lsb-release         \
		wget;               \
	export LANG=C.UTF-8; \
	apt-key adv --keyserver keyserver.ubuntu.com --recv-keys CC86BB64; \
	add-apt-repository ppa:rmescandon/yq; \
	apt-get update; \
	apt-get install -y yq; \
	apt-get purge -y --auto-remove; \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rf /var/lib/apt/lists/*
