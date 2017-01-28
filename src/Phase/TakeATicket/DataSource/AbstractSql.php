<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 03/09/15
 * Time: 20:45
 */

namespace Phase\TakeATicket\DataSource;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Phase\TakeATicket\SongLoader;
use Psr\Log\NullLogger;

abstract class AbstractSql
{
    const TICKETS_TABLE = 'tickets';
    const PERFORMERS_TABLE = 'performers';
    const TICKETS_X_PERFORMERS_TABLE = 'tickets_x_performers';

    /**
     * @var Connection
     */
    protected $dbConn;
    /**
     * @var int
     */
    protected $upcomingCount = 3;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * DataSource constructor.
     *
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
     * @return int
     */
    public function getUpcomingCount()
    {
        return $this->upcomingCount;
    }

    /**
     * @param int $upcomingCount
     */
    public function setUpcomingCount($upcomingCount)
    {
        $this->upcomingCount = $upcomingCount;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param $songId
     *
     * @return mixed
     */
    public function fetchSongById($songId)
    {
        $song = $this->getDbConn()->fetchAssoc('SELECT * FROM songs WHERE id = :code', ['code' => $songId]);
        if ($song) {
            $song = $this->normaliseSongRecord($song);
        }

        return $song;
    }

    /**
     * @param $ticket
     *
     * @return mixed
     */
    public function expandTicketData($ticket)
    {
        $ticket = $this->normaliseTicketRecord($ticket);

        if ($ticket['songId']) {
            $ticket['song'] = $this->fetchSongById($ticket['songId']);
        }
        //FIXME inefficient, but different pages expect different structure while we refactor
        $ticket['band'] = $this->fetchPerformersWithInstrumentByTicketId($ticket['id']);
        $ticket['performers'] = $this->fetchPerformersByTicketId($ticket['id']);

        return $ticket;
    }

    /**
     * expandTicketData for multiple tickets
     *
     * @param $tickets
     *
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
        $codeLength = (int) SongLoader::CODE_LENGTH;
        $regexp = '/^[a-f0-9]{'.$codeLength.'}$/i';

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
                  sum(CASE WHEN t.id IS NOT NULL AND t.used=1 AND t.deleted=0 THEN 1 ELSE 0 END) AS songsDone,
                  sum(CASE WHEN t.id IS NOT NULL AND t.deleted=0 THEN 1 ELSE 0 END) AS songsTotal
               FROM performers p
                    LEFT OUTER JOIN tickets_x_performers txp ON p.id=txp.performerId
                    LEFT OUTER JOIN tickets t ON txp.ticketId = t.id
                GROUP BY p.id ORDER BY p.name';

        $performers = $conn->fetchAll($sql);

        return $performers;
    }

    /**
     * @deprecated Does not store instrument
     *
     * @param $ticketId
     * @param $performerNames
     */
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

    /**
     * Save band to ticket
     *
     * @param $ticketId
     * @param array $band ['instrumentCode' => 'name']
     */
    public function storeBandToTicket($ticketId, $band)
    {
        if (!is_array($band)) {
            throw new \InvalidArgumentException('Band must be array');
        }

        // remove existing performers
        $this->getDbConn()->delete(self::TICKETS_X_PERFORMERS_TABLE, ['ticketId' => $ticketId]);

        foreach ($band as $instrument => $performers) {
            foreach ($performers as $performerName) {
                $performerName = trim($performerName);
                $performerId = $this->getPerformerIdByName($performerName, true);
                if ($performerId) {
                    $link = ['ticketId' => $ticketId, 'performerId' => $performerId, 'instrument' => $instrument];
                    $this->getDbConn()->insert(self::TICKETS_X_PERFORMERS_TABLE, $link);
                }
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
     * @deprecated Use fetchPerformersWithInstrumentByTicketId()
     *
     * @param $ticketId
     *
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

    public function fetchPerformersWithInstrumentByTicketId($ticketId)
    {
        $ticketPerformerSql = 'SELECT performerId, instrument FROM tickets_x_performers WHERE ticketId = :ticketId';
        $performerRows = $this->getDbConn()->fetchAll($ticketPerformerSql, ['ticketId' => $ticketId]);
        $performerIds = [];
        $instrumentsByPerformer = [];
        foreach ($performerRows as $row) {
            $performerId = $row['performerId'];
            $performerIds[] = $performerId;
            $instrumentsByPerformer[$performerId] = $row['instrument'];
        }

        //todo Can probably clean up this algorithm
        $allPerformers = $this->generatePerformerStats();
        $trackPerformers = [];
        $trackPerformersByInstrument = [];
        foreach ($allPerformers as $performer) {
            $performerId = $performer['performerId'];
            if (in_array($performerId, $performerIds)) {
                $instrument = $instrumentsByPerformer[$performerId];
                if (!isset($trackPerformersByInstrument[$instrument])) {
                    $trackPerformersByInstrument[$instrument] = [];
                }
                $trackPerformers[] = $performer;
                $trackPerformersByInstrument[$instrument][] = $performer;
            }
        }

        return $trackPerformersByInstrument;
    }

    /**
     * @param $searchString
     * @param $howMany
     *
     * @return array
     */
    public function findSongsBySearchString($searchString, $howMany = 10)
    {
        $howMany += 0; // force int

        $conn = $this->getDbConn();
        $leadingPattern = implode('%', preg_split('/\s+/', $searchString)).'%';
        $internalPattern = '%'.$leadingPattern;
        $params = [
            'internalPattern' => $internalPattern,
            'leadingPattern' => $leadingPattern,
            'searchString' => $searchString,
        ];

        // this may be unnecessary - chances of a code number hitting anything else is minimal
        if ($this->potentialCodeNumber($searchString)) {
            $sql = "SELECT s.*, max(CASE WHEN t.id IS NOT NULL THEN 1 ELSE 0 END) as queued
            FROM songs s
            LEFT OUTER JOIN tickets t ON s.id = t.songId AND t.deleted=0
            WHERE (s.title = :searchString)
            OR (codeNumber = :searchString)
            GROUP BY s.id
            ORDER BY artist, title
            LIMIT $howMany";
            //allow title just in case
        } else {
            $matchingTokens = ['s.title', '" "', 's.artist'];
            $concatSearchFields = $this->concatenateEscapedFields($matchingTokens);
            $concatSearchFieldsReverse = $this->concatenateEscapedFields(array_reverse($matchingTokens));
            $sql = "SELECT s.*, max(CASE WHEN t.id IS NOT NULL THEN 1 ELSE 0 END) as queued
            FROM songs s
            LEFT OUTER JOIN tickets t ON s.id = t.songId AND t.deleted=0
            WHERE ( $concatSearchFields LIKE :internalPattern)
            OR ($concatSearchFieldsReverse LIKE :internalPattern)
            OR (codeNumber LIKE :leadingPattern)
            OR (s.id = :searchString)
            GROUP BY s.id
            ORDER BY artist, title
            LIMIT $howMany";
        }

        $songs = $conn->fetchAll($sql, $params);

        // normalise data types
        foreach ($songs as &$song) {
            $song = $this->normaliseSongRecord($song);
        }

        return $songs;
    }

    /**
     * @param $id
     *
     * @return int
     */
    public function markTicketUsedById($id)
    {
        $conn = $this->getDbConn();
        $res = $conn->update(self::TICKETS_TABLE, ['used' => 1, 'startTime' => time()], ['id' => $id]);

        return $res;
    }

    /**
     * @param $id
     *
     * @return int
     */
    public function deleteTicketById($id)
    {
        $conn = $this->getDbConn();
        $res = $conn->update(self::TICKETS_TABLE, ['deleted' => 1], ['id' => $id]);

        return $res;
    }

    /**
     * @param $id
     * @param $offset
     *
     * @return mixed
     */
    public function updateTicketOffsetById($id, $offset)
    {
        $id = (int) $id;
        $offset = (int) $offset;
        $fields = ['offset' => $offset];
        $currentTrack = $this->fetchTicketById($id);
        $oldOffset = (int) $currentTrack['offset'];
        $ok = ($oldOffset === $offset);

        $this->getLogger()->debug(
            "Update track $id offset: $oldOffset => $offset: ".
            ($ok ? ' already set' : ' will update')
        );

        if (!$ok) {
            $ok = $this->updateTicketById($id, $fields);
        }

        return $ok;
    }

    /**
     * @param $songKey
     *
     * @return array
     */
    public function fetchSongByKey($songKey)
    {
        $conn = $this->getDbConn();

        $song = $conn->fetchAssoc('SELECT * FROM songs WHERE codeNumber = :code', ['code' => $songKey]);
        if ($song) {
            $song = $this->normaliseSongRecord($song);
        }

        return $song;
    }

    /**
     * @param bool $includePrivate
     *
     * @return array|mixed
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchUpcomingTickets($includePrivate = false)
    {
        $conn = $this->getDbConn();
        $statement = $conn->prepare(
            'SELECT * FROM tickets WHERE deleted=0 AND used=0 '.
            ($includePrivate ? '' : ' AND private = 0 ').
            'ORDER BY offset ASC LIMIT '.(int) $this->upcomingCount
        );
        $statement->execute();
        $next = $statement->fetchAll();
        $next = $this->expandTicketsData($next);

        return $next;
    }

    /**
     * Fetch all non-deleted tickets in offset order
     *
     * @return array|mixed
     *
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
     * Fetch all performed tickets in offset order
     *
     * @return array
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchPerformedTickets()
    {
        $conn = $this->getDbConn();
        $statement = $conn->prepare('SELECT * FROM tickets WHERE deleted=0 and used=1 ORDER BY offset ASC');
        $statement->execute();
        $tickets = $statement->fetchAll();
        $tickets = $this->expandTicketsData($tickets);

        return $tickets;
    }

    /**
     * @param $title
     * @param $songId
     *
     * @return int|false Row ID
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
            'songId' => $songId,
        ];
        $res = $conn->insert(self::TICKETS_TABLE, $ticket);

        return $res ? $ticket['id'] : false;
    }

    /**
     * Normalise datatypes returned in song query
     *
     * @param $song
     *
     * @return mixed
     */
    public function normaliseSongRecord($song)
    {
        $boolFields = ['hasHarmony', 'hasKeys', 'inRb3', 'inRb4'];
        $intFields = ['id', 'duration'];

        foreach ($intFields as $k) {
            $song[$k] = is_null($song[$k]) ? null : (int) $song[$k];
        }

        foreach ($boolFields as $k) {
            $song[$k] = (bool) $song[$k];
        }

        if (isset($song['queued'])) { //FIXME see if this is safe to move to $boolFields
            $song['queued'] = (bool) $song['queued'];
        }

        return $song;
    }

    /**
     * @param $id
     * @param $fields
     *
     * @return int Number of updated rows
     */
    public function updateTicketById($id, $fields)
    {
        if (isset($fields['id'])) {
            throw new \InvalidArgumentException('Fields must not include id');
        }

        return $this->getDbConn()->update(self::TICKETS_TABLE, $fields, ['id' => $id]);
    }

    /**
     * Fetch core ticket data (not band, etc) by ticket id
     *
     * @param $id
     *
     * @return array
     */
    public function fetchTicketById($id)
    {
        $conn = $this->getDbConn();
        $song = $conn->fetchAssoc('SELECT * FROM '.self::TICKETS_TABLE.' WHERE id = :id', ['id' => $id]);

        return $song;
    }

    public function getSetting($key)
    {
        $conn = $this->getDbConn();
        $value = $conn->fetchColumn('SELECT settingValue FROM settings WHERE settingKey=:key', ['key' => $key]);

        return $value;
    }

    /**
     * Return SQL in appropriate dialect to concatenate the listed values
     *
     * @param array $fields
     *
     * @return string
     */
    abstract protected function concatenateEscapedFields($fields);

    /**
     * @param $ticket
     *
     * @return mixed
     */
    protected function normaliseTicketRecord($ticket)
    {
        $boolFields = ['used', 'deleted', 'private', 'blocking'];
        $intFields = ['id', 'songId'];

        foreach ($intFields as $k) {
            $ticket[$k] = is_null($ticket[$k]) ? null : (int) $ticket[$k];
        }

        foreach ($boolFields as $k) {
            $ticket[$k] = (bool) $ticket[$k];
        }

        return $ticket;
    }
}
