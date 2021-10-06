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
	&& tail -f /var/log/apt-cacher-ng/*

# generated @ Tue 05 Oct 2021 02:34:35 PM EDT
# by sean @ hyperterminal