FROM seanmorris/ids.idilic:${LOCALBASE} AS base
MAINTAINER Sean Morris

RUN set -eux                \
	apt-get update;         \
	apt-get install -y --no-install-recommends \
		inotify-tools build-essential; \
	apt-get purge   -y --auto-remove; \
	apt-get autoremove -y;  \
	apt-get clean

WORKDIR /app

ENTRYPOINT ["inotifywait"]

CMD ["-r", "-m", ".", "-e", "close_write"]
