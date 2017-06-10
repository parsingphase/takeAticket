#!/usr/bin/env bash

#Note: takeaticket & takeaticket_fixtures dbs must exist, sql/dbsampler/credentials-mysql.json must be configured
./vendor/bin/dbsampler -c sql/dbsampler/credentials-mysql.json -m sql/dbsampler/ticket-mysql.db.json
echo
echo Password needed for mysqldump
mysqldump -uroot -p --opt --compact -t --skip-extended-insert --complete-insert=TRUE takeaticket_fixtures | sed "s/\\\'/''/g" > sql/sampleSongs.sql
# one day mysql will dump SQL it can import by default… https://bugs.mysql.com/bug.php?id=65941