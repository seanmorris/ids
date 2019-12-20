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
	&& wget -qO /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
	&& sh -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" \
		 > /etc/apt/sources.list.d/sury-php.list' \
	&& apt-get update \
	&& apt-get install -y --no-install-recommends \
		libargon2-0   \
		libsodium23   \
		libssl1.1     \
		libyaml-dev   \
		php7.3           \
		php7.3-cli       \
		php7.3-common    \
		php7.3-dom       \
		php7.3-json      \
		php7.3-opcache   \
		php7.3-pdo-mysql \
		php7.3-readline  \
		php7.3-xml       \
		php7.3-yaml      \
	&& apt-get remove -y \
		apache2-bin \
		apt-transport-https \
		ca-certificates \
		gnupg \
		lsb-release \
		perl \
		php5.6 \
		python \
		software-properties-common \
		wget \
	&& apt-get purge -y --auto-remove \
	&& apt-get autoremove -y \
	&& apt-get clean

ENV PATH="${PATH}:/app/source/Idilic:/app/vendor/bin"

WORKDIR /app

ENTRYPOINT ["idilic"]

CMD ["-d=;", "info"]

FROM base AS dev

RUN apt-get update \
	&& apt-get install -y --no-install-recommends \
		php7.3-xdebug \
	&& apt-get clean \
	&& apt-get autoremove -y

COPY ./infra/xdebug/30-xdebug-cli.ini /etc/php/7.3/cli/conf.d/30-xdebug-cli.ini

FROM base AS prod

COPY ./ /app
