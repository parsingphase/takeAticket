<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 04/08/15
 * Time: 19:16
 */

namespace Phase\TakeATicket;

use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class Controller
{

    const MANAGER_REQUIRED_ROLE = 'ROLE_ADMIN';

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var DataSource
     */
    protected $dataSource;

    protected $bandIdentifier = DataSource::BAND_IDENTIFIER_PERFORMERS;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->dataSource = new DataSource($this->app['db']);
    }

    public function indexAction()
    {
        $viewParams = [];
        $viewParams['displayOptions'] = isset($this->app['config']['displayOptions']) ? $this->app['config']['displayOptions'] : null;
        return $this->app['twig']->render('index.twig', $viewParams);
    }

    public function songSearchAction()
    {
        $viewParams = [];
        $viewParams['displayOptions'] = isset($this->app['config']['displayOptions']) ? $this->app['config']['displayOptions'] : null;
        return $this->app['twig']->render('songSearch.twig', $viewParams);
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
            'manage.twig',
            ['config' => $this->app['config'], 'tickets' => $tickets, 'performers' => $performers]
        );
    }

    public function newTicketPostAction(Request $request)
    {
        $title = $request->get('title');
        $songKey = $request->get('songId');

        $song = null;
        $songId = null;

        if (preg_match('/^[a-f0-9]{6}$/i', $songKey)) {
            $song = $this->dataSource->fetchSongByKey($songKey);
        } else if (preg_match('/^\d+$/', $songKey)) {
            $song = $this->dataSource->fetchSongById($songKey);
        }

        if ($song) {
            $songId = $song['id'];
        }
        $ticketId = $this->dataSource->storeNewTicket($title, $songId);

        if ($this->bandIdentifier === DataSource::BAND_IDENTIFIER_PERFORMERS) {
            $performerNames = preg_split('/\s*,\s*/', $title, -1, PREG_SPLIT_NO_EMPTY);
            $this->dataSource->addPerformersToTicketByName($ticketId, $performerNames);
        }

        $ticket['id'] = $ticketId; //FIXME re-fetch ticket by ID
        $ticket['songId'] = $songId; //FIXME re-fetch ticket by ID

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
        $songs = $this->dataSource->findSongsBySearchString($searchString);

        $jsonResponse = new JsonResponse(['ok' => 'ok', 'searchString' => $searchString, 'songs' => $songs]);
        return $jsonResponse;
    }

    public function getPerformersAction()
    {
        $performers = $this->dataSource->generatePerformerStats();

        $jsonResponse = new JsonResponse(['ok' => 'ok', 'performers' => $performers]);
        return $jsonResponse;
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

}