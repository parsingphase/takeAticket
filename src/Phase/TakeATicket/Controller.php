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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
        $this->dataSource->setLogger($app['logger']);
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

    /**
     * @return JsonResponse
     *
     * FIXME does not honour config.displayOptions.songInPreview - always shows track!
     */
    public function nextJsonAction()
    {
        $this->setJsonErrorHandler();
        $includePrivate = $this->app['security']->isGranted(self::MANAGER_REQUIRED_ROLE);
        $next = $this->dataSource->fetchUpcomingTickets($includePrivate);
        if ($includePrivate) {
            $show = $next;
        } else {
            $show = [];
            foreach ($next as $k => $ticket) {
                $show[$k] = $ticket;
                if ($ticket['blocking']) {
                    break;
                }
            }
        }
        return new JsonResponse($show);
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
        $this->setJsonErrorHandler();

        $title = $request->get('title');
        $songKey = $request->get('songId');
        $band = $request->get('band') ?: []; // band must be array even if null (as passed by AJAX if no performers)
        $private = $request->get('private') === 'true' ? 1 : 0;
        $blocking = $request->get('blocking') === 'true' ? 1 : 0;
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
        } else {
            $ticketId = $this->dataSource->storeNewTicket($title, $songId);
        }

        // update even new tickets so that we can add any new columns easily
        $updated = ['title' => $title, 'songId' => $songId, 'blocking' => $blocking, 'private' => $private];

        $this->app['logger']->debug("Updating ticket", $updated);
        $this->dataSource->updateTicketById(
            $ticketId,
            $updated
        );

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
        $this->setJsonErrorHandler();

        $this->assertRole(self::MANAGER_REQUIRED_ROLE);

        $idOrder = $request->get('idOrder');

        if (!is_array($idOrder)) {
            throw new \InvalidArgumentException('Order must be array!');
        }

        $this->app['logger']->debug("New order: " . print_r($idOrder, true));

        $res = true;
        foreach ($idOrder as $offset => $id) {
            $res = $res && $this->dataSource->updateTicketOffsetById($id, $offset);
        }
        if ($res) {
            $jsonResponse = new JsonResponse(['ok' => 'ok']);
        } else {
            $this->app['logger']->warn("Failed to store track order: " . print_r($idOrder, true));
            $jsonResponse = new JsonResponse(['ok' => 'fail', 'message' => 'Failed to store new sort order'], 500);
        }
        return $jsonResponse;
    }

    public function useTicketPostAction(Request $request)
    {
        $this->setJsonErrorHandler();

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
        $this->setJsonErrorHandler();

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
        $this->setJsonErrorHandler();

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
        $this->setJsonErrorHandler();

        $performers = $this->dataSource->generatePerformerStats();

        $jsonResponse = new JsonResponse(['ok' => 'ok', 'performers' => $performers]);
        return $jsonResponse;
    }

    public function upcomingRssAction()
    {
        $viewParams = [];
        $viewParams['displayOptions'] = $this->getDisplayOptions();
        $includePrivate = $this->app['security']->isGranted(self::MANAGER_REQUIRED_ROLE);
        $viewParams['tickets'] = $this->dataSource->fetchUpcomingTickets($includePrivate);
        $data = $this->app['twig']->render('upcoming.rss.twig', $viewParams);
        $headers = empty($_GET['nt']) ? ['Content-type' => 'application/rss+xml'] : ['Content-type' => 'text/plain'];
        $response = new Response($data, 200, $headers);
        return $response;
    }

    public function helpAction($section)
    {
        $this->assertRole(self::MANAGER_REQUIRED_ROLE);

        $rootDir = realpath(__DIR__ . '/../../../');
        $map = [
            'readme' => $rootDir . '/README.md',
            'CONTRIBUTING' => $rootDir . '/docs/CONTRIBUTING.md',
            'TODO' => $rootDir . '/docs/TODO.md',
        ];

        if (!isset($map[$section])) {
            throw new NotFoundHttpException;
        }

        $markdown = file_get_contents($map[$section]);

        $markdown = preg_replace(
            '#\[docs/\w+.md\]\((./)?docs/(\w+).md\)#',
            '[docs/$2.md](/help/$2)',
            $markdown
        );

        return $this->app['twig']->render(
            'help.html.twig',
            ['helpText' => $markdown]
        );
    }

    public function announceAction($section)
    {
        //$this->assertRole(self::MANAGER_REQUIRED_ROLE);

        $rootDir = realpath(__DIR__ . '/../../../');
        $announceDir = $rootDir . '/docs/announcements';

        if (!preg_match('/^\w+$/', $section)) {
            throw new NotFoundHttpException; // don't give access to anything but plain names
        }

        $candidateFile = $announceDir . '/' . $section . '.md';

        if (!file_exists($candidateFile)) {
            throw new NotFoundHttpException;
        }

        $markdown = file_get_contents($candidateFile);

        return $this->app['twig']->render(
            'announce.html.twig',
            [
                'announcement' => $markdown,
                'messageClass' => $section
            ]
        );
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

    protected function setJsonErrorHandler()
    {
        /** @noinspection PhpUnusedParameterInspection */
        $this->app->error(function (\Exception $e, $code) {
            $message = 'Threw ' . get_class($e) . ': ' . $e->getMessage();
            return new JsonResponse(['error' => $message]);
        });
    }
}
