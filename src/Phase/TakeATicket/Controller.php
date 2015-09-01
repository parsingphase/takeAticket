<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 04/08/15
 * Time: 19:16
 */

namespace Phase\TakeATicket;

use Doctrine\DBAL\Connection;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class Controller
{
    const TICKETS_TABLE = 'tickets';
    const PERFORMERS_TABLE = 'performers';
    const TICKETS_X_PERFORMERS_TABLE = 'tickets_x_performers';
    const BAND_IDENTIFIER_BAND_NAME = 1;
    const BAND_IDENTIFIER_PERFORMERS = 2;

    /**
     * @var Application
     */
    protected $app;

    protected $bandIdentifier = self::BAND_IDENTIFIER_PERFORMERS;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function indexAction()
    {
        $viewParams = [];
        $viewParams['displayOptions'] = isset($this->app['config']['displayOptions']) ? $this->app['config']['displayOptions'] : null;
        return $this->app['twig']->render('index.twig', $viewParams);
    }

    public function nextJsonAction()
    {
        $conn = $this->getDbConn();
        $statement = $conn->prepare('SELECT * FROM tickets WHERE deleted=0 AND used=0 ORDER BY offset ASC LIMIT 3');
        $statement->execute();
        $next = $statement->fetchAll();
        $next = $this->expandTicketsData($next);

        return new JsonResponse($next);
    }

    public function manageAction()
    {
        $conn = $this->getDbConn();
        $statement = $conn->prepare('SELECT * FROM tickets WHERE deleted=0 ORDER BY offset ASC');
        $statement->execute();
        $tickets = $statement->fetchAll();
        $tickets = $this->expandTicketsData($tickets);

        $performers = $this->generatePerformerStats();

        return $this->app['twig']->render(
            'manage.twig',
            ['config' => $this->app['config'], 'tickets' => $tickets, 'performers' => $performers]
        );
    }

    public function newTicketPostAction(Request $request)
    {
        $title = $request->get('title');
        $songKey = $request->get('songId');
        $conn = $this->getDbConn();

        $song = null;
        $songId = null;

        if (preg_match('/^[a-f0-9]{6}$/i', $songKey)) {
            $song = $conn->fetchAssoc('SELECT * FROM songs WHERE codeNumber = :code', ['code' => $songKey]);
        } else if (preg_match('/^\d+$/', $songKey)) {
            $song = $this->getSongById($songKey);
        }

        if ($song) {
            $songId = $song['id'];
        }

        $max = $conn->fetchAssoc('SELECT max(offset) AS o, max(id) AS i FROM tickets');

        $maxOffset = $max['o'];
        $maxId = $max['i'];
        $ticket = [
            'title' => $title,
            'id' => $maxId + 1,
            'offset' => $maxOffset + 1,
            'songId' => $songId
        ];
        $res = $conn->insert(self::TICKETS_TABLE, $ticket);

        if ($this->bandIdentifier === self::BAND_IDENTIFIER_PERFORMERS) {
            $performerNames = preg_split('/\s*,\s*/', $title, -1, PREG_SPLIT_NO_EMPTY);
            $this->addPerformersToTicketByName($ticket['id'], $performerNames);
        }

        $ticket = $this->expandTicketData($ticket);

        $responseData = ['ticket' => $ticket, 'performers' => $this->generatePerformerStats()];

        if ($res) {
            $jsonResponse = new JsonResponse($responseData);
        } else {
            $jsonResponse = new JsonResponse($responseData, 500);
        }
        return $jsonResponse;
    }

    public function newTicketOrderPostAction(Request $request)
    {
        $idOrder = $request->get('idOrder');
        $conn = $this->getDbConn();

        $res = 1;
        foreach ($idOrder as $offset => $id) {
            $res = $res && $conn->update(self::TICKETS_TABLE, ['offset' => $offset], ['id' => $id]);
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
        $id = $request->get('ticketId');
        $conn = $this->getDbConn();
        $res = $conn->update(self::TICKETS_TABLE, ['used' => 1], ['id' => $id]);
        if ($res) {
            $jsonResponse = new JsonResponse(['ok' => 'ok']);
        } else {
            $jsonResponse = new JsonResponse(['ok' => 'fail'], 500);
        }
        return $jsonResponse;
    }

    public function deleteTicketPostAction(Request $request)
    {
        $id = $request->get('ticketId');
        $conn = $this->getDbConn();
        $res = $conn->update(self::TICKETS_TABLE, ['deleted' => 1], ['id' => $id]);
        if ($res) {
            $jsonResponse = new JsonResponse(['ok' => 'ok']);
        } else {
            $jsonResponse = new JsonResponse(['ok' => 'fail'], 500);
        }
        return $jsonResponse;
    }

    public function songSearchAction(Request $request)
    {
        $searchString = $request->get('searchString');
        $conn = $this->getDbConn();
        $leadingPattern = implode('%', preg_split('/\s+/', $searchString)) . '%';
        $internalPattern = '%' . $leadingPattern;
        $params = [
            'internalPattern' => $internalPattern,
            'leadingPattern' => $leadingPattern,
            'searchString' => $searchString
        ];

        // this may be unnecessary - chances of a code number hitting anything else is minimal
        if ($this->potentialCodeNumber($searchString)) {
            $sql = "SELECT * FROM songs
            WHERE (title = :searchString)
            OR (codeNumber = :searchString)
            ORDER BY artist, title
            LIMIT 10";
            //allow title just in case
        } else {
            $sql = "SELECT * FROM songs
            WHERE (title || ' ' || artist LIKE :internalPattern)
            OR (artist || ' ' || title LIKE :internalPattern)
            OR (codeNumber LIKE :leadingPattern)
            OR (id = :searchString)
            ORDER BY artist, title
            LIMIT 10";
        }

        $songs = $conn->fetchAll($sql, $params);

        $jsonResponse = new JsonResponse(['ok' => 'ok', 'searchString' => $searchString, 'songs' => $songs]);
        return $jsonResponse;
    }

    public function getPerformersAction()
    {
        $performers = $this->generatePerformerStats();

        $jsonResponse = new JsonResponse(['ok' => 'ok', 'performers' => $performers]);
        return $jsonResponse;
    }

    /**
     * @return Connection
     */
    protected function getDbConn()
    {
        $conn = $this->app['db'];
        return $conn;
    }

    /**
     * @param $songId
     * @return mixed
     */
    public function getSongById($songId)
    {
        return $this->getDbConn()->fetchAssoc('SELECT * FROM songs WHERE id = :code', ['code' => $songId]);
    }


    /**
     * @param $ticket
     * @return mixed
     */
    protected function expandTicketData($ticket)
    {
        if ($ticket['songId']) {
            $ticket['song'] = $this->getSongById($ticket['songId']);
        }
        $ticket['performers'] = $this->fetchPerformersByTicketId($ticket['id']);
        return $ticket;
    }

    /**
     * expandTicketData for multiple tickets
     *
     * @param $tickets
     * @return mixed
     */
    public function expandTicketsData($tickets)
    {
        foreach ($tickets as &$ticket) {
            $ticket = $this->expandTicketData($ticket);
        }
        return $tickets;
    }

    protected function potentialCodeNumber($searchString)
    {
        $codeLength = (int)SongLoader::CODE_LENGTH;
        $regexp = '/^[a-f0-9]{' . $codeLength . '}$/i';
        return preg_match($regexp, $searchString);
    }

    /**
     * @return array
     */
    protected function generatePerformerStats()
    {
        $conn = $this->getDbConn();
        $sql = 'SELECT p.id AS performerId, p.name AS performerName,
                  sum(CASE WHEN t.id IS NOT NULL AND t.used=0 AND t.deleted=0 THEN 1 ELSE 0 END) AS songsPending,
                  sum(CASE WHEN t.id IS NOT NULL AND t.used=1 AND t.deleted=0 THEN 1 ELSE 0 END) AS songsDone
               FROM performers p
                    LEFT OUTER JOIN tickets_x_performers txp ON p.id=txp.performerId
                    LEFT OUTER JOIN tickets t ON txp.ticketId = t.id
                GROUP BY p.id ORDER BY p.name';

        $performers = $conn->fetchAll($sql);
        return $performers;
    }

    protected function addPerformersToTicketByName($ticketId, $performerNames)
    {
        foreach ($performerNames as $performerName) {
            $performerName = trim($performerName);
            $performerId = $this->getPerformerIdByName($performerName, true);
            if ($performerId) {
                $link = ['ticketId' => $ticketId, 'performerId' => $performerId];
                $this->getDbConn()->insert(self::TICKETS_X_PERFORMERS_TABLE, $link);
            }
        }
    }

    protected function getPerformerIdByName($performerName, $createMissing = false)
    {
        $conn = $this->getDbConn();
        $sql = 'SELECT id FROM performers p WHERE p.name LIKE :name LIMIT 1';
        $performerId = $conn->fetchColumn($sql, ['name' => $performerName]);

        if ($createMissing && !$performerId) {
            $max = $conn->fetchColumn('SELECT max(id) FROM performers');
            $performerId = $max + 1;
            $conn->insert(self::PERFORMERS_TABLE, ['id' => $performerId, 'name' => ucwords($performerName)]);
            //add new performer row
        }

        return $performerId;
    }

    /**
     * Fetch all performers on a song with their stats
     *
     * @param $ticketId
     * @return array[]
     */
    protected function fetchPerformersByTicketId($ticketId)
    {
        $ticketPerformerSql = 'SELECT performerId FROM tickets_x_performers WHERE ticketId = :ticketId';
        $performerRows = $this->getDbConn()->fetchAll($ticketPerformerSql, ['ticketId' => $ticketId]);
        $performerIds = [];
        foreach ($performerRows as $row) {
            $performerIds[] = $row['performerId'];
        }

        $allPerformers = $this->generatePerformerStats();
        $trackPerformers = [];
        foreach ($allPerformers as $performer) {
            if (in_array($performer['performerId'], $performerIds)) {
                $trackPerformers[] = $performer;
            }
        }
        return $trackPerformers;
    }

}