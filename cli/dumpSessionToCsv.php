<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 25/10/15
 * Time: 20:13
 */

require(dirname(__DIR__) . '/vendor/autoload.php');

$app = require(dirname(__DIR__) . '/www/app.php');

$outFile = dirname(__DIR__) .'/log/setlist-' . date('Ymd-His') . '.csv';

$exporter = new \Phase\TakeATicket\PlaylistExporter($app['db']);
$exporter->exportToFile($outFile);
