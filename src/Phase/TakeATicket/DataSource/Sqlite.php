<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 04/10/15
 * Time: 21:09
 */

namespace Phase\TakeATicket\DataSource;

class Sqlite extends AbstractSql
{
    /**
     * @param array $fields
     * @return string
     */
    protected function concatenateEscapedFields($fields)
    {
        return join('||', $fields);
    }
}
