<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 28/01/2017
 * Time: 16:08
 */

namespace Phase\TakeATicketBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class AjaxController extends Controller
{
    use DataStoreAccessTrait;

    /**
     * @return JsonResponse
     *
     * FIXME does not honour config.displayOptions.songInPreview - always shows track!
     */
    public function nextSongsAction()
    {
//        $this->setJsonErrorHandler();
//        $includePrivate = $this->app['security']->isGranted(self::MANAGER_REQUIRED_ROLE);
        $includePrivate = false;
        $next = $this->getDataStore()->fetchUpcomingTickets($includePrivate);
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

    public function songSearchAction(Request $request)
    {

        $searchString = $request->get('searchString');
        $searchCount = 10;
        if ($request->get('searchCount')) {
            $searchCount = $request->get('searchCount');
        }
        $songs = $this->getDataStore()->findSongsBySearchString($searchString, $searchCount);

        $jsonResponse = new JsonResponse(['ok' => 'ok', 'searchString' => $searchString, 'songs' => $songs]);
        return $jsonResponse;

    }

    protected function getDataSource()
    {
        $this->get('database_connection');
    }
}