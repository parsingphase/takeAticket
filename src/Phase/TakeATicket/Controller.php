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
//        Cheza:db wechsler$ sqlite3 app.db
        //sqlite> CREATE TABLE tickets (offset int PRIMARY KEY, title string);

        $conn = $this->app['db'];
        /**
         * @var $conn Connection
         */
        $statement = $conn->prepare('SELECT * FROM tickets WHERE deleted=0 AND used=0 ORDER BY offset ASC LIMIT 3');
        $statement->execute();
        $next = $statement->fetchAll();
//
//        $next = [
//            ['title' => 123],
//            ['title' => 124],
//            ['title' => 139]
//        ];

        return new JsonResponse($next);
    }

    public function manageAction()
    {
        $conn = $this->getDbConn();
        $statement = $conn->prepare('SELECT * FROM tickets WHERE deleted=0 ORDER BY offset ASC');
        $statement->execute();
        $tickets = $statement->fetchAll();


        return $this->app['twig']->render('manage.twig', [self::ticketTable => $tickets]);
    }

    public function newTicketPostAction(Request $request)
    {
        $title = $request->get('title');
        $conn = $this->getDbConn();
        $max = $conn->fetchAssoc('SELECT max(offset) AS o, max(id) AS i FROM tickets');

        $maxOffset = $max['o'];
        $maxId = $max['i'];
        $ticket = [
            'title' => $title,
            'id' => $maxId + 1,
            'offset' => $maxOffset + 1
        ];
        $res = $conn->insert(self::ticketTable, $ticket);

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
        $res =  $conn->update(self::ticketTable, ['used' => 1], ['id' => $id]);
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
        $res =  $conn->update(self::ticketTable, ['deleted' => 1], ['id' => $id]);
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
        /**
         * @var $conn Connection
         */
    }
}