ARG PHP
ARG UID=1000
ARG GID=1000
ARG CORERELDIR
ARG ROOTRELDIR
ARG BASELINUX
ARG IDS_APT_PROXY_HOST
ARG IDS_APT_PROXY_PORT

FROM ${BASELINUX} as base
MAINTAINER Sean Morris <sean@seanmorr.is>

SHELL ["/bin/bash", "-c"]

ARG PHP
ARG CORERELDIR
ARG IDS_APT_PROXY_HOST
ARG IDS_APT_PROXY_PORT

COPY ${CORERELDIR}/infra/apt/proxy-detect.sh /usr/bin/proxy-detect

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
	sh -c "echo 'deb https://packages.sury.org/php/ $(lsb_release -sc) main' \
		 | tee /etc/apt/sources.list.d/sury-php.list"; \
	apt-get update;         \
	apt-get install -y --no-install-recommends \
		libargon2-0         \
		libsodium23         \
		libssl1.1           \
		libyaml-dev         \
		mime-support        \
		php${PHP}           \
		php${PHP}-bcmath    \
		php${PHP}-cli       \
		php${PHP}-common    \
		php${PHP}-curl      \
		php${PHP}-dom       \
		php${PHP}-mbstring  \
		php${PHP}-opcache   \
		php${PHP}-pdo-mysql \
		php${PHP}-redis     \
		php${PHP}-readline  \
		php${PHP}-xml       \
		php${PHP}-yaml;

RUN	apt-get remove -y --no-install-recommends \
		software-properties-common \
		apache2-bin         \
		gnupg               \
		perl                \
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
FROM idilic-base AS idilic-dev

ARG PHP
ARG CORERELDIR

RUN set -eux;       \
	apt-get update; \
	apt-get install -y --no-install-recommends php${PHP}-xdebug; \
	apt-get clean;  \
	rm -rf /var/lib/apt/lists/*

RUN echo ${CORERELDIR} && ls -al

COPY ${CORERELDIR}/infra/xdebug/30-xdebug-cli.ini /etc/php/${PHP}/cli/conf.d/30-xdebug-cli.ini

FROM idilic-base AS idilic-prod

ARG ROOTRELDIR

COPY ${ROOTRELDIR}/ /app

FROM base AS server-base

ARG PHP
ARG GID
ARG CORERELDIR

RUN set -eux;               \
	apt-get update;         \
	apt-get install -y --no-install-recommends \
		apache2             \
		libapache2-mod-php${PHP}; \
	a2dismod mpm_event;     \
	a2enmod rewrite ssl http2 php${PHP}; \
	apt-get remove -y software-properties-common \
		python              \
		wget;               \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rfv /var/www/html;  \
	mkdir -p /etc/php/${PHP}/apache2/conf;\
	mkdir -p /lock;\
	ln -s /app/public /var/www/html; \
	ls -al /var/www; \
	chmod -R ug+rws /var/log/apache2 /var/run /var/lock /var/www; \
	chgrp -R +${GID} /var/log/apache2 /var/run /var/lock /var/www; \
	ln -sf /proc/self/fd/2 /var/log/apache2/access.log; \
	ln -sf /proc/self/fd/2 /var/log/apache2/error.log;  \
	rm -rf /var/lib/apt/lists/*;

RUN set -eux; \
	sed -i '0,/Listen 80/s//Listen 8080/' /etc/apache2/ports.conf; \
	sed -i '0,/Listen 443/s//Listen 4433/' /etc/apache2/ports.conf; \
	sed -i '/DocumentRoot \/var\/www\/html/a \	</Directory>' \
		/etc/apache2/sites-available/000-default.conf; \
	sed -i '0,/<VirtualHost \*:80>/s//<VirtualHost *:8080>/' \
		/etc/apache2/sites-available/000-default.conf; \
	sed -i '/DocumentRoot \/var\/www\/html/a \	\	AllowOverride All' \
		/etc/apache2/sites-available/000-default.conf; \
	sed -i '/DocumentRoot \/var\/www\/html/a \	\	Options FollowSymLinks' \
		/etc/apache2/sites-available/000-default.conf; \
	sed -i '/DocumentRoot \/var\/www\/html/a \	<Directory /var/www/html/>' \
		/etc/apache2/sites-available/000-default.conf;

ENTRYPOINT ["apachectl", "-D", "FOREGROUND"]

ARG UID=0
ARG GID=0
FROM server-base AS server-test
FROM server-base AS server-dev

ARG PHP
ARG CORERELDIR

RUN set -eux;       \
	apt-get update; \
	apt-get install -y --no-install-recommends php${PHP}-xdebug; \
	apt-get clean;  \
	rm -rf /var/lib/apt/lists/*;

COPY ${CORERELDIR}/infra/apache/http2.conf /etc/apache2/conf-enabled/http2.conf
COPY ${CORERELDIR}/infra/xdebug/30-xdebug-apache.ini /etc/php/${PHP}/apache2/conf.d/30-xdebug-apache.ini
#COPY ${CORERELDIR}/infra/php/40-upload-size.ini /etc/php/${PHP}/apache2/conf.d/40-upload-size.ini

FROM server-base AS server-prod
