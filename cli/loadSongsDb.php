<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 20/08/15
 * Time: 13:59
 */

use Phase\TakeATicket\SongLoader;

require(dirname(__DIR__) . '/vendor/autoload.php');

$loader = new SongLoader();

/** @noinspection PhpUndefinedVariableInspection */
if ($argc !== 2) {
    /** @noinspection PhpUndefinedVariableInspection */
    $loader->usage($argv[0]);
    exit(1);
}
/** @noinspection PhpUndefinedVariableInspection */
$sourceFile = $argv[1];

$app = require(dirname(__DIR__) . '/www/app.php');

$loader->run($sourceFile, $app['db']);
