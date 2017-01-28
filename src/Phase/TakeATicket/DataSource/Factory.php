<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 04/10/15
 * Time: 21:05
 */

namespace Phase\TakeATicket\DataSource;

use Doctrine\DBAL\Connection;

class Factory
{
    /**
     * @param Connection $connection
     * @return MySql|Sqlite
     */
    public static function datasourceFromDbConnection(Connection $connection)
    {
        $driverType = $connection->getDriver()->getName();

        switch ($driverType) {
            case 'pdo_mysql':
                return new MySql($connection);
                break;
            case 'pdo_sqlite':
                return new Sqlite($connection);
                break;
            default:
                throw new \InvalidArgumentException("Can't use Db of type $driverType");
        }
    }
}
