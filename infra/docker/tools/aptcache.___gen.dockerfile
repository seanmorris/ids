FROM debian:bullseye-20211220-slim
MAINTAINER Sean Morris

RUN set -eux;               \
	apt-get update;         \
	apt-get install -y apt-cacher-ng; \
	apt-get purge   -y --auto-remove; \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rf /var/lib/apt/lists/*;

CMD /etc/init.d/apt-cacher-ng start     \
	&& tail -f /var/log/apt-cacher-ng/*

# generated @ Sun Feb 19 10:54:15 PM EST 2023
# by sean @ hyperterminal