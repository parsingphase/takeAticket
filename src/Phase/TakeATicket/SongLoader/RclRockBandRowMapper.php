<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 25/02/2017
 * Time: 16:54
 */

namespace Phase\TakeATicket\SongLoader;

use Phase\TakeATicket\DataSource\AbstractSql;
use Phase\TakeATicket\Model\Instrument;
use Phase\TakeATicket\Model\Platform;
use Phase\TakeATicket\Model\Song;
use Phase\TakeATicket\Model\Source;

/**
 * Input file row mapper for the Rock Club London XLS file format
 *
 * To load other file formats, extend / replace this class and implement RowMapperInterface
 */
class RclRockBandRowMapper implements RowMapperInterface
{
    /**
     * @var AbstractSql
     */
    protected $dataStore;
    /**
     * @var bool
     */
    protected $manualIds;
    /**
     * @var int
     */
    protected $lastId;

    const RESULT_OK = 0;
    const RESULT_ERROR = 1;
    const RESULT_DUPLICATE = 2;

    const INPUT_FIELD_ARTIST = 'artist';
    const INPUT_FIELD_TITLE = 'title';
    const INPUT_FIELD_HAS_HARMONY = 'hasHarmony';
    const INPUT_FIELD_HAS_KEYS = 'hasKeys';
    const INPUT_FIELD_SOURCE = 'source';
    const INPUT_FIELD_IN_RB3 = 'inRb3';
    const INPUT_FIELD_IN_RB4 = 'inRb4';
    const INPUT_FIELD_DURATION_MMSS = 'duration_mmss';
    const INPUT_FIELD_DURATION = 'duration';

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

    protected $fieldLookup = [];

    /**
     * RclRowMapper constructor.
     * @param $dataStore
     */
    public function __construct(AbstractSql $dataStore)
    {
        $this->dataStore = $dataStore;
        $this->manualIds = $dataStore->getDbConn()->getDriver() === 'pdo_mysql';
        $this->lastId = 0;
        $this->fieldLookup = array_flip($this->fileFields);
    }

    /**
     * Perform any once-only operations
     *
     * @return void
     */
    public function init()
    {
        //TODO Either load icons from config or remove config option
        $instruments = [
            (new Instrument())->setId(1)->setName('Vocals')->setAbbreviation('V')
                ->setIconHtml('<img src="local/rb-mic.png" class="instrumentIcon"/>'),
            (new Instrument())->setId(2)->setName('Guitar')->setAbbreviation('G')
                ->setIconHtml('<img src="local/rb-guitar.png" class="instrumentIcon"/>'),
            (new Instrument())->setId(3)->setName('Bass')->setAbbreviation('B')
                ->setIconHtml('<img src="local/rb-bass.png" class="instrumentIcon"/>'),
            (new Instrument())->setId(4)->setName('Drums')->setAbbreviation('D')
                ->setIconHtml('<img src="local/rb-drums.png" class="instrumentIcon"/>'),
            (new Instrument())->setId(5)->setName('Keyboard')->setAbbreviation('K')
                ->setIconHtml('<img src="local/rb-keys.png" class="instrumentIcon"/>'),
        ];

        foreach ($instruments as $instrument) {
            $this->dataStore->storeInstrument($instrument);
        }

        $platforms = [
            new Platform('RB3'),
            new Platform('RB4'),
        ];

        foreach ($platforms as $platform) {
            $this->dataStore->storePlatform($platform);
        }
    }

    /**
     * Takes a row with column indexes and stores it to the database
     *
     * Primary table is songs (one per input line)
     * Must also manage instruments, platforms, sources
     *
     * @param array $row Simple array representation of row (character-indexed)
     * @return bool
     */
    public function storeRawRow(array $row)
    {
        $song = new Song();
        $song
            ->setArtist($row[$this->fieldLookup[self::INPUT_FIELD_ARTIST]])
            ->setTitle($row[$this->fieldLookup[self::INPUT_FIELD_TITLE]]);

        $durationMS = trim($row[$this->fieldLookup[self::INPUT_FIELD_DURATION_MMSS]]);
        if ($durationMS && preg_match('/^\s*(\d+):(\d+)\s*$/', $durationMS, $matches)) {
            $song->setDuration(($matches[1] * 60) + $matches[2]);
        }

        $sourceName = trim($row[$this->fieldLookup[self::INPUT_FIELD_SOURCE]]);
        if ($sourceName) {
            $source = $this->dataStore->fetchSourceByName($sourceName);
            if (!$source) {
                $source = new Source($sourceName);
                $this->dataStore->storeSource($source);
            }
            if ($source) {
                $song->setSourceId($source->getId());
            }
        }

        $this->dataStore->storeSong($song); // Store song before all xrefs as we need ID

        // Platforms
        $platformIds = [];
        $platformFields = [
            self::INPUT_FIELD_IN_RB3 => 'RB3',
            self::INPUT_FIELD_IN_RB4 => 'RB4',
        ];
        foreach ($platformFields as $field => $platformName) {
            if (trim($row[$this->fieldLookup[$field]])) {
                $platform = $this->dataStore->fetchPlatformByName($platformName);
                if (!$platform) {
                    $platform = new Platform($platformName);
                    $this->dataStore->storePlatform($platform);
                }
                $platformIds[] = $platform->getId();
            }
        }
        $this->dataStore->storeSongPlatformLinks($song->getId(), $platformIds);

        // Instruments - all stored at init(); // TODO add harmony
        $instruments = ['Vocals', 'Guitar', 'Bass', 'Drums'];
        if (trim($row[$this->fieldLookup[self::INPUT_FIELD_HAS_KEYS]])) {
            $instruments[] = 'Keyboard';
        }

        $datastore = $this->dataStore;
        $instrumentIds = array_map(
            function ($name) use ($datastore) {
                $instrument = $datastore->fetchInstrumentByName($name);
                return $instrument ? $instrument->getId() : null;
            },
            $instruments
        );

        $this->dataStore->storeSongInstrumentLinks($song->getId(), $instrumentIds);


        return true;
    }

    public function getFormatterName()
    {
        return 'RCL multi-instrument formatter';
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
     * Get short name for form keys, CLI etc. Must be unique
     *
     * @return string
     */
    public function getShortName()
    {
        return 'RclRockBand';
    }
}
