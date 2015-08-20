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
    const ticketTable = 'tickets';
    /**
     * @var Application
     */
    protected $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function indexAction()
    {
        return $this->app['twig']->render('index.twig');
    }

    public function nextJsonAction()
    {
        $conn = $this->app['db'];
        /**
         * @var $conn Connection
         */
        $statement = $conn->prepare('SELECT * FROM tickets WHERE deleted=0 AND used=0 ORDER BY offset ASC LIMIT 3');
        $statement->execute();
        $next = $statement->fetchAll();
        $next = $this->expandSongsInTickets($next);

        return new JsonResponse($next);
    }

    public function manageAction()
    {
        $conn = $this->getDbConn();
        $statement = $conn->prepare('SELECT * FROM tickets WHERE deleted=0 ORDER BY offset ASC');
        $statement->execute();
        $tickets = $statement->fetchAll();
        $tickets = $this->expandSongsInTickets($tickets);

        return $this->app['twig']->render('manage.twig', ['config' => $this->app['config'], 'tickets' => $tickets]);
    }

    public function newTicketPostAction(Request $request)
    {
        $title = $request->get('title');
        $songKey = $request->get('song');
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
        $res = $conn->insert(self::ticketTable, $ticket);

        if ($song) {
            $ticket['song'] = $song;
        }

        if ($res) {
            $jsonResponse = new JsonResponse(['ticket' => $ticket]);
        } else {
            $jsonResponse = new JsonResponse(['ticket' => $ticket], 500);
        }
        return $jsonResponse;
    }

    public function newTicketOrderPostAction(Request $request)
    {
        $idOrder = $request->get('idOrder');
//        file_put_contents(dirname(__DIR__) . '../../tmp', json_encode($idOrder) . "\n");
        $conn = $this->getDbConn();

        $res = 1;
        foreach ($idOrder as $offset => $id) {
            $res = $res && $conn->update(self::ticketTable, ['offset' => $offset], ['id' => $id]);
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
        $res = $conn->update(self::ticketTable, ['used' => 1], ['id' => $id]);
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
        $res = $conn->update(self::ticketTable, ['deleted' => 1], ['id' => $id]);
        if ($res) {
            $jsonResponse = new JsonResponse(['ok' => 'ok']);
        } else {
            $jsonResponse = new JsonResponse(['ok' => 'fail'], 500);
        }
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
     * @param $tickets
     * @return mixed
     */
    public function expandSongsInTickets($tickets)
    {
        foreach ($tickets as &$ticket) {
            if ($ticket['songId']) {
                $ticket['song'] = $this->getSongById($ticket['songId']);
            }
        }
        return $tickets;
    }

}