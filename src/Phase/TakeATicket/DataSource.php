<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 03/09/15
 * Time: 20:45
 */

namespace Phase\TakeATicket;


use Doctrine\DBAL\Connection;

class DataSource
{

    const TICKETS_TABLE = 'tickets';
    const PERFORMERS_TABLE = 'performers';
    const TICKETS_X_PERFORMERS_TABLE = 'tickets_x_performers';
    const BAND_IDENTIFIER_BAND_NAME = 1;
    const BAND_IDENTIFIER_PERFORMERS = 2;

    /**
     * @var Connection
     */
    protected $dbConn;

    /**
     * DataSource constructor.
     * @param $dbConn
     */
    public function __construct(Connection $dbConn)
    {
        $this->dbConn = $dbConn;
    }

    /**
     * @return Connection
     */
    public function getDbConn()
    {
        return $this->dbConn;
    }

    /**
     * @param $songId
     * @return mixed
     */
    public function fetchSongById($songId)
    {
        return $this->getDbConn()->fetchAssoc('SELECT * FROM songs WHERE id = :code', ['code' => $songId]);
    }

    /**
     * @param $ticket
     * @return mixed
     */
    public function expandTicketData($ticket)
    {
        if ($ticket['songId']) {
            $ticket['song'] = $this->fetchSongById($ticket['songId']);
        }
        $ticket['performers'] = $this->fetchPerformersByTicketId($ticket['id']);
        return $ticket;
    }

    /**
     * expandTicketData for multiple tickets
     *
     * @param $tickets
     * @return mixed
     */
    public function expandTicketsData($tickets)
    {
        foreach ($tickets as &$ticket) {
            $ticket = $this->expandTicketData($ticket);
        }
        return $tickets;
    }

    public function potentialCodeNumber($searchString)
    {
        $codeLength = (int)SongLoader::CODE_LENGTH;
        $regexp = '/^[a-f0-9]{' . $codeLength . '}$/i';
        return preg_match($regexp, $searchString);
    }

    /**
     * @return array
     */
    public function generatePerformerStats()
    {
        $conn = $this->getDbConn();
        $sql = 'SELECT p.id AS performerId, p.name AS performerName,
                  sum(CASE WHEN t.id IS NOT NULL AND t.used=0 AND t.deleted=0 THEN 1 ELSE 0 END) AS songsPending,
                  sum(CASE WHEN t.id IS NOT NULL AND t.used=1 AND t.deleted=0 THEN 1 ELSE 0 END) AS songsDone
               FROM performers p
                    LEFT OUTER JOIN tickets_x_performers txp ON p.id=txp.performerId
                    LEFT OUTER JOIN tickets t ON txp.ticketId = t.id
                GROUP BY p.id ORDER BY p.name';

        $performers = $conn->fetchAll($sql);
        return $performers;
    }

    public function addPerformersToTicketByName($ticketId, $performerNames)
    {
        foreach ($performerNames as $performerName) {
            $performerName = trim($performerName);
            $performerId = $this->getPerformerIdByName($performerName, true);
            if ($performerId) {
                $link = ['ticketId' => $ticketId, 'performerId' => $performerId];
                $this->getDbConn()->insert(self::TICKETS_X_PERFORMERS_TABLE, $link);
            }
        }
    }

    public function getPerformerIdByName($performerName, $createMissing = false)
    {
        $conn = $this->getDbConn();
        $sql = 'SELECT id FROM performers p WHERE p.name LIKE :name LIMIT 1';
        $performerId = $conn->fetchColumn($sql, ['name' => $performerName]);

        if ($createMissing && !$performerId) {
            $max = $conn->fetchColumn('SELECT max(id) FROM performers');
            $performerId = $max + 1;
            $conn->insert(self::PERFORMERS_TABLE, ['id' => $performerId, 'name' => ucwords($performerName)]);
            //add new performer row
        }

        return $performerId;
    }

    /**
     * Fetch all performers on a song with their stats
     *
     * @param $ticketId
     * @return array[]
     */
    public function fetchPerformersByTicketId($ticketId)
    {
        $ticketPerformerSql = 'SELECT performerId FROM tickets_x_performers WHERE ticketId = :ticketId';
        $performerRows = $this->getDbConn()->fetchAll($ticketPerformerSql, ['ticketId' => $ticketId]);
        $performerIds = [];
        foreach ($performerRows as $row) {
            $performerIds[] = $row['performerId'];
        }

        $allPerformers = $this->generatePerformerStats();
        $trackPerformers = [];
        foreach ($allPerformers as $performer) {
            if (in_array($performer['performerId'], $performerIds)) {
                $trackPerformers[] = $performer;
            }
        }
        return $trackPerformers;
    }


    /**
     * @param $searchString
     * @return array
     */
    public function findSongsBySearchString($searchString)
    {
        $conn = $this->getDbConn();
        $leadingPattern = implode('%', preg_split('/\s+/', $searchString)) . '%';
        $internalPattern = '%' . $leadingPattern;
        $params = [
            'internalPattern' => $internalPattern,
            'leadingPattern' => $leadingPattern,
            'searchString' => $searchString
        ];

        // this may be unnecessary - chances of a code number hitting anything else is minimal
        if ($this->potentialCodeNumber($searchString)) {
            $sql = "SELECT * FROM songs
            WHERE (title = :searchString)
            OR (codeNumber = :searchString)
            ORDER BY artist, title
            LIMIT 10";
            //allow title just in case
        } else {
            $sql = "SELECT * FROM songs
            WHERE (title || ' ' || artist LIKE :internalPattern)
            OR (artist || ' ' || title LIKE :internalPattern)
            OR (codeNumber LIKE :leadingPattern)
            OR (id = :searchString)
            ORDER BY artist, title
            LIMIT 10";
        }

        $songs = $conn->fetchAll($sql, $params);

        // normalise datatypes
        foreach ($songs as &$song) {
            $song = $this->normaliseSongRecord($song);
        }

        return $songs;
    }


    /**
     * @param $id
     * @return int
     */
    public function markTicketUsedById($id)
    {
        $conn = $this->getDbConn();
        $res = $conn->update(DataSource::TICKETS_TABLE, ['used' => 1], ['id' => $id]);
        return $res;
    }

    /**
     * @param $id
     * @return int
     */
    public function deleteTicketById($id)
    {
        $conn = $this->getDbConn();
        $res = $conn->update(DataSource::TICKETS_TABLE, ['deleted' => 1], ['id' => $id]);
        return $res;
    }

    /**
     * @param $id
     * @param $offset
     * @return mixed
     */
    public function updateTicketOffsetById($id, $offset)
    {
        return $this->getDbConn()->update(DataSource::TICKETS_TABLE, ['offset' => $offset], ['id' => $id]);
    }

    /**
     * @param $songKey
     * @return array
     */
    public function fetchSongByKey($songKey)
    {
        $conn = $this->getDbConn();

        $song = $conn->fetchAssoc('SELECT * FROM songs WHERE codeNumber = :code', ['code' => $songKey]);
        return $song;
    }

    /**
     * @return array|mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchUpcomingTickets()
    {
        $conn = $this->getDbConn();
        $statement = $conn->prepare('SELECT * FROM tickets WHERE deleted=0 AND used=0 ORDER BY offset ASC LIMIT 3');
        $statement->execute();
        $next = $statement->fetchAll();
        $next = $this->expandTicketsData($next);
        return $next;
    }

    /**
     * @return array|mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchUndeletedTickets()
    {
        $conn = $this->getDbConn();
        $statement = $conn->prepare('SELECT * FROM tickets WHERE deleted=0 ORDER BY offset ASC');
        $statement->execute();
        $tickets = $statement->fetchAll();
        $tickets = $this->expandTicketsData($tickets);
        return $tickets;
    }

    /**
     * @param $title
     * @param $songId
     * @return int|false
     */
    public function storeNewTicket($title, $songId)
    {
        $conn = $this->getDbConn();
        $max = $conn->fetchAssoc('SELECT max(offset) AS o, max(id) AS i FROM tickets');

        $maxOffset = $max['o'];
        $maxId = $max['i'];
        $ticket = [
            'title' => $title,
            'id' => $maxId + 1,
            'offset' => $maxOffset + 1,
            'songId' => $songId
        ];
        $res = $conn->insert(DataSource::TICKETS_TABLE, $ticket);
        return $res ? $ticket['id'] : false;
    }

    /**
     * @param $song
     * @return mixed
     */
    public function normaliseSongRecord($song)
    {
        $song['id'] = (int)$song['id'];
        $song['hasHarmony'] = (bool)$song['hasHarmony'];
        $song['hasKeys'] = (bool)$song['hasKeys'];
        return $song;
    }

}