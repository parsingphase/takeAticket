<?php

/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 21/08/15
 * Time: 06:53
 */

namespace Phase\TakeATicket;

use Doctrine\DBAL\Connection;
use Iterator;
use Phase\TakeATicket\DataSource\Factory;
use Phase\TakeATicket\SongLoader\RclKaraokeRowMapper;
use Phase\TakeATicket\SongLoader\RclRockBandRowMapper;
use Phase\TakeATicket\SongLoader\RowMapperInterface;

class SongLoader
{
    const CODE_LENGTH = 6; // min to avoid clashes

    protected $startRow = 2;

    /**
     * @var bool
     */
    protected $showProgress = false;

    /**
     * Class to instantiate as RowMapper
     *
     * Must implement RowMapperInterface
     *
     * @var string
     */
    protected $rowMapperClass = RclRockBandRowMapper::class;

    /**
     * Store contents of specified XLS file to the given database handle
     *
     * @param  string $sourceFile Path to file
     * @param  Connection $dbConn DB connection
     * @return int Number of non-duplicate songs stored
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \Doctrine\DBAL\DBALException
     */
    public function run($sourceFile, Connection $dbConn)
    {

        $objPHPExcel = \PHPExcel_IOFactory::load($sourceFile);
        $dataStore = Factory::datasourceFromDbConnection($dbConn);
        // empty table - reset autoincrement if it has one
        $dataStore->resetCatalogue(); //FIXME clear tickets here too?
        $mapper = $this->getRowMapper($dataStore);
        $mapper->init();

        $iterator = $objPHPExcel->getSheet()->getRowIterator($this->startRow);

        $i = 1;
        foreach ($iterator as $sourceRow) {
            $flattenedRow = [];
            /**
             * @var \PHPExcel_Worksheet_Row $sourceRow
             */
            $cells = $sourceRow->getCellIterator();
            /**
             * @var Iterator $cells
             */
            foreach ($cells as $cell) {
                /**
                 * @var \PHPExcel_Cell $cell
                 */
                $column = $cell->getColumn();
                $content = $cell->getFormattedValue();

                $flattenedRow[$column] = trim($content);
            }

            if (implode($flattenedRow, '') !== '') {
                $storedOk = $mapper->storeRawRow($flattenedRow);
                if ($storedOk) {
                    $this->printProgressMarker($i);
                } else {
                    print('x');
                }
                $i++;
            }
        }

        $total = $i - 1;
        if ($this->showProgress) {
            echo "\nImported $total songs\n";
        }
        return $total;
    }

    /**
     * @param bool $showProgress
     * @return SongLoader
     */
    public function setShowProgress($showProgress)
    {
        $this->showProgress = $showProgress;
        return $this;
    }

    /**
     * @return string
     */
    public function getRowMapperClass()
    {
        return $this->rowMapperClass;
    }

    /**
     * Set RowMapper class to use
     *
     * @param string $rowMapperClass
     * @return SongLoader
     * @throws \InvalidArgumentException If classname not valid
     */
    public function setRowMapperClass($rowMapperClass)
    {
        $class = new \ReflectionClass($rowMapperClass);
        if ($class->isSubclassOf(RowMapperInterface::class)) {
            $this->rowMapperClass = $rowMapperClass;
        } else {
            throw new \InvalidArgumentException(
                "$rowMapperClass must implement " . RowMapperInterface::class
            );
        }
        return $this;
    }

    /**
     * @param $i
     */
    protected function printProgressMarker($i)
    {
        if ($this->showProgress) {
            if (!($i % 100)) {
                echo $i;
            } else {
                if (!($i % 10)) {
                    echo '.';
                }
            }
            if (!($i % 1000)) {
                echo "\n";
            }
        }
    }

    /**
     * Get the currently configured RowMapper
     *
     * @param $dataStore
     * @return RowMapperInterface
     */
    protected function getRowMapper($dataStore)
    {
        $rowMapperClass = $this->getRowMapperClass();
        return new $rowMapperClass($dataStore);
    }
}
