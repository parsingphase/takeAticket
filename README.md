TakeATicket
===========
Queue management tool for Rock Club London (http://rockclublondon.com/)

Software created by Richard George (richard@phase.org)

## Usage

Currently runs using PHP's built-in webserver & a sqlite3 database.

To install (requires Composer - https://getcomposer.org):

 `composer install`
 
To create database file/schema:

 `sqlite3 db/app.db < src/db.sql`
 `sqlite3 db/app.db < vendor/jasongrimes/silex-simpleuser/sql/sqlite.sql`
 
 