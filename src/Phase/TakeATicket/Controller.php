<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 04/08/15
 * Time: 19:16
 */

namespace Phase\TakeATicket;

use Phase\TakeATicket\DataSource\AbstractSql;
use Phase\TakeATicket\DataSource\Factory as DataSourceFactory;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class Controller
{

    const MANAGER_REQUIRED_ROLE = 'ROLE_ADMIN';
    const BAND_IDENTIFIER_BAND_NAME = 1;
    const BAND_IDENTIFIER_PERFORMERS = 2;

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var AbstractSql;
     */
    protected $dataSource;

    /**
     * @var int Whether to identify band by its name or a list of performers
     */
    protected $bandIdentifier = self::BAND_IDENTIFIER_PERFORMERS;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->dataSource = DataSourceFactory::datasourceFromDbConnection($this->app['db']);
        $upcomingCount = $this->getUpcomingCount();
        if ($upcomingCount) {
            $this->dataSource->setUpcomingCount($upcomingCount);
        }
    }

    public function indexAction()
    {
        return $this->upcomingAction();
    }

    public function upcomingAction()
    {
        $viewParams = [];
        $viewParams['displayOptions'] = $this->getDisplayOptions();
        return $this->app['twig']->render('upcoming.html.twig', $viewParams);
    }

    public function songSearchAction()
    {
        $viewParams = [];
        $viewParams['displayOptions'] = $this->getDisplayOptions();
        return $this->app['twig']->render('songSearch.html.twig', $viewParams);
    }

    public function nextJsonAction()
    {
        $next = $this->dataSource->fetchUpcomingTickets();
        return new JsonResponse($next);
    }

    public function manageAction()
    {
        $this->assertRole(self::MANAGER_REQUIRED_ROLE);

        $tickets = $this->dataSource->fetchUndeletedTickets();

        $performers = $this->dataSource->generatePerformerStats();

        return $this->app['twig']->render(
            'manage.html.twig',
            ['tickets' => $tickets, 'performers' => $performers]
        );
    }

    public function saveTicketPostAction(Request $request)
    {
        $title = $request->get('title');
        $songKey = $request->get('songId');
        $band = $request->get('band');
        $existingTicketId = $request->get('existingTicketId');

        $song = null;
        $songId = null;

        if (preg_match('/^[a-f0-9]{6}$/i', $songKey)) {
            $song = $this->dataSource->fetchSongByKey($songKey);
        } elseif (preg_match('/^\d+$/', $songKey)) {
            $song = $this->dataSource->fetchSongById($songKey);
        }

        if ($song) {
            $songId = $song['id'];
        }

        if (!$title) {
            $title = null;
        }

        if ($existingTicketId) {
            $ticketId = $existingTicketId;
            $this->dataSource->updateTicketById($existingTicketId, ['title' => $title, 'songId' => $songId]);
        } else {
            $ticketId = $this->dataSource->storeNewTicket($title, $songId);
        }

        if ($this->bandIdentifier === self::BAND_IDENTIFIER_PERFORMERS) {
            $this->dataSource->storeBandToTicket($ticketId, $band);
        }

        $ticket = $this->dataSource->fetchTicketById($ticketId);

        $ticket = $this->dataSource->expandTicketData($ticket);

        $responseData = ['ticket' => $ticket, 'performers' => $this->dataSource->generatePerformerStats()];

        if ($ticketId) {
            $jsonResponse = new JsonResponse($responseData);
        } else {
            $jsonResponse = new JsonResponse($responseData, 500);
        }
        return $jsonResponse;
    }

    public function newTicketOrderPostAction(Request $request)
    {
        $this->assertRole(self::MANAGER_REQUIRED_ROLE);

        $idOrder = $request->get('idOrder');

        $res = true;
        foreach ($idOrder as $offset => $id) {
            $res = $res && $this->dataSource->updateTicketOffsetById($id, $offset);
        }
        if ($res) {
            $jsonResponse = new JsonResponse(['ok' => 'ok']);
        } else {
            $jsonResponse = new JsonResponse(['ok' => 'fail'], 500);
        }
        return $jsonResponse;
    }

    public function useTicketPostAction(Request $request)
    {
        $this->assertRole(self::MANAGER_REQUIRED_ROLE);

        $id = $request->get('ticketId');
        $res = $this->dataSource->markTicketUsedById($id);
        //FIXME fetch ticket with extra data
        if ($res) {
            $jsonResponse = new JsonResponse(['ok' => 'ok']);
        } else {
            $jsonResponse = new JsonResponse(['ok' => 'fail'], 500);
        }
        return $jsonResponse;
    }

    public function deleteTicketPostAction(Request $request)
    {
        $this->assertRole(self::MANAGER_REQUIRED_ROLE);

        $id = $request->get('ticketId');
        $res = $this->dataSource->deleteTicketById($id);
        if ($res) {
            $jsonResponse = new JsonResponse(['ok' => 'ok']);
        } else {
            $jsonResponse = new JsonResponse(['ok' => 'fail'], 500);
        }
        return $jsonResponse;
    }

    public function songSearchApiAction(Request $request)
    {
        $searchString = $request->get('searchString');
        $searchCount = 10;
        if ($request->get('searchCount')) {
            $searchCount = $request->get('searchCount');
        }
        $songs = $this->dataSource->findSongsBySearchString($searchString, $searchCount);

        $jsonResponse = new JsonResponse(['ok' => 'ok', 'searchString' => $searchString, 'songs' => $songs]);
        return $jsonResponse;
    }

    public function getPerformersAction()
    {
        $performers = $this->dataSource->generatePerformerStats();

        $jsonResponse = new JsonResponse(['ok' => 'ok', 'performers' => $performers]);
        return $jsonResponse;
    }

    public function upcomingRssAction()
    {
        $viewParams = [];
        $viewParams['displayOptions'] = $this->getDisplayOptions();
        $viewParams['tickets'] = $this->dataSource->fetchUpcomingTickets();
        $data = $this->app['twig']->render('upcoming.rss.twig', $viewParams);
        $headers = empty($_GET['nt']) ? ['Content-type' => 'application/rss+xml'] : ['Content-type' => 'text/plain'];
        $response = new Response($data, 200, $headers);
        return $response;
    }

    /**
     * @param $requiredRole
     */
    public function assertRole($requiredRole)
    {
        if (!$this->app['security']->isGranted($requiredRole)) {
            throw new AccessDeniedException();
        }
    }

    /**
     * Get display options from config, with overrides if possible
     * @return array
     */
    protected function getDisplayOptions()
    {
        $displayOptions = isset($this->app['displayOptions']) ? $this->app['displayOptions'] : [];
        if ($this->app['security']->isGranted(self::MANAGER_REQUIRED_ROLE)) {
            $displayOptions['songInPreview'] = true; // force for logged-in users
        }
        return $displayOptions;
    }

    /**
     * Get UpcomingCount
     * @return int
     */
    protected function getUpcomingCount()
    {
        $displayOptions = $this->getDisplayOptions();
        return isset($displayOptions['upcomingCount']) ? $displayOptions['upcomingCount'] : null;
    }
}
