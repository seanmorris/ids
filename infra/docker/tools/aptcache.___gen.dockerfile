FROM debian:buster-20191118-slim
MAINTAINER Sean Morris

RUN set -eux;               \
	apt-get update;         \
	apt-get install -y apt-cacher-ng; \
	apt-get purge   -y --auto-remove; \
	apt-get autoremove -y;  \
	apt-get clean;          \
	rm -rf /var/lib/apt/lists/*;

CMD /etc/init.d/apt-cacher-ng start     \
	&& tail -f /var/log/apt-cacher-ng/* \

# generated @ Tue Jan 14 03:07:41 EST 2020
# by sean @ the-altar
