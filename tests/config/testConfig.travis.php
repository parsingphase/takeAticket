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
        ]
    ]
];

return $config;
