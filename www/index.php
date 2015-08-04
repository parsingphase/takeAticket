<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 04/08/15
 * Time: 19:00
 */

use Silex\Provider\DoctrineServiceProvider;
use Silex\Provider\MonologServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;

require_once __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('Europe/London');

$app = new \Silex\Application();

//$app['twig.cache.dir'] = dirname(__DIR__).'/cache';
$app['monolog.logfile'] = dirname(__DIR__).'/log/app.log';

$app['db.options'] = [
    'driver'   => 'pdo_sqlite',
    'path'     => dirname(__DIR__).'/db/app.db',
];

$app->register(new MonologServiceProvider());

$app->register(new DoctrineServiceProvider());

$app->register(new UrlGeneratorServiceProvider());
$app->register(new ServiceControllerServiceProvider());

$app->register(
    new TwigServiceProvider(),
    ['twig.path' => dirname(__DIR__).'/views']
);

//$app->register(new TwigExtensionsServiceProvider());

$app->mount('/',new \Phase\TakeATicket\ControllerProvider($app));

$app->run();
