<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 28/01/2017
 * Time: 17:17
 */

namespace Phase\TakeATicketBundle\Controller;

use Phase\TakeATicket\DataSource\Factory;

trait DataStoreAccessTrait
{
    abstract protected function get($param);

    /**
     * @return \Phase\TakeATicket\DataSource\AbstractSql
     */
    protected function getDataStore()
    {
        return Factory::datasourceFromDbConnection($this->get('database_connection'));
    }
}
