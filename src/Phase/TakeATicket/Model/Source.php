<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 25/02/2017
 * Time: 18:18
 */

namespace Phase\TakeATicket\Model;

class Source
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * Source constructor.
     * @param string $name
     */
    public function __construct($name = null)
    {
        $this->name = $name;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return Source
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
     * @return Source
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
}
