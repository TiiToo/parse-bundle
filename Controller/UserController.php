<?php

namespace Adadgio\ParseBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Adadgio\ParseBundle\Controller\BaseController as Controller;
use Adadgio\GearBundle\Component\Api\ApiRequest;
use Adadgio\GearBundle\Component\Api\ApiResponse;
use Adadgio\GearBundle\Component\Api\Annotation\Api;

use Adadgio\ParseBundle\Component\Utility\Decoder;
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
    public function indexAction(ApiHandler $api)
    {
        $this->injectDependencies();

        switch($api->body('_method')) {
            case 'GET':
                return $this->queryAction($api);
            break;
            case null:
                return $this->postAction($api); // its a signup
            break;
            default:
                return $this->invalidMethodException($api, 'only GET is allowed');
            break;
        }
    }

    /**
     * @Route("/_User/{objectId}", requirements={"objectId":"[A-Za-z0-9]+"}))
     * @Method("GET")
     * @Api(method={"GET"}, security=true, type="Headers",
     *      with={"client_id"="X-Parse-Application-Id","token"="X-Parse-Client-Key"},
     *      provider="adadgio.rocket.api_authentication_provider.default",
     *      requirements={"body"={}}
     * )
     */
    public function getAction(Apihandler $api, $objectId)
    {
        $this->injectDependencies();
        $id = ParseObjectFactory::getIdFromObjectId('_User', $objectId);

        $user = $this->em
            ->getRepository('MedicalCoreBundle:User')
            ->findOneBy(array('id' => $id));

        if (null === $user) {
            return $this->notFoundException($api);
        }

        return $this->objectResultResponse($api, $this->serializer->serialize($user));
    }

    /**
     * @Route("/_User/{objectId}", requirements={"objectId":"[A-Za-z0-9]+"}))
     * @Method("PUT")
     * @Api(method={"PUT"}, security=true, type="Headers",
     *      with={"client_id"="X-Parse-Application-Id", "token"="X-Parse-Client-Key"},
     *      provider="adadgio.rocket.api_authentication_provider.default",
     *      requirements={}
     * )
     */
    public function putAction(ApiHandler $api, $objectId)
    {
        $this->injectDependencies();

        $data = $api->body(); // partial data about the user to be updated
        $username = $api->body('username');
        $userId = ParseObjectFactory::getIdFromObjectId('_User', $objectId);

        $user = $this->em
            ->getRepository('MedicalCoreBundle:User')
            ->findOneBy(array('id' => $userId));

        if (null === $user) {
            return $this->notFoundException($api);
        }

        // update the user...
        // @todo Use Entity converter and create a method called ::validateAllowedProperties to be PUT
        $user = $this->converter->hydrate($user, $data);

        // validate the user entity
        $violationList = $this->validator->validate($user, array('MedicalMobileRegistration'));

        if ($violationList->count() > 0) {
            // get the first violation
            return $this->violationException($api, $this->getFirstViolationMessage($violationList));
        }

        $this->em->flush();

        return $this->queryResultsResponse($api, $this->serializer->serialize($user));
    }

    /**
     * @Route("/_User")
     * @Method("POST")
     * @Api(method={"POST"}, security=true, type="Headers",
     *      with={"client_id"="X-Parse-Application-Id", "token"="X-Parse-Client-Key"},
     *      provider="adadgio.rocket.api_authentication_provider.default",
     *      requirements={}
     * )
     */
    public function postAction(ApiHandler $api)
    {
        $this->injectDependencies();

        $data = $api->body();
        $username = $api->body('username');
        $password = $api->body('password');

        if (empty($username) OR empty($password)) {
            return $this->violationException($api, 'username and password are required to sign up');
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
            return $this->signupViolationException($api, $this->getFirstViolationMessage($violationList));
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

        return $this->objectResultResponse($api, $this->loginSerializer->serialize($user));
    }

    /**
     * Called by POST index action with the "_method":"GET" parameter.
     */
    public function queryAction(ApiHandler $api)
    {
        $this->injectDependencies();

        $composer = $this
            ->get('adadgio.rocket.parse.query_composer')
            ->createFromRequestBody($api->body(), 'User')
        ;

        $collection = $composer->getResult();

        return $this->queryResultsResponse($api, $this->serializer->serialize($collection, false));
    }
}
