FROM debian:bullseye-20211220-slim
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
		ca-certificates     \
		dpkg-dev            \
		gnupg               \
		jq                  \
		lsb-release         \
		wget;               \
	wget -O /usr/bin/yq     \
		https://github.com/mikefarah/yq/releases/download/2.4.1/yq_linux_$(dpkg-architecture -qDEB_HOST_ARCH_CPU); \
	chmod +x /usr/bin/yq;   \
	wget -O /etc/apt/trusted.gpg.d/php.gpg             \
		https://packages.sury.org/php/apt.gpg;         \
	sh -c "echo 'deb https://packages.sury.org/php/ $$(lsb_release -sc) main' \
		 | tee /etc/apt/sources.list.d/sury-php.list"; \
	apt-get purge -y --auto-remove; \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rf /var/lib/apt/lists/*
