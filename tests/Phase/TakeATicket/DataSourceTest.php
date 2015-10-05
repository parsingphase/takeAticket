<?php

namespace Phase\TakeATicket;

use /** @noinspection PhpInternalEntityUsedInspection */
    Doctrine\DBAL\Configuration; // PHPStorm warning quirk
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Phase\TakeATicket\DataSource\Factory;

class DataSourceTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Doctrine\DBAL\Connection[]
     */
    protected $databases;


    public function setUp()
    {
        $configDir = dirname(dirname(dirname(__DIR__))) . '/config/';

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
//            print("Got config for $k");
            $connection = $this->makeDbalConnection($v);
            $this->assertTrue($connection instanceof Connection, 'DBAL connection must be created');
            $this->databases[$k] = $connection;
        }

        $sqlDir = dirname(dirname(dirname(__DIR__))) . '/sql/';

        $sqlSourceFiles = [];
        $sqlSourceFiles['mysql'] = $sqlDir . 'db-mysql.sql';
        $sqlSourceFiles['sqlite'] = $sqlDir . 'db-sqlite.sql';

        $songSqlFile = $sqlDir . 'sampleSongs.sql';
        $songInserts = file($songSqlFile);

        $this->assertTrue(is_array($songInserts) && count($songInserts), 'Sample songs required');

        foreach ($sqlSourceFiles as $k => $v) {
            $sqlClauses = $this->schemaFileToClauses($v);

            if (isset($this->databases[$k])) {
                foreach ($sqlClauses as $sql) {
                    $this->databases[$k]->exec($sql);
                }

                foreach ($songInserts as $insertSql) {
                    if ($insertSql && !preg_match('/^\s+-- /', $insertSql)) {
                        $this->databases[$k]->exec($insertSql);
                    }
                }
            }
        }
    }

    public function testSearch()
    {
        $searchString = 'When you';
        // should return The Killers: When You Were Young

        foreach ($this->databases as $k => $conn) {
            print("Test search for DB: $k\n");
            $dataSource = Factory::datasourceFromDbConnection($conn);
            $hits = $dataSource->findSongsBySearchString($searchString);
            $this->assertTrue(is_array($hits));
//            var_dump($hits);
            $this->assertEquals(1, count($hits), 'Should return 1 hit for search with DB '.$k);
        }
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

        $conn = DriverManager::getConnection($connectionParams, $config);

        return $conn;
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
}
