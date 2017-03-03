<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 03/03/2017
 * Time: 20:09
 */

namespace Phase\TakeATicket\SongLoader;

use Doctrine\DBAL\Connection;
use Phase\TakeATicket\DataSource\Factory;

class RowMapperManager
{

    /**
     * @var string[]
     */
    protected $rowMapperClasses = [];

    /**
     * @var Connection
     */
    protected $dbConn;

    /**
     * @var RowMapperInterface[]
     */
    protected $rowMappers;

    /**
     * RowMapperManager constructor.
     * @param Connection $dbConn
     * @param \string[] $rowMapperClasses
     */
    public function __construct(Connection $dbConn, array $rowMapperClasses)
    {
        $this->dbConn = $dbConn;
        $dataStore = Factory::datasourceFromDbConnection($dbConn);
        $this->rowMapperClasses = $rowMapperClasses;

        foreach ($rowMapperClasses as $class) {
            $this->validateClass($class);
            $mapper = new $class($dataStore);
            /** @var RowMapperInterface $mapper */
            $this->rowMappers[strtolower($mapper->getShortName())] = $mapper;
        }
    }

    /**
     * Get the registered row mapper with the specified short name
     *
     * @param $shortName
     * @return null|RowMapperInterface
     */
    public function getRowMapperByShortName($shortName)
    {
        $shortName = strtolower($shortName);
        return array_key_exists($shortName, $this->rowMappers) ? $this->rowMappers[$shortName] : null;
    }

    /**
     * Get the registered row mapper with the specified short name
     *
     * @param $shortName
     * @return null|RowMapperInterface
     */
    public function getRowMapperClassByShortName($shortName)
    {
        $mapper = $this->getRowMapperByShortName($shortName);
        return $mapper ? get_class($mapper) : null;
    }

    public function getRowMapperShortNames()
    {
        return array_keys($this->rowMappers);
    }

    /**
     * Ensure that provided classname is a valid RowMapperInterface
     *
     * @param $rowMapperClass
     * @throws \InvalidArgumentException
     */
    protected function validateClass($rowMapperClass)
    {
        $class = new \ReflectionClass($rowMapperClass);
        if ($class->isSubclassOf(RowMapperInterface::class)) {
            $this->rowMapperClass = $rowMapperClass;
        } else {
            throw new \InvalidArgumentException(
                "$rowMapperClass must implement " . RowMapperInterface::class
            );
        }
    }
}
