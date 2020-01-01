FROM ${BASELINUX}
MAINTAINER Sean Morris

RUN set -eux;               \
	apt-get update          \
	&& apt search inotify-tools       \
	&& apt-get install -y --no-install-recommends \
		inotify-tools;                \
	apt-get purge   -y --auto-remove; \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rf /var/lib/apt/lists/*;

WORKDIR /app

ENTRYPOINT ["inotifywait"]

CMD ["-r", "-m", ".", "-e", "close_write"]
