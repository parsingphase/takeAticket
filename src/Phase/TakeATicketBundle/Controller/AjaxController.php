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

    const MANAGER_REQUIRED_ROLE = 'ROLE_ADMIN';
    const BAND_IDENTIFIER_BAND_NAME = 1;
    const BAND_IDENTIFIER_PERFORMERS = 2;

    /**
     * @var int Whether to identify band by its name or a list of performers
     */
    protected $bandIdentifier = self::BAND_IDENTIFIER_PERFORMERS;

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

    public function useTicketAction(Request $request)
    {
        //        $this->setJsonErrorHandler();

        $this->denyAccessUnlessGranted(self::MANAGER_REQUIRED_ROLE);

        $id = $request->get('ticketId');
        $res = $this->getDataStore()->markTicketUsedById($id);
        //FIXME fetch ticket with extra data
        if ($res) {
            $jsonResponse = new JsonResponse(['ok' => 'ok']);
        } else {
            $jsonResponse = new JsonResponse(['ok' => 'fail'], 500);
        }
        return $jsonResponse;
    }

    public function remotesRedirectAction()
    {
        return new JsonResponse($this->getDataStore()->getSetting('remotesUrl'));
    }

    public function saveTicketAction(Request $request)
    {
        //        $this->setJsonErrorHandler();
        $this->denyAccessUnlessGranted(self::MANAGER_REQUIRED_ROLE);

        $title = $request->get('title');
        $songKey = $request->get('songId');
        $band = $request->get('band') ?: []; // band must be array even if null (as passed by AJAX if no performers)
        $private = $request->get('private') === 'true' ? 1 : 0;
        $blocking = $request->get('blocking') === 'true' ? 1 : 0;
        $existingTicketId = $request->get('existingTicketId');

        $song = null;
        $songId = null;

        if (preg_match('/^[a-f0-9]{6}$/i', $songKey)) {
            $song = $this->getDataStore()->fetchSongByKey($songKey);
        } elseif (preg_match('/^\d+$/', $songKey)) {
            $song = $this->getDataStore()->fetchSongById($songKey);
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
            $ticketId = $this->getDataStore()->storeNewTicket($title, $songId);
        }

        // update even new tickets so that we can add any new columns easily
        $updated = ['title' => $title, 'songId' => $songId, 'blocking' => $blocking, 'private' => $private];

//        $this->app['logger']->debug("Updating ticket", $updated);
        $this->getDataStore()->updateTicketById(
            $ticketId,
            $updated
        );

        if ($this->bandIdentifier === self::BAND_IDENTIFIER_PERFORMERS) {
            $this->getDataStore()->storeBandToTicket($ticketId, $band);
        }

        $ticket = $this->getDataStore()->fetchTicketById($ticketId);

        $ticket = $this->getDataStore()->expandTicketData($ticket);

        $responseData = [
            'ticket' => $ticket,
            'performers' => $this->getDataStore()->generatePerformerStats()
        ];

        if ($ticketId) {
            $jsonResponse = new JsonResponse($responseData);
        } else {
            $jsonResponse = new JsonResponse($responseData, 500);
        }
        return $jsonResponse;
    }


    public function deleteTicketAction(Request $request)
    {
        //        $this->setJsonErrorHandler();

        $this->denyAccessUnlessGranted(self::MANAGER_REQUIRED_ROLE);

        $id = $request->get('ticketId');
        $res = $this->getDataStore()->deleteTicketById($id);
        if ($res) {
            $jsonResponse = new JsonResponse(['ok' => 'ok']);
        } else {
            $jsonResponse = new JsonResponse(['ok' => 'fail'], 500);
        }
        return $jsonResponse;
    }
}
