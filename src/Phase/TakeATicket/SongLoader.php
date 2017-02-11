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

class SongLoader
{
    const CODE_LENGTH = 6; // min to avoid clashes

    const INPUT_FIELD_ARTIST = 'artist';
    const INPUT_FIELD_TITLE = 'title';
    const INPUT_FIELD_HAS_HARMONY = 'hasHarmony';
    const INPUT_FIELD_HAS_KEYS = 'hasKeys';
    const INPUT_FIELD_SOURCE = 'source';
    const INPUT_FIELD_IN_RB3 = 'inRb3';
    const INPUT_FIELD_IN_RB4 = 'inRb4';
    const INPUT_FIELD_DURATION_MMSS = 'duration_mmss';

    /**
     * Column map for input file
     *
     * @var string[]
     */
    protected $fileFields = [
        'B' => self::INPUT_FIELD_ARTIST,
        'C' => self::INPUT_FIELD_TITLE,
        'D' => self::INPUT_FIELD_HAS_HARMONY,
        'E' => self::INPUT_FIELD_HAS_KEYS,
        'I' => self::INPUT_FIELD_SOURCE,
        'F' => self::INPUT_FIELD_IN_RB3,
        'G' => self::INPUT_FIELD_IN_RB4,
        'H' => self::INPUT_FIELD_DURATION_MMSS,
    ];

    protected $startRow = 2;

    protected $showProgress = false;

    /**
     * @var array[]
     */
    protected $duplicates = [];

    const INPUT_FIELD_DURATION = 'duration';

    /**
     * Store contents of specified XLS file to the given database handle
     *
     * @param  string     $sourceFile Path to file
     * @param  Connection $dbConn     DB connection
     * @return int Number of non-duplicate songs stored
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \Doctrine\DBAL\DBALException
     */
    public function run($sourceFile, Connection $dbConn)
    {
        $driverType = $dbConn->getDriver()->getName();
        $sqlite = (stripos($driverType, 'sqlite') !== false);

        $objPHPExcel = \PHPExcel_IOFactory::load($sourceFile);

        //TODO move to Storage class
        $dbConn->exec($sqlite ? 'DELETE FROM songs' : 'TRUNCATE TABLE songs');
        // empty table - reset autoincrement if it has one

        $iterator = $objPHPExcel->getSheet()->getRowIterator($this->startRow);

        $i = 1;
        $codeStored = [];

        foreach ($iterator as $row) {
            $raw = [];
            /**
 * @var \PHPExcel_Worksheet_Row $row
*/
            //            $rowIdx = $row->getRowIndex();
            $cells = $row->getCellIterator();
            /**
 * @var Iterator $cells
*/
            foreach ($cells as $cell) {
                /**
 * @var \PHPExcel_Cell $cell
*/
                $column = $cell->getColumn();
                $content = $cell->getFormattedValue();

                $targetField = isset($this->fileFields[$column]) ? $this->fileFields[$column] : null;
                if ($targetField) {
                    $raw[$targetField] = trim($content);
                }
            }
            //map row
            $storable = $this->rowToStorable($raw);

            if (implode($storable, '') !== '') {
                if ($sqlite && (!isset($storable['id']))) {
                    $storable['id'] = $i;
                }

                if (!isset($storable['codeNumber'])) {
                    $storable['codeNumber'] = (string)$this->makeCodeNumberFromArray($storable);
                }

                if (isset($codeStored[$storable['codeNumber']])) {
                    if ($this->showProgress) {
                        echo
                            "\nDuplicate: " . $storable[self::INPUT_FIELD_ARTIST] . ': ' .
                            $storable[self::INPUT_FIELD_TITLE] . "\n";
                    }
                    $this->duplicates[] = $storable;
                } else {
                    $dbConn->insert('songs', $storable);
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
                    ++$i;
                    $codeStored[$storable['codeNumber']] = true;
                }
            }
        }
        $total = $i - 1;
        if ($this->showProgress) {
            echo "\nImported $total songs\n";
        }
        return $total;
    }

    public function usage($scriptname = null)
    {
        echo "Usage: php $scriptname path/to/sourcefile.xlsx\n";
    }

    /**
     * @param $storable
     *
     * @return string
     */
    public function makeCodeNumberFromArray($storable)
    {
        $normalisedSong = strtoupper(
            preg_replace('/[^a-z0-9]/i', '', $storable[self::INPUT_FIELD_ARTIST]) .
            '::' .
            preg_replace('/[^a-z0-9]/i', '', $storable[self::INPUT_FIELD_TITLE])
        );
        $hash = (string)md5($normalisedSong);
        return strtoupper(substr($hash, 0, self::CODE_LENGTH));
    }

    /**
     * @param $row
     *
     * @return array
     */
    protected function rowToStorable($row)
    {
        $directMapFields = [
            self::INPUT_FIELD_ARTIST,
            self::INPUT_FIELD_TITLE,
            self::INPUT_FIELD_HAS_HARMONY,
            self::INPUT_FIELD_HAS_KEYS,
            self::INPUT_FIELD_SOURCE,
            self::INPUT_FIELD_DURATION,
        ];

        $trueIfPresentFields = [
            self::INPUT_FIELD_HAS_HARMONY,
            self::INPUT_FIELD_HAS_KEYS,
            self::INPUT_FIELD_IN_RB3,
            self::INPUT_FIELD_IN_RB4,
        ];

        $storable = [];

        foreach ($directMapFields as $k) {
            $storable[$k] = empty($row[$k]) ? null : $row[$k];
        }

        foreach ($trueIfPresentFields as $k) {
            $storable[$k] = empty($row[$k]) ? 0 : 1;
        }

        if (isset($row[self::INPUT_FIELD_DURATION_MMSS])
            && preg_match('/^\s*(\d+):(\d+)\s*$/', $row[self::INPUT_FIELD_DURATION_MMSS], $matches)
        ) {
            $storable[self::INPUT_FIELD_DURATION] = ($matches[1] * 60) + $matches[2];
        }

        return $storable;
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
}
