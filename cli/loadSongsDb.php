<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 20/08/15
 * Time: 13:59
 */

namespace Phase\TakeATicket;

use Doctrine\DBAL\Connection;

class SongLoader
{
    private $fileFields = [
        'B' => 'artist',
        'C' => 'song',
        'D' => 'source',
        'E' => 'hasHarmony',
        'F' => 'hasKeys'
    ];

    private $startRow = 2;

    const CODELENGTH = 6; // min to avoid clashes

    public function run($sourceFile, Connection $dbConn)
    {
        $objPHPExcel = \PHPExcel_IOFactory::load($sourceFile);

        $dbConn->exec('delete from songs'); // empty table

        $iterator = $objPHPExcel->getSheet()->getRowIterator($this->startRow);

        $i = 0;
        $codeStored = [];

        foreach ($iterator as $row) {
            $storable = [];

            /** @var \PHPExcel_Worksheet_Row $row */
            $rowIdx = $row->getRowIndex();
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
                if (!isset($storable['id'])) {
                    $storable['id'] = $i;
                }

                if (!isset($storable['codeNumber'])) {
                    $storable['codeNumber'] = (string)$this->makeCodeNumberFromArray($storable);
                }

                if (isset($codeStored[$storable['codeNumber']])) {
                    print("\nDuplicate: " . $storable['artist'] . ': ' . $storable['song'] . "\n");
                } else {
                    $dbConn->insert('songs', $storable);
                    $i++;
                    $codeStored[$storable['codeNumber']] = true;
                }

                if (!($i % 100)) {
                    echo $i;
                } else if (!($i % 10)) {
                    echo '.';
                }
                if (!($i % 1000)) {
                    echo "\n";
                }
            }
        }
        echo "\nImported $i songs\n";
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
        $normalisedSong = strtoupper(preg_replace('/[^a-z0-9]/i', '', $storable['artist']) . '::' . preg_replace('/[^a-z0-9]/i', '', $storable['song']));
        $hash = (string)md5($normalisedSong);
        $code = strtoupper(substr($hash, 0, self::CODELENGTH));
//        print("\n$hash     $code      $normalisedSong  ");
        return $code;
    }
}

require(dirname(__DIR__) . '/vendor/autoload.php');

$loader = new SongLoader();

/** @noinspection PhpUndefinedVariableInspection */
if ($argc !== 2) {
    /** @noinspection PhpUndefinedVariableInspection */
    $loader->usage($argv[0]);
    exit(1);
}
/** @noinspection PhpUndefinedVariableInspection */
$sourceFile = $argv[1];

$app = require(dirname(__DIR__) . '/www/app.php');

$loader->run($sourceFile, $app['db']);