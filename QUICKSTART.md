
## Quick start

You can use Docker to download and run the app locally in two steps:

 1. Install Docker from [https://www.docker.com/community-edition#/download](https://www.docker.com/community-edition#/download)
 2. Run `docker run -i -t -p 8000:8000 quay.io/parsingphase/takeaticket` at a command line to download & run the image

Note that both these downloads are quite large, but both only need to be downloaded once until an update comes along:

 - Docker itself is about 100MB
 - The image is about 750MB - any docker experts who want to put it on a smaller image are welcome to 
 [contribute](CONTRIBUTING.md)

Important notes:

The docker version of this software is not designed as a full production system, but you can use it on a closed system
if you understand the following limitations: 

 1. The admin account/password is set to 'admin/admin' - changing it is currently an advanced operation, see below. 
 2. **If you stop the running docker image, your queue and song list will be wiped.** 
 3. The image contains a small example song list, but you can upload more via the admin interface

## Options for those comfortable with Docker

 - You can change the admin password by connecting to the container and running 
            `./bin/console fos:user:change-password admin NEWPASSWORD`
 - The sqlite database is held at `/app/var/db` and initialised from `/app/sql/db-sqlite.sql`, should you want to extend 
 the Dockerfile to mount the database for persistence
          
{% include footer.md %}