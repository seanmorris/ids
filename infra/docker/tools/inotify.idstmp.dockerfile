FROM ${BASELINUX}
MAINTAINER Sean Morris

ENV WATCH=.
ENV EVENT=close_write

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

ENTRYPOINT ["inotifywait", "-e", "$${EVENT}", "-r", "-m"]

CMD ["$${WATCH}"]
