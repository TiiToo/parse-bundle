<?php

namespace Adadgio\ParseBundle\Controller;

use Adadgio\ParseBundle\Controller\BaseController as Controller;

use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Adadgio\GearBundle\Component\Api\ApiRequest;
use Adadgio\GearBundle\Component\Api\ApiResponse;
use Adadgio\GearBundle\Component\Api\Annotation\Api;

use Adadgio\ParseBundle\Factory\ParseObjectFactory;

/**
 * @Route("/parse/classes")
 */
class ObjectController extends Controller
{
    /**
     * @Route("/{class}", requirements={"class":"^[A-Z]{1}[a-z]+$"})
     * @Method("POST")
     * @Api(method={"POST"}, security=true, type="static",
     *     with={"client_id"="x-parse-application-id","token"="x-parse-client-key"}
     * )
     */
    public function indexAction(ApiRequest $request, $class)
    {
        $this->injectDependencies();

        switch($request->get('_method')) {
            case 'GET':
                return $this->queryAction($request, $class);
            break;
            case null:
                return $this->postAction($request, $class);
            break;
            default:
                return $this->invalidMethodException($request, 'only GET is allowed');
            break;
        }
    }

    /**
     * @Route("/{class}/{objectId}", requirements={"class":"^[A-Z]{1}[a-zA-Z]+$", "objectId":"[A-Za-z0-9]+"}))
     * @Method("GET")
     * @Api(method={"GET"}, security=true, type="static",
     *     with={"client_id"="x-parse-application-id","token"="x-parse-client-key"}
     * )
     */
    public function getAction(ApiRequest $request, $class, $objectId)
    {
        $this->injectDependencies();
        $entityId = ParseObjectFactory::getIdFromObjectId($class, $objectId);

        $entity = $this
            ->getAbstractRepository($class)
            ->find($entityId);

        if (null === $entity) {
            return $this->notFoundException();
        }

        return $this->objectResultResponse($this->serializer->serialize($entity));
    }

    /**
     * @Route("/{class}/{objectId}", requirements={"class":"^[A-Z]{1}[a-zA-Z]+$", "objectId":"[A-Za-z0-9]+"}))
     * @Method("PUT")
     * @Api(method={"PUT"}, security=true, type="Headers",
     *      with={"client_id"="X-Parse-Application-Id","token"="X-Parse-Client-Key"},
     *      provider="adadgio.rocket.api_authentication_provider.default",
     *      requirements={"body"={}}
     * )
     */
    public function putAction(ApiRequest $request, $class, $objectId)
    {
        $this->injectDependencies();
        $entityId = ParseObjectFactory::getIdFromObjectId($class, $objectId);

        $entity = $this
            ->getAbstractRepository($class)
            ->find($entityId);

        if (null === $entity) {
            return $this->notFoundException($request);
        }

        // update the entity (field defined in converter)
        $entity = $this->converter->hydrate($entity, $request->all());
        $this->em->flush();

        return $this->updatedAtResponse($request, $this->serializer->serialize($entity));
    }

    /**
     * @Route("/{class}", requirements={"class":"^[A-Z]{1}[a-zA-Z]+$"}))
     * @Method("POST")
     * @Api(method={"POST"}, security=true, type="Headers",
     *      with={"client_id"="X-Parse-Application-Id","token"="X-Parse-Client-Key"},
     *      provider="adadgio.rocket.api_authentication_provider.default",
     *      requirements={"body"={}}
     * )
     */
    public function postAction(ApiRequest $request, $class)
    {
        $data = $request->all();
        $this->injectDependencies();

        // (1) create a new object and set all its properties
        $entity = $this->newAbstractInstance($class);
        $entity = $this->converter->hydrate($entity, $data);

        // create the entity
        $this->em->persist($entity);
        $this->em->flush();

        return $this->createdAtResponse($this->serializer->serialize($entity));
    }
    
    /**
     * Called by POST index action with the "_method":"GET" parameter.
     */
    public function queryAction(ApiRequest $request, $class)
    {
        $composer = $this
            ->get('adadgio_parse.query_composer')
            ->createFromRequestBody($request->all(), $class)
        ;

        $collection = $composer->getResult();

        $this->serializer->setIncludes($composer->getIncludes()); // will allow whiole related childrene entities inside parent entity instead of pointers
        $this->serializer->executeParallelHydration($class, $composer->getIncludes(), $collection); // will activate and user parallel hdyration :-)

        return $this->queryResultsResponse($this->serializer->serialize($collection));
    }
}
