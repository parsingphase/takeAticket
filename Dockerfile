#VERSION 0.0.1

#Runs the Symfony app via the internal server. Admin accout is user: admin, password: admin
# Not recommended for production use!

FROM ubuntu:16.10
MAINTAINER Richard George "richard@phase.org"

RUN apt-get update
RUN apt-get install -y php ant git

WORKDIR /root
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "copy('https://composer.github.io/installer.sig', 'composer-setup.sig');" && \
    php -r "if (hash_file('SHA384', 'composer-setup.php') === trim(file_get_contents('composer-setup.sig'))) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php');  die(-1); } echo PHP_EOL;"  && \
    php composer-setup.php  && \
#    php -r "unlink('composer-setup.php');"
    cp composer.phar /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN apt-get update && apt-get install -y php-xml php-dom php-xmlwriter php-zip php-sqlite3 php-mbstring \
    nodejs npm sqlite3

# Ubuntu still installs node as nodejs
RUN ln -s /usr/bin/nodejs /usr/local/bin/node

RUN mkdir /app
#VOLUME ["/app"]
ADD . /app

WORKDIR /app
RUN mkdir var #excluded in .dockerfile - see http://stackoverflow.com/questions/34198591/
RUN cp app/config/parameters-docker.yml app/config/parameters.yml
RUN /usr/local/bin/composer --ansi install
RUN npm install

RUN sqlite3 db/app.db < sql/db-sqlite.sql

RUN ln -s ../components web/components
#&& \
#    ln -s ../../docs/images web/docs/image

RUN php bin/console doctrine:schema:update --force && \
    php bin/console fos:user:create admin admin@localhost admin --super-admin
RUN vendor/bin/phing test-all

# Sample data:
RUN sqlite3 db/app.db < sql/sampleSongs.sql

RUN rm -rf var/cache/*
# Avoid Cannot rename "/app/var/cache/dev" to "/app/var/cache/de~".
# https://twitter.com/lsmith/status/804246742103429121?lang=en-gb

EXPOSE 8000

# WORKDIR /app if not current
ENTRYPOINT php bin/console server:run -e prod 0.0.0.0:8000

#CMD startServer.sh
