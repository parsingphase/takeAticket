TakeATicket
===========
Queue management tool for Rock Club London (http://rockclublondon.com/)

Software created by Richard George (richard@phase.org)

Canonical source: https://github.com/parsingphase/takeAticket

## Usage

Currently runs using PHP's built-in webserver & a sqlite3 database.

To install (requires Composer - https://getcomposer.org):

 `git clone git@github.com:parsingphase/takeAticket.git`
 `cd takeAticket`
 `composer install`
 
To create database file/schema:

 `sqlite3 db/app.db < src/db.sql`  (application tables)
 `sqlite3 db/app.db < vendor/jasongrimes/silex-simpleuser/sql/sqlite.sql` (user access tables)
 
To configure
 
 `cp config/config.sample.php config/config.php`
 
To load song library

 `php cli/loadSongsDb path/to/songlib.xls` (file format to follow but see Phase\TakeATicket\SongLoader::$fileFields)
 
To start server

`./startServer.sh`
