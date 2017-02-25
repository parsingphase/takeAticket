<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 29/01/2017
 * Time: 12:43
 */

namespace Phase\TakeATicketBundle\Controller;

use Phase\TakeATicket\DataSource\AbstractSql;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Phase\TakeATicket\DataSource\Factory;

/**
 * @property  \Symfony\Component\DependencyInjection\Container container
 */
abstract class BaseController extends Controller
{
    /**
     * Use getDataStore() rather than accessing directly as this may not be populated
     *
     * @var AbstractSql
     */
    protected $dataSource;

    /**
     * @return AbstractSql
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
        $displayOptions = $this->container->hasParameter('displayOptions') ?
            $this->getParameter('displayOptions') : [];

        $displayOptions['upcomingCount'] = $this->getDataStore()->getSetting('upcomingCount') ?: 3;
        $displayOptions['songInPreview'] = (bool)$this->getDataStore()->getSetting('songInPreview');

        if ($this->isGranted('ROLE_ADMIN')) {
            $displayOptions['songInPreview'] = true; // force for logged-in users
            $displayOptions['isAdmin'] = true; // force for logged-in users
        }

        return $displayOptions;
    }
}
