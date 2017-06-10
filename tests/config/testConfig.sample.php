<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 30/09/15
 * Time: 20:47
 */

$config = [
    'databases' => [
        'sqlite' => [
            'driver' => 'pdo_sqlite',
            'path' => dirname(__DIR__) . '/db/test.db',
        ],
        'mysql' => [
            'driver' => 'pdo_mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'dbname' => 'test',
            'user' => 'USER',
            'password' => 'PASS',
        ]
    ]
];

return $config;
