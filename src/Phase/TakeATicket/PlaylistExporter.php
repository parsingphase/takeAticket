<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 28/10/15
 * Time: 08:11
 */

namespace Phase\TakeATicket;

use Doctrine\DBAL\Connection;
use Phase\TakeATicket\DataSource\Factory;

class PlaylistExporter
{
    /**
     * @var Connection
     */
    protected $dbConn;

    /**
     * PlaylistExporter constructor.
     *
     * @param Connection $dbConn
     */
    public function __construct(Connection $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    public function exportToFile($outFile)
    {
        $dataSource = Factory::datasourceFromDbConnection($this->dbConn);

        $tickets = $dataSource->fetchPerformedTickets();

        $handle = fopen($outFile, 'w');

        if ($handle === false) {
            echo "Failed to open '$outFile'\n";
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
                $this->performersByInstrument($band, 'V'),
                $this->performersByInstrument($band, 'G'),
                $this->performersByInstrument($band, 'B'),
                $this->performersByInstrument($band, 'D'),
                $this->performersByInstrument($band, 'K'),
                $ticket['song']['inRb3'] ? 'x' : '',
                $ticket['song']['inRb4'] ? 'x' : '',
            ];

            fputcsv($handle, $line);
        }
        fputcsv($handle, ['ENDS']);

//        echo "\n Wrote to '$outFile'\n";
    }

    public function performersByInstrument($band, $instrument)
    {
        $performers = '';

        if (is_array($band) && !empty($band[$instrument])) {
            $performers = array_map(
                function ($performer) {
                    return $performer['performerName'];
                },
                $band[$instrument]
            );

            $performers = implode(', ', $performers);
        }

        return $performers;
    }
}
