FROM ${BASELINUX}
MAINTAINER Sean Morris

RUN set -eux;               \
	apt-get update;         \
	apt-get install -y apt-cacher-ng; \
	apt-get purge   -y --auto-remove; \
	apt-get autoremove -y;  \
	apt-get clean

CMD /etc/init.d/apt-cacher-ng start   \
	&& ls -al /var/log/apt-cacher-ng/ \
	&& tail -f /var/log/apt-cacher-ng/*

# generated @ $(shell date)
# by ${shell whoami} @ ${shell hostname}
