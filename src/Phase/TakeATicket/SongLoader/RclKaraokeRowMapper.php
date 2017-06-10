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
class RclKaraokeRowMapper extends SimpleKaraokeRowMapper
{
    /**
     * RclRowMapper constructor.
     * @param $dataStore
     */
    public function __construct(AbstractSql $dataStore)
    {
        $this->fileFields = [
        'B' => self::INPUT_FIELD_ARTIST,
        'C' => self::INPUT_FIELD_TITLE,
        'H' => self::INPUT_FIELD_DURATION_MMSS,
        ];
        parent::__construct($dataStore);
    }

    /**
     * Get formatter name for interface / selection
     *
     * @return string
     */
    public function getFormatterName()
    {
        return 'RCL Karaoke formatter';
    }

    protected function getDefaultPlatformName()
    {
        return 'Rock Band';
    }

    /**
     * Get short name for form keys, CLI etc
     *
     * @return string
     */
    public function getShortName()
    {
        return 'RclKaraoke';
    }
}
