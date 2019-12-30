FROM ${BASELINUX} as base
MAINTAINER Sean Morris <sean@seanmorr.is>

ARG IDS_APT_PROXY_HOST
ARG IDS_APT_PROXY_PORT

COPY ./infra/apt/proxy-detect.sh /usr/bin/proxy-detect

RUN set -eux;                  \
	echo 'Acquire::HTTP::Proxy-Auto-Detect /usr/bin/proxy-detect;' \
		>> /etc/apt/apt.conf.d/01proxy; \
	echo "HTTP Proxy:" `/usr/bin/proxy-detect`; \
	apt-get update;            \
	apt-get install -y --no-install-recommends software-properties-common \
		ca-certificates        \
		gnupg                  \
		lsb-release            \
		wget;                  \
	wget -qO /usr/bin/yq       \
		https://github.com/mikefarah/yq/releases/download/2.4.1/yq_linux_arm64; \
	wget -qO /etc/apt/trusted.gpg.d/php.gpg        \
		https://packages.sury.org/php/apt.gpg;     \
	sh -c "echo 'deb https://packages.sury.org/php/ $$(lsb_release -sc) main' \
		 | tee /etc/apt/sources.list.d/sury-php.list"; \
	apt-get update;            \
	apt-get install -y --no-install-recommends \
		libargon2-0            \
		libsodium23            \
		libssl1.1              \
		libyaml-dev            \
		php${PHP}              \
		php${PHP}-cli          \
		php${PHP}-common       \
		php${PHP}-dom          \
		php${PHP}-json         \
		php${PHP}-opcache      \
		php${PHP}-pdo-mysql    \
		php${PHP}-readline     \
		php${PHP}-xml          \
		php${PHP}-yaml;        \
	apt-get remove -y software-properties-common \
		apache2-bin            \
		apt-transport-https    \
		ca-certificates        \
		gnupg                  \
		lsb-release            \
		perl                   \
		php5.6                 \
		python                 \
		wget;                  \
	apt-get purge -y --auto-remove; \
	apt-get autoremove -y;     \
	apt-get clean

ENV IDS_INSIDE_DOCKER=true
ENV PATH="$${PATH}:/app/source/Idilic:/app/vendor/seanmorris/ids/source/Idilic:/app/vendor/bin"

SHELL ["/bin/bash", "-c"]

WORKDIR /app

ENTRYPOINT ["idilic"]

CMD ["-d=;", "info"]

COPY ./ /app

FROM base AS test
FROM base AS dev

RUN set -eux;       \
	apt-get update; \
	apt-get install -y --no-install-recommends php${PHP}-xdebug; \
	apt-get clean;

COPY ./infra/xdebug/30-xdebug-cli.ini /etc/php/${PHP}/cli/conf.d/30-xdebug-cli.ini

FROM base AS prod
