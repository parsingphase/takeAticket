<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 20/08/15
 * Time: 13:46
 */

$config = [
    'displayOptions' => [
        'songInPreview' => false,
        'upcomingCount' => 4, // number of tickets to show on upcoming page
        // files cannot currently be distributed due to copyright issues
//            'iconMapHtml' => [
//                'V' => '<img src="local/rb-mic.png" class="instrumentIcon"/>',
//                'G' => '<img src="local/rb-guitar.png" class="instrumentIcon"/>',
//                'B' => '<img src="local/rb-bass.png" class="instrumentIcon"/>',
//                'D' => '<img src="local/rb-drums.png" class="instrumentIcon"/>',
//                'K' => '<img src="local/rb-keys.png" class="instrumentIcon"/>'
//            ]
    ],
    // db config is optional and default to a sqlite database at /db/app.db
    // See http://silex.sensiolabs.org/doc/providers/doctrine.html for all options
//    'db.options' => [
//        'driver' => 'pdo_mysql',
//        'host' => '127.0.0.1',
//        'port' => 3306,
//        'dbname' => 'SOMEDB',
//        'user' => 'SOMEUSER',
//        'password' => 'SOMEPASSWORD',
//    ]
];

return $config;
