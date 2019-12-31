FROM seanmorris/ids.idilic:${LOCALBASE} AS base
MAINTAINER Sean Morris <sean@seanmorr.is>

RUN set -eux;                        \
	apt-get update;                  \
	apt-get install -y --no-install-recommends \
		apache2                      \
		libapache2-mod-php${PHP};    \
	a2dismod mpm_event;              \
	a2enmod rewrite ssl php${PHP};   \
	apt-get remove -y software-properties-common \
		python                       \
		wget;                        \
	apt-get autoremove -y;           \
	apt-get clean;                   \
	rm -rfv /var/www/html;           \
	ln -s /app/public /var/www/html; \
	ln -sf /proc/self/fd/1 /var/log/apache2/access.log; \
	ln -sf /proc/self/fd/1 /var/log/apache2/error.log;  \
	rm -rf /var/lib/apt/lists/*;

ENTRYPOINT ["apachectl", "-D", "FOREGROUND"]

FROM base AS test
FROM base AS dev

RUN set -eux;       \
	apt-get update; \
	apt-get install -y --no-install-recommends php${PHP}-xdebug; \
	apt-get clean   \
	rm -rf /var/lib/apt/lists/*;

COPY ./infra/xdebug/30-xdebug-apache.ini /etc/php/${PHP}/apache2/conf.d/30-xdebug-apache.ini

FROM base AS prod
