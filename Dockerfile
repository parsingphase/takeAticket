#VERSION 0.0.1

#For running the app in demo mode with PHP internal server and small sample songlist

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

RUN apt-get install -y php-xml php-dom php-xmlwriter php-zip php-sqlite3 \
    nodejs npm sqlite3

# Ubuntu still installs node as nodejs
RUN ln -s /usr/bin/nodejs /usr/local/bin/node

RUN mkdir /app
#VOLUME ["/app"]
ADD . /app

WORKDIR /app
RUN /usr/local/bin/composer --ansi install
RUN npm install

RUN sqlite3 db/app.db < sql/db-sqlite.sql
RUN sqlite3 db/app.db < vendor/jasongrimes/silex-simpleuser/sql/sqlite.sql
RUN cp config/config.sample.php config/config.php && \
    ln -s ../components www/components && \
    ln -s ../../docs/images www/docs/image

RUN vendor/bin/phing test-all

# Sample data:
RUN sqlite3 db/app.db < sql/sampleSongs.sql

EXPOSE 8080

# WORKDIR /app if not current
ENTRYPOINT php -S 0:8080 -t www

#CMD startServer.sh
