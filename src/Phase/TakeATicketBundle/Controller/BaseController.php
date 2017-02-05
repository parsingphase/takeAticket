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

/**
 * @property  \Symfony\Component\DependencyInjection\Container container
 */
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
        $displayOptions = $this->container->hasParameter('displayOptions') ?
            $this->getParameter('displayOptions') : [];

        // Fixme configure parameter types cleanly
        if (strtolower($displayOptions['songInPreview']) === 'false') {
            $displayOptions['songInPreview'] = false;
        }

        if ($this->isGranted('ROLE_ADMIN')) {
            $displayOptions['songInPreview'] = true; // force for logged-in users
            $displayOptions['isAdmin'] = true; // force for logged-in users
        }

//        $displayOptions['upcomingCount'] = 3;

        return $displayOptions;
    }
}