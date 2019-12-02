FROM debian:buster-20191118-slim as base
MAINTAINER Sean Morris <sean@seanmorr.is>

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		apt-transport-https \
		ca-certificates \
		gnupg \
		lsb-release \
		software-properties-common \
		wget \
	&& apt-get clean

RUN wget -qO /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
	&& sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" \
		 > /etc/apt/sources.list.d/sury-php.list'

RUN  apt-get update \
	&& apt-get install -y --no-install-recommends \
		libargon2-0   \
		libsodium23   \
		libssl1.1     \
		libyaml-dev   \
		php7.3           \
		php7.3-cli       \
		php7.3-common    \
		php7.3-json      \
		php7.3-opcache   \
		php7.3-pdo-mysql \
		php7.3-readline  \
		php7.3-yaml      \
	&& apt-get clean

RUN ln -s /app/source/Idilic/idilic /usr/local/bin/idilic

WORKDIR /app

CMD ["-d=;", "info"]

ENTRYPOINT ["idilic"]

FROM base as dev

RUN  apt-get update \
	&& apt-get install -y --no-install-recommends \
		php7.3-xdebug \
	&& apt-get clean \
	&& echo -e "xdebug.remote_autostart=1\nxdebug.remote_mode=req" > \
		/etc/php/7.3/cli/conf.d/30-xdebug-cli.ini \
	&& chmod 777 /etc/php/7.3/cli/conf.d/30-xdebug-cli.ini

FROM base as prod

COPY ./ /app