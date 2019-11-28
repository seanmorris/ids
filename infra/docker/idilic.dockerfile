FROM debian:buster-20191118-slim
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
		 > /etc/apt/sources.list.d/sury-php.list' \
	&& sh -c 'echo "deb http://ppa.launchpad.net/apt-fast/stable/ubuntu trusty main\
deb-src http://ppa.launchpad.net/apt-fast/stable/ubuntu trusty main" \
		 > /etc/apt/sources.list.d/apt-fast.list'

RUN apt-key adv --keyserver keyserver.ubuntu.com --recv-keys A2166B8DE8BDC3367D1901C11EE2FF37CA8DA16B \
	&& apt-get update \
	&& apt-get -y --no-install-recommends install apt-fast \
	&& apt-get clean

RUN apt-get install apt-fast \
	&& apt-fast install -y --no-install-recommends \
		libargon2-0   \
		libsodium23   \
		libssl1.1     \
		libyaml-dev   \
		localectl     \
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

# RUN localedef -i en_US -f UTF-8 en_US.UTF-8 \
# 	&& sed -i -e 's/^\/etc\/locale.gen/\/etc\/locale.gen/'

# ENV LC_ALL en_US.UTF-8

WORKDIR /app

CMD ["-d=;", "info"]

ENTRYPOINT ["idilic"]
