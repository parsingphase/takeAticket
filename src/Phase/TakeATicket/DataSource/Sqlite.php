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
     *
     * @return string
     */
    protected function concatenateEscapedFields($fields)
    {
        return implode('||', $fields);
    }

    /**
     * Get current value of a named setting, NULL if missing
     *
     * @param  $key
     * @return mixed|null
     */
    public function fetchSetting($key)
    {
        $conn = $this->getDbConn();
        $query = $conn->executeQuery('SELECT settingValue FROM settings WHERE settingKey=:key', ['key' => $key]);

        return $query->fetchColumn(); // rowCount seems to not work on sqlite
    }
}
