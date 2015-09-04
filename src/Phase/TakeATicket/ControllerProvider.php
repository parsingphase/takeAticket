<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 04/08/15
 * Time: 19:17
 */

namespace Phase\TakeATicket;


use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;

class ControllerProvider implements ControllerProviderInterface
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * Returns routes to connect to the given application.
     *
     * @param Application $app An Application instance
     *
     * @return ControllerCollection A ControllerCollection instance
     */
    public function connect(Application $app)
    {
        // Make sure we're using an Adze app, and use that knowledge to set up the required template and resource paths
        // It's possible we should really do this as a ServiceProvider ... ?
        $this->app = $app;
        $controllers = $this->getControllerFactory();

        $app['ticket.controller'] = $app->share(
            function (Application $app) {
                return new Controller($app);
            }
        );

        $controllers->match(
            '/',
            'ticket.controller:indexAction'
        );

        $controllers->match(
            '/api/next',
            'ticket.controller:nextJsonAction'
        );

        $controllers->match(
            '/manage',
            'ticket.controller:manageAction'
        );

        $controllers->match(
            '/api/newTicket',
            'ticket.controller:newTicketPostAction'
        );
        $controllers->match(
            '/api/newOrder',
            'ticket.controller:newTicketOrderPostAction'
        );

        $controllers->match(
            '/api/useTicket',
            'ticket.controller:useTicketPostAction'
        );

        $controllers->match(
            '/api/deleteTicket',
            'ticket.controller:deleteTicketPostAction'
        );

        $controllers->match(
            '/songSearch',
            'ticket.controller:songSearchAction'
        );

        $controllers->match(
            '/api/songSearch',
            'ticket.controller:songSearchApiAction'
        );

        $controllers->match(
            '/api/getPerformers',
            'ticket.controller:getPerformersAction'
        );

        return $controllers;
    }

    /**
     * Accessor for the Controller Factory, to help create new Controllers
     *
     * @return ControllerCollection
     */
    public function getControllerFactory()
    {
        return $this->app['controllers_factory'];
    }

}