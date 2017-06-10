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
 * Imports an XLS file with Column A: Artist, B: Song Title, C: Duration (min:sec)
 */
class SimpleKaraokeRowMapper implements RowMapperInterface
{

    const INPUT_FIELD_ARTIST = 'artist';
    const INPUT_FIELD_TITLE = 'title';
    const INPUT_FIELD_DURATION_MMSS = 'duration_mmss';
    const INPUT_FIELD_DURATION = 'duration';

    /**
     * Column map for input file
     *
     * @var string[]
     */
    protected $fileFields = [
        'A' => self::INPUT_FIELD_ARTIST,
        'B' => self::INPUT_FIELD_TITLE,
        'C' => self::INPUT_FIELD_DURATION_MMSS,
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
     * Get formatter name for interface / selection
     *
     * @return string
     */
    public function getFormatterName()
    {
        return 'Simple Karaoke formatter';
    }

    protected function getDefaultPlatformName()
    {
        return 'Karaoke';
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

        $platform = new Platform($this->getDefaultPlatformName());
        $this->dataStore->storePlatform($platform);
        $this->platform = $platform;

        $source = new Source('Karaoke');
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

    /**
     * Get short name for form keys, CLI etc
     *
     * @return string
     */
    public function getShortName()
    {
        return 'Karaoke';
    }
}
