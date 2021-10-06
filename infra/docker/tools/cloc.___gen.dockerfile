FROM debian:bullseye-20210927
MAINTAINER Sean Morris

RUN set -eux;               \
	apt-get update          \
	&& apt-get install -y --no-install-recommends \
		ca-certificates     \
		wget;               \
	wget -O /usr/bin/cloc   \
		https://github.com/AlDanial/cloc/blob/51847c36d4d47478d96c426fc801810c8e54fac5/cloc; \
	chmod +x /usr/bin/cloc; \
	apt-get purge   -y --auto-remove; \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rf /var/lib/apt/lists/*;

WORKDIR /app

ENTRYPOINT ["make"]

CMD ["help"]