FROM debian:buster-20191118-slim
MAINTAINER Sean Morris

RUN set -eux;               \
	apt-get update          \
	&& apt-get install -y --no-install-recommends \
		bsdmainutils        \
		build-essential     \
		ca-certificates     \
		gettext-base        \
		wget;               \
	wget -O /usr/bin/docker-compose               \
		https://github.com/docker/compose/releases/download/1.25.0/docker-compose-`uname -s`-`uname -m`; \
	chmod +x /usr/bin/docker-compose;             \
	apt-get purge   -y --auto-remove;             \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rf /var/lib/apt/lists/*;

WORKDIR /app

ENTRYPOINT ["make"]

CMD ["help"]