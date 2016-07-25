<?php

namespace Adadgio\ParseBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Adadgio\ParseBundle\Controller\BaseController as Controller;
use Adadgio\GearBundle\Component\Api\ApiRequest;
use Adadgio\GearBundle\Component\Api\ApiResponse;
use Adadgio\GearBundle\Component\Api\Annotation\Api;

use Adadgio\ParseBundle\Factory\ParseObjectFactory;

/**
 * @Route("/parse/classes")
 */
class UserController extends Controller
{
    /**
     * @Route("/_User")
     * @Method("POST")
     * @Api(method={"POST"}, security=true, type="static",
     *     with={"client_id"="x-parse-application-id","token"="x-parse-client-key"}
     * )
     */
    public function indexAction(ApiRequest $request)
    {
        $this->injectDependencies();

        switch($request->body('_method')) {
            case 'GET':
                return $this->queryAction($request);
            break;
            case null:
                return $this->postAction($request); // its a signup
            break;
            default:
                return $this->invalidMethodException($request, 'only GET is allowed');
            break;
        }
    }

    /**
     * @Route("/_User/{objectId}", requirements={"objectId":"[A-Za-z0-9]+"}))
     * @Method("GET")
     * @Api(method={"GET"}, security=true, type="static",
     *     with={"client_id"="x-parse-application-id","token"="x-parse-client-key"}
     * )
     */
    public function getAction(ApiRequest $request, $objectId)
    {
        $this->injectDependencies();
        $id = ParseObjectFactory::getIdFromObjectId('_User', $objectId);

        $user = $this->userManager
            ->findUserBy(array('id' => $id));

        if (null === $user) {
            return $this->notFoundException($request);
        }

        return $this->objectResultResponse($request, $this->serializer->serialize($user));
    }

    /**
     * @Route("/_User/{objectId}", requirements={"objectId":"[A-Za-z0-9]+"}))
     * @Method("PUT")
     * @Api(method={"PUT"}, security=true, type="static",
     *     with={"client_id"="x-parse-application-id","token"="x-parse-client-key"}
     * )
     */
    public function putAction(ApiRequest $request, $objectId)
    {
        $this->injectDependencies();

        $data = $request->body(); // partial data about the user to be updated
        $username = $request->body('username');
        $userId = ParseObjectFactory::getIdFromObjectId('_User', $objectId);

        $user = $this->userManager
            ->findUserBy(array('id' => $userId));

        if (null === $user) {
            return $this->notFoundException($request);
        }

        // update the user...
        // @todo Use Entity converter and create a method called ::validateAllowedProperties to be PUT
        $user = $this->converter->hydrate($user, $data);

        // validate the user entity
        $violationList = $this->validator->validate($user, array('MedicalMobileRegistration'));

        if ($violationList->count() > 0) {
            // get the first violation
            return $this->violationException($request, $this->getFirstViolationMessage($violationList));
        }

        $this->em->flush();

        return $this->queryResultsResponse($request, $this->serializer->serialize($user));
    }
    
    /**
     * @Route("/_User")
     * @Method("POST")
     * @Api(method={"POST"}, security=true, type="static",
     *     with={"client_id"="x-parse-application-id","token"="x-parse-client-key"}
     * )
     */
    public function postAction(ApiRequest $request)
    {
        $this->injectDependencies();

        $data = $request->body();
        $username = $request->body('username');
        $password = $request->body('password');

        if (empty($username) OR empty($password)) {
            return $this->violationException($request, 'username and password are required to sign up');
        }

        $user = $this->userManager->createUser();

        $user
            ->setEmail($username)
            ->setUsername($username)
            ->setPlainPassword($password);

        // hydrate the entity with the converter
        $user = $this->converter->hydrate($user, $data);

        // validate the user entity
        $violationList = $this->validator->validate($user, array('MedicalMobileRegistration'));

        if ($violationList->count() > 0) {
            return $this->signupViolationException($request, $this->getFirstViolationMessage($violationList));
        }

        // set random confirmation token
        $confirmationToken = sha1($user->getId().uniqid(mt_rand(), true));
        $user->setConfirmationToken($confirmationToken);

        // sign up the user
        $this->userManager->updateUser($user);

        // send him a registration confirmation message by email
        $fosUser = $this->userManager->findUserByEmail($user->getEmail());
        $this
            ->get('core.medical_user_mailer')
            ->sendConfirmationEmailMessage($fosUser)
        ;

        return $this->objectResultResponse($request, $this->loginSerializer->serialize($user));
    }

    /**
     * Called by POST index action with the "_method":"GET" parameter.
     */
    public function queryAction(ApiRequest $request)
    {
        $this->injectDependencies();

        $composer = $this
            ->get('adadgio.rocket.parse.query_composer')
            ->createFromRequestBody($request->body(), 'User')
        ;

        $collection = $composer->getResult();

        return $this->queryResultsResponse($request, $this->serializer->serialize($collection, false));
    }
}
