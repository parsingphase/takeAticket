<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 25/02/2017
 * Time: 17:48
 */

namespace Phase\TakeATicket\Model;

class Instrument
{
    /**
     * DB ID
     *
     * @var int
     */
    protected $id;

    /**
     * Full name
     *
     * @var string
     */
    protected $name;

    /**
     * Short (single-letter?) name
     *
     * @var string
     */
    protected $abbreviation;

    /**
     * HTML fragment for display icons
     *
     * @var string
     */
    protected $iconHtml;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Instrument
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Instrument
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getAbbreviation()
    {
        return $this->abbreviation;
    }

    /**
     * @param string $abbreviation
     * @return Instrument
     */
    public function setAbbreviation($abbreviation)
    {
        $this->abbreviation = $abbreviation;
        return $this;
    }

    /**
     * @return string
     */
    public function getIconHtml()
    {
        return $this->iconHtml;
    }

    /**
     * @param string $iconHtml
     * @return Instrument
     */
    public function setIconHtml($iconHtml)
    {
        $this->iconHtml = $iconHtml;
        return $this;
    }
}
