<?php

namespace Phase\TakeATicket;

use /** @noinspection PhpInternalEntityUsedInspection */
    Doctrine\DBAL\Configuration; // PHPStorm warning quirk
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phase\TakeATicket\DataSource\Factory;
use Phase\TakeATicket\Model\Song;
use PHPUnit\Framework\TestCase;

class DataSourceTest extends TestCase
{
    const SONG_SOURCE_TESTS = 999;

    /**
     * @var \Doctrine\DBAL\Connection[]
     * Loaded on demand by ::getConfiguredDatabases
     */
    protected $databases = false;

    public function setUp()
    {
        $databases = $this->getConfiguredDatabases();

        $sqlDir = dirname(dirname(dirname(__DIR__))) . '/sql/';

        $sqlSourceFiles = [];
        $sqlSourceFiles['mysql'] = $sqlDir . 'db-mysql.sql';
        $sqlSourceFiles['sqlite'] = $sqlDir . 'db-sqlite.sql';

        $songSqlFile = $sqlDir . 'sampleSongs.sql';
        $songInserts = file($songSqlFile);

        $this->assertTrue(is_array($songInserts) && count($songInserts), 'Sample songs required');

        foreach ($sqlSourceFiles as $k => $v) {
            $sqlClauses = $this->schemaFileToClauses($v);

            if (isset($databases[$k])) {
                foreach ($sqlClauses as $sql) {
                    $databases[$k]->exec($sql);
                }

                foreach ($songInserts as $insertSql) {
                    $insertSql = trim($insertSql);
                    if ($insertSql && !preg_match('/^\s+-- /', $insertSql)) {
                        try {
                            $databases[$k]->exec(str_replace("\\'", "''", $insertSql));
                        } catch (\Exception $e) {
                            print("\nError importing $insertSql:\n" . $e->getMessage() . "\n");
                            throw $e;
                        }
                    }
                }
            }
        }
    }

    /**
     * Make sure we're testing the correct subclasses of the DB source
     *
     * @dataProvider databasesProvider
     * @param string $dbName
     * @param Connection $conn
     */
    public function testFactory($dbName, $conn)
    {
        $expectedFactoryClasses = [
            'mysql' => '/mysql$/i',
            'sqlite' => '/sqlite$/i',
        ];

        $this->assertArrayHasKey($dbName, $expectedFactoryClasses, "Must recognise Data Source for $dbName");
        $dataSource = Factory::datasourceFromDbConnection($conn);
        $this->assertTrue(is_object($dataSource));
        $dataSourceClass = get_class($dataSource);

        $this->assertRegExp(
            $expectedFactoryClasses[$dbName],
            $dataSourceClass,
            "Data Source Class for $dbName must be as expected"
        );
    }

    /**
     * @dataProvider databasesProvider
     * @param string $dbName
     * @param Connection $conn
     */
    public function testSearch($dbName, $conn)
    {
        $searchString = strtolower($conn->fetchColumn('SELECT title FROM songs LIMIT 5,1'));
        // grab exact title to search for

        $dataSource = Factory::datasourceFromDbConnection($conn);
        $hits = $dataSource->findSongsBySearchString($searchString);
        $this->assertTrue(is_array($hits));
        $this->assertEquals(1, count($hits), 'Should return 1 hit for search with DB ' . $dbName);
    }

    /**
     * @dataProvider databasesProvider
     * @param string $dbName
     * @param Connection $conn
     */
    public function testInsertAndFetchUsers($dbName, $conn)
    {
        // Note: we start these tests with an empty, truncated user table
        $dataSource = Factory::datasourceFromDbConnection($conn);
        $noSuchUser = $dataSource->fetchPerformerIdByName('Bob');
        $this->assertFalse($noSuchUser, "Nonexistent user must not be found ($dbName)");

        $firstUserId = $dataSource->fetchPerformerIdByName('Bob', true);
        $this->assertEquals(1, $firstUserId, "Creating first user must return ID=1 ($dbName)");
        // Only works for DBs with monotonic int IDs - may need later revision

        $secondUserId = $dataSource->fetchPerformerIdByName('Harry', true);
        $this->assertTrue($secondUserId > 1, "Creating second user must return ID>1 ($dbName)");
        // Only works for DBs with monotonic int IDs - may need later revision

        $existingUserId = $dataSource->fetchPerformerIdByName(strtolower('Bob'), false);
        // strtolower - check that we don't need case matching
        $this->assertEquals($firstUserId, $existingUserId, "Second search for same user must return same ID ($dbName)");
    }

    /**
     * @dataProvider databasesProvider
     * @param string $dbName
     * @param Connection $conn
     */
    public function testInsertAndUpdateTickets($dbName, $conn)
    {
        // Note: we start these tests with an empty, truncated user table
        $dataSource = Factory::datasourceFromDbConnection($conn);
        $newTicketId = $dataSource->storeNewTicket('AC/DC', 114);
        $this->assertTrue(is_numeric($newTicketId), "Storing ticket must return an ID ($dbName)");

        $rawTicket = $dataSource->fetchTicketById($newTicketId);
        $this->assertTrue(is_array($rawTicket));
        $this->assertEquals('AC/DC', $rawTicket['title']);

        $updateResult = $dataSource->updateTicketById($newTicketId, ['title' => 'The Wurzels']);
        $this->assertEquals(1, $updateResult);

        $revisedTicket = $dataSource->fetchTicketById($newTicketId);
        $this->assertEquals('The Wurzels', $revisedTicket['title']);

        $dataSource->storeBandToTicket(1, ['V' => ['Bob']]);

        $populatedTicket = $dataSource->expandTicketData($revisedTicket);
        $this->assertTrue(is_array($populatedTicket['band']));
        $this->assertEquals(1, count($populatedTicket['band']));
        $this->assertEquals(1, count($populatedTicket['band']['V']));

        // change the band
        $dataSource->storeBandToTicket(1, ['V' => ['Steve'], 'G' => ['Eric']]);
        $newBandTicket = $dataSource->fetchTicketById(1);
        $newBandTicket = $dataSource->expandTicketData($newBandTicket);

        $this->assertTrue(is_array($newBandTicket['band']));
        $this->assertEquals(2, count($newBandTicket['band']));
        $this->assertEquals(
            1,
            count($newBandTicket['band']['V']),
            "Should only be one vocalist after update ($dbName)"
        );
    }

    /**
     * @dataProvider databasesProvider
     * @param string $dbName
     * @param Connection $conn
     */
    public function testChangeSongOrder($dbName, $conn)
    {
        $dataSource = Factory::datasourceFromDbConnection($conn);
        $upcoming = $dataSource->fetchUpcomingTickets();
        $this->assertTrue(is_array($upcoming));
        $this->assertEquals(0, count($upcoming), "Should start with no songs after setup ($dbName)");

        //645,647,941
        $trackOrder = [];
        $trackOrder[] = $dataSource->storeNewTicket('first', 647);
        $trackOrder[] = $dataSource->storeNewTicket('second', 941);
        $trackOrder[] = $dataSource->storeNewTicket('third', 645);
        $upcoming = $dataSource->fetchUpcomingTickets();
        $this->assertTrue(is_array($upcoming));
        $this->assertEquals(3, count($trackOrder), "Should have inserted 3 songs ($dbName)");
        $this->assertEquals(3, count($upcoming), "Should have 3 upcoming songs ($dbName)");


        // same order
        $ok = true;
        foreach ($trackOrder as $offset => $id) {
            $ok = $ok && $dataSource->updateTicketOffsetById($id, $offset);
        }
        $this->assertTrue($ok, "Can update tracks to same order ($dbName)");

        // same order, bad data types
        $ok = true;
        foreach ($trackOrder as $offset => $id) {
            $ok = $ok && $dataSource->updateTicketOffsetById("$id", $offset);
        }
        $this->assertTrue($ok, "Can update tracks to same order, even with string ids ($dbName)");

        // same order, bad data types
        $ok = true;
        foreach ($trackOrder as $offset => $id) {
            $ok = $ok && $dataSource->updateTicketOffsetById($id, "$offset");
        }
        $this->assertTrue($ok, "Can update tracks to same order, even with string offsets ($dbName)");

        $reversed = array_reverse($trackOrder);
        $ok = true;
        foreach ($reversed as $offset => $id) {
            $ok = $ok && $dataSource->updateTicketOffsetById($id, $offset);
        }
        $this->assertTrue($ok, "Can update tracks to reversed order ($dbName)");
    }

    /**
     * @dataProvider databasesProvider
     * @param string $dbName
     * @param Connection $conn
     */
    public function testMultipleSongStore($dbName, $conn)
    {
        $dataSource = Factory::datasourceFromDbConnection($conn);

        $duration1 = mt_rand(500, 600);

        $song = new Song();
        $song
            ->setSourceId(self::SONG_SOURCE_TESTS)
            ->setArtist('Disturbed')
            ->setTitle('The Sound of Silence')
            ->setDuration($duration1);

        $dataSource->storeSong($song);
        $firstSongId = $song->getId();
        $this->assertTrue($firstSongId > 0, "Stored song has ID (DB $dbName)");

        $fetched = $dataSource->fetchSongRowById($firstSongId);
        $this->assertEquals($duration1, $fetched['duration']);
        $this->assertEquals(self::SONG_SOURCE_TESTS, $fetched['sourceId']);

        $song = new Song();
        $song
            ->setSourceId(self::SONG_SOURCE_TESTS)
            ->setArtist('Skunk Anansie')
            ->setTitle('Secretly')
            ->setDuration(240);
        $dataSource->storeSong($song);
        $secondSongId = $song->getId();
        $this->assertTrue($secondSongId > $firstSongId, "Second song has ID above first (DB $dbName)");
    }

    /**
     * @dataProvider databasesProvider
     * @param string $dbName
     * @param Connection $conn
     */
    public function testSettings($dbName, $conn)
    {
        $dataSource = Factory::datasourceFromDbConnection($conn);

        $empty = $dataSource->fetchSetting('nosuch');
        $this->assertNull($empty, "$dbName: non-existing setting should return null");

        $rand = mt_rand(1234, 5678);
        $dataSource->updateSetting('randomNumber', $rand);
        $this->assertEquals(
            $rand,
            $dataSource->fetchSetting('randomNumber'),
            "$dbName: existing setting should return stored value"
        );
    }

    /**
     * @param $connectionParams
     * @return \Doctrine\DBAL\Connection
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function makeDbalConnection($connectionParams)
    {
        /** @noinspection PhpInternalEntityUsedInspection */
        $config = new Configuration();

        return DriverManager::getConnection($connectionParams, $config);
    }

    /**
     * @param $filename
     * @return array
     */
    protected function schemaFileToClauses($filename)
    {
        $sqlClauses = [];
        $clause = 0;

        $sqlFile = file($filename);
        foreach ($sqlFile as $line) {
            if (preg_match('/^--/', $line)) {
                //sql comment, skip it
            } elseif (preg_match('/\S/', $line)) {
                //non-empty
                if (!isset($sqlClauses[$clause])) {
                    $sqlClauses[$clause] = $line;
                } else {
                    $sqlClauses[$clause] .= $line;
                }
            } else {
                if (isset($sqlClauses[$clause])) {
                    $clause++; //empty line - next clause
                }
            }
        }
        return $sqlClauses;
    }

    public function databasesProvider()
    {
        $databases = $this->getConfiguredDatabases();
        $packedDbs = [];
        foreach ($databases as $k => $v) {
            $packedDbs[] = [$k, $v];
        }
        return $packedDbs;
    }

    /**
     * @return Connection[]
     */
    protected function getConfiguredDatabases()
    {
        if ($this->databases === false) {
            $databases = [];

            $configDir = dirname(dirname(__DIR__)) . '/config/';

            $configFile = $configDir . 'testConfig.dist.php'; // default
            if (file_exists($configDir . 'testConfig.php')) {
                $configFile = $configDir . 'testConfig.php';
            } elseif (getenv('TRAVIS') === 'true') {
                $configFile = $configDir . 'testConfig.travis.php';
            }

            $this->assertTrue(file_exists($configFile), 'Test config file muse be present');
            /** @noinspection PhpIncludeInspection */
            $config = require($configFile);

            $this->assertArrayHasKey('databases', $config, 'Databases must be configured');
            $this->assertTrue(
                is_array($config['databases']) && count($config['databases']),
                'At least one DB must be configured'
            );

            foreach ($config['databases'] as $k => $v) {
                $connection = $this->makeDbalConnection($v);
                $this->assertTrue($connection instanceof Connection, 'DBAL connection must be created');
                $databases[$k] = $connection;
            }
            $this->databases = $databases;
        }

        return $this->databases;
    }
}
