<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 28/01/2017
 * Time: 19:12
 */

namespace Phase\TakeATicketBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class ManagementController extends Controller
{
    use DataStoreAccessTrait;

    public function indexAction()
    {

//        $this->assertRole(self::MANAGER_REQUIRED_ROLE);

        $tickets = $this->getDataStore()->fetchUndeletedTickets();

        $performers = $this->getDataStore()->generatePerformerStats();

        return $this->render(
            'default/manage.html.twig',
            [
                'tickets' => $tickets,
                'performers' => $performers,
                'displayOptions' => $this->getDisplayOptions()
            ]
        );

    }

    /**
     * //FIXME Copied from DefaultController which is itself a hack
     *
     * Get display options from config, with overrides if possible
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