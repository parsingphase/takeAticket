<?php

/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 21/08/15
 * Time: 06:53
 */
namespace Phase\TakeATicket;

use Doctrine\DBAL\Connection;

class SongLoader
{
    private $fileFields = [
        'B' => 'artist',
        'C' => 'title',
        'D' => 'source',
        'E' => 'hasHarmony',
        'F' => 'hasKeys'
    ];

    private $startRow = 2;

    const CODE_LENGTH = 6; // min to avoid clashes

    public function run($sourceFile, Connection $dbConn)
    {
        $driverType = $dbConn->getDriver()->getName();
        $sqlite = preg_match('/sqlite/i', $driverType);

        $objPHPExcel = \PHPExcel_IOFactory::load($sourceFile);

        $dbConn->exec($sqlite ? 'DELETE FROM songs' : 'TRUNCATE TABLE songs');
        // empty table - reset autoincrement if it has one

        $iterator = $objPHPExcel->getSheet()->getRowIterator($this->startRow);

        $i = 1;
        $codeStored = [];

        foreach ($iterator as $row) {
            $storable = [];

            /** @var \PHPExcel_Worksheet_Row $row */
//            $rowIdx = $row->getRowIndex();
            $cells = $row->getCellIterator();
            foreach ($cells as $cell) {
                /** @var \PHPExcel_Cell $cell */
                $column = $cell->getColumn();
                $content = $cell->getFormattedValue();

                $targetField = isset($this->fileFields[$column]) ? $this->fileFields[$column] : null;
                if ($targetField) {
                    $storable[$targetField] = trim($content);
                }
                $storable['hasHarmony'] = empty($storable['hasHarmony']) ? 1 : 0;
                $storable['hasKeys'] = empty($storable['hasKeys']) ? 1 : 0;
            }

            if (strlen(join($storable, ''))) {
                if ($sqlite && (!isset($storable['id']))) {
                    $storable['id'] = $i;
                }

                if (!isset($storable['codeNumber'])) {
                    $storable['codeNumber'] = (string)$this->makeCodeNumberFromArray($storable);
                }

                if (isset($codeStored[$storable['codeNumber']])) {
                    print("\nDuplicate: " . $storable['artist'] . ': ' . $storable['title'] . "\n");
                } else {
                    $dbConn->insert('songs', $storable);
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
                    $i++;
                    $codeStored[$storable['codeNumber']] = true;
                }


            }
        }
        $total = $i - 1;
        echo "\nImported $total songs\n";
    }

    public function usage($scriptname = null)
    {
        print("Usage: php $scriptname path/to/sourcefile.xlsx\n");
    }

    /**
     * @param $storable
     * @return string
     */
    public function makeCodeNumberFromArray($storable)
    {
        $normalisedSong = strtoupper(
            preg_replace('/[^a-z0-9]/i', '', $storable['artist']) .
            '::' .
            preg_replace('/[^a-z0-9]/i', '', $storable['title'])
        );
        $hash = (string)md5($normalisedSong);
        $code = strtoupper(substr($hash, 0, self::CODE_LENGTH));
//        print("\n$hash     $code      $normalisedSong  ");
        return $code;
    }
}
