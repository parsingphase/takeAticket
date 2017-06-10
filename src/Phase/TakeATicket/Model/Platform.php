<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 25/02/2017
 * Time: 18:21
 */

namespace Phase\TakeATicket\Model;

class Platform
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
     * Platform constructor.
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
     * @return Platform
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
     * @return Platform
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }
}
