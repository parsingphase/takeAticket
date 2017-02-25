<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 25/02/2017
 * Time: 18:30
 */

namespace Phase\TakeATicket\Model;

use Phase\TakeATicket\DataSource\AbstractSql;

class Song
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $artist;

    /**
     * @var string
     */
    protected $title;

    /**
     * @var int
     */
    protected $sourceId;

    /**
     * Seconds
     *
     * @var int
     */
    protected $duration;

    /**
     * @var string
     */
    protected $codeNumber;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Song
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getArtist()
    {
        return $this->artist;
    }

    /**
     * @param string $artist
     * @return Song
     */
    public function setArtist($artist)
    {
        $this->artist = $artist;
        $this->updateLookupCode();
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return Song
     */
    public function setTitle($title)
    {
        $this->title = $title;
        $this->updateLookupCode();
        return $this;
    }

    /**
     * @return int
     */
    public function getSourceId()
    {
        return $this->sourceId;
    }

    /**
     * @param int $sourceId
     * @return Song
     */
    public function setSourceId($sourceId)
    {
        $this->sourceId = $sourceId;
        return $this;
    }

    /**
     * @return int
     */
    public function getDuration()
    {
        return $this->duration;
    }

    /**
     * @param int $duration
     * @return Song
     */
    public function setDuration($duration)
    {
        $this->duration = $duration;
        return $this;
    }

    /**
     * @return string
     */
    public function getCodeNumber()
    {
        return $this->codeNumber;
    }

    /**
     * @param string $codeNumber
     * @return Song
     */
    protected function setCodeNumber($codeNumber)
    {
        $this->codeNumber = $codeNumber;
        return $this;
    }

    protected function updateLookupCode()
    {
        $codeNumber = null;
        if ($this->artist && $this->title) {
            $codeNumber = $this->generateLookupCode($this->artist, $this->title);
        }
        $this->setCodeNumber($codeNumber);
    }

    protected function generateLookupCode($artist, $title)
    {
        $normalisedSong = strtoupper(
            preg_replace('/[^a-z0-9]/i', '', $artist) .
            '::' .
            preg_replace('/[^a-z0-9]/i', '', $title)
        );
        $hash = (string)md5($normalisedSong);
        return strtoupper(substr($hash, 0, AbstractSql::CODE_LENGTH));
    }
}
