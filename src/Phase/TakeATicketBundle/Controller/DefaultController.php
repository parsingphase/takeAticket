<?php

namespace Phase\TakeATicketBundle\Controller;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DefaultController extends BaseController
{
    public function indexAction()
    {
        $viewParams = $this->defaultViewParams();
        $viewParams['freeze'] = $this->getDataStore()->fetchSetting('freeze');
        $viewParams['freezeMessage'] = $this->getDataStore()->fetchSetting('freezeMessage');

        // replace this example code with whatever you need
        return $this->render('default/upcoming.html.twig', $viewParams);
    }

    public function songSearchAction()
    {
        $viewParams = $this->defaultViewParams();
        $viewParams['freeze'] = $this->getDataStore()->fetchSetting('freeze');
        return $this->render('default/songSearch.html.twig', $viewParams);
    }

    public function announceAction($section)
    {
        /** @noinspection RealpathInSteamContextInspection */
        $rootDir = realpath(__DIR__.'/../../../../'); // FIXME get from Kernel
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
