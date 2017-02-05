<?php

namespace Phase\TakeATicketBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DefaultController extends BaseController
{
    public function indexAction()
    {
        $viewParams = $this->defaultViewParams();
        $viewParams['freeze'] = $this->getDataStore()->getSetting('freeze');
        $viewParams['freezeMessage'] = $this->getDataStore()->getSetting('freezeMessage');

        // replace this example code with whatever you need
        return $this->render('default/upcoming.html.twig', $viewParams);
    }

    public function songSearchAction()
    {
        $viewParams = $this->defaultViewParams();
        $viewParams['freeze'] = $this->getDataStore()->getSetting('freeze');
        return $this->render('default/songSearch.html.twig', $viewParams);
    }

    /**
     * @return array
     */
    protected function defaultViewParams()
    {
        //$protocol = $_SERVER['HTTPS'] ? 'https' : 'http';
        $protocol = 'http'; // $_SERVER['HTTPS'] ? 'https' : 'http';
        /** @noinspection RealpathInSteamContextInspection */
        $viewParams = [
            //'serverInfo' => $protocol . '://' . $_SERVER['SERVER_ADDR'] . ':' . $_SERVER['SERVER_PORT'] . '/',
            'serverInfo' => $protocol.'://127.0.0.1:8000/',
            'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
        ];
        $viewParams['displayOptions'] = $this->getDisplayOptions();

        return $viewParams;
    }

    public function announceAction($section)
    {
        $rootDir = realpath(__DIR__.'/../../../../');
        $announceDir = $rootDir.'/docs/announcements';

        if (!preg_match('/^\w+$/', $section)) {
            throw new NotFoundHttpException(); // don't give access to anything but plain names
        }

        $candidateFile = $announceDir.'/'.$section.'.md';

        if (!file_exists($candidateFile)) {
            throw new NotFoundHttpException();
        }

        $markdown = file_get_contents($candidateFile);

        return $this->render(
            ':default:announce.html.twig',
            [
                'announcement' => $markdown,
                'messageClass' => $section,
                'displayOptions' => $this->getDisplayOptions(),
            ]
        );
    }
}
