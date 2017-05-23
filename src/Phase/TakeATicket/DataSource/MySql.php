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
     * {@inheritdoc}
     */
    protected function concatenateEscapedFields($fields)
    {
        return 'CONCAT('.implode(', ', $fields).')';
    }

    /**
     * Overridden to take advantage of autoIds
     *
     * @param $title
     * @param $songId
     *
     * @param null $userId
     * @return false|int
     */
    public function storeNewTicket($title, $songId, $userId = null)
    {
        $conn = $this->getDbConn();
        $max = $conn->fetchAssoc('SELECT max(`offset`) AS o FROM tickets');

        $maxOffset = $max['o'];
        $ticket = [
            'title' => $title,
            'offset' => $maxOffset + 1,
            'songId' => $songId,
        ];

        if ($userId) {
            $ticket['createdBy'] = $userId;
        }
        $res = $conn->insert(self::TICKETS_TABLE, $ticket);

        return $res ? $conn->lastInsertId() : false;
    }

    /**
     * Overridden to take advantage of autoIds
     *
     * @param $performerName
     * @param bool|false    $createMissing
     *
     * @return mixed
     */
    public function fetchPerformerIdByName($performerName, $createMissing = false)
    {
        $conn = $this->getDbConn();
        $sql = 'SELECT id FROM performers p WHERE p.name LIKE :name LIMIT 1';
        $performerId = $conn->fetchColumn($sql, ['name' => $performerName]);

        if ($createMissing && !$performerId) {
            $conn->insert(self::PERFORMERS_TABLE, ['name' => ucwords($performerName)]);
            //add new performer row
            $performerId = $conn->lastInsertId();
        }

        return $performerId;
    }
}
