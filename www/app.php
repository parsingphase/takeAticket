<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 20/08/15
 * Time: 14:13
 */

use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\RememberMeServiceProvider;
use Silex\Provider\SecurityServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\SwiftmailerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Europe/London');

$app = new \Silex\Application();

$app['config'] = require(dirname(__DIR__) . '/config/config.php');

//$app['twig.cache.dir'] = dirname(__DIR__).'/cache';
$app['monolog.logfile'] = dirname(__DIR__) . '/log/app.log';

$app['db.options'] = [
    'driver' => 'pdo_sqlite',
    'path' => dirname(__DIR__) . '/db/app.db',
];

// simpleuser - requires mysql? - nope
// Database config. See http://silex.sensiolabs.org/doc/providers/doctrine.html
//$app['db.options'] = array(
//    'driver'   => 'pdo_mysql',
//    'host' => 'localhost',
//    'dbname' => 'mydbname',
//    'user' => 'mydbuser',
//    'password' => 'mydbpassword',
//);


$app->register(new MonologServiceProvider());
$app->register(new DoctrineServiceProvider());
$app->register(new SecurityServiceProvider());
$app->register(new RememberMeServiceProvider());
$app->register(new SessionServiceProvider());
$app->register(new ServiceControllerServiceProvider());
$app->register(new UrlGeneratorServiceProvider());
$app->register(new TwigServiceProvider());
$app->register(new SwiftmailerServiceProvider());

// Register the SimpleUser service provider.
$simpleUserProvider = new SimpleUser\UserServiceProvider();
$app->register($simpleUserProvider);

$app->register(
    new TwigServiceProvider(),
    ['twig.path' => dirname(__DIR__) . '/views']
);

//$app->register(new TwigExtensionsServiceProvider());

$app->mount('/', new \Phase\TakeATicket\ControllerProvider($app));
$app->mount('/user', $simpleUserProvider);


// SimpleUser options. See https://github.com/jasongrimes/silex-simpleuser for details.
$app['user.options'] = [
    'mailer' => [
        'enabled' => false
    ],
    'emailConfirmation' => [
        'required' => true // force manual enabling
    ]
];

// Security config. See http://silex.sensiolabs.org/doc/providers/security.html for details.
$app['security.firewalls'] = array(
    /* // Ensure that the login page is accessible to all, if you set anonymous => false below.
    'login' => array(
        'pattern' => '^/user/login$',
    ), */
    'secured_area' => array(
        'pattern' => '^.*$',
        'anonymous' => true,
        'remember_me' => array(),
        'form' => array(
            'login_path' => '/user/login',
            'check_path' => '/user/login_check',
        ),
        'logout' => array(
            'logout_path' => '/user/logout',
        ),
        'users' => $app->share(function ($app) {
            return $app['user.manager'];
        }),
    ),
);

// Mailer config. See http://silex.sensiolabs.org/doc/providers/swiftmailer.html
$app['swiftmailer.options'] = array(); // not actually using it at the moment


// end simpleuser stuff

return $app;
