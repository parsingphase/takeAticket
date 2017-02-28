<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 29/01/2017
 * Time: 12:43
 */

namespace Phase\TakeATicketBundle\Controller;

use Phase\TakeATicket\DataSource\AbstractSql;
use Phase\TakeATicket\Model\Instrument;
use Phase\TakeATicket\Model\Platform;
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
            $this->dataSource->setUpcomingCount($this->getUpcomingCount()); // FIXME reduce navel-gazing
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

        $displayOptions['upcomingCount'] = $this->getUpcomingCount();
        $displayOptions['songInPreview'] = (bool)$this->getDataStore()->fetchSetting('songInPreview');

        if ($this->isGranted('ROLE_ADMIN')) {
            $displayOptions['songInPreview'] = true; // force for logged-in users
            $displayOptions['isAdmin'] = true; // force for logged-in users
        }

        return $displayOptions;
    }

    /**
     * @return int
     */
    protected function getUpcomingCount()
    {
        return $this->getDataStore()->fetchSetting('upcomingCount') ?: 3;
    }

    /**
     * @return array
     */
    protected function defaultViewParams()
    {
        $allPlatforms = $this->getDataStore()->fetchAllPlatforms();
        $platformNames = array_map(function (Platform $platform) {
            return $platform->getName();
        }, $allPlatforms);

        $instruments = $this->getDataStore()->fetchAllInstruments();
        $instrumentOrder = array_map(function (Instrument $instrument) {
            return $instrument->getAbbreviation();
        }, $instruments);

        /** @noinspection RealpathInSteamContextInspection */
        $viewParams = [
            'base_dir' => realpath($this->getParameter('kernel.root_dir') . '/..'),
            'allPlatforms' => $platformNames,
            'instrumentOrder' => $instrumentOrder,
        ];
        $viewParams['displayOptions'] = $this->getDisplayOptions();

        return $viewParams;
    }
}
