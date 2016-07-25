<?php

namespace Adadgio\ParseBundle\Controller;

use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

use Adadgio\ParseBundle\Controller\BaseController as Controller;
use Adadgio\GearBundle\Component\Api\ApiRequest;
use Adadgio\GearBundle\Component\Api\ApiResponse;
use Adadgio\GearBundle\Component\Api\Annotation\Api;

use Adadgio\ParseBundle\Entity\Installation;

/**
 * @Route("/parse")
 */
class SecurityController extends Controller
{
    /**
     * @Route("/login")
     * @Method({"GET","POST"})
     * @Api(method={"GET","POST"}, security=true, type="Headers",
     *      with={"client_id"="X-Parse-Application-Id","token"="X-Parse-Client-Key"},
     *      provider="adadgio.rocket.api_authentication_provider.default",
     *      requirements={"body"={"_method":"string","username":"string","password":"string"}}
     * )
     */
    public function loginAction(ApiHandler $api, Request $request)
    {
        $this->injectDependencies();

        $method = $api->body('_method');
        $username = $api->body('username');
        $password = $api->body('password');

        if ($method !== 'GET') {
            return $this->invalidMethodException($api, 'GET');
        }

        $context     = $this->get('security.token_storage');
        $checker     = $this->get('security.authorization_checker');
        $userManager = $this->get('fos_user.user_manager');

        $user = $userManager->findUserByEmail($username);

        if (null === $user) {
            return $this->invalidLoginParameters($api);
        }

        // authenticate with encoded password
        $encoder = $this
            ->get('security.encoder_factory')
            ->getEncoder($user);

        $passwordEncoded = $encoder->encodePassword($password, $user->getSalt());

        if ($user->getPassword() !== $passwordEncoded) {
            return $this->invalidLoginParameters($api);
        }
        
        // authenticate the user
        $token = new UsernamePasswordToken($user, $user->getPassword(), 'main', $user->getRoles());
        $context->setToken($token);

        if (!$checker->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->invalidLoginParameters($api);
        }

        // dispatch the login event so that FOS and Sf security handlers are executed (like last login field update)
        $event = new InteractiveLoginEvent($request, $token);
        $this->get('event_dispatcher')->dispatch('security.interactive_login', $event);

        // re-configure user feeds
        $this
            ->get('core.user_configurator')
            ->configureFeeds($user);

        // link the installation to the user if applicable
        $installationId = $api->header('x-parse-installation-id');

        if (null !== $installationId) {
            // fetch the installation, add the user
            $installation = $this->em
                ->getRepository('MedicalCoreBundle:Installation')
                ->findOneBy(array('installationId' => $installationId));

            if (null !== $installation) {
                $installation->setUser($user);
                $this->em->flush();
            } else {
                // create a new installation with that installation id
                // you cant create that here, you dont have enough info on the installation
                // $installation = new Installation();
                // $installation = $this
                //     ->converter
                //     ->setPrefix(null)
                //     ->setUser($user)
                //     ->hydrate($installation, $installationData);
                //
                // $this->em->persist($installation);
                // $this->em->flush();
            }
        }

        return $this->objectResultResponse($api, $this->loginSerializer->serialize($user));
    }

    /**
     * @Route("/logout")
     * @Method({"GET","POST"})
     * @Api(method={"GET","POST"}, security=true, type="Headers",
     *      with={"client_id"="X-Parse-Application-Id","token"="X-Parse-Client-Key"},
     *      provider="adadgio.rocket.api_authentication_provider.default",
     *      requirements={}
     * )
     */
    public function logoutAction(ApiHandler $api)
    {
        return $this->objectResultResponse($api, array('message' => 'bye bye'));
    }

    /**
     * @Route("/users")
     * @Method("POST")
     * @Api(method={"POST"}, security=true, type="Headers",
     *      with={"client_id"="X-Parse-Application-Id", "token"="X-Parse-Client-Key"},
     *      provider="adadgio.rocket.api_authentication_provider.default",
     *      requirements={}
     * )
     * @param  ApiHandler $api [description]
     * @return [type]          [description]
     */
    public function signupAction(ApiHandler $api)
    {
        return $this->postAction($api);
    }
}
