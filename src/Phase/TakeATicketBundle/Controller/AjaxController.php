<?php
/**
 * Created by PhpStorm.
 * User: wechsler
 * Date: 28/01/2017
 * Time: 16:08
 */

namespace Phase\TakeATicketBundle\Controller;

use Phase\TakeATicket\Model\Instrument;
use Phase\TakeATicket\Model\Platform;
use Phase\TakeATicketBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AjaxController extends BaseController
{
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

    public function reloadPerformersAction()
    {
        $performers = $this->getDataStore()->generatePerformerStats();
        return new JsonResponse($performers);
    }

    public function songSearchAction(Request $request)
    {
        $searchString = $request->get('searchString');
        $searchCount = 10;
        if ($request->get('searchCount')) {
            $searchCount = $request->get('searchCount');
        }
        $dataStore = $this->getDataStore();
        $songs = $dataStore->findSongsBySearchString($searchString, $searchCount);
        // Need to add sources, instruments
        $hydrated = [];

        foreach ($songs as $song) {
            $song = $dataStore->expandSongData($song);

            $hydrated[] = $song;
        }

        $jsonResponse = new JsonResponse(['ok' => 'ok', 'searchString' => $searchString, 'songs' => $hydrated]);

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
        return new JsonResponse($this->getDataStore()->fetchSetting('remotesUrl'));
    }

    public function saveTicketAction(Request $request)
    {
        if (!$this->getDataStore()->fetchSetting('selfSubmission')) {
            $this->denyAccessUnlessGranted(self::MANAGER_REQUIRED_ROLE);
        }

        $title = $request->get('title');
        $songKey = $request->get('songId');
        $band = $request->get('band') ?: []; // band must be array even if null (as passed by AJAX if no performers)
        $private = $request->get('private') === 'true' ? 1 : 0;
        $blocking = $request->get('blocking') === 'true' ? 1 : 0;
        $existingTicketId = $request->get('existingTicketId');
        $submissionKey = trim($request->get('submissionKey'));
        $userId = null;

        if ($this->isGranted(self::MANAGER_REQUIRED_ROLE)) {
            $user = $this->getUser();
            /** @var User $user */
            $userId = $user->getId();
        } else {
            $blocking = 0;
            $private = 1;
            if ($existingTicketId) {
                throw new AccessDeniedException('Cannot modify existing tickets');
            }

            // Check song is unused, check no performers have more than 3 upcoming songs
            // Ensure that name format is valid

            $dataErrors = [];
            $fixableError = false;

            $ticketsForSong = $this->getDataStore()->getQueueEntriesForSongId($songKey);

            if (count($ticketsForSong)) {
                $dataErrors[] = 'The song is already taken';
            }

            $performerStats = $this->getDataStore()->generatePerformerStats();

            foreach ($band as $instrumentCandidates) {
                foreach ($instrumentCandidates as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate) {
                        if (preg_match('/\w+ \w+/', $candidate)) {
                            foreach ($performerStats as $performerStat) {
                                if (($performerStat['songsPending'] > 2) &&
                                    !strcasecmp($candidate, $performerStat['performerName'])
                                ) {
                                    $dataErrors[] = "$candidate has too many songs pending";
                                }
                            }
                        } else {
                            $dataErrors[] = "'$candidate' is not a valid name";
                        }
                    }
                }
            }

            if ($this->getDataStore()->fetchSetting('selfSubmissionKey') &&
                strcasecmp($submissionKey, $this->getDataStore()->fetchSetting('selfSubmissionKey'))
            ) {
                $dataErrors[] = 'Secret code wrong or missing';
                $fixableError = 'E_BAD_SECRET';
            }

            if ($dataErrors) {
                $responseData = [
                    'status' => 'error',
                    'message' => implode(', ', $dataErrors)
                ];
                if ($fixableError) {
                    $responseData['error'] = $fixableError; // more than one error = faily death.
                }
                return new JsonResponse($responseData, 200);
            }
        }

        $song = null;
        $songId = null;

        if (preg_match('/^[a-f0-9]{6}$/i', $songKey)) {
            $song = $this->getDataStore()->fetchSongByKey($songKey);
        } elseif (preg_match('/^\d+$/', $songKey)) {
            $song = $this->getDataStore()->fetchSongRowById($songKey);
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
            $ticketId = $this->getDataStore()->storeNewTicket($title, $songId, $userId);
        }

        // update even new tickets so that we can add any new columns easily
        $updated = ['title' => $title, 'songId' => $songId, 'blocking' => $blocking, 'private' => $private];

        $this->getDataStore()->updateTicketById(
            $ticketId,
            $updated
        );

        // think this is legacy?
        if ($this->bandIdentifier === self::BAND_IDENTIFIER_PERFORMERS) {
            $this->getDataStore()->storeBandToTicket($ticketId, $band);
        }

        $ticket = $this->getDataStore()->fetchTicketById($ticketId);

        $ticket = $this->getDataStore()->expandTicketData($ticket);

        $responseData = [
            'ticket' => $ticket,
            'performers' => $this->getDataStore()->generatePerformerStats(),
            'status' => 'ok'
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

    /**
     * Opaque hash that changes when new tickets are added (may cover further changes in future)
     *
     * @return JsonResponse
     * @throws \Doctrine\DBAL\DBALException
     */
    public function lastUpdateHashAction()
    {
        return new JsonResponse(['hash' => count($this->getDataStore()->fetchUndeletedTickets())]);
    }

    public function newTicketOrderAction(Request $request)
    {
        $this->denyAccessUnlessGranted(self::MANAGER_REQUIRED_ROLE);

        $idOrder = $request->get('idOrder');

        if (!is_array($idOrder)) {
            throw new \InvalidArgumentException('Order must be array!');
        }

        $res = true;
        foreach ($idOrder as $offset => $id) {
            $res = $res && $this->getDataStore()->updateTicketOffsetById($id, $offset);
        }
        if ($res) {
            $jsonResponse = new JsonResponse(['ok' => 'ok']);
        } else {
            $jsonResponse = new JsonResponse(['ok' => 'fail', 'message' => 'Failed to store new sort order'], 500);
        }

        return $jsonResponse;
    }
}
