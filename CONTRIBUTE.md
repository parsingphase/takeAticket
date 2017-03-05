Contributing
============

The project is based on a standard [Symfony 3.2](http://symfony.com/doc/3.2/index.html) install

Code must pass existing tests (including linting, formatting etc) and others that may be added (eg, jshint is to be added).

Recommended IDE is PHPStorm but any may be used so long as tests pass.

Please add an issue on GitHub for any TODO task you start.

This project is intended to run with the absolute minimum of dependencies, except:

 - PHP 5.6+
 - Composer
 - sqlite3

so that it can be run from most Linux or OSX desktop environments without server configuration / further installs being needed.

In particular, npm or ant must not be required for running or basic development of the code. 
**npm** *is* used for jshint testing, including by travis, but this is not strictly required for either development or execution. 

[Phing](https://www.phing.info) is used to run tests through `phing.xml`, but the main `build.xml` file allows the use of
 Apache ant for production deployments.

Please discuss before adding any further requirements, or adding any further dependencies via composer.

### Source data
`sql/sampleSongs.sql` is included to get you started. Full `.xlsx` file may be available on request.

### CSS
CSS is compiled from www/css/ticket.less to www/css/ticket.css 
 
 - Run `./rebuildLess.sh` to compile
 - CSS file must be checked in

### Input files
See [PROCESSORS](PROCESSORS.md) for details of how to add custom format loaders.

### Docker
The included Dockerfile runs the codebase on an Ubuntu base image - this makes for a very large image but it's the distro
I'm most familiar with. Migration to a smaller base image, at least as a branch option, would be welcomed.

### Testing
Run `./vendor/bin/phing -f phing.xml -p` for details of tests and tools. You **must** run `./vendor/bin/phing -f phing.xml test-mindeps` before 
submitting code; `./vendor/bin/phing -f phing.xml test-all` is optional, but your code **must** pass this when travis runs it to be accepted.
