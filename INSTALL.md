Installation
============

## 1) Create database 

The software can run with either a MySQL or sqlite database.

If using MySQL, the `utf8mb4` character set should be used

     CREATE DATABASE `takeaticket`  DEFAULT CHARACTER SET utf8mb4 collate utf8mb4_unicode_ci;

You will also need a user with `CREATE, INSERT, UPDATE, DELETE` usage on this table

## 2a) Install with Ant

An [apache ant](http://ant.apache.org) script will attempt to install and configure the software:

    ant build-current         # for production use OR
    ant build-current-dev     # for development use

## 2b) Or install manually
    
Check the contents of the [Dockerfile](https://github.com/parsingphase/takeAticket/blob/master/Dockerfile) 
for the steps needed. You need to start with    
    
    composer install
    npm install    
    
and you may need to follow the Symfony install guide linked below.    
 
## 3) Populating the database

#### Populate application tables:
 
     sqlite3 var/db/app.sqlite < sql/db-sqlite.sql                 # for sqlite OR  
     mysql -u[USER] -p[PASSWORD] -D[DATABASE] < sql/db-mysql.sql   # for mysql

#### Populate user & login tables     
     
     php bin/console doctrine:schema:update --force                # any platform
      
#### Create admin user
     
     php bin/console fos:user:create admin admin@localhost admin --super-admin
     
## 4) Configure and run web server     

For development or limited-scope use you can use Symfony's internal PHP server:

    php bin/console -e=prod server:run     # for standard use
    php bin/console -e=dev server:run      # for development use     
    
To run under an external web server such as Apache or NGINX, consult the Symfony documentation    

## Further help

 - [Standard Symfony app install guide](http://symfony.com/doc/current/deployment.html) 

{% include footer.md %}