TakeATicket [![Build Status](https://travis-ci.org/parsingphase/takeAticket.svg?branch=master)](https://travis-ci.org/parsingphase/takeAticket) [![Dependency Status](https://www.versioneye.com/user/projects/57bc7d77968d6400336020a3/badge.svg?style=flat-square)](https://www.versioneye.com/user/projects/57bc7d77968d6400336020a3)
===========
Queue management tool for Rock Club London (http://rockclublondon.com/)

Software created by Richard George (richard@phase.org)

Canonical source: https://github.com/parsingphase/takeAticket

## Setup

The tool can use either a sqlite3 or mySQL database, and run under a standard server or PHP's internal server mode.

To install the source code (requires Composer - https://getcomposer.org):

    git clone git@github.com:parsingphase/takeAticket.git
    cd takeAticket
    composer install
 
To create database file/schema:

    sqlite3 db/app.db < sql/db-sqlite.sql
    sqlite3 db/app.db < vendor/jasongrimes/silex-simpleuser/sql/sqlite.sql

or create a mysql database and a user with  DROP,SELECT,INSERT,UPDATE,DELETE permissions on that database, and load the following schema files:

*  `sql/db-mysql.sql`
*  `vendor/jasongrimes/silex-simpleuser/sql/mysql.sql`
 
A couple of symlinks are required to make certain frontend resources available:

    ln -s ../components www/components
    ln -s ../../docs/images www/docs/images
 
To configure the database and optional settings
 
    cp config/config.sample.php config/config.php

The sample file is documented to help with setup.
 
To load song the library from a spreadsheet:

    php cli/loadSongsDb path/to/songlib.xls
    
(file format to follow but see `Phase\TakeATicket\SongLoader::$fileFields`)
 
To start the app in PHP's internal server.

    ./startServer.sh

Running the app under any other server is left as an exercise to the reader. The document root is `/web`; 
refer to [Silex setup documentation](http://silex.sensiolabs.org/doc/web_servers.html) for further details. 

## Usage

Using the startServer script, the app runs on localhost & all attached IPs at port 8080 
(copy & edit `startServer.sh` to change this).
Visit http://localhost:8080 when the server is running. The URLs in the documentation below all assume that this is the 
server being used.

### Navigation
**Icons:** Upcoming, Search, RSS feed, Queue Management, Login

![Iconbar](docs/images/iconbar.png)

### Upcoming (index page)

Lists the next 3 bands up. Auto-updates when bands perform or order changes. If you have `'displayOptions' => ['songInPreview' => true ]`
set in config.php OR you're logged in as admin (see below), the song title will be shown as well as the band details.

### Search

http://localhost:8080/songSearch

Allows users to search for songs by band and/or title. Displays the first 10 hits when at least 3 characters are typed in the search box.

### RSS feed

For integration with other tools. Shows the next 3 upcoming bands.

### Manage Queue

Accessible to admins only. 

![Management interface](docs/images/QueueManagement.png)

Tracks can be dragged into a new order. When a group starts performing, click "performing" and it'll be greyed out here and
vanish from the "upcoming" page. This also logs the time at which the track starts, which is used to estimate times for
upcoming tickets.

Tracks can be removed completely (eg if a band fails to appear) by clicking "remove". 
Don't remove performed tracks as it'll skew statistics and times. 

The statistics for each performer on each track show the number of songs performed by this user *before* 
the displayed track, and the total number they've signed up for. Performers are shown in red if they're scheduled to be 
on stage twice in quick succession.

Add a song by searching in the song field and clicking the appropriate result. 

Build a band by assigning performers to instruments. You *can* assign any number of performers to each instrument, but
in most cases (except vocals) this won't make sense. Assign by clicking name buttons or typing a new name into the New 
Performer field and hitting enter. Remove a name by clicking when the appropriate instrument is selected.

Assigning a performer will skip to the next instrument, so to add multiple performers you'll need to re-select the 
instrument.

You can also add a band name if you want, but this is optional.

Click "Add" to store the track once song names and band member lists appear above their respective inputs:

![Management interface](docs/images/AddTicketFormFilled.png)


### Login

http://localhost:8080/users/login 

You can register a user here but the confirmation email will **not** be sent - the system is designed to work on a closed network 
without access to either the internet in general or a mailserver. Once you've registered, you'll need to edit your account to enable
it and add admin capability:

     takeAticket $ sqlite3 db/app.db 
     sqlite> select * from users;
     1|richard@phase.org|...
     
Find your user (probably the only one) and check the id field (the first field shown here). Then enable the account and add ROLE_ADMIN:
     
     sqlite> update users set roles = 'ROLE_USER,ROLE_ADMIN', isEnabled=1 WHERE id=1;

You can now log in, and will be able to use the 'manage' page. Once logged in, the login icon will be replaced with a logout icon.

## TODO 

See [docs/TODO.md](./docs/TODO.md) for intended further work; 
feel free to [open issues on github](https://github.com/parsingphase/takeAticket/issues) to make feature requests, 
report bugs, or "upvote" existing tasks.

## CONTRIBUTING 

See [docs/CONTRIBUTING.md](./docs/CONTRIBUTING.md) for details on how to run included tests and/or contribute to the project.
