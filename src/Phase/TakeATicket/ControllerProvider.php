<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 04/08/15
 * Time: 19:17
 */

namespace Phase\TakeATicket;

use Aptoma\Twig\Extension\MarkdownEngine\MichelfMarkdownEngine;
use Aptoma\Twig\Extension\MarkdownExtension;
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


        $app['twig'] = $app->share(
            $app->extend(
                'twig',
                function (\Twig_Environment $twig, $app) {
                    // add custom globals, filters, tags, ...
                    $engine = new MichelfMarkdownEngine();
                    $twig->addExtension(new MarkdownExtension($engine));
                    return $twig;
                }
            )
        );

        $app['ticket.controller'] = $app->share(
            function (Application $app) {
                return new Controller($app);
            }
        );

        $controllers->match(
            '/',
            'ticket.controller:indexAction'
        )->bind('index');

        $controllers->match(
            '/upcoming',
            'ticket.controller:upcomingAction'
        )->bind('upcoming');

        $controllers->match(
            '/upcomingRss',
            'ticket.controller:upcomingRssAction'
        )->bind('upcomingRss');

        $controllers->match(
            '/api/next',
            'ticket.controller:nextJsonAction'
        );

        $controllers->match(
            '/manage',
            'ticket.controller:manageAction'
        );

        $controllers->match(
            '/api/saveTicket',
            'ticket.controller:saveTicketPostAction'
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
            '/songSearch/{songCode}',
            'ticket.controller:songSearchAction'
        )->bind('songs');

        $controllers->match(
            '/api/songSearch',
            'ticket.controller:songSearchApiAction'
        );

        $controllers->match(
            '/api/getPerformers',
            'ticket.controller:getPerformersAction'
        );

        $controllers->match(
            '/help/{section}',
            'ticket.controller:helpAction'
        )->value('section', 'readme');

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
