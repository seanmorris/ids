FROM debian:buster-20191118-slim as base
MAINTAINER Sean Morris <sean@seanmorr.is>

SHELL ["/bin/bash", "-c"]

ARG IDS_APT_PROXY_HOST
ARG IDS_APT_PROXY_PORT

ARG CORERELDIR
ARG ROOTRELDIR

COPY ${CORERELDIR}/infra/apt/proxy-detect.sh /usr/bin/proxy-detect

RUN set -eux;               \
	chmod ugo+rx /usr/bin/proxy-detect;         \
	echo 'Acquire::HTTP::Proxy-Auto-Detect /usr/bin/proxy-detect;' \
		> /etc/apt/apt.conf.d/02proxy;          \
	echo "HTTP Proxy:" `/usr/bin/proxy-detect`; \
	apt-get update;         \
	apt-get install -y --no-install-recommends software-properties-common \
		ca-certificates     \
		gnupg               \
		jq                  \
		lsb-release         \
		wget;               \
	wget -O /usr/bin/yq     \
		https://github.com/mikefarah/yq/releases/download/2.4.1/yq_linux_amd64; \
	chmod +x /usr/bin/yq;   \
	wget -O /etc/apt/trusted.gpg.d/php.gpg             \
		https://packages.sury.org/php/apt.gpg;         \
	sh -c "echo 'deb https://packages.sury.org/php/ $(lsb_release -sc) main' \
		 | tee /etc/apt/sources.list.d/sury-php.list"; \
	apt-get update;         \
	apt-get install -y --no-install-recommends \
		libargon2-0         \
		libsodium23         \
		libssl1.1           \
		libyaml-dev         \
		php7.3           \
		php7.3-cli       \
		php7.3-common    \
		php7.3-dom       \
		php7.3-json      \
		php7.3-opcache   \
		php7.3-pdo-mysql \
		php7.3-readline  \
		php7.3-xml       \
		php7.3-yaml;     \
	apt-get remove -y software-properties-common \
		apache2-bin         \
		apt-transport-https \
		ca-certificates     \
		gnupg               \
		lsb-release         \
		perl                \
		php5.6              \
		python              \
		wget;               \
	apt-get purge -y --auto-remove; \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rf /var/lib/apt/lists/*

ENV IDS_INSIDE_DOCKER=true
ENV PATH="${PATH}:/app/source/Idilic:/app/vendor/seanmorris/ids/source/Idilic:/app/vendor/bin"

WORKDIR /app

ENTRYPOINT ["idilic"]

CMD ["-d=;", "info"]

FROM base as idilic-base
FROM idilic-base AS idilic-test

RUN set -eux;       \
	apt-get update; \
	apt-get install -y --no-install-recommends php7.3-xdebug; \
	apt-get clean;  \
	rm -rf /var/lib/apt/lists/*

COPY ${CORERELDIR}/infra/xdebug/30-xdebug-cli.ini /etc/php/7.3/cli/conf.d/30-xdebug-cli.ini

FROM idilic-test AS idilic-dev
FROM idilic-base AS idilic-prod

COPY ${ROOTRELDIR}/ /app

FROM base AS server-base

ARG UID=1000
ARG GID=1000

RUN set -eux;               \
	apt-get update;         \
	apt-get install -y --no-install-recommends \
		apache2             \
		libapache2-mod-php7.3; \
	sed -i '0,/Listen 80/s//Listen 8080/' /etc/apache2/ports.conf; \
	sed -i '0,/Listen 443/s//Listen 4433/' /etc/apache2/ports.conf; \
	a2dismod mpm_event;     \
	a2enmod rewrite ssl php7.3; \
	apt-get remove -y software-properties-common \
		python              \
		wget;               \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rfv /var/www/html;  \
	ln -s /app/public /var/www/html; \
	chmod -R ug+rw /var/log/apache2 /var/run/apache2; \
	chgrp -R +${GID} /var/log/apache2 /var/run/apache2; \
	ln -sf /proc/self/fd/1 /var/log/apache2/access.log; \
	ln -sf /proc/self/fd/1 /var/log/apache2/error.log;  \
	rm -rf /var/lib/apt/lists/*;

ENTRYPOINT ["apachectl", "-D", "FOREGROUND"]

FROM server-base AS server-test
FROM server-base AS server-dev

RUN set -eux;       \
	apt-get update; \
	apt-get install -y --no-install-recommends php7.3-xdebug; \
	apt-get clean;  \
	rm -rf /var/lib/apt/lists/*;

COPY ${CORERELDIR}/infra/xdebug/30-xdebug-apache.ini /etc/php/7.3/apache2/conf.d/30-xdebug-apache.ini

FROM server-base AS server-prod