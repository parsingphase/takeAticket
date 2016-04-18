<?php

namespace Phase\TakeATicketBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        $viewParams = $this->defaultViewParams();
        $viewParams['freeze'] = false;//(bool)$this->dataSource->getSetting('freeze');
        $viewParams['freezeMessage'] = false;//$this->dataSource->getSetting('freezeMessage');

        // replace this example code with whatever you need
        return $this->render('default/upcoming.html.twig', $viewParams);
    }

    /**
     * @return array
     */
    protected function defaultViewParams()
    {
        //$protocol = $_SERVER['HTTPS'] ? 'https' : 'http';
        $protocol = 'http';// $_SERVER['HTTPS'] ? 'https' : 'http';
        $viewParams = [
            //'serverInfo' => $protocol . '://' . $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT'] . '/',
            'serverInfo' => $protocol . '://127.0.0.1:8000/',
            'base_dir' => realpath($this->getParameter('kernel.root_dir') . '/..'),

        ];
        $viewParams['displayOptions'] = $this->getDisplayOptions();
        return $viewParams;
    }

    /**
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

        $displayOptions['upcomingCount']=3;

        return $displayOptions;
    }

}
