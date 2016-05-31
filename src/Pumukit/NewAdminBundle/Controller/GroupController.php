<?php

namespace Pumukit\NewAdminBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Pumukit\SchemaBundle\Document\Group;
use Pumukit\SchemaBundle\Document\User;
use Pumukit\SchemaBundle\Document\MultimediaObject;

/**
 * @Security("is_granted('ROLE_ACCESS_GROUPS')")
 */
class GroupController extends AdminController implements NewAdminController
{
    /**
     * Index
     *
     * @Template()
     */
    public function indexAction(Request $request)
    {
        $config = $this->getConfiguration();
        $criteria = $this->getCriteria($config);
        $groups = $this->getResources($request, $config, $criteria);

        return array('groups' => $groups);
    }

    /**
     * List action
     *
     * @Template()
     */
    public function listAction(Request $request)
    {
        $config = $this->getConfiguration();
        $criteria = $this->getCriteria($config);
        $groups = $this->getResources($request, $config, $criteria);

        return array('groups' => $groups);
    }

    /**
     * Create Action
     * Overwrite to use group service
     * to check if exists and dispatch event
     *
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
    public function createAction(Request $request)
    {
        $dm = $this->get('doctrine_mongodb')->getManager();
        $config = $this->getConfiguration();

        $group = $this->createNew();
        $form = $this->getForm($group);

        if ($form->handleRequest($request)->isValid()) {
            try {
                $group = $this->get('pumukitschema.group')->create($group);
            } catch (\Exception $e) {
                return new JsonResponse(array($e->getMessage()), Response::HTTP_BAD_REQUEST);
            }

            if ($this->config->isApiRequest()) {
                return $this->handleView($this->view($group, 201));
            }

            if (null === $group) {
              return $this->redirect($this->generateUrl('pumukitnewadmin_group_list'));
            }

            return $this->redirect($this->generateUrl('pumukitnewadmin_group_list'));
        }

        if ($this->config->isApiRequest()) {
            return $this->handleView($this->view($form));
        }

        return $this->render("PumukitNewAdminBundle:Group:create.html.twig",
                             array(
                                   'group' => $group,
                                   'form' => $form->createView()
                                   ));
    }

    /**
     * Update Action
     * Overwrite to avoid updating not
     * local groups and to use group service
     * to update group and dispatch event
     *
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
    public function updateAction(Request $request)
    {
        $dm = $this->get('doctrine_mongodb')->getManager();
        $config = $this->getConfiguration();
        $group = $this->findOr404($request);
        if ($group->getOrigin() !== Group::ORIGIN_LOCAL) {
            return new Response("Not allowed to update not local Group", Response::HTTP_METHOD_NOT_ALLOWED);
        }

        $form = $this->getForm($group);

        if (in_array($request->getMethod(), array('POST', 'PUT', 'PATCH')) && $form->submit($request, !$request->isMethod('PATCH'))->isValid()) {
            try {
                $group = $this->get('pumukitschema.group')->update($group);
            } catch (\Exception $e) {
                return new JsonResponse(array("status" => $e->getMessage()), Response::HTTP_BAD_REQUEST);
            }

            if ($this->config->isApiRequest()) {
                return $this->handleView($this->view($group, 204));
            }

            return $this->redirect($this->generateUrl('pumukitnewadmin_group_list'));
        }

        if ($this->config->isApiRequest()) {
            return $this->handleView($this->view($form));
        }

        return $this->render("PumukitNewAdminBundle:Group:update.html.twig",
                             array(
                                   'group' => $group,
                                   'form' => $form->createView()
                                   ));
    }

    /**
     * Delete Group
     *
     * @Template("PumukitNewAdminBundle:Group:list.html")
     */
    public function deleteAction(Request $request)
    {
        $groupService = $this->get('pumukitschema.group');
        $group = $groupService->findById($request->get('id'));
        try {
            $groupService->delete($group);
        } catch (\Exception $e) {
            return new Response("Can not delete Group '".$group->getName()."'. ".$e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->redirect($this->generateUrl('pumukitnewadmin_group_list'));
    }

    /**
     * Batch delete Group
     * Overwrite to use GroupService
     */
    public function batchDeleteAction(Request $request)
    {
        $ids = $this->getRequest()->get('ids');

        if ('string' === gettype($ids)){
            $ids = json_decode($ids, true);
        }

        $groupService = $this->get('pumukitschema.group');
        foreach ($ids as $id) {
            $group = $groupService->findById($id);
            try {
                $groupService->delete($group);
            } catch (\Exception $e) {
                return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
            }
            if ($id === $this->get('session')->get('admin/group/id')){
                $this->get('session')->remove('admin/group/id');
            }
        }

        return $this->redirect($this->generateUrl('pumukitnewadmin_group_list'));
    }

    /**
     * Gets the list of resources according to a criteria
     */
    public function getResources(Request $request, $config, $criteria)
    {
        $sorting = $this->getSorting($request);
        $repository = $this->getRepository();
        $session = $this->get('session');
        $sessionNamespace = 'admin/group';

        if ($config->isPaginated()) {
            $resources = $this
                ->resourceResolver
                ->getResource($repository, 'createPaginator', array($criteria, $sorting));

            if ($request->get('page', null)) {
                $session->set($sessionNamespace.'/page', $request->get('page', 1));
            }

            if ($request->get('paginate', null)) {
                $session->set($sessionNamespace.'/paginate', $request->get('paginate', 10));
            }

            $resources
                ->setMaxPerPage($session->get($sessionNamespace.'/paginate', 10))
                ->setNormalizeOutOfRangePages(true)
                ->setCurrentPage($session->get($sessionNamespace.'/page', 1));
        } else {
            $resources = $this
                ->resourceResolver
                ->getResource($repository, 'findBy', array($criteria, $sorting, $config->getLimit()));
        }

        return $resources;
    }

    /**
     * Get sorting for group
     *
     * @param Request $request
     * @return array
     */
    private function getSorting(Request $request)
    {
        $session = $this->get('session');
        if ($sorting = $request->get('sorting')){
            $session->set('admin/group/type', $sorting[key($sorting)]);
            $session->set('admin/group/sort', key($sorting));
        }
        $value = $session->get('admin/group/type', 'asc');
        $key = $session->get('admin/group/sort', 'name');

        return array($key => $value);
    }

    /**
     * Info Action
     * @Template()
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
    public function infoAction(Request $request)
    {
        $group = $this->findOr404($request);
        $users = $this->get('pumukitschema.group')->findUsersInGroup($group);
        $dm = $this->get('doctrine_mongodb.odm.document_manager');
        $mmobjRepo = $dm->getRepository('PumukitSchemaBundle:MultimediaObject');
        $adminMultimediaObjects = $mmobjRepo->findWithGroup($group);
        $viewerMultimediaObjects = $mmobjRepo->findWithGroupInEmbeddedBroadcast($group);
        $groupService = $this->get('pumukitschema.group');
        $countResources = $groupService->countResourcesInGroup($group);
        $canBeDeleted = $groupService->canBeDeleted($group);
        $deleteMessage = $groupService->getDeleteMessage($group, $request->get('_locale'));

        return array(
                     'group' => $group,
                     'users' => $users,
                     'admin_multimedia_objects' => $adminMultimediaObjects,
                     'viewer_multimedia_objects' => $viewerMultimediaObjects,
                     'countResources' => $countResources,
                     'can_delete' => $canBeDeleted,
                     'delete_group_message' => $deleteMessage
                     );
    }

    /**
     * Data Resource Action
     * @Template("PumukitNewAdminBundle:Group:dataresources.html.twig")
     * @param Request $request
     *
     * @return RedirectResponse|Response
     */
    public function dataResourcesAction(Group $group, Request $request)
    {
        $resourceName = $request->get('resourceName', null);
        if (!$resourceName) {
            throw new \Exception('Missing resource name');
        }
        if ('user' === $resourceName) {
            $resources = $this->get('pumukitschema.group')->findUsersInGroup($group);
        } elseif ('multimediaobject' === $resourceName) {
            $dm = $this->get('doctrine_mongodb.odm.document_manager');
            $mmobjRepo = $dm->getRepository('PumukitSchemaBundle:MultimediaObject');
            $resources = $mmobjRepo->findWithGroup($group);
        } elseif ('embeddedbroadcast' === $resourceName) {
            $dm = $this->get('doctrine_mongodb.odm.document_manager');
            $mmobjRepo = $dm->getRepository('PumukitSchemaBundle:MultimediaObject');
            $resources = $mmobjRepo->findWithGroupInEmbeddedBroadcast($group);
        } else {
            throw new \Exception('Invalid resource name');
        }

        return array(
                     'group'         => $group,
                     'resources'     => $resources,
                     'resource_name' => $resourceName
                     );
    }

    /**
     * Delete User from Group action
     *
     * @ParamConverter("user", class="PumukitSchemaBundle:User", options={"id" = "userId"})
     */
    public function deleteUserAction(User $user, Request $request)
    {
        $group = $this->findOr404($request);
        $user = $this->get('pumukitschema.user')->deleteGroup($group, $user);

        return $this->redirect($this->generateUrl('pumukitnewadmin_group_data_resources', array('id' => $group->getId(), 'resourceName' => 'user')));
    }

    /**
     * Delete MultimediaObject from Group action
     *
     * @ParamConverter("multimediaObject", class="PumukitSchemaBundle:MultimediaObject", options={"id" = "mmId"})
     */
    public function deleteMultimediaObjectAction(MultimediaObject $multimediaObject, Request $request)
    {
        $group = $this->findOr404($request);
        $multimediaobject = $this->get('pumukitschema.multimedia_object')->deleteGroup($group, $multimediaObject);

        return $this->redirect($this->generateUrl('pumukitnewadmin_group_data_resources', array('id' => $group->getId(), 'resourceName' => 'multimediaobject')));
    }

    /**
     * Delete Embeddedbroadcast from Group action
     *
     * @ParamConverter("multimediaObject", class="PumukitSchemaBundle:MultimediaObject", options={"id" = "mmId"})
     */
    public function deleteEmbeddedBroadcastAction(MultimediaObject $multimediaObject, Request $request)
    {
        $group = $this->findOr404($request);
        $multimediaobject = $this->get('pumukitschema.embeddedbroadcast')->deleteGroup($group, $multimediaObject);

        return $this->redirect($this->generateUrl('pumukitnewadmin_group_data_resources', array('id' => $group->getId(), 'resourceName' => 'embeddedbroadcast')));
    }

    /**
     * Can be deleted
     *
     * @param  Group   $group
     * @param  Request $request
     * @return JsonResponse
     */
    public function canBeDeletedAction(Group $group, Request $request)
    {
        try {
            $groupService = $this->get('pumukitschema.group');
            $canBeDeleted = $groupService->canBeDeleted($group);
            $value = $canBeDeleted ? 1:0;
            $deleteMessage = $groupService->getDeleteMessage($group, $request->get('_locale'));
        } catch (\Exception $e){
            return new JsonResponse(array('error' => $e->getMessage()), Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(array(
                                      'canbedeleted' => $value,
                                      'deleteMessage' => $deleteMessage
                                      ));
    }

    /**
     * Delete all users from group
     *
     * @param Group $group
     * @param Request $request
     * @return Response
     */
    public function deleteAllUsersAction(Group $group, Request $request)
    {
        try {
            $userService = $this->get('pumukitschema.user');
            $userService->deleteAllFromGroup($group);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->redirect($this->generateUrl('pumukitnewadmin_group_data_resources', array('id' => $group->getId(), 'resourceName' => 'user')));
    }

    /**
     * Delete all multimediaObjects from group
     *
     * @param Group $group
     * @param Request $request
     * @return Response
     */
    public function deleteAllMultimediaObjectsAction(Group $group, Request $request)
    {
        try {
            $mmsService = $this->get('pumukitschema.multimedia_object');
            $mmsService->deleteAllFromGroup($group);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->redirect($this->generateUrl('pumukitnewadmin_group_data_resources', array('id' => $group->getId(), 'resourceName' => 'multimediaobject')));
    }

    /**
     * Delete all embeddedbroadcasts from group
     *
     * @param Group $group
     * @param Request $request
     * @return Response
     */
    public function deleteAllEmbeddedBroadcastsAction(Group $group, Request $request)
    {
        try {
            $embeddedBroadcastService = $this->get('pumukitschema.embeddedbroadcast');
            $embeddedBroadcastService->deleteAllFromGroup($group);
        } catch (\Exception $e) {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->redirect($this->generateUrl('pumukitnewadmin_group_data_resources', array('id' => $group->getId(), 'resourceName' => 'embeddedbroadcast')));
    }
}