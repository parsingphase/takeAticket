TakeATicket
===========
Queue management tool for Rock Club London (http://rockclublondon.com/)

Software created by Richard George (richard@phase.org)

Canonical source: https://github.com/parsingphase/takeAticket

## Setup

Currently runs using PHP's built-in webserver & a sqlite3 database.

To install (requires Composer - https://getcomposer.org):

    git clone git@github.com:parsingphase/takeAticket.git
    cd takeAticket
    composer install
 
To create database file/schema:

    sqlite3 db/app.db < sql/db.sql
    sqlite3 db/app.db < vendor/jasongrimes/silex-simpleuser/sql/sqlite.sql
 
To configure
 
    cp config/config.sample.php config/config.php
 
To load song library

    php cli/loadSongsDb path/to/songlib.xls
    
(file format to follow but see `Phase\TakeATicket\SongLoader::$fileFields`)
 
To start server

    ./startServer.sh

## Usage

Runs on localhost & all attached IPs at port 8080 (copy & edit startServer.sh to change this).
Visit http://localhost:8080 when server is running

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
vanish from the "upcoming" page. Tracks can be removed completely (eg if a band fails to appear) by clicking "remove". 

Don't remove performed tracks as statistics (work in progress) won't work. (Ignore numbers on band members for now).

Add a song by searching in the song field and clicking the appropriate result. Add a band by typing a comma-separated list 
of names into the band field (hit enter when band is complete) and/or clicking name buttons. 

New name buttons will appear as tickets are saved with new participants. 

Click "Add" once song names and band member lists appear above their respective inputs:

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

