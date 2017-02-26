<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 03/09/15
 * Time: 20:45
 */

namespace Phase\TakeATicket\DataSource;

use Doctrine\DBAL\Connection;
use PDO;
use Phase\TakeATicket\Model\Instrument;
use Phase\TakeATicket\Model\Platform;
use Phase\TakeATicket\Model\Song;
use Phase\TakeATicket\Model\Source;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Base Datasource
 *
 * Onward naming convention for methods (note that some are currently misnamed)
 *
 * get*() property getter
 * set*() property setter
 *
 * fetch*Row() Returns raw DB content as array
 * fetch*Data() Returns non-object data that's not just homogenous rows
 * fetch[ModelName]() Returns model objects
 *
 * protected build[ModelName]FromDbRow() Return single model object from array
 * protected [modelName]ToDbRow() Return single array suitable for DB insertion from model object
 */
abstract class AbstractSql
{
    const TICKETS_TABLE = 'tickets';
    const PERFORMERS_TABLE = 'performers';
    const TICKETS_X_PERFORMERS_TABLE = 'tickets_x_performers';
    const CODE_LENGTH = 6;

    /**
     * @var Connection
     */
    protected $dbConn;
    /**
     * @var int
     */
    protected $upcomingCount = 3;

    /**
     * @var LoggerInterface
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
     * @return LoggerInterface
     */
    public function getLogger()
    {
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }

        return $this->logger;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param $songId
     *
     * @return array
     */
    public function fetchSongRowById($songId)
    {
        $song = $this->getDbConn()->fetchAssoc('SELECT * FROM songs WHERE id = :code', ['code' => $songId]);
        if ($song) {
            $song = $this->normaliseSongRecord($song);
        }

        return $song;
    }

    /**
     * Fill out names of linked object ids for given ticket
     *
     * @param $ticket
     *
     * @return mixed
     */
    public function expandTicketData($ticket)
    {
        $ticket = $this->normaliseTicketRecord($ticket);

        if ($ticket['songId']) {
            $ticket['song'] = $this->expandSongData($this->fetchSongRowById($ticket['songId']));
        }
        //FIXED inefficient, but different pages expect different structure while we refactor
        $ticket['band'] = $this->fetchPerformersWithInstrumentByTicketId($ticket['id']);

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


    /**
     * Fill out names of linked object ids for given song
     *
     * @param $song
     * @return mixed
     */
    public function expandSongData($song)
    {
        $dataStore = $this;
        $instruments = $dataStore->fetchInstrumentsForSongId($song['id']);
        $instruments = array_map(
            function (Instrument $instrument) {
                return $instrument->getName();
            },
            $instruments
        );
        $song['instruments'] = $instruments;

        $source = $dataStore->fetchSourceById($song['sourceId']);
        if ($source) {
            $song['source'] = $source->getName();
        }

        $platforms = $dataStore->fetchPlatformsForSongId($song['id']);
        $platforms = array_map(
            function (Platform $platform) {
                return $platform->getName();
            },
            $platforms
        );
        $song['platforms'] = $platforms;
        //Legacy format - TODO remove this, use ['song']['platforms']
        $song['inRb3'] = in_array('RB3', $song['platforms']);
        $song['inRb4'] = in_array('RB4', $song['platforms']);
        return $song;
    }

    public function isPotentialCodeNumber($searchString)
    {
        $codeLength = (int)self::CODE_LENGTH;
        $regexp = '/^[a-f0-9]{' . $codeLength . '}$/i';

        return preg_match($regexp, $searchString);
    }

    /**
     * Get performed, pending data for all performers
     *
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

        return $conn->fetchAll($sql);
    }

    /**
     * Save band to ticket
     *
     * @param $ticketId
     * @param array $band ['instrumentCode' => 'name'] FIXME update
     */
    public function storeBandToTicket($ticketId, $band)
    {
        if (!is_array($band)) {
            throw new \InvalidArgumentException('Band must be array');
        }

        // remove existing performers
        $this->getDbConn()->delete(self::TICKETS_X_PERFORMERS_TABLE, ['ticketId' => $ticketId]);

        foreach ($band as $instrumentCode => $performers) {
            $instrument = $this->fetchInstrumentByAbbreviation($instrumentCode);
            if ($instrument) {
                $instrumentId = $instrument->getId();

                foreach ($performers as $performerName) {
                    $performerName = trim($performerName);
                    $performerId = $this->fetchPerformerIdByName($performerName, true);
                    if ($performerId) {
                        $link = [
                            'ticketId' => $ticketId,
                            'performerId' => $performerId,
                            'instrumentId' => $instrumentId
                        ];
                        $this->getDbConn()->insert(self::TICKETS_X_PERFORMERS_TABLE, $link);
                    }
                }
            } else {
                throw new \UnexpectedValueException("Unknown instrument abbreviation '$instrumentCode'");
            }
        }
    }

    public function fetchPerformerIdByName($performerName, $createMissing = false)
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
        $ticketPerformerSql = 'SELECT x.performerId, i.abbreviation AS instrument
          FROM tickets_x_performers x INNER JOIN instruments i ON x.instrumentId = i.id
          WHERE x.ticketId = :ticketId';
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
        $trackPerformersByInstrument = [];
        foreach ($allPerformers as $performer) {
            $performerId = $performer['performerId'];
            if (in_array($performerId, $performerIds)) {
                $instrument = $instrumentsByPerformer[$performerId];
                if (!isset($trackPerformersByInstrument[$instrument])) {
                    $trackPerformersByInstrument[$instrument] = [];
                }
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
        $leadingPattern = implode('%', preg_split('/\s+/', $searchString)) . '%';
        $internalPattern = '%' . $leadingPattern;
        $params = [
            'internalPattern' => $internalPattern,
            'leadingPattern' => $leadingPattern,
            'searchString' => $searchString,
        ];

        // this may be unnecessary - chances of a code number hitting anything else is minimal
        if ($this->isPotentialCodeNumber($searchString)) {
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
        return $conn->update(self::TICKETS_TABLE, ['used' => 1, 'startTime' => time()], ['id' => $id]);
    }

    /**
     * @param $id
     *
     * @return int
     */
    public function deleteTicketById($id)
    {
        $conn = $this->getDbConn();
        return $conn->update(self::TICKETS_TABLE, ['deleted' => 1], ['id' => $id]);
    }

    /**
     * @param $id
     * @param $offset
     *
     * @return mixed
     */
    public function updateTicketOffsetById($id, $offset)
    {
        $id = (int)$id;
        $offset = (int)$offset;
        $fields = ['offset' => $offset];
        $currentTrack = $this->fetchTicketById($id);
        $oldOffset = (int)$currentTrack['offset'];
        $ok = ($oldOffset === $offset);

        $this->getLogger()->debug(
            "Update track $id offset: $oldOffset => $offset: " .
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
        $privateClause = $includePrivate ? '' : ' AND private = 0 ';
        $statement = $conn->prepare(
            'SELECT * FROM tickets WHERE deleted=0 AND used=0 ' .
            $privateClause .
            'ORDER BY OFFSET ASC LIMIT ' . (int)$this->upcomingCount
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
        $statement = $conn->prepare('SELECT * FROM tickets WHERE deleted=0 AND used=1 ORDER BY offset ASC');
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
        $boolFields = [];
        $intFields = ['id', 'duration', 'sourceId'];

        foreach ($intFields as $k) {
            $song[$k] = is_null($song[$k]) ? null : (int)$song[$k];
        }

        foreach ($boolFields as $k) {
            $song[$k] = (bool)$song[$k];
        }

        // Search API adds a 'queued' parameter to show if song is taken
        if (isset($song['queued'])) { //TODO see if this is safe to move to $boolFields
            $song['queued'] = (bool)$song['queued'];
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
        $song = $conn->fetchAssoc('SELECT * FROM ' . self::TICKETS_TABLE . ' WHERE id = :id', ['id' => $id]);

        return $song;
    }

    /**
     * Get current value of a named setting, NULL if missing
     *
     * @param  $key
     * @return mixed|null
     */
    public function fetchSetting($key)
    {
        $conn = $this->getDbConn();
        $query = $conn->executeQuery('SELECT settingValue FROM settings WHERE settingKey=:key', ['key' => $key]);

        return $query->rowCount() ? $query->fetchColumn() : null;
    }


    public function settingExists($key)
    {
        $conn = $this->getDbConn();
        $value = $conn->fetchColumn('SELECT 1 FROM settings WHERE settingKey=:key', ['key' => $key]);

        return (bool)$value;
    }

    public function updateSetting($k, $v)
    {
        $conn = $this->getDbConn();
        if ($this->settingExists($k)) {
            $conn->update('settings', ['settingValue' => $v], ['settingKey' => $k]);
        } else {
            $conn->insert('settings', ['settingValue' => $v, 'settingKey' => $k]);
        }
        return $this->fetchSetting($k);
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
     * Return ticket array with fields converted to correct datatype
     *
     * @param $ticket
     *
     * @return mixed
     */
    protected function normaliseTicketRecord($ticket)
    {
        $boolFields = ['used', 'deleted', 'private', 'blocking'];
        $intFields = ['id', 'songId'];

        foreach ($intFields as $k) {
            $ticket[$k] = is_null($ticket[$k]) ? null : (int)$ticket[$k];
        }

        foreach ($boolFields as $k) {
            $ticket[$k] = (bool)$ticket[$k];
        }

        return $ticket;
    }

    /**
     * Delete all song & performer data
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function resetAllSessionData()
    {
        $truncateTables = [
            'tickets_x_performers',
            'performers',
            'tickets'
        ];

        $connection = $this->getDbConn();

        foreach ($truncateTables as $table) {
            $connection->query(
                'TRUNCATE TABLE ' . $connection->quoteIdentifier($table)
            );
        }
    }

    /**
     * Delete all catalogue data
     *
     * @throws \Doctrine\DBAL\DBALException
     */
    public function resetCatalogue()
    {
        $dbConn = $this->getDbConn();
        $driverType = $dbConn->getDriver()->getName();
        $sqlite = (stripos($driverType, 'sqlite') !== false);
        // FIXME refactor to subclasses

        $truncateTables = [
            'songs_x_instruments',
            'songs_x_platforms',
            'songs',
            'instruments',
            'platforms'
        ];
        foreach ($truncateTables as $table) {
            $dbConn->exec(
                ($sqlite ? 'DELETE FROM ' : 'TRUNCATE TABLE ') .
                $this->dbConn->quoteIdentifier($table)
            );
        }
    }

    /**
     * Store an instrument to DB
     *
     * @param Instrument $instrument
     */
    public function storeInstrument(Instrument $instrument)
    {
        $asArray = $this->instrumentToDbRow($instrument);
        if ($this->getDbConn()->insert('instruments', $asArray)) {
            $instrument->setId($this->dbConn->lastInsertId());
        }
    }

    /**
     * Store a platform to DB
     *
     * @param Platform $platform
     */
    public function storePlatform(Platform $platform)
    {
        $asArray = $this->platformToDbRow($platform);
        if ($this->getDbConn()->insert('platforms', $asArray)) {
            $platform->setId($this->dbConn->lastInsertId());
        }
    }

    /**
     * Store a source to DB
     *
     * @param Source $source
     */
    public function storeSource(Source $source)
    {
        $asArray = $this->sourceToDbRow($source);
        if ($this->getDbConn()->insert('sources', $asArray)) {
            $source->setId($this->dbConn->lastInsertId());
        }
    }

    /**
     * Store a song to DB
     *
     * @param Song $song
     */
    public function storeSong(Song $song)
    {
        $asArray = $this->songToDbRow($song);
        if ($this->getDbConn()->insert('songs', $asArray)) {
            $song->setId($this->dbConn->lastInsertId());
        }
    }

    /**
     * @param string $sourceName
     *
     * @return null|Source
     */
    public function fetchSourceByName($sourceName)
    {
        $source = null;
        $query = $this->dbConn->createQueryBuilder()
            ->select('*')
            ->from('sources')
            ->where('name = :name')
            ->setParameter(':name', $sourceName);

        $row = $query->execute()->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $source = $this->buildSourceFromDbRow($row);
        }

        return $source;
    }


    /**
     * @param $sourceId
     * @return null|Source
     */
    public function fetchSourceById($sourceId)
    {
        $source = null;
        $query = $this->dbConn->createQueryBuilder()
            ->select('*')
            ->from('sources')
            ->where('id = :id')
            ->setParameter(':id', $sourceId);

        $row = $query->execute()->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $source = $this->buildSourceFromDbRow($row);
        }

        return $source;
    }

    public function fetchPlatformByName($platformName)
    {
        $platform = null;
        $query = $this->dbConn->createQueryBuilder()
            ->select('*')
            ->from('platforms')
            ->where('name = :name')
            ->setParameter(':name', $platformName);

        $row = $query->execute()->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $platform = $this->buildPlatformFromDbRow($row);
        }

        return $platform;
    }

    /**
     * Fetch all available platforms
     *
     * @return Platform[]
     */
    public function fetchAllPlatforms()
    {
        $query = $this->dbConn->createQueryBuilder()
            ->select('*')
            ->from('platforms')
            ->orderBy('id');

        $rows = $query->execute()->fetchAll(PDO::FETCH_ASSOC);

        $platforms = [];
        foreach ($rows as $row) {
            $platforms[] = $this->buildPlatformFromDbRow($row);
        }

        return $platforms;
    }

    /**
     * @param $songId
     * @param array $platformIds
     */
    public function storeSongPlatformLinks($songId, array $platformIds)
    {
        $this->dbConn->delete('songs_x_platforms', ['songId' => $songId]);

        foreach ($platformIds as $platformId) {
            $this->dbConn->insert('songs_x_platforms', ['songId' => $songId, 'platformId' => $platformId]);
        }
    }

    /**
     * @param $name
     * @return null|Instrument
     */
    public function fetchInstrumentByName($name)
    {
        $instrument = null;
        $query = $this->dbConn->createQueryBuilder()
            ->select('*')
            ->from('instruments')
            ->where('name = :name')
            ->setParameter(':name', $name);

        $row = $query->execute()->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $instrument = $this->buildInstrumentFromDbRow($row);
        }

        return $instrument;
    }

    protected function fetchInstrumentByAbbreviation($abbreviation)
    {
        $instrument = null;
        $query = $this->dbConn->createQueryBuilder()
            ->select('*')
            ->from('instruments')
            ->where('abbreviation = :abbreviation')
            ->setParameter(':abbreviation', $abbreviation);

        $row = $query->execute()->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $instrument = $this->buildInstrumentFromDbRow($row);
        }

        return $instrument;
    }


    /**
     * @param $songId
     * @param array $instrumentIds
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function storeSongInstrumentLinks($songId, array $instrumentIds)
    {
        $this->dbConn->delete('songs_x_instruments', ['songId' => $songId]);

        foreach ($instrumentIds as $instrumentId) {
            $this->dbConn->insert('songs_x_instruments', ['songId' => $songId, 'instrumentId' => $instrumentId]);
        }
    }

    /**
     * @param $songId
     * @return Instrument[]
     */
    public function fetchInstrumentsForSongId($songId)
    {
        $instrumentRows = $this->dbConn->fetchAll(
            'SELECT i.* FROM songs_x_instruments si 
              INNER JOIN instruments i ON si.instrumentId = i.id WHERE si.songId = :songId',
            ['songId' => $songId]
        );

        $dbConn = $this;
        $instruments = array_map(
            function ($row) use ($dbConn) {
                return $dbConn->buildInstrumentFromDbRow($row);
            },
            $instrumentRows
        );

        return $instruments;
    }

    /**
     * @param $songId
     * @return Platform[]
     */
    public function fetchPlatformsForSongId($songId)
    {
        $platformRows = $this->dbConn->fetchAll(
            'SELECT p.* FROM songs_x_platforms sp 
              INNER JOIN platforms p ON sp.platformId = p.id WHERE sp.songId = :songId',
            ['songId' => $songId]
        );

        $dbConn = $this;
        $platforms = array_map(
            function ($row) use ($dbConn) {
                return $dbConn->buildPlatformFromDbRow($row);
            },
            $platformRows
        );

        return $platforms;
    }

    /**
     * @param $row
     * @return Instrument
     */
    protected function buildInstrumentFromDbRow($row)
    {
        $instrument = new Instrument();
        $instrument
            ->setId($row['id'])
            ->setName($row['name'])
            ->setAbbreviation($row['abbreviation'])
            ->setIconHtml($row['iconHtml']);
        return $instrument;
    }

    /**
     * @param $row
     * @return Platform
     */
    protected function buildPlatformFromDbRow($row)
    {
        $platform = new Platform();
        $platform
            ->setId($row['id'])
            ->setName($row['name']);
        return $platform;
    }

    /**
     * @param $row
     * @return Source
     */
    protected function buildSourceFromDbRow($row)
    {
        $source = new Source();
        $source
            ->setId($row['id'])
            ->setName($row['name']);
        return $source;
    }

    /**
     * @param Song $song
     * @return array
     */
    protected function songToDbRow(Song $song)
    {
        $asArray = [];
        if ($song->getId()) {
            $asArray['id'] = $song->getId();
        }

        $asArray['artist'] = $song->getArtist();
        $asArray['title'] = $song->getTitle();
        $asArray['duration'] = $song->getDuration();
        $asArray['sourceId'] = $song->getSourceId();
        $asArray['codeNumber'] = $song->getCodeNumber();
        return $asArray;
    }

    /**
     * @param Source $source
     * @return array
     */
    protected function sourceToDbRow(Source $source)
    {
        $asArray = [];
        if ($source->getId()) {
            $asArray['id'] = $source->getId();
        }

        $asArray['name'] = $source->getName();
        return $asArray;
    }

    /**
     * @param Instrument $instrument
     * @return array
     */
    protected function instrumentToDbRow(Instrument $instrument)
    {
        $asArray = [];
        if ($instrument->getId()) {
            $asArray['id'] = $instrument->getId();
        }

        $asArray['name'] = $instrument->getName();
        $asArray['abbreviation'] = $instrument->getAbbreviation();
        $asArray['iconHtml'] = $instrument->getIconHtml();
        return $asArray;
    }

    /**
     * @param Platform $platform
     * @return array
     */
    protected function platformToDbRow(Platform $platform)
    {
        $asArray = [];
        if ($platform->getId()) {
            $asArray['id'] = $platform->getId();
        }

        $asArray['name'] = $platform->getName();
        return $asArray;
    }
}
