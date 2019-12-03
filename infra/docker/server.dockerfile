FROM seanmorris/ids.idilic:latest as base
MAINTAINER Sean Morris <sean@seanmorr.is>

RUN apt-get update \
	&& apt-get install -y --no-install-recommends apache2 libapache2-mod-php7.3 \
	&& apt-get clean \
	&& rm -rfv /var/www/html \
	&& ln -s /app/public /var/www/html

RUN a2dismod mpm_event \
	&& a2enmod rewrite \
	&& a2enmod ssl \
	&& a2enmod php7.3

RUN ln -sf /proc/self/fd/1 /var/log/apache2/access.log \
    && ln -sf /proc/self/fd/1 /var/log/apache2/error.log

ENTRYPOINT ["apachectl", "-D", "FOREGROUND"]

FROM base as dev

COPY ./infra/xdebug/30-xdebug-apache.ini /etc/php/7.3/apache2/conf.d/30-xdebug-apache.ini

FROM base as prod
