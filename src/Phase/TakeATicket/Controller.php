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

    public function nextAction()
    {
//        Cheza:db wechsler$ sqlite3 app.db
        //sqlite> CREATE TABLE tickets (offset int PRIMARY KEY, title string);

        $conn=$this->app['db'];
        /**
         * @var $conn Connection
         */
        $statement = $conn->prepare('SELECT * FROM tickets ORDER BY offset ASC');
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
}