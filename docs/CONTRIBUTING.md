Contributing
============

Code must pass existing tests (including linting, formatting etc) and others that may be added (eg, jshint is to be added).

Recommended IDE is PHPStorm but any may be used so long as tests pass.

Please add an issue on GitHub for any TODO task you start.

This project is intended to run with the absolute minimum of dependencies, except:

* PHP 5.5+
* Composer
* sqlite3

so that it can be run from most Linux or OSX desktop environments without server configuration / further installs being needed.

In particular, npm or ant must not be required for running or basic development of the code. 
**npm** *is* used for jshint testing, including by travis, but this is not strictly required for either development or execution. 

[Phing](https://www.phing.info) is used in place of ant - I'm not convinced it's actually as good as ant, but it benefits from
being installable via composer and not requiring any OS-level installs (eg Java).

Please discuss before adding any further requirements, or adding any further dependencies via composer.

The application itself is based on [Silex](http://silex.sensiolabs.org), which has pretty good documentation.

### Source data
`sql/sampleSongs.sql` is included to get you started. Full `.xlsx` file may be available on request.

### CSS
CSS is compiled from www/css/ticket.less to www/css/ticket.css 
 
* Run `./rebuildLess.sh` to compile
* CSS file must be checked in

### Testing
* Run `./vendor/bin/phing -p` for details of tests and tools. You **must** run `./vendor/bin/phing test-mindeps` before 
submitting code; `./vendor/bin/phing test-all` is optional, but your code **must** pass this when travis runs it to be accepted.