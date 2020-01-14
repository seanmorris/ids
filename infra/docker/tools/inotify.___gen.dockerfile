FROM debian:buster-20191118-slim
MAINTAINER Sean Morris

ENV EVENT=close_write

RUN set -eux;               \
	apt-get update          \
	&& apt search inotify-tools       \
	&& apt-get install -y --no-install-recommends \
		inotify-tools;      \
	apt-get purge --auto-remove -y; \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rf /var/lib/apt/lists/*;

WORKDIR /app

ENTRYPOINT ["/usr/bin/inotifywait"]
