#VERSION 0.0.1

#Runs the Symfony app via the internal server. Admin account is user: admin, password: admin
# Not recommended for production use!

FROM ubuntu:18.04
MAINTAINER Richard George "richard@phase.org"

RUN apt-get update && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y php ant git php-xml php-dom php-xmlwriter php-zip php-sqlite3 php-mbstring nodejs npm sqlite3

WORKDIR /root
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "copy('https://composer.github.io/installer.sig', 'composer-setup.sig');" && \
    php -r "if (hash_file('SHA384', 'composer-setup.php') === trim(file_get_contents('composer-setup.sig'))) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php');  die(-1); } echo PHP_EOL;"  && \
    php composer-setup.php  && \
    php -r "unlink('composer-setup.php');" && \
    cp composer.phar /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER 1

# Ubuntu still installs node as nodejs
RUN ln -s /usr/bin/nodejs /usr/local/bin/node

RUN mkdir /app
#VOLUME ["/app"]
ADD . /app

WORKDIR /app
RUN mkdir -p var/db #excluded in .dockerfile - see http://stackoverflow.com/questions/34198591/
RUN cp app/config/parameters-docker.yml app/config/parameters.yml
RUN /usr/local/bin/composer --ansi install && \
    npm install

RUN sqlite3 var/db/app.sqlite < sql/db-sqlite.sql

RUN mkdir -p web/docs && \
    ln -s ../components web/components && \
    ln -s ../../docs/images web/docs/images

RUN php bin/console doctrine:schema:update --force && \
    php bin/console fos:user:create admin admin@localhost admin --super-admin
RUN vendor/bin/phing -f phing.xml test-all

# Sample data:
RUN sqlite3 var/db/app.sqlite < sql/sampleSongs.sql

RUN rm -rf var/cache/*
# Avoid Cannot rename "/app/var/cache/dev" to "/app/var/cache/de~".
# https://twitter.com/lsmith/status/804246742103429121?lang=en-gb

EXPOSE 8000

# WORKDIR /app if not current
ENTRYPOINT php bin/console server:run -e prod 0.0.0.0:8000
