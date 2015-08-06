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
        $statement = $conn->prepare('SELECT * FROM tickets ORDER BY offset ASC LIMIT 3');
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
        $statement = $conn->prepare('SELECT * FROM tickets ORDER BY offset ASC');
        $statement->execute();
        $tickets = $statement->fetchAll();


        return $this->app['twig']->render('manage.twig', ['tickets' => $tickets]);
    }

    public function newTicketPostAction(Request $request)
    {
        $title = $request->get('title');
        $conn = $this->getDbConn();
        $statement = $conn->prepare('SELECT max(offset) FROM tickets');
        $statement->execute();
        $maxOffset = $statement->fetchColumn();
        $ticket = [
            'title' => $title,
            'offset' => $maxOffset + 1
        ];
        $res = $conn->insert('tickets', $ticket);
        file_put_contents(dirname(__DIR__) . '../../tmp', json_encode($ticket) . "\n$res");

        if ($res) {
            $jsonResponse = new JsonResponse(['ticket' => $ticket]);
        } else {
            $jsonResponse = new JsonResponse(['ticket' => $ticket], 500);
        }
        return $jsonResponse;
    }

    /**
     * @return Connection
     */
    public function getDbConn()
    {
        $conn = $this->app['db'];
        return $conn;
        /**
         * @var $conn Connection
         */
    }
}