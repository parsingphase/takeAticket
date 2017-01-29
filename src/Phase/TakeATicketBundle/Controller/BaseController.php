<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 29/01/2017
 * Time: 12:43
 */

namespace Phase\TakeATicketBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Phase\TakeATicket\DataSource\Factory;

abstract class BaseController extends Controller
{
    protected $dataSource;

    /**
     * @return \Phase\TakeATicket\DataSource\AbstractSql
     */
    protected function getDataStore()
    {
        if (!$this->dataSource) {
            $this->dataSource = Factory::datasourceFromDbConnection($this->get('database_connection'));
        }
        return $this->dataSource;
    }

    /**
     * Get display options from config, with overrides if possible
     *
     * @return array
     */
    protected function getDisplayOptions()
    {
        $displayOptions = [];
        //FIXME reinstate security
        //$displayOptions = isset($this->app['displayOptions']) ? $this->app['displayOptions'] : [];
        //if ($this->app['security']->isGranted(self::MANAGER_REQUIRED_ROLE)) {
        $displayOptions['songInPreview'] = true; // force for logged-in users
        $displayOptions['isAdmin'] = true; // force for logged-in users
        //}
        // FIXME hardcoding

        $displayOptions['upcomingCount'] = 3;

        return $displayOptions;
    }
}