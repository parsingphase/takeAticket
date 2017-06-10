<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 25/02/2017
 * Time: 17:19
 */

namespace Phase\TakeATicket\SongLoader;

use Phase\TakeATicket\DataSource\AbstractSql;

interface RowMapperInterface
{

    /**
     * RowMapperInterface constructor.
     *
     * @param AbstractSql $dataStore
     */
    public function __construct(AbstractSql $dataStore);

    /**
     * Get formatter name for interface
     *
     * @return string
     */
    public function getFormatterName();

    /**
     * Get short name for form keys, CLI etc. Must be unique
     *
     * @return string
     */
    public function getShortName();

    /**
     * Initialise the RowMapper. May create some DB records if already known
     *
     * Constructor must not have side effects
     *
     * @return void
     */
    public function init();

    /**
     * Store a single spreadsheet row
     *
     * @param array $row Character-indexed flattened DB row
     * @return bool True on success
     */
    public function storeRawRow(array $row);
}
