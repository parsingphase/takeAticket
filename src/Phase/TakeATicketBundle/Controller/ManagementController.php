<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 28/01/2017
 * Time: 19:12
 */

namespace Phase\TakeATicketBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ManagementController extends BaseController
{
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
                'displayOptions' => $this->getDisplayOptions(),
            ]
        );
    }

    /**
     * //FIXME Copied from DefaultController which is itself a hack
     *
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

    public function helpAction($section = 'readme')
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $rootDir = realpath(__DIR__.'/../../../../');
        $map = [
            'readme' => $rootDir.'/README.md',
            'CONTRIBUTING' => $rootDir.'/docs/CONTRIBUTING.md',
            'TODO' => $rootDir.'/docs/TODO.md',
        ];

        if (!isset($map[$section])) {
            throw new NotFoundHttpException();
        }

        $markdown = file_get_contents($map[$section]);

        $markdown = preg_replace(
            '#\[docs/\w+.md\]\((./)?docs/(\w+).md\)#',
            '[docs/$2.md](/help/$2)',
            $markdown
        );

        return $this->render(
            ':default:help.html.twig',
            ['helpText' => $markdown]
        );
    }
}
