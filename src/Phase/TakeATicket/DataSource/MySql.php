<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 04/10/15
 * Time: 21:08
 */

namespace Phase\TakeATicket\DataSource;

class MySql extends AbstractSql
{
    /**
     * @inheritDoc
     */
    protected function concatenateEscapedFields($fields)
    {
        return ('CONCAT(' . join(', ', $fields) . ')');
    }
}
