<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 03/03/2017
 * Time: 18:11
 */

namespace Phase\TakeATicket\SongLoader;


use Doctrine\DBAL\Exception\InvalidArgumentException;
use Phase\TakeATicket\DataSource\AbstractSql;
use Phase\TakeATicket\Model\Instrument;
use Phase\TakeATicket\Model\Platform;
use Phase\TakeATicket\Model\Song;
use Phase\TakeATicket\Model\Source;

/**
 * Imports the RCL XLS file but only loads fields relevant to solo karaoke performance
 */
class RclKaraokeRowMapper implements RowMapperInterface
{

    const INPUT_FIELD_ARTIST = 'artist';
    const INPUT_FIELD_TITLE = 'title';
//    const INPUT_FIELD_HAS_HARMONY = 'hasHarmony';
//    const INPUT_FIELD_HAS_KEYS = 'hasKeys';
//    const INPUT_FIELD_SOURCE = 'source';
//    const INPUT_FIELD_IN_RB3 = 'inRb3';
//    const INPUT_FIELD_IN_RB4 = 'inRb4';
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
//        'D' => self::INPUT_FIELD_HAS_HARMONY,
//        'E' => self::INPUT_FIELD_HAS_KEYS,
//        'I' => self::INPUT_FIELD_SOURCE,
//        'F' => self::INPUT_FIELD_IN_RB3,
//        'G' => self::INPUT_FIELD_IN_RB4,
        'H' => self::INPUT_FIELD_DURATION_MMSS,
    ];

    protected $fieldLookup = [];

    /**
     * @var AbstractSql
     */
    protected $dataStore;

    /**
     * @var Instrument
     */
    protected $vocals;

    /**
     * @var Platform
     */
    protected $platform;

    /**
     * @var Platform
     */
    protected $source;

    /**
     * RclRowMapper constructor.
     * @param $dataStore
     */
    public function __construct(AbstractSql $dataStore)
    {
        $this->dataStore = $dataStore;
        $this->fieldLookup = array_flip($this->fileFields);
    }

    /**
     * Initialise the RowMapper
     *
     * @return void
     */
    public function init()
    {
        $vocals = (new Instrument())->setId(1)->setName('Vocals')->setAbbreviation('V');
        $this->dataStore->storeInstrument($vocals);
        $this->vocals = $vocals;

        $platform = new Platform('Rock Band');
        $this->dataStore->storePlatform($platform);
        $this->platform = $platform;

        $source = new Source('Karaoke'); // TODO for RCL karaoke purposes we could keep the real source
        $this->dataStore->storeSource($source);
        $this->source = $source;
    }

    /**
     * Store a single spreadsheet row
     *
     * @param array $row Character-indexed flattened DB row
     * @return bool True on success
     * @throws \Doctrine\DBAL\Exception\InvalidArgumentException
     */
    public function storeRawRow(array $row)
    {
        $song = new Song();
        $song
            ->setArtist($row[$this->fieldLookup[self::INPUT_FIELD_ARTIST]])
            ->setTitle($row[$this->fieldLookup[self::INPUT_FIELD_TITLE]])
            ->setSourceId($this->source->getId());

        $durationMS = trim($row[$this->fieldLookup[self::INPUT_FIELD_DURATION_MMSS]]);
        if ($durationMS && preg_match('/^\s*(\d+):(\d+)\s*$/', $durationMS, $matches)) {
            $song->setDuration(($matches[1] * 60) + $matches[2]);
        }

        $this->dataStore->storeSong($song); // need id below

        try {
            $this->dataStore->storeSongPlatformLinks($song->getId(), [$this->platform->getId()]);
            $this->dataStore->storeSongInstrumentLinks($song->getId(), [$this->vocals->getId()]);
        } catch (InvalidArgumentException $e) {
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }

        return true;
    }
}