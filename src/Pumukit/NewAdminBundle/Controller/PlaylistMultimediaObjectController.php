<?php

namespace Pumukit\NewAdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Pagerfanta\Adapter\DoctrineODMMongoDBAdapter;
use Pagerfanta\Adapter\DoctrineSelectableAdapter;
use Pagerfanta\Adapter\DoctrineCollectionAdapter;
use Pagerfanta\Adapter\MongoAdapter;
use Pagerfanta\Adapter\ArrayAdapter;
use Pagerfanta\Pagerfanta;
use Pumukit\SchemaBundle\Document\Series;
use Pumukit\SchemaBundle\Document\Tag;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Security\Permission;
use Pumukit\NewAdminBundle\Form\Type\MultimediaObjectMetaType;
use Pumukit\NewAdminBundle\Form\Type\MultimediaObjectPubType;
use Pumukit\SchemaBundle\Event\MultimediaObjectEvent;
use Pumukit\SchemaBundle\Event\SchemaEvents;
use Pumukit\SchemaBundle\Document\PermissionProfile;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
/**
 * @Security("is_granted('ROLE_ACCESS_EDIT_PLAYLIST')")
 */
class PlaylistMultimediaObjectController extends Controller
{
    /**
     * Overwrite to search criteria with date
     * @Template
     */
    public function indexAction(Request $request)
    {
        $factoryService = $this->get('pumukitschema.factory');
        $session = $this->get('session');
        $sessionId = $session->get('admin/playlist/id', null);
        $series = $factoryService->findSeriesById($request->query->get('id'), $sessionId);
        if(!$series) throw $this->createNotFoundException();

        $session->set('admin/playlist/id', $series->getId());

        if($request->query->has('mmid')) {
            $session->set('admin/playlistmms/id', $request->query->get('mmid'));
        }
        $mms = $this->getPlaylistMmobjs($series, $request);
        return array(
                     'playlist' => $series,
                     'mms' => $mms
                     );
    }

    /**
     * Displays the preview.
     * @Template("PumukitNewAdminBundle:MultimediaObject:show.html.twig")
     */
    public function showAction(MultimediaObject $mmobj, Request $request)
    {
        $this->get('session')->set('admin/playlistmms/id', $mmobj->getId());
        if($request->query->has('pos'))
            $this->get('session')->set('admin/playlistmms/pos', $request->query->get('pos'));
        $roles = $this->get('pumukitschema.person')->getRoles();
        $activeEditor = $this->checkHasEditor();
        return array(
            'mm' => $mmobj,
            'roles' => $roles,
            'active_editor' => $activeEditor,
        );
    }

    /**
     * @Template
     */
    public function listAction(Request $request)
    {
        $factoryService = $this->get('pumukitschema.factory');
        $sessionId = $this->get('session')->get('admin/playlist/id', null);
        $series = $factoryService->findSeriesById($request->query->get('id'), $sessionId);
        if(!$series) throw $this->createNotFoundException();

        $this->get('session')->set('admin/playlist/id', $series->getId());

        if($request->query->has('mmid')) {
            $this->get('session')->set('admin/playlistmms/id', $request->query->get('mmid'));
        }

        $mms = $this->getPlaylistMmobjs($series, $request);
        return array(
                     'playlist' => $series,
                     'mms' => $mms
                     );
    }

    //TODO: This? Or getResources(like in PlaylistController?)
    protected function getPlaylistMmobjs(Series $series, $request)
    {
        $mms = $series->getPlaylist()->getMultimediaObjects();
        $adapter = new DoctrineCollectionAdapter($mms);
        $pagerfanta = new Pagerfanta($adapter);

        $session = $this->get('session');
        if ($request->get('page', null)) {
            $session->set('admin/playlistmms/page', $request->get('page', 1));
        }

        if ($request->get('paginate', null)) {
            $session->set('admin/playlistmms/paginate', $request->get('paginate', 10));
        }

        $page = $session->get('admin/playlistmms/page', 1);
        $maxPerPage = $session->get('admin/playlistmms/paginate', 10);

        $pagerfanta->setMaxPerPage($maxPerPage)->setNormalizeOutOfRangePages(true);
        $pagerfanta->setCurrentPage($page);

        return $pagerfanta;
    }

    /**
     * Returns a modal window where to add mmobjs to a playlist.
     *
     * It is meant to be used through ajax.
     * @Template("PumukitNewAdminBundle:PlaylistMultimediaObject:modal.html.twig")
     */
    public function modalAction(Series $playlist, Request $request)
    {
        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $mmobjs = $dm->getRepository('PumukitSchemaBundle:MultimediaObject')->findAllAsIterable();

        return array(
            'my_mmobjs' => $mmobjs,
            'playlist' => $playlist
        );
    }

    /**
     * @Template("PumukitNewAdminBundle:PlaylistMultimediaObject:modal_search_list.html.twig")
     */
    public function searchModalAction(Request $request)
    {
        $this->enableFilter();
        $value = $request->query->get('search', '');
        $criteria = array('$text' => array('$search' => $value));
        $queryBuilder = $this->get('doctrine_mongodb.odm.document_manager')->getRepository('PumukitSchemaBundle:MultimediaObject')->createStandardQueryBuilder();
        $criteria = array_merge($queryBuilder->getQueryArray(), $criteria);
        $queryBuilder->setQueryArray($criteria);
        $adapter = new DoctrineODMMongoDBAdapter($queryBuilder);
        $mmobjs = new Pagerfanta($adapter);
        $mmobjs->setMaxPerPage($mmobjs->getNbResults());
        return array('mmobjs' => $mmobjs);
    }

    /**
     * @Template("PumukitNewAdminBundle:PlaylistMultimediaObject:modal_url_list.html.twig")
     */
    public function urlModalAction(Request $request)
    {
        $this->enableFilter();
        $id = $request->query->get('mmid', '');
        $mmobj = $this->get('doctrine_mongodb.odm.document_manager')->getRepository('PumukitSchemaBundle:MultimediaObject')->find($id);
        return array(
            'mmobj' => $mmobj,
            'mmobj_id' => $id
        );
    }


    public function addBatchAction(Series $playlist, Request $request)
    {
        $mmobjIds = $this->getRequest()->get('ids', '');
        if(!$mmobjIds)
            throw $this->createNotFoundException();
        //Sanity check. (May be remove if we want to mix series and playlists in the future.)
        if($playlist->getType() != Series::TYPE_PLAYLIST)
            throw $this->createNotFoundException();

        if ('string' === gettype($mmobjIds)){
            $mmobjIds = json_decode($mmobjIds, true);
        }

        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $mmobjRepo = $dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        foreach ($mmobjIds as $id) {
            $mmobj = $mmobjRepo->find($id);
            if(!$mmobj)
                continue;
            $playlist->getPlaylist()->addMultimediaObject($mmobj);
            $dm->persist($playlist);
            $dm->flush();
        }

        return $this->redirect($this->generateUrl('pumukitnewadmin_playlistmms_list', array('id' => $playlist->getId())));
    }

    public function deleteBatchAction(Series $playlist, Request $request)
    {
        $mmobjIds = $this->getRequest()->get('ids', '');
        if(!$mmobjIds)
            throw $this->createNotFoundException();
        //Sanity check. (May be remove if we want to mix series and playlists in the future.)
        if($playlist->getType() != Series::TYPE_PLAYLIST)
            throw $this->createNotFoundException();

        if ('string' === gettype($mmobjIds)){
            $mmobjIds = json_decode($mmobjIds, true);
        }

        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        //$mmobjRepo = $dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $mms = $playlist->getPlaylist()->getMultimediaObjects();
        foreach ($mmobjIds as $pos => $id) {
            if(isset($mms[$pos]) && $mms[$pos]->getId() == $id)
                $playlist->getPlaylist()->removeMultimediaObjectByPos($pos);
        }
        $dm->persist($playlist);
        $dm->flush();

        return $this->redirect($this->generateUrl('pumukitnewadmin_playlistmms_list', array('id' => $playlist->getId())));
    }

    /**
     * Adds mmobj to the playlist. (as last).
     */
    public function addMmobjAction(Series $series, Request $request)
    {
        if(!$request->query->has('mm_id'))
            throw new \Exception('The request is missing the \'mm_id\' parameter');

        $playlistEmbed = $series->getPlaylist();
        $mmobjId = $request->query->get('mm_id');
        $mm = $mmobjRepo->find($mmobjId);
        if(!$mm)
            throw new \Exception("The id: $mmobjId , does not belong to any Multimedia Object");

        $playlistEmbed->addMultimediaObject($mm);
        $dm->persist($playlistEmbed);
        $dm->flush();
    }

    //Workaround function to check if the VideoEditorBundle is installed.
    protected function checkHasEditor()
    {
        $router = $this->get('router');
        $routes = $router->getRouteCollection()->all();
        $activeEditor = array_key_exists('pumukit_videoeditor_index', $routes);

        return $activeEditor;
    }

    //Disables the standard backoffice filter and enables the 'personal' filter. (Check own videos or public videos)
    protected function enableFilter()
    {
        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $filter = $dm->getFilterCollection()->enable("personal");
        $user = $this->get('security.context')->getToken()->getUser();
        if ($this->isGranted(PermissionProfile::SCOPE_GLOBAL)){
            return;
        }
        $filter = $dm->getFilterCollection()->disable("backoffice");
        $person = $this->get('pumukitschema.person')->getPersonFromLoggedInUser($user);
        $people = array();
        if ((null != $person) && (null != ($roleCode = $this->get('pumukitschema.person')->getPersonalScopeRoleCode()))) {
            $people['$elemMatch'] = array();
            $people['$elemMatch']['people._id'] = new \MongoId($person->getId());
            $people['$elemMatch']['cod'] = $roleCode;
        }
        $groups = array();
        $groups['$in'] = $user->getGroupsIds();
        $filter->setParameter('people', $people);
        $filter->setParameter('groups', $groups);
        $filter->setParameter('status', MultimediaObject::STATUS_PUBLISHED);
        $filter->setParameter("display_track_tag", "display");
    }
}
