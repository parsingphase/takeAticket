<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 25/10/15
 * Time: 20:13
 */

require(dirname(__DIR__) . '/vendor/autoload.php');

$app = require(dirname(__DIR__) . '/www/app.php');

$dataSource = Phase\TakeATicket\DataSource\Factory::datasourceFromDbConnection($app['db']);

$tickets = $dataSource->fetchPerformedTickets();

$outFile = dirname(__DIR__) .'/log/setlist-' . date('Ymd-His') . '.csv';

$handle = fopen($outFile, 'w');

if ($handle === false) {
    print("Failed to open '$outFile'\n");
}

$title = [
    'Ticket Id',
    'Start Time',
    'Song Id',
    'Artist',
    'Title',
    'Duration (seconds)',
    'Band Name',
    'Vocals',
    'Guitar',
    'Bass',
    'Drums',
    'Keytar',
    'RB3',
    'RB4',
];

fputcsv($handle, $title);

foreach ($tickets as $ticket) {
    $band = $ticket['band'];

    $line = [
        $ticket['id'],
        date('H:i:s', $ticket['startTime']),
        $ticket['song']['id'],
        $ticket['song']['artist'],
        $ticket['song']['title'],
        $ticket['song']['duration'],
        $ticket['title'],
        performersByInstrument($band, 'V'),
        performersByInstrument($band, 'G'),
        performersByInstrument($band, 'B'),
        performersByInstrument($band, 'D'),
        performersByInstrument($band, 'K'),
        $ticket['song']['inRb3']?'x':'',
        $ticket['song']['inRb4']?'x':'',
    ];

    fputcsv($handle, $line);
}
fputcsv($handle, ['ENDS']);

print("\n Wrote to '$outFile'\n");

function performersByInstrument($band, $instrument)
{
    $performers = '';

    if (is_array($band) && !empty($band[$instrument])) {
        $performers = array_map(
            function ($performer) {
                return $performer['performerName'];
            },
            $band[$instrument]
        );

        $performers = join(', ', $performers);
    }
    return $performers;
}
