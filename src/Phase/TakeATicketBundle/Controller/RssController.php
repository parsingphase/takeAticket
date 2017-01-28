<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 28/01/2017
 * Time: 18:36
 */

namespace Phase\TakeATicketBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class RssController extends Controller
{
    use DataStoreAccessTrait;

    public function upcomingRssAction()
    {
        $viewParams = [];//$this->defaultViewParams();
        $includePrivate = false;//$this->app['security']->isGranted(self::MANAGER_REQUIRED_ROLE);
        $viewParams['tickets'] = $this->getDataStore()->fetchUpcomingTickets($includePrivate);
        $data = $this->render('default/upcoming.rss.twig', $viewParams);
        $headers = empty($_GET['nt']) ? ['Content-type' => 'application/rss+xml'] : ['Content-type' => 'text/plain'];
        return new Response($data, 200, $headers);
    }
}